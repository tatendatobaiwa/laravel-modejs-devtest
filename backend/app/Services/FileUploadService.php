<?php

namespace App\Services;

use App\Models\User;
use App\Models\UploadedDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class FileUploadService
{
    /**
     * Maximum file size in bytes (10MB).
     */
    const MAX_FILE_SIZE = 10 * 1024 * 1024;

    /**
     * Maximum files per user.
     */
    const MAX_FILES_PER_USER = 10;

    /**
     * Maximum total storage per user in bytes (100MB).
     */
    const MAX_STORAGE_PER_USER = 100 * 1024 * 1024;

    /**
     * Allowed MIME types for uploaded files.
     */
    const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        'text/csv',
    ];

    /**
     * Allowed file extensions.
     */
    const ALLOWED_EXTENSIONS = [
        'pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv'
    ];

    /**
     * Document types mapping.
     */
    const DOCUMENT_TYPES = [
        'pdf' => 'document',
        'doc' => 'document',
        'docx' => 'document',
        'xls' => 'spreadsheet',
        'xlsx' => 'spreadsheet',
        'csv' => 'spreadsheet',
        'txt' => 'text',
        'jpg' => 'image',
        'jpeg' => 'image',
        'png' => 'image',
        'gif' => 'image',
    ];

    /**
     * Upload a file for a user.
     */
    public function uploadFile(
        UploadedFile $file,
        User $user,
        ?string $documentType = null,
        ?string $notes = null
    ): UploadedDocument {
        $this->validateFile($file);
        $this->validateUserQuota($user, $file->getSize());

        $originalFilename = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();
        
        // Generate secure filename
        $storedFilename = $this->generateSecureFilename($originalFilename, $extension);
        
        // Determine document type
        $documentType = $documentType ?? $this->determineDocumentType($extension);
        
        // Create user-specific directory path
        $userDirectory = $this->getUserDirectory($user);
        $filePath = $userDirectory . '/' . $storedFilename;
        
        try {
            // Store the file
            $storedPath = $file->storeAs($userDirectory, $storedFilename, 'local');
            
            if (!$storedPath) {
                throw new RuntimeException('Failed to store file');
            }
            
            // Calculate file hash for integrity checking
            $fileHash = $this->calculateFileHash($file);
            
            // Create database record
            $uploadedDocument = UploadedDocument::create([
                'user_id' => $user->id,
                'original_filename' => $originalFilename,
                'stored_filename' => $storedFilename,
                'file_path' => $storedPath,
                'mime_type' => $mimeType,
                'file_size' => $file->getSize(),
                'file_hash' => $fileHash,
                'document_type' => $documentType,
                'is_verified' => false,
                'notes' => $notes,
            ]);
            
            Log::info('File uploaded successfully', [
                'user_id' => $user->id,
                'document_id' => $uploadedDocument->id,
                'original_filename' => $originalFilename,
                'file_size' => $file->getSize(),
                'mime_type' => $mimeType,
            ]);
            
            return $uploadedDocument;
            
        } catch (\Exception $e) {
            // Clean up file if database operation failed
            if (isset($storedPath) && Storage::exists($storedPath)) {
                Storage::delete($storedPath);
            }
            
            Log::error('File upload failed', [
                'user_id' => $user->id,
                'original_filename' => $originalFilename,
                'error' => $e->getMessage(),
            ]);
            
            throw new RuntimeException('File upload failed: ' . $e->getMessage());
        }
    }

    /**
     * Delete a file and its database record.
     */
    public function deleteFile(UploadedDocument $document, ?int $deletedBy = null): bool
    {
        try {
            // Delete physical file
            if (Storage::exists($document->file_path)) {
                Storage::delete($document->file_path);
            }
            
            // Soft delete the database record
            $document->delete();
            
            Log::info('File deleted successfully', [
                'document_id' => $document->id,
                'user_id' => $document->user_id,
                'filename' => $document->original_filename,
                'deleted_by' => $deletedBy ?? auth()->id(),
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('File deletion failed', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Verify a document (mark as verified).
     */
    public function verifyDocument(
        UploadedDocument $document,
        int $verifiedBy,
        ?string $notes = null
    ): UploadedDocument {
        $document->update([
            'is_verified' => true,
            'verified_at' => now(),
            'verified_by' => $verifiedBy,
            'notes' => $notes ?? $document->notes,
        ]);
        
        Log::info('Document verified', [
            'document_id' => $document->id,
            'user_id' => $document->user_id,
            'verified_by' => $verifiedBy,
        ]);
        
        return $document->fresh();
    }

    /**
     * Get file download URL (secure temporary URL).
     */
    public function getDownloadUrl(UploadedDocument $document, int $expirationMinutes = 60): string
    {
        if (!Storage::exists($document->file_path)) {
            throw new RuntimeException('File not found');
        }
        
        return Storage::temporaryUrl($document->file_path, now()->addMinutes($expirationMinutes));
    }

    /**
     * Clean up old files based on retention policy.
     */
    public function cleanupOldFiles(int $retentionDays = 365): array
    {
        $cutoffDate = now()->subDays($retentionDays);
        
        $oldDocuments = UploadedDocument::onlyTrashed()
            ->where('deleted_at', '<', $cutoffDate)
            ->get();
        
        $cleanedUp = [];
        
        foreach ($oldDocuments as $document) {
            try {
                // Delete physical file if it exists
                if (Storage::exists($document->file_path)) {
                    Storage::delete($document->file_path);
                }
                
                // Force delete from database
                $document->forceDelete();
                
                $cleanedUp[] = [
                    'document_id' => $document->id,
                    'filename' => $document->original_filename,
                    'success' => true,
                ];
                
            } catch (\Exception $e) {
                $cleanedUp[] = [
                    'document_id' => $document->id,
                    'filename' => $document->original_filename,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
                
                Log::error('Failed to cleanup old file', [
                    'document_id' => $document->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        Log::info('File cleanup completed', [
            'retention_days' => $retentionDays,
            'files_processed' => count($cleanedUp),
            'successful_cleanups' => collect($cleanedUp)->where('success', true)->count(),
        ]);
        
        return $cleanedUp;
    }

    /**
     * Get user's storage usage statistics.
     */
    public function getUserStorageStats(User $user): array
    {
        $documents = $user->uploadedDocuments()->get();
        $totalSize = $documents->sum('file_size');
        $fileCount = $documents->count();
        
        return [
            'total_files' => $fileCount,
            'total_size_bytes' => $totalSize,
            'total_size_formatted' => $this->formatFileSize($totalSize),
            'quota_used_percentage' => round(($totalSize / self::MAX_STORAGE_PER_USER) * 100, 2),
            'remaining_quota_bytes' => max(0, self::MAX_STORAGE_PER_USER - $totalSize),
            'remaining_quota_formatted' => $this->formatFileSize(max(0, self::MAX_STORAGE_PER_USER - $totalSize)),
            'can_upload_more' => $fileCount < self::MAX_FILES_PER_USER && $totalSize < self::MAX_STORAGE_PER_USER,
        ];
    }

    /**
     * Validate uploaded file.
     */
    private function validateFile(UploadedFile $file): void
    {
        // Check if file was uploaded successfully
        if (!$file->isValid()) {
            throw new InvalidArgumentException('Invalid file upload');
        }
        
        // Check file size
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new InvalidArgumentException(
                'File size exceeds maximum allowed size of ' . $this->formatFileSize(self::MAX_FILE_SIZE)
            );
        }
        
        // Check MIME type
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            throw new InvalidArgumentException('File type not allowed: ' . $mimeType);
        }
        
        // Check file extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            throw new InvalidArgumentException('File extension not allowed: ' . $extension);
        }
        
        // Additional security checks
        $this->performSecurityChecks($file);
    }

    /**
     * Validate user's upload quota.
     */
    private function validateUserQuota(User $user, int $fileSize): void
    {
        $stats = $this->getUserStorageStats($user);
        
        // Check file count limit
        if ($stats['total_files'] >= self::MAX_FILES_PER_USER) {
            throw new InvalidArgumentException(
                'Maximum number of files reached (' . self::MAX_FILES_PER_USER . ')'
            );
        }
        
        // Check storage quota
        if (($stats['total_size_bytes'] + $fileSize) > self::MAX_STORAGE_PER_USER) {
            throw new InvalidArgumentException(
                'Storage quota exceeded. Available: ' . $stats['remaining_quota_formatted']
            );
        }
    }

    /**
     * Perform additional security checks on the file.
     */
    private function performSecurityChecks(UploadedFile $file): void
    {
        // Check for executable files
        $dangerousExtensions = ['exe', 'bat', 'cmd', 'com', 'pif', 'scr', 'vbs', 'js', 'jar', 'php', 'asp'];
        $extension = strtolower($file->getClientOriginalExtension());
        
        if (in_array($extension, $dangerousExtensions)) {
            throw new InvalidArgumentException('Executable files are not allowed');
        }
        
        // Check file content for basic malware patterns
        $fileContent = file_get_contents($file->getRealPath());
        $malwarePatterns = ['<script', '<?php', '<%', 'javascript:', 'vbscript:'];
        
        foreach ($malwarePatterns as $pattern) {
            if (stripos($fileContent, $pattern) !== false) {
                throw new InvalidArgumentException('File contains potentially malicious content');
            }
        }
    }

    /**
     * Generate a secure filename.
     */
    private function generateSecureFilename(string $originalFilename, string $extension): string
    {
        $basename = pathinfo($originalFilename, PATHINFO_FILENAME);
        $sanitizedBasename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $basename);
        $sanitizedBasename = substr($sanitizedBasename, 0, 50); // Limit length
        
        $timestamp = now()->format('Y-m-d_H-i-s');
        $randomString = Str::random(8);
        
        return "{$sanitizedBasename}_{$timestamp}_{$randomString}.{$extension}";
    }

    /**
     * Get user-specific directory path.
     */
    private function getUserDirectory(User $user): string
    {
        return 'uploads/users/' . $user->id;
    }

    /**
     * Determine document type based on file extension.
     */
    private function determineDocumentType(string $extension): string
    {
        return self::DOCUMENT_TYPES[$extension] ?? 'unknown';
    }

    /**
     * Calculate file hash for integrity checking.
     */
    private function calculateFileHash(UploadedFile $file): string
    {
        return hash_file('sha256', $file->getRealPath());
    }

    /**
     * Format file size in human-readable format.
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get system-wide file statistics.
     */
    public function getSystemFileStats(): array
    {
        $totalFiles = UploadedDocument::count();
        $totalSize = UploadedDocument::sum('file_size');
        $verifiedFiles = UploadedDocument::where('is_verified', true)->count();
        $deletedFiles = UploadedDocument::onlyTrashed()->count();
        
        $documentTypes = UploadedDocument::selectRaw('document_type, COUNT(*) as count')
            ->groupBy('document_type')
            ->pluck('count', 'document_type')
            ->toArray();
        
        return [
            'total_files' => $totalFiles,
            'total_size_bytes' => $totalSize,
            'total_size_formatted' => $this->formatFileSize($totalSize),
            'verified_files' => $verifiedFiles,
            'verification_rate' => $totalFiles > 0 ? round(($verifiedFiles / $totalFiles) * 100, 2) : 0,
            'deleted_files' => $deletedFiles,
            'document_types' => $documentTypes,
            'average_file_size' => $totalFiles > 0 ? round($totalSize / $totalFiles, 2) : 0,
        ];
    }
}