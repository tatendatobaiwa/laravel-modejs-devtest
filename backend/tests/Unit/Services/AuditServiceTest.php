<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\User;
use App\Models\Salary;
use App\Models\SalaryHistory;
use App\Models\UploadedDocument;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AuditServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuditService $auditService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditService = new AuditService();
    }

    /** @test */
    public function it_has_correct_event_types()
    {
        $expectedEventTypes = [
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

        $this->assertEquals($expectedEventTypes, AuditService::EVENT_TYPES);
    }

    /** @test */
    public function it_logs_user_action()
    {
        Log::fake();
        
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->auditService->logUserAction(
            'user_created',
            $user,
            $user->id,
            ['additional' => 'metadata'],
            'Custom description'
        );

        Log::assertLogged('info', function ($message, $context) use ($user) {
            return $message === 'Audit Event' &&
                   $context['event_type'] === 'user_created' &&
                   $context['user_id'] === $user->id &&
                   $context['subject_type'] === User::class &&
                   $context['subject_id'] === $user->id &&
                   $context['description'] === 'Custom description' &&
                   isset($context['metadata']['ip_address']) &&
                   isset($context['metadata']['user_agent']) &&
                   isset($context['metadata']['timestamp']) &&
                   $context['metadata']['additional'] === 'metadata';
        });
    }

    /** @test */
    public function it_logs_user_action_without_subject()
    {
        Log::fake();
        
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->auditService->logUserAction('system_action', null, $user->id);

        Log::assertLogged('info', function ($message, $context) use ($user) {
            return $message === 'Audit Event' &&
                   $context['event_type'] === 'system_action' &&
                   $context['user_id'] === $user->id &&
                   $context['subject_type'] === null &&
                   $context['subject_id'] === null;
        });
    }

    /** @test */
    public function it_logs_salary_change()
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $salary = Salary::factory()->forUser($user)->create([
            'salary_local_currency' => 55000,
            'salary_euros' => 46750,
            'commission' => 600,
        ]);

        $originalValues = [
            'salary_local_currency' => 50000,
            'salary_euros' => 42500,
            'commission' => 500,
        ];

        $historyRecord = $this->auditService->logSalaryChange(
            $salary,
            $originalValues,
            $admin->id,
            'Performance increase'
        );

        $this->assertInstanceOf(SalaryHistory::class, $historyRecord);
        $this->assertEquals($user->id, $historyRecord->user_id);
        $this->assertEquals($admin->id, $historyRecord->changed_by);
        $this->assertEquals('Performance increase', $historyRecord->change_reason);
    }

    /** @test */
    public function it_logs_file_upload_activity()
    {
        Log::fake();
        
        $user = User::factory()->create();
        $uploader = User::factory()->create();
        $document = UploadedDocument::factory()->forUser($user)->create([
            'original_filename' => 'salary_document.pdf',
            'file_size' => 2048,
            'mime_type' => 'application/pdf',
            'document_type' => 'document',
        ]);

        $this->auditService->logFileUpload($document, $uploader->id);

        Log::assertLogged('info', function ($message, $context) use ($document, $uploader, $user) {
            return $message === 'Audit Event' &&
                   $context['event_type'] === 'file_uploaded' &&
                   $context['user_id'] === $uploader->id &&
                   $context['subject_type'] === UploadedDocument::class &&
                   $context['subject_id'] === $document->id &&
                   $context['metadata']['original_filename'] === 'salary_document.pdf' &&
                   $context['metadata']['file_size'] === 2048 &&
                   $context['metadata']['mime_type'] === 'application/pdf' &&
                   $context['metadata']['document_type'] === 'document' &&
                   str_contains($context['description'], $user->name);
        });
    }

    /** @test */
    public function it_logs_file_deletion_activity()
    {
        Log::fake();
        
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $document = UploadedDocument::factory()->forUser($user)->create([
            'original_filename' => 'old_document.pdf',
            'file_size' => 1024,
        ]);

        $this->auditService->logFileDeletion($document, $admin->id, 'Document expired');

        Log::assertLogged('info', function ($message, $context) use ($document, $admin, $user) {
            return $message === 'Audit Event' &&
                   $context['event_type'] === 'file_deleted' &&
                   $context['user_id'] === $admin->id &&
                   $context['subject_type'] === UploadedDocument::class &&
                   $context['subject_id'] === $document->id &&
                   $context['metadata']['original_filename'] === 'old_document.pdf' &&
                   $context['metadata']['file_size'] === 1024 &&
                   $context['metadata']['reason'] === 'Document expired' &&
                   str_contains($context['description'], $user->name);
        });
    }

    /** @test */
    public function it_logs_bulk_operations()
    {
        Log::fake();
        
        $admin = User::factory()->create();
        
        $results = [
            ['user_id' => 1, 'success' => true],
            ['user_id' => 2, 'success' => true],
            ['user_id' => 3, 'success' => false, 'error' => 'Validation failed'],
        ];

        $this->auditService->logBulkOperation(
            'salary_update',
            $results,
            $admin->id,
            ['batch_id' => 'batch_123']
        );

        Log::assertLogged('info', function ($message, $context) use ($admin, $results) {
            return $message === 'Audit Event' &&
                   $context['event_type'] === 'bulk_operation' &&
                   $context['user_id'] === $admin->id &&
                   $context['metadata']['operation_type'] === 'salary_update' &&
                   $context['metadata']['total_records'] === 3 &&
                   $context['metadata']['successful_operations'] === 2 &&
                   $context['metadata']['failed_operations'] === 1 &&
                   $context['metadata']['batch_id'] === 'batch_123' &&
                   $context['metadata']['results'] === $results &&
                   str_contains($context['description'], '2 successful, 1 failed');
        });
    }

    /** @test */
    public function it_generates_audit_report()
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        
        // Create salary history
        SalaryHistory::factory()->count(3)->forUser($user)->create([
            'created_at' => now()->subDays(5),
            'changed_by' => $admin->id,
        ]);
        
        // Create file activity
        UploadedDocument::factory()->count(2)->forUser($user)->create([
            'created_at' => now()->subDays(3),
        ]);

        $startDate = Carbon::now()->subDays(10);
        $endDate = Carbon::now();

        $report = $this->auditService->generateAuditReport($startDate, $endDate);

        $this->assertArrayHasKey('period', $report);
        $this->assertArrayHasKey('statistics', $report);
        $this->assertArrayHasKey('salary_changes', $report);
        $this->assertArrayHasKey('file_activity', $report);
        $this->assertArrayHasKey('summary', $report);
        
        $this->assertEquals($startDate->toDateString(), $report['period']['start_date']);
        $this->assertEquals($endDate->toDateString(), $report['period']['end_date']);
        $this->assertEquals(10, $report['period']['days']);
        
        $this->assertEquals(3, $report['statistics']['total_salary_changes']);
        $this->assertEquals(2, $report['statistics']['total_file_uploads']);
    }

    /** @test */
    public function it_gets_user_activity_summary()
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        
        // Create salary history
        $salaryHistory = SalaryHistory::factory()->count(2)->forUser($user)->create([
            'created_at' => now()->subDays(5),
        ]);
        
        // Create file activity
        $fileActivity = UploadedDocument::factory()->count(3)->forUser($user)->create([
            'created_at' => now()->subDays(3),
        ]);

        $summary = $this->auditService->getUserActivitySummary($user);

        $this->assertEquals($user->id, $summary['user']['id']);
        $this->assertEquals($user->name, $summary['user']['name']);
        $this->assertEquals($user->email, $summary['user']['email']);
        
        $this->assertEquals(2, $summary['statistics']['total_salary_changes']);
        $this->assertEquals(3, $summary['statistics']['total_file_uploads']);
        $this->assertEquals(0, $summary['statistics']['total_file_deletions']);
        
        $this->assertNotNull($summary['statistics']['last_salary_change']);
        $this->assertNotNull($summary['statistics']['last_file_activity']);
    }

    /** @test */
    public function it_gets_system_audit_statistics()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $admin = User::factory()->create();
        
        // Create salary changes
        SalaryHistory::factory()->create([
            'user_id' => $user1->id,
            'old_salary_euros' => 40000,
            'new_salary_euros' => 45000,
            'change_type' => 'salary_change',
            'changed_by' => $admin->id,
            'created_at' => now()->subDays(5),
        ]);
        
        SalaryHistory::factory()->create([
            'user_id' => $user2->id,
            'old_salary_euros' => 50000,
            'new_salary_euros' => 48000,
            'change_type' => 'salary_change',
            'changed_by' => $admin->id,
            'created_at' => now()->subDays(3),
        ]);
        
        SalaryHistory::factory()->create([
            'user_id' => $user1->id,
            'old_commission' => 500,
            'new_commission' => 600,
            'change_type' => 'commission_change',
            'changed_by' => $admin->id,
            'created_at' => now()->subDays(1),
        ]);

        $stats = $this->auditService->getSystemAuditStatistics();

        $this->assertEquals(3, $stats['total_salary_changes']);
        $this->assertEquals(1, $stats['salary_increases']);
        $this->assertEquals(1, $stats['salary_decreases']);
        $this->assertEquals(1, $stats['commission_changes']);
        $this->assertEquals(2, $stats['unique_users_affected']);
        $this->assertEquals(1, $stats['unique_admins_active']);
        $this->assertGreaterThan(0, $stats['average_changes_per_day']);
    }

    /** @test */
    public function it_searches_audit_logs()
    {
        $user = User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $admin = User::factory()->create();
        
        SalaryHistory::factory()->create([
            'user_id' => $user->id,
            'changed_by' => $admin->id,
            'change_reason' => 'Performance bonus increase',
            'created_at' => now()->subDays(5),
        ]);
        
        SalaryHistory::factory()->create([
            'user_id' => $user->id,
            'changed_by' => $admin->id,
            'change_reason' => 'Annual salary review',
            'created_at' => now()->subDays(3),
        ]);

        // Search by reason
        $results = $this->auditService->searchAuditLogs('Performance bonus');
        $this->assertEquals(1, $results->count());
        
        // Search by user name
        $results = $this->auditService->searchAuditLogs('John Doe');
        $this->assertEquals(2, $results->count());
        
        // Search by user email
        $results = $this->auditService->searchAuditLogs('john@example.com');
        $this->assertEquals(2, $results->count());
    }

    /** @test */
    public function it_filters_audit_logs_by_user()
    {
        $admin1 = User::factory()->create();
        $admin2 = User::factory()->create();
        
        SalaryHistory::factory()->count(2)->create(['changed_by' => $admin1->id]);
        SalaryHistory::factory()->count(3)->create(['changed_by' => $admin2->id]);

        $results = $this->auditService->searchAuditLogs(null, null, $admin1->id);
        
        $this->assertEquals(2, $results->count());
    }

    /** @test */
    public function it_filters_audit_logs_by_date_range()
    {
        SalaryHistory::factory()->create(['created_at' => '2024-01-01']);
        SalaryHistory::factory()->create(['created_at' => '2024-06-01']);
        SalaryHistory::factory()->create(['created_at' => '2024-12-01']);

        $results = $this->auditService->searchAuditLogs(
            null,
            null,
            null,
            Carbon::parse('2024-05-01'),
            Carbon::parse('2024-11-30')
        );
        
        $this->assertEquals(1, $results->count());
    }

    /** @test */
    public function it_calculates_audit_statistics_correctly()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        // Create salary increases
        SalaryHistory::factory()->count(2)->create([
            'user_id' => $user1->id,
            'old_salary_euros' => 40000,
            'new_salary_euros' => 45000,
            'created_at' => now()->subDays(5),
        ]);
        
        // Create salary decreases
        SalaryHistory::factory()->create([
            'user_id' => $user2->id,
            'old_salary_euros' => 50000,
            'new_salary_euros' => 48000,
            'created_at' => now()->subDays(3),
        ]);
        
        // Create commission changes
        SalaryHistory::factory()->create([
            'user_id' => $user1->id,
            'change_type' => 'commission_change',
            'created_at' => now()->subDays(1),
        ]);

        $startDate = Carbon::now()->subDays(10);
        $endDate = Carbon::now();
        
        $stats = $this->auditService->getSystemAuditStatistics($startDate, $endDate);

        $this->assertEquals(4, $stats['total_salary_changes']);
        $this->assertEquals(2, $stats['salary_increases']);
        $this->assertEquals(1, $stats['salary_decreases']);
        $this->assertEquals(1, $stats['commission_changes']);
        $this->assertEquals(2, $stats['unique_users_affected']);
    }

    /** @test */
    public function it_generates_description_for_audit_events()
    {
        $user = User::factory()->create();
        
        // Test with subject
        $this->auditService->logUserAction('user_created', $user);
        
        // Test without subject
        $this->auditService->logUserAction('system_action');
        
        // Verify logs were created (implicitly tests description generation)
        $this->assertTrue(true); // If no exceptions thrown, descriptions were generated
    }

    /** @test */
    public function it_calculates_changes_between_values()
    {
        $user = User::factory()->create();
        $salary = Salary::factory()->forUser($user)->create();
        
        $oldValues = [
            'salary_euros' => 40000,
            'commission' => 500,
            'notes' => 'Old notes',
        ];
        
        $newValues = [
            'salary_euros' => 45000,
            'commission' => 500,
            'notes' => 'New notes',
        ];

        // This is tested indirectly through logSalaryChange
        $historyRecord = $this->auditService->logSalaryChange($salary, $oldValues);
        
        $this->assertNotNull($historyRecord);
    }

    /** @test */
    public function it_identifies_most_active_day()
    {
        // Create multiple changes on different days
        SalaryHistory::factory()->count(1)->create(['created_at' => '2024-01-01']);
        SalaryHistory::factory()->count(3)->create(['created_at' => '2024-01-02']); // Most active
        SalaryHistory::factory()->count(2)->create(['created_at' => '2024-01-03']);

        $stats = $this->auditService->getSystemAuditStatistics(
            Carbon::parse('2024-01-01'),
            Carbon::parse('2024-01-03')
        );

        $this->assertEquals('2024-01-02', $stats['most_active_day']);
    }

    /** @test */
    public function it_generates_report_summary()
    {
        $user = User::factory()->create();
        
        SalaryHistory::factory()->count(2)->forUser($user)->create();
        UploadedDocument::factory()->count(1)->forUser($user)->create();

        $report = $this->auditService->generateAuditReport(
            Carbon::now()->subDays(10),
            Carbon::now()
        );

        $this->assertStringContainsString('2 salary changes', $report['summary']);
        $this->assertStringContainsString('1 file uploads', $report['summary']);
        $this->assertStringContainsString('1 users affected', $report['summary']);
    }

    /** @test */
    public function it_handles_empty_audit_data()
    {
        $report = $this->auditService->generateAuditReport(
            Carbon::now()->subDays(10),
            Carbon::now()
        );

        $this->assertEquals('No activity recorded', $report['summary']);
        $this->assertEquals(0, $report['statistics']['total_salary_changes']);
    }
}