<?php

namespace App\Services;

use App\Models\User;
use App\Models\Salary;
use App\Models\SalaryHistory;
use App\Models\UploadedDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class AuditService
{
    /**
     * Audit event types.
     */
    const EVENT_TYPES = [
        'user_created' => 'User Created',
        'user_updated' => 'User Updated',
        'user_deleted' => 'User Deleted',
        'salary_created' => 'Salary Created',
        'salary_updated' => 'Salary Updated',
        'commission_updated' => 'Commission Updated',
        'file_uploaded' => 'File Uploaded',
        'file_deleted' => 'File Deleted',
        'file_verified' => 'File Verified',
        'bulk_operation' => 'Bulk Operation',
        'system_action' => 'System Action',
    ];

    /**
     * Log a user action for audit purposes.
     */
    public function logUserAction(
        string $eventType,
        ?Model $subject = null,
        ?int $userId = null,
        ?array $metadata = null,
        ?string $description = null
    ): void {
        $userId = $userId ?? auth()->id();
        
        $auditData = [
            'event_type' => $eventType,
            'user_id' => $userId,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject ? $subject->id : null,
            'description' => $description ?? $this->generateDescription($eventType, $subject),
            'metadata' => array_merge([
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'timestamp' => now()->toISOString(),
                'session_id' => session()->getId(),
            ], $metadata ?? []),
        ];

        // Log to application logs
        Log::info('Audit Event', $auditData);

        // Store in database if needed (you could create an audit_logs table)
        $this->storeAuditLog($auditData);
    }

    /**
     * Log salary changes with detailed tracking.
     */
    public function logSalaryChange(
        Salary $salary,
        array $originalValues,
        ?int $changedBy = null,
        ?string $reason = null
    ): SalaryHistory {
        $changedBy = $changedBy ?? auth()->id();
        
        // Create detailed salary history record
        $historyRecord = SalaryHistory::createFromSalaryChange(
            $salary,
            $originalValues,
            $changedBy,
            $reason ?? 'Salary updated'
        );

        // Log the audit event
        $this->logUserAction(
            'salary_updated',
            $salary,
            $changedBy,
            [
                'salary_history_id' => $historyRecord->id,
                'changes' => $this->calculateChanges($originalValues, $salary->toArray()),
                'reason' => $reason,
            ],
            "Salary updated for user {$salary->user->name} ({$salary->user->email})"
        );

        return $historyRecord;
    }

    /**
     * Log file upload activity.
     */
    public function logFileUpload(
        UploadedDocument $document,
        ?int $uploadedBy = null
    ): void {
        $uploadedBy = $uploadedBy ?? auth()->id();
        
        $this->logUserAction(
            'file_uploaded',
            $document,
            $uploadedBy,
            [
                'original_filename' => $document->original_filename,
                'file_size' => $document->file_size,
                'mime_type' => $document->mime_type,
                'document_type' => $document->document_type,
            ],
            "File uploaded: {$document->original_filename} for user {$document->user->name}"
        );
    }

    /**
     * Log file deletion activity.
     */
    public function logFileDeletion(
        UploadedDocument $document,
        ?int $deletedBy = null,
        ?string $reason = null
    ): void {
        $deletedBy = $deletedBy ?? auth()->id();
        
        $this->logUserAction(
            'file_deleted',
            $document,
            $deletedBy,
            [
                'original_filename' => $document->original_filename,
                'file_size' => $document->file_size,
                'reason' => $reason,
            ],
            "File deleted: {$document->original_filename} for user {$document->user->name}"
        );
    }

    /**
     * Log bulk operations.
     */
    public function logBulkOperation(
        string $operationType,
        array $results,
        ?int $performedBy = null,
        ?array $metadata = null
    ): void {
        $performedBy = $performedBy ?? auth()->id();
        
        $successCount = collect($results)->where('success', true)->count();
        $failureCount = collect($results)->where('success', false)->count();
        
        $this->logUserAction(
            'bulk_operation',
            null,
            $performedBy,
            array_merge([
                'operation_type' => $operationType,
                'total_records' => count($results),
                'successful_operations' => $successCount,
                'failed_operations' => $failureCount,
                'results' => $results,
            ], $metadata ?? []),
            "Bulk {$operationType}: {$successCount} successful, {$failureCount} failed"
        );
    }

    /**
     * Generate audit report for a specific time period.
     */
    public function generateAuditReport(
        Carbon $startDate,
        Carbon $endDate,
        ?array $eventTypes = null,
        ?int $userId = null,
        int $perPage = 50
    ): array {
        $query = SalaryHistory::query()
            ->with(['user:id,name,email', 'changedBy:id,name,email'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($userId) {
            $query->where('changed_by', $userId);
        }

        $salaryChanges = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Get file activity
        $fileActivity = UploadedDocument::withTrashed()
            ->with(['user:id,name,email'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orWhereBetween('deleted_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get();

        // Calculate statistics
        $stats = $this->calculateAuditStatistics($startDate, $endDate, $eventTypes, $userId);

        return [
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'days' => $startDate->diffInDays($endDate),
            ],
            'statistics' => $stats,
            'salary_changes' => $salaryChanges,
            'file_activity' => $fileActivity,
            'summary' => $this->generateReportSummary($stats),
        ];
    }

    /**
     * Get user activity summary.
     */
    public function getUserActivitySummary(
        User $user,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array {
        $startDate = $startDate ?? now()->subDays(30);
        $endDate = $endDate ?? now();

        // Get salary history
        $salaryHistory = $user->salaryHistory()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get();

        // Get file activity
        $fileActivity = $user->uploadedDocuments()
            ->withTrashed()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orWhereBetween('deleted_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get();

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'salary_changes' => $salaryHistory,
            'file_activity' => $fileActivity,
            'statistics' => [
                'total_salary_changes' => $salaryHistory->count(),
                'total_file_uploads' => $fileActivity->whereNull('deleted_at')->count(),
                'total_file_deletions' => $fileActivity->whereNotNull('deleted_at')->count(),
                'last_salary_change' => $salaryHistory->first()?->created_at,
                'last_file_activity' => $fileActivity->first()?->created_at,
            ],
        ];
    }

    /**
     * Get system-wide audit statistics.
     */
    public function getSystemAuditStatistics(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->subDays(30);
        $endDate = $endDate ?? now();

        return $this->calculateAuditStatistics($startDate, $endDate);
    }

    /**
     * Search audit logs with filters.
     */
    public function searchAuditLogs(
        ?string $searchTerm = null,
        ?array $eventTypes = null,
        ?int $userId = null,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        int $perPage = 50
    ): LengthAwarePaginator {
        $startDate = $startDate ?? now()->subDays(30);
        $endDate = $endDate ?? now();

        $query = SalaryHistory::query()
            ->with(['user:id,name,email', 'changedBy:id,name,email'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($searchTerm) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('change_reason', 'LIKE', "%{$searchTerm}%")
                  ->orWhereHas('user', function ($userQuery) use ($searchTerm) {
                      $userQuery->where('name', 'LIKE', "%{$searchTerm}%")
                               ->orWhere('email', 'LIKE', "%{$searchTerm}%");
                  });
            });
        }

        if ($userId) {
            $query->where('changed_by', $userId);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Store audit log in database (implement if you create an audit_logs table).
     */
    private function storeAuditLog(array $auditData): void
    {
        // This would store in a dedicated audit_logs table if implemented
        // For now, we're using the existing SalaryHistory table and application logs
        
        // Example implementation:
        // DB::table('audit_logs')->insert($auditData + ['created_at' => now()]);
    }

    /**
     * Generate description for audit events.
     */
    private function generateDescription(string $eventType, ?Model $subject = null): string
    {
        $eventName = self::EVENT_TYPES[$eventType] ?? $eventType;
        
        if (!$subject) {
            return $eventName;
        }

        $subjectType = class_basename($subject);
        $subjectId = $subject->id;

        return "{$eventName} - {$subjectType} #{$subjectId}";
    }

    /**
     * Calculate changes between old and new values.
     */
    private function calculateChanges(array $oldValues, array $newValues): array
    {
        $changes = [];
        
        foreach ($newValues as $key => $newValue) {
            $oldValue = $oldValues[$key] ?? null;
            
            if ($oldValue !== $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }
        
        return $changes;
    }

    /**
     * Calculate audit statistics for a given period.
     */
    private function calculateAuditStatistics(
        Carbon $startDate,
        Carbon $endDate,
        ?array $eventTypes = null,
        ?int $userId = null
    ): array {
        $salaryChangesQuery = SalaryHistory::whereBetween('created_at', [$startDate, $endDate]);
        $fileActivityQuery = UploadedDocument::withTrashed()
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($userId) {
            $salaryChangesQuery->where('changed_by', $userId);
            $fileActivityQuery->where('user_id', $userId);
        }

        $salaryChanges = $salaryChangesQuery->get();
        $fileActivity = $fileActivityQuery->get();

        return [
            'total_salary_changes' => $salaryChanges->count(),
            'salary_increases' => $salaryChanges->filter(fn($h) => $h->isSalaryIncrease())->count(),
            'salary_decreases' => $salaryChanges->filter(fn($h) => $h->isSalaryDecrease())->count(),
            'commission_changes' => $salaryChanges->where('change_type', 'commission_change')->count(),
            'total_file_uploads' => $fileActivity->whereNull('deleted_at')->count(),
            'total_file_deletions' => $fileActivity->whereNotNull('deleted_at')->count(),
            'unique_users_affected' => $salaryChanges->pluck('user_id')->unique()->count(),
            'unique_admins_active' => $salaryChanges->pluck('changed_by')->unique()->count(),
            'average_changes_per_day' => $startDate->diffInDays($endDate) > 0 
                ? round($salaryChanges->count() / $startDate->diffInDays($endDate), 2) 
                : 0,
            'most_active_day' => $this->getMostActiveDay($salaryChanges),
            'change_types_distribution' => $salaryChanges->countBy('change_type')->toArray(),
        ];
    }

    /**
     * Get the most active day in the audit period.
     */
    private function getMostActiveDay($changes): ?string
    {
        if ($changes->isEmpty()) {
            return null;
        }

        $dailyCounts = $changes->groupBy(function ($change) {
            return $change->created_at->toDateString();
        })->map->count();

        $mostActiveDay = $dailyCounts->sortDesc()->keys()->first();
        
        return $mostActiveDay;
    }

    /**
     * Generate a summary of the audit report.
     */
    private function generateReportSummary(array $stats): string
    {
        $summary = [];
        
        if ($stats['total_salary_changes'] > 0) {
            $summary[] = "{$stats['total_salary_changes']} salary changes";
            
            if ($stats['salary_increases'] > 0) {
                $summary[] = "{$stats['salary_increases']} increases";
            }
            
            if ($stats['salary_decreases'] > 0) {
                $summary[] = "{$stats['salary_decreases']} decreases";
            }
        }
        
        if ($stats['total_file_uploads'] > 0) {
            $summary[] = "{$stats['total_file_uploads']} file uploads";
        }
        
        if ($stats['total_file_deletions'] > 0) {
            $summary[] = "{$stats['total_file_deletions']} file deletions";
        }
        
        if ($stats['unique_users_affected'] > 0) {
            $summary[] = "{$stats['unique_users_affected']} users affected";
        }
        
        return implode(', ', $summary) ?: 'No activity recorded';
    }
}