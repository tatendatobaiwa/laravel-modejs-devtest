<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;
use Illuminate\Http\UploadedFile;

class FileUploadRequest extends FormRequest
{
    /**
     * Maximum file size in KB (5MB).
     */
    const MAX_FILE_SIZE = 5120;

    /**
     * Maximum files per user per day.
     */
    const MAX_FILES_PER_DAY = 10;

    /**
     * Maximum total storage per user in MB.
     */
    const MAX_USER_STORAGE = 50;

    /**
     * Allowed MIME types for security.
     */
    const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'image/jpeg',
        'image/jpg',
        'image/png',
        'text/plain',
    ];

    /**
     * Allowed file extensions.
     */
    const ALLOWED_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt'
    ];

    /**
     * Dangerous file signatures to detect.
     */
    const DANGEROUS_SIGNATURES = [
        'MZ',      // Windows executable
        'PK',      // ZIP/Office files (can contain macros)
        '7z',      // 7-Zip archive
        'Rar!',    // RAR archive
        '#!/',     // Shell script
        '<?php',   // PHP script
        '<script', // JavaScript
        '<html',   // HTML file
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check(); // Only authenticated users can upload files
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                File::types($this->getAllowedExtensions())
                    ->max(self::MAX_FILE_SIZE)
                    ->rules([
                        function ($attribute, $value, $fail) {
                            if (!$this->validateFileContent($value)) {
                                $fail('The uploaded file contains potentially dangerous content.');
                            }
                        },
                        function ($attribute, $value, $fail) {
                            if (!$this->validateMimeType($value)) {
                                $fail('The file type is not allowed for security reasons.');
                            }
                        },
                        function ($attribute, $value, $fail) {
                            if (!$this->validateFileSignature($value)) {
                                $fail('The file signature indicates a potentially dangerous file type.');
                            }
                        },
                        function ($attribute, $value, $fail) {
                            if (!$this->validateUploadQuota()) {
                                $fail('You have exceeded your daily upload limit or storage quota.');
                            }
                        },
                    ]),
            ],
            'file_type' => [
                'required',
                'string',
                'in:salary_document,identity_document,contract,other',
            ],
            'description' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9\s\-_\.]+$/', // Only alphanumeric, spaces, hyphens, underscores, dots
            ],
            'is_sensitive' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Please select a file to upload.',
            'file.file' => 'The uploaded item must be a valid file.',
            'file.max' => 'The file size cannot exceed ' . (self::MAX_FILE_SIZE / 1024) . 'MB.',
            
            'file_type.required' => 'Please specify the type of document you are uploading.',
            'file_type.in' => 'The selected document type is not valid.',
            
            'description.max' => 'The description cannot exceed 255 characters.',
            'description.regex' => 'The description contains invalid characters.',
            
            'is_sensitive.boolean' => 'The sensitive flag must be true or false.',
        ];
    }

    /**
     * Get custom attribute names for validation errors.
     */
    public function attributes(): array
    {
        return [
            'file_type' => 'document type',
            'is_sensitive' => 'sensitive document flag',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitize description
        if ($this->has('description')) {
            $this->merge([
                'description' => $this->sanitizeDescription($this->input('description')),
            ]);
        }

        // Set default values
        $this->merge([
            'is_sensitive' => $this->boolean('is_sensitive', false),
            'uploaded_by' => auth()->id(),
            'upload_ip' => $this->ip(),
            'user_agent' => $this->userAgent(),
        ]);
    }

    /**
     * Handle a passed validation attempt.
     */
    protected function passedValidation(): void
    {
        // Add metadata about the upload
        $file = $this->file('file');
        
        $this->merge([
            'original_filename' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'file_hash' => hash_file('sha256', $file->getRealPath()),
            'upload_timestamp' => now(),
        ]);
    }

    /**
     * Get allowed file extensions.
     */
    private function getAllowedExtensions(): array
    {
        return self::ALLOWED_EXTENSIONS;
    }

    /**
     * Validate file content for malicious patterns.
     */
    private function validateFileContent(UploadedFile $file): bool
    {
        // Read first 1KB of file to check for dangerous patterns
        $handle = fopen($file->getRealPath(), 'rb');
        if (!$handle) {
            return false;
        }

        $content = fread($handle, 1024);
        fclose($handle);

        // Check for dangerous signatures
        foreach (self::DANGEROUS_SIGNATURES as $signature) {
            if (strpos($content, $signature) === 0) {
                return false;
            }
        }

        // Check for embedded scripts in images
        if (in_array($file->getClientOriginalExtension(), ['jpg', 'jpeg', 'png'])) {
            if (strpos($content, '<?php') !== false || 
                strpos($content, '<script') !== false ||
                strpos($content, 'javascript:') !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate MIME type against allowed types.
     */
    private function validateMimeType(UploadedFile $file): bool
    {
        $mimeType = $file->getMimeType();
        return in_array($mimeType, self::ALLOWED_MIME_TYPES);
    }

    /**
     * Validate file signature matches extension.
     */
    private function validateFileSignature(UploadedFile $file): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $handle = fopen($file->getRealPath(), 'rb');
        
        if (!$handle) {
            return false;
        }

        $signature = fread($handle, 8);
        fclose($handle);

        // Define expected signatures for file types
        $signatures = [
            'pdf' => ['%PDF'],
            'jpg' => ["\xFF\xD8\xFF"],
            'jpeg' => ["\xFF\xD8\xFF"],
            'png' => ["\x89PNG\r\n\x1a\n"],
            'doc' => ["\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"],
            'docx' => ['PK'],
            'txt' => [], // Text files don't have a specific signature
        ];

        if (!isset($signatures[$extension])) {
            return false;
        }

        // Text files are allowed without signature check
        if (empty($signatures[$extension])) {
            return true;
        }

        // Check if file signature matches expected signatures
        foreach ($signatures[$extension] as $expectedSignature) {
            if (strpos($signature, $expectedSignature) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate upload quota and rate limiting.
     */
    private function validateUploadQuota(): bool
    {
        $userId = auth()->id();
        $today = now()->startOfDay();

        // Check daily upload limit
        $dailyUploads = \App\Models\UploadedDocument::where('user_id', $userId)
            ->where('created_at', '>=', $today)
            ->count();

        if ($dailyUploads >= self::MAX_FILES_PER_DAY) {
            return false;
        }

        // Check total storage quota
        $totalStorage = \App\Models\UploadedDocument::where('user_id', $userId)
            ->sum('file_size');

        $maxStorageBytes = self::MAX_USER_STORAGE * 1024 * 1024; // Convert MB to bytes
        
        if (($totalStorage + $this->file('file')->getSize()) > $maxStorageBytes) {
            return false;
        }

        return true;
    }

    /**
     * Sanitize description input.
     */
    private function sanitizeDescription(?string $description): ?string
    {
        if (!$description) {
            return null;
        }

        // Remove HTML tags and normalize whitespace
        $description = strip_tags($description);
        $description = preg_replace('/\s+/', ' ', trim($description));
        
        // Remove potentially dangerous characters
        $description = preg_replace('/[<>"\']/', '', $description);
        
        return $description;
    }

    /**
     * Get file metadata for storage.
     */
    public function getFileMetadata(): array
    {
        $file = $this->file('file');
        
        return [
            'original_filename' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'file_extension' => $file->getClientOriginalExtension(),
            'file_hash' => hash_file('sha256', $file->getRealPath()),
            'upload_ip' => $this->ip(),
            'user_agent' => $this->userAgent(),
            'is_virus_scanned' => false, // Will be updated after virus scan
            'scan_result' => null,
        ];
    }

    /**
     * Generate secure filename for storage.
     */
    public function generateSecureFilename(): string
    {
        $file = $this->file('file');
        $extension = $file->getClientOriginalExtension();
        $hash = hash_file('sha256', $file->getRealPath());
        
        // Create filename with timestamp and hash for uniqueness
        return sprintf(
            '%s_%s_%s.%s',
            auth()->id(),
            now()->format('Y-m-d_H-i-s'),
            substr($hash, 0, 8),
            $extension
        );
    }

    /**
     * Get storage path for the file.
     */
    public function getStoragePath(): string
    {
        $userId = auth()->id();
        $year = now()->year;
        $month = now()->format('m');
        
        return "uploads/users/{$userId}/{$year}/{$month}";
    }

    /**
     * Perform virus scan simulation.
     * In production, integrate with actual antivirus service.
     */
    public function performVirusScan(): array
    {
        // Simulate virus scanning
        // In production, integrate with ClamAV, VirusTotal API, or similar service
        
        $file = $this->file('file');
        $scanResult = [
            'is_clean' => true,
            'scan_engine' => 'simulated',
            'scan_timestamp' => now(),
            'threats_found' => [],
        ];

        // Simple heuristic checks
        $content = file_get_contents($file->getRealPath());
        
        // Check for suspicious patterns
        $suspiciousPatterns = [
            'eval(',
            'exec(',
            'system(',
            'shell_exec(',
            'passthru(',
            'base64_decode(',
            'javascript:',
            'vbscript:',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (stripos($content, $pattern) !== false) {
                $scanResult['is_clean'] = false;
                $scanResult['threats_found'][] = "Suspicious pattern detected: {$pattern}";
            }
        }

        return $scanResult;
    }

    /**
     * Get upload statistics for the current user.
     */
    public function getUploadStatistics(): array
    {
        $userId = auth()->id();
        $today = now()->startOfDay();
        
        return [
            'daily_uploads' => \App\Models\UploadedDocument::where('user_id', $userId)
                ->where('created_at', '>=', $today)
                ->count(),
            'daily_limit' => self::MAX_FILES_PER_DAY,
            'total_storage_used' => \App\Models\UploadedDocument::where('user_id', $userId)
                ->sum('file_size'),
            'storage_limit' => self::MAX_USER_STORAGE * 1024 * 1024,
            'remaining_uploads' => max(0, self::MAX_FILES_PER_DAY - 
                \App\Models\UploadedDocument::where('user_id', $userId)
                    ->where('created_at', '>=', $today)
                    ->count()),
        ];
    }
}