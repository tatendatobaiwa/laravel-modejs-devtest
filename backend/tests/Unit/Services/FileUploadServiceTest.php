<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\User;
use App\Models\UploadedDocument;
use App\Services\FileUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class FileUploadServiceTest extends TestCase
{
    use RefreshDatabase;

    private FileUploadService $fileUploadService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fileUploadService = new FileUploadService();
        Storage::fake('local');
    }

    /** @test */
    public function it_has_correct_constants()
    {
        $this->assertEquals(10 * 1024 * 1024, FileUploadService::MAX_FILE_SIZE);
        $this->assertEquals(10, FileUploadService::MAX_FILES_PER_USER);
        $this->assertEquals(100 * 1024 * 1024, FileUploadService::MAX_STORAGE_PER_USER);
    }

    /** @test */
    public function it_has_allowed_mime_types()
    {
        $expectedMimeTypes = [
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

        $this->assertEquals($expectedMimeTypes, FileUploadService::ALLOWED_MIME_TYPES);
    }

    /** @test */
    public function it_has_allowed_extensions()
    {
        $expectedExtensions = [
            'pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv'
        ];

        $this->assertEquals($expectedExtensions, FileUploadService::ALLOWED_EXTENSIONS);
    }

    /** @test */
    public function it_has_document_types_mapping()
    {
        $expectedMapping = [
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

        $this->assertEquals($expectedMapping, FileUploadService::DOCUMENT_TYPES);
    }

    /** @test */
    public function it_uploads_file_successfully()
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('test.pdf', 1024, 'application/pdf');

        $uploadedDocument = $this->fileUploadService->uploadFile($file, $user, 'document', 'Test notes');

        $this->assertInstanceOf(UploadedDocument::class, $uploadedDocument);
        $this->assertEquals($user->id, $uploadedDocument->user_id);
        $this->assertEquals('test.pdf', $uploadedDocument->original_filename);
        $this->assertEquals('application/pdf', $uploadedDocument->mime_type);
        $this->assertEquals(1024, $uploadedDocument->file_size);
        $this->assertEquals('document', $uploadedDocument->document_type);
        $this->assertFalse($uploadedDocument->is_verified);
        $this->assertEquals('Test notes', $uploadedDocument->notes);
        $this->assertNotNull($uploadedDocument->file_hash);
    }

    /** @test */
    public function it_generates_secure_filename()
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('original file name.pdf', 1024, 'application/pdf');

        $uploadedDocument = $this->fileUploadService->uploadFile($file, $user);

        $this->assertNotEquals('original file name.pdf', $uploadedDocument->stored_filename);
        $this->assertStringContainsString('.pdf', $uploadedDocument->stored_filename);
        $this->assertStringContainsString('original_file_name', $uploadedDocument->stored_filename);
    }

    /** @test */
    public function it_determines_document_type_automatically()
    {
        $user = User::factory()->create();
        
        $testCases = [
            ['filename' => 'test.pdf', 'expected' => 'document'],
            ['filename' => 'test.jpg', 'expected' => 'image'],
            ['filename' => 'test.xlsx', 'expected' => 'spreadsheet'],
            ['filename' => 'test.txt', 'expected' => 'text'],
        ];

        foreach ($testCases as $testCase) {
            $file = UploadedFile::fake()->create($testCase['filename'], 1024);
            $uploadedDocument = $this->fileUploadService->uploadFile($file, $user);
            
            $this->assertEquals($testCase['expected'], $uploadedDocument->document_type);
        }
    }

    /** @test */
    public function it_validates_file_size()
    {
        $user = User::factory()->create();
        $largeFile = UploadedFile::fake()->create('large.pdf', 11 * 1024); // 11MB

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File size exceeds maximum allowed size');

        $this->fileUploadService->uploadFile($largeFile, $user);
    }

    /** @test */
    public function it_validates_mime_type()
    {
        $user = User::factory()->create();
        $invalidFile = UploadedFile::fake()->create('test.exe', 1024, 'application/x-executable');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File type not allowed');

        $this->fileUploadService->uploadFile($invalidFile, $user);
    }

    /** @test */
    public function it_validates_file_extension()
    {
        $user = User::factory()->create();
        $invalidFile = UploadedFile::fake()->create('test.exe', 1024, 'application/pdf');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File extension not allowed');

        $this->fileUploadService->uploadFile($invalidFile, $user);
    }

    /** @test */
    public function it_validates_user_file_count_quota()
    {
        $user = User::factory()->create();
        
        // Create maximum allowed files
        UploadedDocument::factory()->count(10)->forUser($user)->create();

        $file = UploadedFile::fake()->create('test.pdf', 1024, 'application/pdf');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Maximum number of files reached');

        $this->fileUploadService->uploadFile($file, $user);
    }

    /** @test */
    public function it_validates_user_storage_quota()
    {
        $user = User::factory()->create();
        
        // Create files that use up most of the quota
        UploadedDocument::factory()->forUser($user)->create([
            'file_size' => 99 * 1024 * 1024, // 99MB
        ]);

        $file = UploadedFile::fake()->create('test.pdf', 2 * 1024); // 2MB

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Storage quota exceeded');

        $this->fileUploadService->uploadFile($file, $user);
    }

    /** @test */
    public function it_creates_user_specific_directory()
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('test.pdf', 1024, 'application/pdf');

        $uploadedDocument = $this->fileUploadService->uploadFile($file, $user);

        $this->assertStringContainsString("uploads/users/{$user->id}", $uploadedDocument->file_path);
    }

    /** @test */
    public function it_calculates_file_hash()
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('test.pdf', 1024, 'application/pdf');

        $uploadedDocument = $this->fileUploadService->uploadFile($file, $user);

        $this->assertNotNull($uploadedDocument->file_hash);
        $this->assertEquals(64, strlen($uploadedDocument->file_hash)); // SHA256 hash length
    }

    /** @test */
    public function it_deletes_file_successfully()
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('test.pdf', 1024, 'application/pdf');
        $uploadedDocument = $this->fileUploadService->uploadFile($file, $user);
        $admin = User::factory()->create();

        $result = $this->fileUploadService->deleteFile($uploadedDocument, $admin->id);

        $this->assertTrue($result);
        $this->assertSoftDeleted('uploaded_documents', ['id' => $uploadedDocument->id]);
    }

    /** @test */
    public function it_verifies_document()
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $document = UploadedDocument::factory()->forUser($user)->unverified()->create();

        $verifiedDocument = $this->fileUploadService->verifyDocument(
            $document,
            $admin->id,
            'Document verified successfully'
        );

        $this->assertTrue($verifiedDocument->is_verified);
        $this->assertNotNull($verifiedDocument->verified_at);
        $this->assertEquals($admin->id, $verifiedDocument->verified_by);
        $this->assertEquals('Document verified successfully', $verifiedDocument->notes);
    }

    /** @test */
    public function it_gets_download_url()
    {
        Storage::shouldReceive('exists')->andReturn(true);
        Storage::shouldReceive('temporaryUrl')->andReturn('https://example.com/download/test.pdf');

        $document = UploadedDocument::factory()->create();

        $url = $this->fileUploadService->getDownloadUrl($document, 30);

        $this->assertEquals('https://example.com/download/test.pdf', $url);
    }

    /** @test */
    public function it_throws_exception_when_file_not_found_for_download()
    {
        Storage::shouldReceive('exists')->andReturn(false);

        $document = UploadedDocument::factory()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File not found');

        $this->fileUploadService->getDownloadUrl($document);
    }

    /** @test */
    public function it_cleans_up_old_files()
    {
        $oldDocument = UploadedDocument::factory()->create([
            'deleted_at' => now()->subDays(400),
        ]);
        
        $recentDocument = UploadedDocument::factory()->create([
            'deleted_at' => now()->subDays(30),
        ]);

        Storage::shouldReceive('exists')->andReturn(true);
        Storage::shouldReceive('delete')->once();

        $results = $this->fileUploadService->cleanupOldFiles(365);

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertEquals($oldDocument->id, $results[0]['document_id']);
    }

    /** @test */
    public function it_gets_user_storage_statistics()
    {
        $user = User::factory()->create();
        
        UploadedDocument::factory()->count(3)->forUser($user)->create([
            'file_size' => 1024 * 1024, // 1MB each
        ]);

        $stats = $this->fileUploadService->getUserStorageStats($user);

        $this->assertEquals(3, $stats['total_files']);
        $this->assertEquals(3 * 1024 * 1024, $stats['total_size_bytes']);
        $this->assertEquals('3 MB', $stats['total_size_formatted']);
        $this->assertEquals(3.0, $stats['quota_used_percentage']); // 3MB out of 100MB
        $this->assertTrue($stats['can_upload_more']);
    }

    /** @test */
    public function it_prevents_upload_when_quota_exceeded()
    {
        $user = User::factory()->create();
        
        // Create 10 files (max allowed)
        UploadedDocument::factory()->count(10)->forUser($user)->create();

        $stats = $this->fileUploadService->getUserStorageStats($user);

        $this->assertFalse($stats['can_upload_more']);
    }

    /** @test */
    public function it_formats_file_sizes_correctly()
    {
        $user = User::factory()->create();
        
        $testCases = [
            ['size' => 512, 'expected' => '512 B'],
            ['size' => 1536, 'expected' => '1.5 KB'],
            ['size' => 1572864, 'expected' => '1.5 MB'],
            ['size' => 1610612736, 'expected' => '1.5 GB'],
        ];

        foreach ($testCases as $testCase) {
            UploadedDocument::factory()->forUser($user)->create(['file_size' => $testCase['size']]);
            $stats = $this->fileUploadService->getUserStorageStats($user);
            
            $this->assertEquals($testCase['expected'], $stats['total_size_formatted']);
            
            // Clean up for next iteration
            UploadedDocument::where('user_id', $user->id)->delete();
        }
    }

    /** @test */
    public function it_gets_system_file_statistics()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        UploadedDocument::factory()->count(2)->forUser($user1)->verified()->create(['file_size' => 1024]);
        UploadedDocument::factory()->count(1)->forUser($user2)->unverified()->create(['file_size' => 2048]);
        UploadedDocument::factory()->count(1)->forUser($user1)->create(['deleted_at' => now()]);

        $stats = $this->fileUploadService->getSystemFileStats();

        $this->assertEquals(3, $stats['total_files']);
        $this->assertEquals(4096, $stats['total_size_bytes']); // 2*1024 + 1*2048
        $this->assertEquals(2, $stats['verified_files']);
        $this->assertEquals(66.67, $stats['verification_rate']); // 2/3 * 100
        $this->assertEquals(1, $stats['deleted_files']);
        $this->assertEquals(1365.33, $stats['average_file_size']); // 4096/3
    }

    /** @test */
    public function it_performs_security_checks()
    {
        $user = User::factory()->create();
        
        // Test dangerous extensions
        $dangerousFile = UploadedFile::fake()->create('malware.exe', 1024);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Executable files are not allowed');

        $this->fileUploadService->uploadFile($dangerousFile, $user);
    }

    /** @test */
    public function it_logs_successful_uploads()
    {
        Log::fake();
        
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('test.pdf', 1024, 'application/pdf');

        $this->fileUploadService->uploadFile($file, $user);

        Log::assertLogged('info', function ($message, $context) use ($user) {
            return $message === 'File uploaded successfully' &&
                   $context['user_id'] === $user->id &&
                   $context['original_filename'] === 'test.pdf' &&
                   $context['file_size'] === 1024 &&
                   $context['mime_type'] === 'application/pdf';
        });
    }

    /** @test */
    public function it_logs_file_deletions()
    {
        Log::fake();
        
        $document = UploadedDocument::factory()->create();
        $admin = User::factory()->create();

        $this->fileUploadService->deleteFile($document, $admin->id);

        Log::assertLogged('info', function ($message, $context) use ($document, $admin) {
            return $message === 'File deleted successfully' &&
                   $context['document_id'] === $document->id &&
                   $context['user_id'] === $document->user_id &&
                   $context['deleted_by'] === $admin->id;
        });
    }

    /** @test */
    public function it_logs_document_verification()
    {
        Log::fake();
        
        $document = UploadedDocument::factory()->unverified()->create();
        $admin = User::factory()->create();

        $this->fileUploadService->verifyDocument($document, $admin->id);

        Log::assertLogged('info', function ($message, $context) use ($document, $admin) {
            return $message === 'Document verified' &&
                   $context['document_id'] === $document->id &&
                   $context['user_id'] === $document->user_id &&
                   $context['verified_by'] === $admin->id;
        });
    }

    /** @test */
    public function it_handles_upload_failure_gracefully()
    {
        Log::fake();
        Storage::shouldReceive('exists')->andReturn(false);
        Storage::shouldReceive('delete')->never();
        
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('test.pdf', 1024, 'application/pdf');
        
        // Mock storage failure
        Storage::shouldReceive('storeAs')->andReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File upload failed');

        $this->fileUploadService->uploadFile($file, $user);
    }

    /** @test */
    public function it_cleans_up_file_on_database_failure()
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('test.pdf', 1024, 'application/pdf');
        
        // Force a database error by using invalid user_id
        $user->id = 999999;

        Storage::shouldReceive('exists')->andReturn(true);
        Storage::shouldReceive('delete')->once();

        $this->expectException(RuntimeException::class);

        $this->fileUploadService->uploadFile($file, $user);
    }
}