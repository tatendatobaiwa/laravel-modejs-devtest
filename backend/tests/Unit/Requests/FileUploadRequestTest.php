<?php

                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            ?>';
        $file = UploadedFile::fake()->createWithContent('malicious.txt', $suspiciousContent);
        
        $request = new FileUploadRequest();
        $request->files->set('file', $file);

        $scanResult = $request->performVirusScan();

        $this->assertFalse($scanResult['is_clean']);
        $this->assertNotEmpty($scanResult['threats_found']);
    }

    /** @test */
    public function it_gets_upload_statistics()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create some existing uploads
        UploadedDocument::factory()->count(3)->forUser($user)->create([
            'created_at' => now(),
            'file_size' => 1024 * 1024, // 1MB each
        ]);

        $request = new FileUploadRequest();
        $stats = $request->getUploadStatistics();

        $this->assertEquals(3, $stats['daily_uploads']);
        $this->assertEquals(10, $stats['daily_limit']);
        $this->assertEquals(3 * 1024 * 1024, $stats['total_storage_used']);
        $this->assertEquals(50 * 1024 * 1024, $stats['storage_limit']);
        $this->assertEquals(7, $stats['remaining_uploads']); // 10 - 3
    }

    /** @test */
    public function it_validates_file_size_limit()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $largeFile = UploadedFile::fake()->create('large.pdf', 6000, 'application/pdf'); // 6MB > 5MB limit
        
        $data = [
            'file' => $largeFile,
            'file_type' => 'salary_document',
        ];

        $request = new FileUploadRequest();
        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('file', $validator->errors()->toArray());
    }

    /** @test */
    public function it_validates_allowed_file_extensions()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $invalidFile = UploadedFile::fake()->create('test.exe', 1024, 'application/pdf');
        
        $data = [
            'file' => $invalidFile,
            'file_type' => 'salary_document',
        ];

        $request = new FileUploadRequest();
        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('file', $validator->errors()->toArray());
    }

    /** @test */
    public function it_passes_validation_with_valid_data()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->create('test.pdf', 1024, 'application/pdf');
        
        $data = [
            'file' => $file,
            'file_type' => 'salary_document',
            'description' => 'Valid description',
            'is_sensitive' => true,
        ];

        $request = new FileUploadRequest();
        $validator = Validator::make($data, $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_handles_optional_fields()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->create('test.pdf', 1024, 'application/pdf');
        
        $data = [
            'file' => $file,
            'file_type' => 'salary_document',
            // description and is_sensitive are optional
        ];

        $request = new FileUploadRequest();
        $validator = Validator::make($data, $request->rules());

        $this->assertFalse($validator->fails());
    }

    /** @test */
    public function it_sanitizes_description_removing_dangerous_characters()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $request = new FileUploadRequest();
        $request->merge([
            'description' => 'Test <script>alert("xss")</script> description',
        ]);

        $request->prepareForValidation();

        $this->assertEquals('Test  description', $request->input('description'));
    }

    /** @test */
    public function it_handles_null_description()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $request = new FileUploadRequest();
        $request->merge(['description' => null]);

        $request->prepareForValidation();

        $this->assertNull($request->input('description'));
    }

    /** @test */
    public function it_validates_daily_upload_quota()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create maximum daily uploads
        UploadedDocument::factory()->count(10)->forUser($user)->create([
            'created_at' => now(),
        ]);

        $file = UploadedFile::fake()->create('test.pdf', 1024, 'application/pdf');
        
        $data = [
            'file' => $file,
            'file_type' => 'salary_document',
        ];

        $request = new FileUploadRequest();
        $validator = Validator::make($data, $request->rules());

        // This should fail due to quota validation in the custom rule
        $this->assertTrue($validator->fails());
    }

    /** @test */
    public function it_validates_storage_quota()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create files that use up the storage quota
        UploadedDocument::factory()->forUser($user)->create([
            'file_size' => 49 * 1024 * 1024, // 49MB
        ]);

        $file = UploadedFile::fake()->create('test.pdf', 2 * 1024, 'application/pdf'); // 2MB
        
        $data = [
            'file' => $file,
            'file_type' => 'salary_document',
        ];

        $request = new FileUploadRequest();
        $validator = Validator::make($data, $request->rules());

        // This should fail due to storage quota validation
        $this->assertTrue($validator->fails());
    }
}