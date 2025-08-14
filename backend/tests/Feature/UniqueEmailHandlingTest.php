<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Salary;
use App\Models\SalaryHistory;
use App\Models\UploadedDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

class UniqueEmailHandlingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** @test */
    public function it_handles_case_insensitive_email_uniqueness_on_create()
    {
        // Create user with lowercase email
        $userData1 = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'salary_local_currency' => 50000,
            'local_currency_code' => 'USD'
        ];

        $response1 = $this->postJson('/api/v1/public/users', $userData1);
        $response1->assertStatus(201);

        // Try to create another user with uppercase email
        $userData2 = [
            'name' => 'John Updated',
            'email' => 'JOHN@EXAMPLE.COM',
            'salary_local_currency' => 55000,
            'local_currency_code' => 'EUR'
        ];

        $response2 = $this->postJson('/api/v1/public/users', $userData2);
        
        $response2->assertStatus(200) // Should update, not create
                  ->assertJson([
                      'success' => true,
                      'data' => [
                          'is_update' => true
                      ]
                  ]);

        // Should still be only one user
        $this->assertEquals(1, User::count());
        
        // User should be updated
        $user = User::first();
        $this->assertEquals('John Updated', $user->name);
        $this->assertEquals('john@example.com', $user->email); // Original case preserved
    }

    /** @test */
    public function it_handles_mixed_case_email_variations()
    {
        $emailVariations = [
            'test@example.com',
            'TEST@EXAMPLE.COM',
            'Test@Example.Com',
            'tEsT@eXaMpLe.CoM'
        ];

        foreach ($emailVariations as $index => $email) {
            $userData = [
                'name' => "User {$index}",
                'email' => $email,
                'salary_local_currency' => 50000 + ($index * 1000),
                'local_currency_code' => 'USD'
            ];

            $response = $this->postJson('/api/v1/public/users', $userData);

            if ($index === 0) {
                $response->assertStatus(201); // First one creates
            } else {
                $response->assertStatus(200) // Others update
                        ->assertJson([
                            'data' => [
                                'is_update' => true
                            ]
                        ]);
            }
        }

        // Should still be only one user
        $this->assertEquals(1, User::count());
        
        // Final user should have the last update
        $user = User::first();
        $this->assertEquals('User 3', $user->name);
        $this->assertEquals(53000, $user->salary->salary_local_currency);
    }

    /** @test */
    public function it_handles_email_with_plus_addressing()
    {
        // Create user with plus addressing
        $userData1 = [
            'name' => 'John Doe',
            'email' => 'john+work@example.com',
            'salary_local_currency' => 50000,
            'local_currency_code' => 'USD'
        ];

        $response1 = $this->postJson('/api/v1/public/users', $userData1);
        $response1->assertStatus(201);

        // Try with different plus addressing
        $userData2 = [
            'name' => 'John Updated',
            'email' => 'john+personal@example.com',
            'salary_local_currency' => 55000,
            'local_currency_code' => 'EUR'
        ];

        $response2 = $this->postJson('/api/v1/public/users', $userData2);
        $response2->assertStatus(201); // Should create new user (different emails)

        // Should have two users
        $this->assertEquals(2, User::count());
    }

    /** @test */
    public function it_handles_email_with_dots_in_gmail_style()
    {
        // Gmail treats john.doe@gmail.com and johndoe@gmail.com as the same
        // But our system treats them as different (which is technically correct)
        
        $userData1 = [
            'name' => 'John Doe',
            'email' => 'john.doe@gmail.com',
            'salary_local_currency' => 50000,
            'local_currency_code' => 'USD'
        ];

        $response1 = $this->postJson('/api/v1/public/users', $userData1);
        $response1->assertStatus(201);

        $userData2 = [
            'name' => 'John Updated',
            'email' => 'johndoe@gmail.com',
            'salary_local_currency' => 55000,
            'local_currency_code' => 'EUR'
        ];

        $response2 = $this->postJson('/api/v1/public/users', $userData2);
        $response2->assertStatus(201); // Should create new user (different emails)

        // Should have two users
        $this->assertEquals(2, User::count());
    }

    /** @test */
    public function it_preserves_original_email_case_on_updates()
    {
        // Create user with specific case
        $userData1 = [
            'name' => 'John Doe',
            'email' => 'John.Doe@Company.Com',
            'salary_local_currency' => 50000,
            'local_currency_code' => 'USD'
        ];

        $response1 = $this->postJson('/api/v1/public/users', $userData1);
        $response1->assertStatus(201);

        // Update with different case
        $userData2 = [
            'name' => 'John Updated',
            'email' => 'john.doe@company.com',
            'salary_local_currency' => 55000,
            'local_currency_code' => 'EUR'
        ];

        $response2 = $this->postJson('/api/v1/public/users', $userData2);
        $response2->assertStatus(200);

        // Original email case should be preserved
        $user = User::first();
        $this->assertEquals('John.Doe@Company.Com', $user->email);
        $this->assertEquals('John Updated', $user->name);
    }

    /** @test */
    public function it_handles_unicode_characters_in_emails()
    {
        $unicodeEmails = [
            'test@münchen.de',
            'тест@example.com',
            'user@日本.jp',
            'test@xn--fsq.xn--0zwm56d' // Punycode version
        ];

        foreach ($unicodeEmails as $index => $email) {
            $userData = [
                'name' => "User {$index}",
                'email' => $email,
                'salary_local_currency' => 50000,
                'local_currency_code' => 'USD'
            ];

            $response = $this->postJson('/api/v1/public/users', $userData);
            
            // Should handle unicode emails properly
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $response->assertStatus(201);
            } else {
                $response->assertStatus(422)
                        ->assertJsonValidationErrors(['email']);
            }
        }
    }

    /** @test */
    public function it_handles_very_long_email_addresses()
    {
        // Create a very long but valid email
        $longLocalPart = str_repeat('a', 64); // Max local part length
        $longDomain = str_repeat('b', 60) . '.com'; // Long domain
        $longEmail = $longLocalPart . '@' . $longDomain;

        $userData = [
            'name' => 'Long Email User',
            'email' => $longEmail,
            'salary_local_currency' => 50000,
            'local_currency_code' => 'USD'
        ];

        $response = $this->postJson('/api/v1/public/users', $userData);
        
        if (strlen($longEmail) <= 254) { // RFC 5321 limit
            $response->assertStatus(201);
        } else {
            $response->assertStatus(422)
                    ->assertJsonValidationErrors(['email']);
        }
    }

    /** @test */
    public function it_handles_email_updates_with_salary_history()
    {
        // Create user
        $userData1 = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'salary_local_currency' => 50000,
            'local_currency_code' => 'USD',
            'commission' => 500
        ];

        $response1 = $this->postJson('/api/v1/public/users', $userData1);
        $response1->assertStatus(201);

        $user = User::where('email', 'john@example.com')->first();
        $originalSalary = $user->salary;

        // Update with same email (case insensitive) but different salary
        $userData2 = [
            'name' => 'John Updated',
            'email' => 'JOHN@EXAMPLE.COM',
            'salary_local_currency' => 60000,
            'local_currency_code' => 'EUR',
            'commission' => 750
        ];

        $response2 = $this->postJson('/api/v1/public/users', $userData2);
        $response2->assertStatus(200);

        // Should have created salary history
        $this->assertDatabaseHas('salary_histories', [
            'user_id' => $user->id,
            'old_salary_local_currency' => 50000,
            'new_salary_local_currency' => 60000,
            'old_commission' => 500,
            'new_commission' => 750
        ]);
    }

    /** @test */
    public function it_handles_concurrent_email_updates()
    {
        // Create initial user
        $user = User::factory()->create(['email' => 'test@example.com']);
        Salary::factory()->create(['user_id' => $user->id]);

        // Simulate concurrent updates with same email
        $userData1 = [
            'name' => 'Update 1',
            'email' => 'test@example.com',
            'salary_local_currency' => 55000,
            'local_currency_code' => 'USD'
        ];

        $userData2 = [
            'name' => 'Update 2',
            'email' => 'TEST@EXAMPLE.COM',
            'salary_local_currency' => 60000,
            'local_currency_code' => 'EUR'
        ];

        // Make concurrent requests
        $response1 = $this->postJson('/api/v1/public/users', $userData1);
        $response2 = $this->postJson('/api/v1/public/users', $userData2);

        // Both should succeed as updates
        $response1->assertStatus(200);
        $response2->assertStatus(200);

        // Should still be only one user
        $this->assertEquals(1, User::count());

        // One of the updates should have won (last one typically)
        $updatedUser = User::first();
        $this->assertTrue(in_array($updatedUser->name, ['Update 1', 'Update 2']));
    }

    /** @test */
    public function it_handles_email_updates_with_file_uploads()
    {
        $file1 = UploadedFile::fake()->create('document1.pdf', 100, 'application/pdf');
        $file2 = UploadedFile::fake()->create('document2.pdf', 100, 'application/pdf');

        // Create user with file
        $userData1 = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'salary_local_currency' => 50000,
            'local_currency_code' => 'USD',
            'document' => $file1
        ];

        $response1 = $this->postJson('/api/v1/public/users', $userData1);
        $response1->assertStatus(201);

        $user = User::where('email', 'john@example.com')->first();
        $this->assertEquals(1, $user->uploadedDocuments()->count());

        // Update with same email and new file
        $userData2 = [
            'name' => 'John Updated',
            'email' => 'JOHN@EXAMPLE.COM',
            'salary_local_currency' => 55000,
            'local_currency_code' => 'EUR',
            'document' => $file2
        ];

        $response2 = $this->postJson('/api/v1/public/users', $userData2);
        $response2->assertStatus(200);

        // Should have both files
        $user->refresh();
        $this->assertEquals(2, $user->uploadedDocuments()->count());

        // Both files should exist in storage
        $documents = $user->uploadedDocuments;
        foreach ($documents as $document) {
            Storage::assertExists($document->stored_path);
        }
    }

    /** @test */
    public function it_handles_email_normalization_edge_cases()
    {
        $edgeCaseEmails = [
            ['input' => '  john@example.com  ', 'normalized' => 'john@example.com'],
            ['input' => 'John@Example.Com', 'normalized' => 'john@example.com'],
            ['input' => 'JOHN@EXAMPLE.COM', 'normalized' => 'john@example.com'],
        ];

        foreach ($edgeCaseEmails as $index => $emailCase) {
            $userData = [
                'name' => "User {$index}",
                'email' => $emailCase['input'],
                'salary_local_currency' => 50000,
                'local_currency_code' => 'USD'
            ];

            $response = $this->postJson('/api/v1/public/users', $userData);

            if ($index === 0) {
                $response->assertStatus(201);
                
                // Verify email was normalized (trimmed)
                $user = User::first();
                $this->assertEquals(trim($emailCase['input']), $user->email);
            } else {
                $response->assertStatus(200); // Should update existing
            }
        }

        // Should still be only one user
        $this->assertEquals(1, User::count());
    }

    /** @test */
    public function it_handles_database_constraints_on_email_uniqueness()
    {
        // Create user directly in database to test constraint
        $user1 = User::factory()->create(['email' => 'test@example.com']);

        // Try to create another user with same email via API
        $userData = [
            'name' => 'Another User',
            'email' => 'test@example.com',
            'salary_local_currency' => 50000,
            'local_currency_code' => 'USD'
        ];

        $response = $this->postJson('/api/v1/public/users', $userData);
        
        // Should update existing user, not create new one
        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        'is_update' => true
                    ]
                ]);

        $this->assertEquals(1, User::count());
    }

    /** @test */
    public function it_handles_admin_email_updates_properly()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        // Create a regular user
        $user = User::factory()->create(['email' => 'user@example.com']);

        // Admin tries to update user's name (not email)
        $response = $this->putJson("/api/v1/admin/users/{$user->id}", [
            'name' => 'Updated Name'
        ]);

        $response->assertStatus(200);

        // Email should remain unchanged
        $user->refresh();
        $this->assertEquals('user@example.com', $user->email);
        $this->assertEquals('Updated Name', $user->name);
    }

    /** @test */
    public function it_prevents_email_spoofing_attempts()
    {
        $spoofingAttempts = [
            'admin@example.com\x00@malicious.com',
            'admin@example.com\r\n@malicious.com',
            'admin@example.com\n@malicious.com',
            "admin@example.com\t@malicious.com",
        ];

        foreach ($spoofingAttempts as $maliciousEmail) {
            $userData = [
                'name' => 'Malicious User',
                'email' => $maliciousEmail,
                'salary_local_currency' => 50000,
                'local_currency_code' => 'USD'
            ];

            $response = $this->postJson('/api/v1/public/users', $userData);
            
            // Should reject malicious emails
            $response->assertStatus(422)
                    ->assertJsonValidationErrors(['email']);
        }
    }

    /** @test */
    public function it_handles_validation_errors_during_email_updates()
    {
        // Create existing user
        $existingUser = User::factory()->create(['email' => 'existing@example.com']);
        Salary::factory()->create(['user_id' => $existingUser->id]);

        // Try to update with invalid data
        $userData = [
            'name' => 'Updated Name',
            'email' => 'existing@example.com',
            'salary_local_currency' => -1000, // Invalid negative salary
            'local_currency_code' => 'USD'
        ];

        $response = $this->postJson('/api/v1/public/users', $userData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['salary_local_currency']);

        // Original user should remain unchanged
        $existingUser->refresh();
        $this->assertNotEquals('Updated Name', $existingUser->name);
    }
}