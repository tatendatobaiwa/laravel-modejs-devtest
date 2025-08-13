<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\User;
use App\Models\UploadedDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Collection;

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
    public function it_has_hidden_attributes()
    {
        $hidden = ['file_path', 'file_hash'];
        $document = new UploadedDocument();
        
        $this->assertEquals($hidden, $document->getHidden());
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $document = UploadedDocument::create([
            'user_id' => User::factory()->create()->id,
            'original_filename' => 'test.pdf',
            'stored_filename' => 'stored.pdf',
            'file_path' => 'documents/stored.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'file_hash' => 'abc123',
            'document_type' => 'salary_document',
            'is_verified' => true,
            'verified_at' => '2024-01-01 12:00:00',
        ]);

        $this->assertIsInt($document->file_size);
        $this->assertIsBool($document->is_verified);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $document->verified_at);
        $this->assertTrue($document->is_verified);
        $this->assertEquals(1024, $document->file_size);
    }

    /** @test */
    public function it_belongs_to_user()
    {
        $user = User::factory()->create();
        $document = UploadedDocument::create([
            'user_id' => $user->id,
            'original_filename' => 'test.pdf',
            'stored_filename' => 'stored.pdf',
            'file_path' => 'documents/stored.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
        ]);

        $this->assertInstanceOf(User::class, $document->user);
        $this->assertEquals($user->id, $document->user->id);
    }

    /** @test */
    public function it_belongs_to_verified_by_user()
    {
        $user = User::factory()->create();
        $verifier = User::factory()->create();
        
        $document = UploadedDocument::create([
            'user_id' => $user->id,
            'original_filename' => 'test.pdf',
            'stored_filename' => 'stored.pdf',
            'file_path' => 'documents/stored.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'verified_by' => $verifier->id,
        ]);

        $this->assertInstanceOf(User::class, $document->verifiedBy);
        $this->assertEquals($verifier->id, $document->verifiedBy->id);
    }

    /** @test */
    public function it_can_scope_verified_documents()
    {
        $user = User::factory()->create();
        
        $verifiedDoc = UploadedDocument::create([
            'user_id' => $user->id,
            'original_filename' => 'verified.pdf',
            'stored_filename' => 'verified.pdf',
            'file_path' => 'documents/verified.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'is_verified' => true,
        ]);
        
        $unverifiedDoc = UploadedDocument::create([
            'user_id' => $user->id,
            'original_filename' => 'unverified.pdf',
            'stored_filename' => 'unverified.pdf',
            'file_path' => 'documents/unverified.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'is_verified' => false,
        ]);

        $verifiedDocuments = UploadedDocument::verified()->get();
        
        $this->assertCount(1, $verifiedDocuments);
        $this->assertEquals($verifiedDoc->id, $verifiedDocuments->first()->id);
    }

    /** @test */
    public function it_can_scope_documents_by_type()
    {
        $user = User::factory()->create();
        
        $salaryDoc = UploadedDocument::create([
            'user_id' => $user->id,
            'original_filename' => 'salary.pdf',
            'stored_filename' => 'salary.pdf',
            'file_path' => 'documents/salary.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'document_type' => 'salary_document',
        ]);
        
        $contractDoc = UploadedDocument::create([
            'user_id' => $user->id,
            'original_filename' => 'contract.pdf',
            'stored_filename' => 'contract.pdf',
            'file_path' => 'documents/contract.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'document_type' => 'contract',
        ]);

        $salaryDocuments = UploadedDocument::ofType('salary_document')->get();
        
        $this->assertCount(1, $salaryDocuments);
        $this->assertEquals($salaryDoc->id, $salaryDocuments->first()->id);
    }

    /** @test */
    public function it_returns_full_file_path_attribute()
    {
        $document = UploadedDocument::create([
            'user_id' => User::factory()->create()->id,
            'original_filename' => 'test.pdf',
            'stored_filename' => 'stored.pdf',
            'file_path' => 'documents/stored.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
        ]);

        $expectedPath = storage_path('app/documents/stored.pdf');
        $this->assertEquals($expectedPath, $document->full_file_path);
    }

    /** @test */
    public function it_returns_formatted_file_size_attribute()
    {
        $testCases = [
            ['size' => 512, 'expected' => '512 B'],
            ['size' => 1024, 'expected' => '1 KB'],
            ['size' => 1536, 'expected' => '1.5 KB'],
            ['size' => 1048576, 'expected' => '1 MB'],
            ['size' => 1073741824, 'expected' => '1 GB'],
        ];

        foreach ($testCases as $testCase) {
            $document = UploadedDocument::create([
                'user_id' => User::factory()->create()->id,
                'original_filename' => 'test.pdf',
                'stored_filename' => 'stored.pdf',
                'file_path' => 'documents/stored.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => $testCase['size'],
            ]);

            $this->assertEquals($testCase['expected'], $document->formatted_file_size);
        }
    }

    /** @test */
    public function it_uses_soft_deletes()
    {
        $document = UploadedDocument::create([
            'user_id' => User::factory()->create()->id,
            'original_filename' => 'test.pdf',
            'stored_filename' => 'stored.pdf',
            'file_path' => 'documents/stored.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
        ]);
        
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
        $document = UploadedDocument::create([
            'user_id' => User::factory()->create()->id,
            'original_filename' => 'test.pdf',
            'stored_filename' => 'stored.pdf',
            'file_path' => 'documents/stored.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
        ]);
        
        $documentId = $document->id;

        $document->delete();
        $this->assertSoftDeleted('uploaded_documents', ['id' => $documentId]);

        $document->restore();
        $this->assertDatabaseHas('uploaded_documents', ['id' => $documentId, 'deleted_at' => null]);
    }

    /** @test */
    public function it_handles_null_verified_at_date()
    {
        $document = UploadedDocument::create([
            'user_id' => User::factory()->create()->id,
            'original_filename' => 'test.pdf',
            'stored_filename' => 'stored.pdf',
            'file_path' => 'documents/stored.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'is_verified' => false,
            'verified_at' => null,
        ]);

        $this->assertNull($document->verified_at);
        $this->assertFalse($document->is_verified);
    }

    /** @test */
    public function it_handles_null_verified_by_user()
    {
        $document = UploadedDocument::create([
            'user_id' => User::factory()->create()->id,
            'original_filename' => 'test.pdf',
            'stored_filename' => 'stored.pdf',
            'file_path' => 'documents/stored.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'verified_by' => null,
        ]);

        $this->assertNull($document->verifiedBy);
    }

    /** @test */
    public function it_stores_document_metadata_correctly()
    {
        $user = User::factory()->create();
        
        $document = UploadedDocument::create([
            'user_id' => $user->id,
            'original_filename' => 'my document.pdf',
            'stored_filename' => 'stored_document_123.pdf',
            'file_path' => 'uploads/users/1/2024/01/stored_document_123.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 2048576, // ~2MB
            'file_hash' => 'sha256hash123',
            'document_type' => 'salary_document',
            'is_verified' => false,
            'notes' => 'Uploaded via web form',
        ]);

        $this->assertEquals('my document.pdf', $document->original_filename);
        $this->assertEquals('stored_document_123.pdf', $document->stored_filename);
        $this->assertEquals('uploads/users/1/2024/01/stored_document_123.pdf', $document->file_path);
        $this->assertEquals('application/pdf', $document->mime_type);
        $this->assertEquals(2048576, $document->file_size);
        $this->assertEquals('sha256hash123', $document->file_hash);
        $this->assertEquals('salary_document', $document->document_type);
        $this->assertFalse($document->is_verified);
        $this->assertEquals('Uploaded via web form', $document->notes);
    }

    /** @test */
    public function it_hides_sensitive_attributes_in_serialization()
    {
        $document = UploadedDocument::create([
            'user_id' => User::factory()->create()->id,
            'original_filename' => 'test.pdf',
            'stored_filename' => 'stored.pdf',
            'file_path' => 'secret/path/stored.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'file_hash' => 'secret_hash_123',
        ]);

        $serialized = $document->toArray();

        $this->assertArrayNotHasKey('file_path', $serialized);
        $this->assertArrayNotHasKey('file_hash', $serialized);
        $this->assertArrayHasKey('original_filename', $serialized);
        $this->assertArrayHasKey('mime_type', $serialized);
    }

    /** @test */
    public function it_handles_zero_file_size()
    {
        $document = UploadedDocument::create([
            'user_id' => User::factory()->create()->id,
            'original_filename' => 'empty.txt',
            'stored_filename' => 'empty.txt',
            'file_path' => 'documents/empty.txt',
            'mime_type' => 'text/plain',
            'file_size' => 0,
        ]);

        $this->assertEquals('0 B', $document->formatted_file_size);
    }

    /** @test */
    public function it_handles_large_file_sizes()
    {
        $document = UploadedDocument::create([
            'user_id' => User::factory()->create()->id,
            'original_filename' => 'large.zip',
            'stored_filename' => 'large.zip',
            'file_path' => 'documents/large.zip',
            'mime_type' => 'application/zip',
            'file_size' => 5368709120, // 5GB
        ]);

        $this->assertEquals('5 GB', $document->formatted_file_size);
    }

    /** @test */
    public function it_can_query_documents_with_complex_conditions()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $verifier = User::factory()->create();
        
        // Create various documents
        $verifiedSalaryDoc = UploadedDocument::create([
            'user_id' => $user1->id,
            'original_filename' => 'salary.pdf',
            'stored_filename' => 'salary.pdf',
            'file_path' => 'documents/salary.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'document_type' => 'salary_document',
            'is_verified' => true,
            'verified_by' => $verifier->id,
        ]);
        
        $unverifiedContractDoc = UploadedDocument::create([
            'user_id' => $user2->id,
            'original_filename' => 'contract.pdf',
            'stored_filename' => 'contract.pdf',
            'file_path' => 'documents/contract.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 2048,
            'document_type' => 'contract',
            'is_verified' => false,
        ]);

        // Query verified salary documents
        $results = UploadedDocument::verified()
            ->ofType('salary_document')
            ->get();
        
        $this->assertCount(1, $results);
        $this->assertEquals($verifiedSalaryDoc->id, $results->first()->id);
    }
}