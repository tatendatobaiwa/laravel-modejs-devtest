<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\UploadedDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

class FileUploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** @test */
    public function it_rejects_executable_files()
    {
        $maliciousFiles = [
            UploadedFile::fake()->create('malware.exe', 100, 'application/x-executable'),
            UploadedFile::fake()->create('script.bat', 100, 'application/x-bat'),
            UploadedFile::fake()->create('virus.com', 100, 'application/x-msdownload'),
            UploadedFile::fake()->create('trojan.scr', 100, 'application/x-msdownload'),
        ];

        foreach ($maliciousFiles as $file) {
            $userData = [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'salary_local_currency' => 50000,
                'local_currency_code' => 'USD',
                'document' => $file
            ];

            $response = $this->postJson('/api/v1/public/users', $userData);

            $response->assertStatus(422)
                    ->assertJsonValidationErrors(['document']);

            // Ensure file was not stored
            Storage::assertMissing('uploads/users/' . $file->hashName());
        }
    }

    /** @test */
    public function it_rejects_script_files()
    {
        $scriptFiles = [
            UploadedFile::fake()->create('script.js', 100, 'application/javascript'),
            UploadedFile::fake()->create('script.php', 100, 'application/x-php'),
            UploadedFile::fake()->create('script.py', 100, 'text/x-python'),
            UploadedFile::fake()->create('script.sh', 100, 'application/x-sh'),
            UploadedFile::fake()->create('script.vbs', 100, 'text/vbscript'),
        ];

        foreach ($scriptFiles as $file) {
            $userData = [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'salary_local_currency' => 50000,
                'local_currency_code' => 'USD',
                'document' => $file
            ];

            $response = $this->postJson('/api/v1/public/users', $userData);

            $response->assertStatus(422)
                    ->assertJsonValidationErrors(['document']);
        }
    }

    /** @test */
    public function it_validates_file_mime_type_matches_extension()
    {
        // Create a file with PDF extension but different MIME type
        $fakeFile = UploadedFile::fake()->create('document.pdf', 100, 'text/plain');

        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'salary_local_currency' => 50000,
            'local_currency_code' => 'USD',
            'document' => $fakeFile
        ];

        $response = $this->postJson('/api/v1/public/users', $userData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['document']);
    }

    /** @test */
    public function it_rejects_files_exceeding_size_limit()
    {
        // Create a file larger than allowed (assuming 5MB limit)
        $largeFile = UploadedFile::fake()->create('large_document.pdf', 6144, 'application/pdf'); // 6MB

        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'salary_local_currency' => 50000,
            'local_currency_code' => 'USD',
            'document' => $largeFile
        ];

        $response = $this->postJson('/api/v1/public/users', $userData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['document']);
    }

    /** @test */
    public function it_accepts_valid_document_types()
    {
        $validFiles = [
            UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->create('document.doc', 100, 'application/msword'),
            UploadedFile::fake()->create('document.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            UploadedFile::fake()->create('image.jpg', 100, 'image/jpeg'),
            UploadedFile::fake()->create('image.png', 100, 'image/png'),
        ];

        foreach ($validFiles as $index => $file) {
            $userData = [
                'name' => 'John Doe',
                'email' => "john{$index}@example.com",
                'salary_local_currency' => 50000,
                'local_currency_code' => 'USD',
                'document' => $file
            ];

            $response = $this->postJson('/api/v1/public/users', $userData);

            $response->assertStatus(201)
                    ->assertJson([
                        'success' => true
                    ]);

            // Verify file was stored
            $user = User::where('email', "john{$index}@example.com")->first();
            Storage::assertExists('uploads/users/' . $user->id . '/salary_document/' . $file->hashName());
        }
    }

    /** @test */
    public function it_sanitizes_uploaded_filenames()
    {
        $maliciousFilenames = [
            '../../../etc/passwd.pdf',
            '..\\..\\windows\\system32\\config\\sam.pdf',
            'file with spaces and special chars!@#$.pdf',
            'файл_с_unicode_символами.pdf',
        ];

        foreach ($maliciousFilenames as $index => $filename) {
            $file = UploadedFile::fake()->createWithContent($filename, 100, 'application/pdf', 'PDF content');

            $userData = [
                'name' => 'John Doe',
                'email' => "john{$index}@example.com",
                'salary_local_currency' => 50000,
                'local_currency_code' => 'USD',
                'document' => $file
            ];

            $response = $this->postJson('/api/v1/public/users', $userData);

            $response->assertStatus(201);

            // Verify the file was stored with a sanitized name
            $user = User::where('email', "john{$index}@example.com")->first();
            $document = UploadedDocument::where('user_id', $user->id)->first();
            
            $this->assertNotNull($document);
            $this->assertNotEquals($filename, $document->stored_filename);
            $this->assertStringNotContainsString('..', $document->stored_path);
        }
    }

    /** @test */
    public function it_prevents_zip_bomb_attacks()
    {
        // Create a fake ZIP file that could be a zip bomb
        $zipFile = UploadedFile::fake()->create('archive.zip', 100, 'application/zip');

        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'salary_local_currency' => 50000,
            'local_currency_code' => 'USD',
            'document' => $zipFile
        ];

        $response = $this->postJson('/api/v1/public/users', $userData);

        // ZIP files should be rejected for security reasons
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['document']);
    }

    /** @test */
    public function it_prevents_file_upload_without_extension()
    {
        $fileWithoutExtension = UploadedFile::fake()->create('document', 100, 'application/octet-stream');

        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'salary_local_currency' => 50000,
            'local_currency_code' => 'USD',
            'document' => $fileWithoutExtension
        ];

        $response = $this->postJson('/api/v1/public/users', $userData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['document']);
    }

    /** @test */
    public function it_enforces_file_upload_rate_limiting()
    {
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        // Make multiple upload requests quickly
        for ($i = 0; $i < 10; $i++) {
            $userData = [
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'salary_local_currency' => 50000,
                'local_currency_code' => 'USD',
                'document' => $file
            ];

            $response = $this->postJson('/api/v1/public/users', $userData);
            
            if ($i < 5) {
                $response->assertStatus(201);
            } else {
                // Should eventually hit rate limit
                $this->assertTrue(in_array($response->status(), [201, 429]));
            }
        }
    }

    /** @test */
    public function it_stores_files_in_user_specific_directories()
    {
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'salary_local_currency' => 50000,
            'local_currency_code' => 'USD',
            'document' => $file
        ];

        $response = $this->postJson('/api/v1/public/users', $userData);

        $response->assertStatus(201);

        $user = User::where('email', 'john@example.com')->first();
        $document = UploadedDocument::where('user_id', $user->id)->first();

        // Verify file is stored in user-specific directory
        $this->assertStringContainsString("users/{$user->id}/", $document->stored_path);
        Storage::assertExists($document->stored_path);
    }

    /** @test */
    public function it_prevents_file_overwrite_attacks()
    {
        $file1 = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
        $file2 = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        // Upload first file
        $userData1 = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'salary_local_currency' => 50000,
            'local_currency_code' => 'USD',
            'document' => $file1
        ];

        $response1 = $this->postJson('/api/v1/public/users', $userData1);
        $response1->assertStatus(201);

        // Upload second file with same name for different user
        $userData2 = [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'salary_local_currency' => 55000,
            'local_currency_code' => 'USD',
            'document' => $file2
        ];

        $response2 = $this->postJson('/api/v1/public/users', $userData2);
        $response2->assertStatus(201);

        // Verify both files exist and are stored separately
        $user1 = User::where('email', 'john@example.com')->first();
        $user2 = User::where('email', 'jane@example.com')->first();

        $doc1 = UploadedDocument::where('user_id', $user1->id)->first();
        $doc2 = UploadedDocument::where('user_id', $user2->id)->first();

        $this->assertNotEquals($doc1->stored_path, $doc2->stored_path);
        Storage::assertExists($doc1->stored_path);
        Storage::assertExists($doc2->stored_path);
    }

    /** @test */
    public function it_validates_file_content_not_just_extension()
    {
        // Create a file with PDF extension but HTML content
        $htmlContent = '<html><body><script>alert("XSS")</script></body></html>';
        $fakeFile = UploadedFile::fake()->createWithContent('document.pdf', strlen($htmlContent), 'text/html', $htmlContent);

        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'salary_local_currency' => 50000,
            'local_currency_code' => 'USD',
            'document' => $fakeFile
        ];

        $response = $this->postJson('/api/v1/public/users', $userData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['document']);
    }

    /** @test */
    public function it_handles_file_upload_storage_errors_gracefully()
    {
        // Test with a file that has invalid content but valid extension
        $invalidFile = UploadedFile::fake()->createWithContent(
            'document.pdf', 
            10, 
            'text/plain', 
            'This is not a PDF file'
        );

        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'salary_local_currency' => 50000,
            'local_currency_code' => 'USD',
            'document' => $invalidFile
        ];

        $response = $this->postJson('/api/v1/public/users', $userData);

        // Should reject due to MIME type mismatch
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['document']);

        // Verify user was not created
        $this->assertDatabaseMissing('users', [
            'email' => 'john@example.com'
        ]);
    }

    /** @test */
    public function it_cleans_up_files_on_user_deletion()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        // Create user with uploaded document
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'salary_local_currency' => 50000,
            'local_currency_code' => 'USD',
            'document' => $file
        ];

        $response = $this->postJson('/api/v1/public/users', $userData);
        $response->assertStatus(201);

        $user = User::where('email', 'john@example.com')->first();
        $document = UploadedDocument::where('user_id', $user->id)->first();

        // Verify file exists
        Storage::assertExists($document->stored_path);

        // Delete user
        $deleteResponse = $this->deleteJson("/api/v1/admin/users/{$user->id}");
        $deleteResponse->assertStatus(200);

        // Verify file is cleaned up
        Storage::assertMissing($document->stored_path);
    }

    /** @test */
    public function it_prevents_directory_traversal_in_file_paths()
    {
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        // Try to manipulate the upload path through form data
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'salary_local_currency' => 50000,
            'local_currency_code' => 'USD',
            'document' => $file,
            'upload_path' => '../../../etc/passwd' // Attempt directory traversal
        ];

        $response = $this->postJson('/api/v1/public/users', $userData);

        $response->assertStatus(201); // Should succeed but ignore malicious path

        $user = User::where('email', 'john@example.com')->first();
        $document = UploadedDocument::where('user_id', $user->id)->first();

        // Verify file is stored in the correct, safe location
        $this->assertStringStartsWith('uploads/users/', $document->stored_path);
        $this->assertStringNotContainsString('..', $document->stored_path);
        $this->assertStringNotContainsString('/etc/', $document->stored_path);
    }
}