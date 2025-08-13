<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\User;
use App\Models\UploadedDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

class UploadedDocumentTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_has_fillable_attributes()
    {
        $fillable = [
            'user_id',
            'original_filename',
            'stored_filename',
            'file_path',
            'mime_type',
            'file_size',
            'file_hash',
            'document_type',
            'is_verified',
            'verified_at',
            'verified_by',
            'notes',
        ];
        
        $document = new UploadedDocument();
        $this->assertEquals($fillable, $document->getFillable());
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $document = UploadedDocument::factory()->create([
            'file_size' => '1024',
            'is_verified' => '1',
            'verified_at' => '2024-01-01 12:00:00',
        ]);

        $this->assertIsInt($document->file_size);
        $this->assertIsBool($document->is_verified);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $document->verified_at);
    }

    /** @test */
    public function it_hides_sensitive_attributes()
    {
        $hidden = ['file_path', 'file_hash'];
        $document = new UploadedDocument();
        
        $this->assertEquals($hidden, $document->getHidden());
    }

    /** @test */
    public function it_belongs_to_user()
    {
        $user = User::factory()->create();
        $document = UploadedDocument::factory()->forUser($user)->create();

        $this->assertInstanceOf(User::class, $document->user);
        $this->assertEquals($user->id, $document->user->id);
    }

    /** @test */
    public function it_belongs_to_verified_by_user()
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $document = UploadedDocument::factory()->forUser($user)->create([
            'verified_by' => $admin->id,
        ]);

        $this->assertInstanceOf(User::class, $document->verifiedBy);
        $this->assertEquals($admin->id, $document->verifiedBy->id);
    }

    /** @test */
    public function it_can_scope_verified_documents()
    {
        $verifiedDoc = UploadedDocument::factory()->verified()->create();
        $unverifiedDoc = UploadedDocument::factory()->unverified()->create();

        $verifiedDocs = UploadedDocument::verified()->get();
        
        $this->assertCount(1, $verifiedDocs);
        $this->assertEquals($verifiedDoc->id, $verifiedDocs->first()->id);
    }

    /** @test */
    public function it_can_scope_documents_by_type()
    {
        $pdfDoc = UploadedDocument::factory()->create(['document_type' => 'document']);
        $imageDoc = UploadedDocument::factory()->create(['document_type' => 'image']);

        $pdfDocs = UploadedDocument::ofType('document')->get();
        
        $this->assertCount(1, $pdfDocs);
        $this->assertEquals($pdfDoc->id, $pdfDocs->first()->id);
    }

    /** @test */
    public function it_generates_full_file_path_attribute()
    {
        $document = UploadedDocument::factory()->create([
            'file_path' => 'uploads/users/1/test.pdf',
        ]);

        $expectedPath = storage_path('app/uploads/users/1/test.pdf');
        $this->assertEquals($expectedPath, $document->full_file_path);
    }

    /** @test */
    public function it_formats_file_size_for_display()
    {
        $smallFile = UploadedDocument::factory()->create(['file_size' => 512]);
        $mediumFile = UploadedDocument::factory()->create(['file_size' => 1536]); // 1.5 KB
        $largeFile = UploadedDocument::factory()->create(['file_size' => 1572864]); // 1.5 MB
        $hugeFile = UploadedDocument::factory()->create(['file_size' => 1610612736]); // 1.5 GB

        $this->assertEquals('512 B', $smallFile->formatted_file_size);
        $this->assertEquals('1.5 KB', $mediumFile->formatted_file_size);
        $this->assertEquals('1.5 MB', $largeFile->formatted_file_size);
        $this->assertEquals('1.5 GB', $hugeFile->formatted_file_size);
    }

    /** @test */
    public function it_uses_soft_deletes()
    {
        $document = UploadedDocument::factory()->create();
        $documentId = $document->id;

        $document->delete();

        // Document should be soft deleted
        $this->assertSoftDeleted('uploaded_documents', ['id' => $documentId]);
        
        // Document should not be found in normal queries
        $this->assertNull(UploadedDocument::find($documentId));
        
        // Document should be found in withTrashed queries
        $this->assertNotNull(UploadedDocument::withTrashed()->find($documentId));
    }

    /** @test */
    public function it_can_be_restored_after_soft_delete()
    {
        $document = UploadedDocument::factory()->create();
        $documentId = $document->id;

        $document->delete();
        $this->assertSoftDeleted('uploaded_documents', ['id' => $documentId]);

        $document->restore();
        $this->assertDatabaseHas('uploaded_documents', ['id' => $documentId, 'deleted_at' => null]);
    }

    /** @test */
    public function it_validates_required_fields()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        UploadedDocument::create([
            // Missing required user_id
            'original_filename' => 'test.pdf',
            'stored_filename' => 'stored_test.pdf',
            'file_path' => 'uploads/test.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
        ]);
    }

    /** @test */
    public function it_stores_file_metadata_correctly()
    {
        $user = User::factory()->create();
        
        $document = UploadedDocument::create([
            'user_id' => $user->id,
            'original_filename' => 'salary_document.pdf',
            'stored_filename' => 'stored_salary_document_123.pdf',
            'file_path' => 'uploads/users/1/salary_document_123.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 2048,
            'file_hash' => 'abc123def456',
            'document_type' => 'document',
            'is_verified' => false,
            'notes' => 'Salary documentation',
        ]);

        $this->assertEquals($user->id, $document->user_id);
        $this->assertEquals('salary_document.pdf', $document->original_filename);
        $this->assertEquals('stored_salary_document_123.pdf', $document->stored_filename);
        $this->assertEquals('uploads/users/1/salary_document_123.pdf', $document->file_path);
        $this->assertEquals('application/pdf', $document->mime_type);
        $this->assertEquals(2048, $document->file_size);
        $this->assertEquals('abc123def456', $document->file_hash);
        $this->assertEquals('document', $document->document_type);
        $this->assertFalse($document->is_verified);
        $this->assertEquals('Salary documentation', $document->notes);
    }

    /** @test */
    public function it_can_be_verified_by_admin()
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        
        $document = UploadedDocument::factory()->forUser($user)->unverified()->create();
        
        $document->update([
            'is_verified' => true,
            'verified_at' => now(),
            'verified_by' => $admin->id,
            'notes' => 'Document verified by admin',
        ]);

        $this->assertTrue($document->is_verified);
        $this->assertNotNull($document->verified_at);
        $this->assertEquals($admin->id, $document->verified_by);
        $this->assertEquals('Document verified by admin', $document->notes);
    }

    /** @test */
    public function it_handles_different_document_types()
    {
        $documentTypes = ['document', 'image', 'spreadsheet', 'text', 'unknown'];
        
        foreach ($documentTypes as $type) {
            $document = UploadedDocument::factory()->create(['document_type' => $type]);
            $this->assertEquals($type, $document->document_type);
        }
    }

    /** @test */
    public function it_stores_file_hash_for_integrity_checking()
    {
        $document = UploadedDocument::factory()->create([
            'file_hash' => 'sha256:abcdef123456789',
        ]);

        $this->assertEquals('sha256:abcdef123456789', $document->file_hash);
        
        // Verify hash is hidden in array representation
        $array = $document->toArray();
        $this->assertArrayNotHasKey('file_hash', $array);
    }

    /** @test */
    public function it_hides_file_path_in_array_representation()
    {
        $document = UploadedDocument::factory()->create([
            'file_path' => 'uploads/users/1/sensitive_path.pdf',
        ]);

        $array = $document->toArray();
        $this->assertArrayNotHasKey('file_path', $array);
        
        // But it should still be accessible as attribute
        $this->assertEquals('uploads/users/1/sensitive_path.pdf', $document->file_path);
    }

    /** @test */
    public function it_handles_null_verified_by_user()
    {
        $document = UploadedDocument::factory()->create(['verified_by' => null]);

        $this->assertNull($document->verifiedBy);
    }

    /** @test */
    public function it_formats_different_file_sizes_correctly()
    {
        $testCases = [
            ['size' => 0, 'expected' => '0 B'],
            ['size' => 1, 'expected' => '1 B'],
            ['size' => 1023, 'expected' => '1023 B'],
            ['size' => 1024, 'expected' => '1 KB'],
            ['size' => 1536, 'expected' => '1.5 KB'],
            ['size' => 1048576, 'expected' => '1 MB'],
            ['size' => 1572864, 'expected' => '1.5 MB'],
            ['size' => 1073741824, 'expected' => '1 GB'],
            ['size' => 1610612736, 'expected' => '1.5 GB'],
        ];

        foreach ($testCases as $testCase) {
            $document = UploadedDocument::factory()->create(['file_size' => $testCase['size']]);
            $this->assertEquals($testCase['expected'], $document->formatted_file_size);
        }
    }

    /** @test */
    public function it_can_filter_by_verification_status()
    {
        $verifiedDocs = UploadedDocument::factory()->count(3)->verified()->create();
        $unverifiedDocs = UploadedDocument::factory()->count(2)->unverified()->create();

        $verified = UploadedDocument::where('is_verified', true)->get();
        $unverified = UploadedDocument::where('is_verified', false)->get();

        $this->assertCount(3, $verified);
        $this->assertCount(2, $unverified);
    }

    /** @test */
    public function it_maintains_referential_integrity_with_user()
    {
        $user = User::factory()->create();
        $document = UploadedDocument::factory()->forUser($user)->create();

        // Verify the relationship works both ways
        $this->assertEquals($user->id, $document->user_id);
        $this->assertTrue($user->uploadedDocuments->contains($document));
    }

    /** @test */
    public function it_handles_large_file_sizes()
    {
        $largeSize = 5368709120; // 5 GB
        $document = UploadedDocument::factory()->create(['file_size' => $largeSize]);

        $this->assertEquals($largeSize, $document->file_size);
        $this->assertEquals('5 GB', $document->formatted_file_size);
    }

    /** @test */
    public function it_stores_mime_type_correctly()
    {
        $mimeTypes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'application/msword',
            'text/plain',
        ];

        foreach ($mimeTypes as $mimeType) {
            $document = UploadedDocument::factory()->create(['mime_type' => $mimeType]);
            $this->assertEquals($mimeType, $document->mime_type);
        }
    }
}