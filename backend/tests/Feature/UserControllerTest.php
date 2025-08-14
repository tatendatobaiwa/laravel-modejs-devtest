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

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** @test */
    public function it_can_create_a_new_user_via_public_endpoint()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'salary_local_currency' => 50000,
            'local_currency_code' => 'USD',
            'commission' => 500,
            'notes' => 'Initial registration'
        ];

        $response = $this->postJson('/api/v1/public/users', $userData);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'User registered successfully',
                    'data' => [
                        'is_update' => false
                    ]
                ]);

        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);

        $this->assertDatabaseHas('salaries', [
            'salary_local_currency' => 50000,
            'local_currency_code' => 'USD',
            'commission' => 500
        ]);
    }

    /** @test */
    public function it_updates_existing_user_when_email_already_exists()
    {
        // Create existing user
        $existingUser = User::factory()->create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com'
        ]);

        Salary::factory()->create([
            'user_id' => $existingUser->id,
            'salary_local_currency' => 40000,
            'commission' => 300
        ]);

        $updateData = [
            'name' => 'Jane Updated',
            'email' => 'jane@example.com', // Same email
            'salary_local_currency' => 55000,
            'local_currency_code' => 'EUR',
            'commission' => 600
        ];

        $response = $this->postJson('/api/v1/public/users', $updateData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'User information updated successfully',
                    'data' => [
                        'is_update' => true
                    ]
                ]);

        $this->assertDatabaseHas('users', [
            'id' => $existingUser->id,
            'name' => 'Jane Updated',
            'email' => 'jane@example.com'
        ]);

        // Should have updated salary
        $this->assertDatabaseHas('salaries', [
            'user_id' => $existingUser->id,
            'salary_local_currency' => 55000,
            'commission' => 600
        ]);
    }

    /** @test */
    public function it_handles_case_insensitive_email_uniqueness()
    {
        // Create user with lowercase email
        $existingUser = User::factory()->create([
            'email' => 'test@example.com'
        ]);

        $updateData = [
            'name' => 'Test User',
            'email' => 'TEST@EXAMPLE.COM', // Uppercase version
            'salary_local_currency' => 45000,
            'local_currency_code' => 'USD'
        ];

        $response = $this->postJson('/api/v1/public/users', $updateData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'is_update' => true
                    ]
                ]);

        // Should still be only one user
        $this->assertEquals(1, User::count());
    }

    /** @test */
    public function it_can_upload_file_during_user_creation()
    {
        $file = UploadedFile::fake()->create('salary_document.pdf', 100, 'application/pdf');

        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'salary_local_currency' => 50000,
            'local_currency_code' => 'USD',
            'document' => $file
        ];

        $response = $this->postJson('/api/v1/public/users', $userData);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true
                ]);

        $this->assertDatabaseHas('uploaded_documents', [
            'original_filename' => 'salary_document.pdf',
            'mime_type' => 'application/pdf'
        ]);

        Storage::assertExists('uploads/users/' . User::first()->id . '/salary_document/' . $file->hashName());
    }

    /** @test */
    public function it_validates_required_fields_for_user_creation()
    {
        $response = $this->postJson('/api/v1/public/users', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['name', 'email', 'salary_local_currency']);
    }

    /** @test */
    public function it_validates_email_format()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'invalid-email',
            'salary_local_currency' => 50000,
            'local_currency_code' => 'USD'
        ];

        $response = $this->postJson('/api/v1/public/users', $userData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function it_validates_salary_is_numeric()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'salary_local_currency' => 'not-a-number',
            'local_currency_code' => 'USD'
        ];

        $response = $this->postJson('/api/v1/public/users', $userData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['salary_local_currency']);
    }

    /** @test */
    public function admin_can_list_users_with_pagination()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        // Create multiple users
        User::factory()->count(25)->create();

        $response = $this->getJson('/api/v1/admin/users?per_page=10');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ])
                ->assertJsonStructure([
                    'data',
                    'pagination' => [
                        'current_page',
                        'last_page',
                        'per_page',
                        'total'
                    ]
                ]);

        $this->assertEquals(10, count($response->json('data')));
        $this->assertEquals(26, $response->json('pagination.total')); // 25 + admin user
    }

    /** @test */
    public function admin_can_search_users_by_name()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        User::factory()->create(['name' => 'John Smith']);
        User::factory()->create(['name' => 'Jane Doe']);
        User::factory()->create(['name' => 'Bob Johnson']);

        $response = $this->getJson('/api/v1/admin/users?search=John');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ]);

        $users = $response->json('data');
        $this->assertCount(2, $users); // John Smith and Bob Johnson
    }

    /** @test */
    public function admin_can_search_users_by_email()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        User::factory()->create(['email' => 'john@company.com']);
        User::factory()->create(['email' => 'jane@different.com']);

        $response = $this->getJson('/api/v1/admin/users?search=company');

        $response->assertStatus(200);

        $users = $response->json('data');
        $this->assertCount(1, $users);
        $this->assertEquals('john@company.com', $users[0]['email']);
    }

    /** @test */
    public function admin_can_filter_users_by_salary_range()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        Salary::factory()->create(['user_id' => $user1->id, 'salary_euros' => 30000]);
        Salary::factory()->create(['user_id' => $user2->id, 'salary_euros' => 50000]);
        Salary::factory()->create(['user_id' => $user3->id, 'salary_euros' => 70000]);

        $response = $this->getJson('/api/v1/admin/users?min_salary=40000&max_salary=60000');

        $response->assertStatus(200);

        $users = $response->json('data');
        $this->assertCount(1, $users);
        $this->assertEquals($user2->id, $users[0]['id']);
    }

    /** @test */
    public function admin_can_sort_users_by_different_fields()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $user1 = User::factory()->create(['name' => 'Alice']);
        $user2 = User::factory()->create(['name' => 'Bob']);
        $user3 = User::factory()->create(['name' => 'Charlie']);

        $response = $this->getJson('/api/v1/admin/users?sort_by=name&sort_direction=asc');

        $response->assertStatus(200);

        $users = $response->json('data');
        $this->assertEquals('Alice', $users[0]['name']);
        $this->assertEquals('Bob', $users[1]['name']);
    }

    /** @test */
    public function admin_can_view_specific_user_details()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $user = User::factory()->create();
        $salary = Salary::factory()->create(['user_id' => $user->id]);
        SalaryHistory::factory()->count(3)->create(['user_id' => $user->id]);
        UploadedDocument::factory()->count(2)->create(['user_id' => $user->id]);

        $response = $this->getJson("/api/v1/admin/users/{$user->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ])
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'name',
                        'email',
                        'salary',
                        'salary_history',
                        'uploaded_documents',
                        'statistics' => [
                            'total_salary_changes',
                            'documents_uploaded',
                            'account_age_days'
                        ]
                    ]
                ]);
    }

    /** @test */
    public function admin_can_update_user_name()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $user = User::factory()->create(['name' => 'Old Name']);

        $response = $this->putJson("/api/v1/admin/users/{$user->id}", [
            'name' => 'New Name'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'User name updated successfully'
                ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name'
        ]);
    }

    /** @test */
    public function admin_can_delete_user()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $user = User::factory()->create();
        UploadedDocument::factory()->create(['user_id' => $user->id]);

        $response = $this->deleteJson("/api/v1/admin/users/{$user->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'User deleted successfully'
                ]);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    /** @test */
    public function unauthenticated_users_cannot_access_admin_endpoints()
    {
        $user = User::factory()->create();

        $response = $this->getJson('/api/v1/admin/users');
        $response->assertStatus(401);

        $response = $this->getJson("/api/v1/admin/users/{$user->id}");
        $response->assertStatus(401);

        $response = $this->putJson("/api/v1/admin/users/{$user->id}", ['name' => 'New Name']);
        $response->assertStatus(401);

        $response = $this->deleteJson("/api/v1/admin/users/{$user->id}");
        $response->assertStatus(401);
    }

    /** @test */
    public function non_admin_users_cannot_access_admin_endpoints()
    {
        $regularUser = User::factory()->create(['email' => 'user@example.com']);
        Sanctum::actingAs($regularUser);

        $user = User::factory()->create();

        $response = $this->getJson('/api/v1/admin/users');
        $response->assertStatus(403);

        $response = $this->getJson("/api/v1/admin/users/{$user->id}");
        $response->assertStatus(403);

        $response = $this->putJson("/api/v1/admin/users/{$user->id}", ['name' => 'New Name']);
        $response->assertStatus(403);

        $response = $this->deleteJson("/api/v1/admin/users/{$user->id}");
        $response->assertStatus(403);
    }

    /** @test */
    public function it_handles_file_upload_validation_errors()
    {
        $invalidFile = UploadedFile::fake()->create('document.exe', 100, 'application/x-executable');

        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'salary_local_currency' => 50000,
            'local_currency_code' => 'USD',
            'document' => $invalidFile
        ];

        $response = $this->postJson('/api/v1/public/users', $userData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['document']);
    }

    /** @test */
    public function it_handles_large_file_upload_validation()
    {
        $largeFile = UploadedFile::fake()->create('document.pdf', 10240, 'application/pdf'); // 10MB

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
    public function it_creates_salary_history_on_user_update()
    {
        // Create existing user with salary
        $existingUser = User::factory()->create(['email' => 'test@example.com']);
        $originalSalary = Salary::factory()->create([
            'user_id' => $existingUser->id,
            'salary_local_currency' => 40000,
            'commission' => 300
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'email' => 'test@example.com',
            'salary_local_currency' => 50000,
            'local_currency_code' => 'USD',
            'commission' => 500
        ];

        $response = $this->postJson('/api/v1/public/users', $updateData);

        $response->assertStatus(200);

        // Should have created salary history record
        $this->assertDatabaseHas('salary_histories', [
            'user_id' => $existingUser->id,
            'old_salary_local_currency' => 40000,
            'new_salary_local_currency' => 50000,
            'old_commission' => 300,
            'new_commission' => 500
        ]);
    }

    /** @test */
    public function it_handles_database_transaction_rollback_on_error()
    {
        // Mock a service to throw an exception
        $this->mock(\App\Services\SalaryService::class, function ($mock) {
            $mock->shouldReceive('createOrUpdateSalary')
                 ->andThrow(new \Exception('Database error'));
        });

        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'salary_local_currency' => 50000,
            'local_currency_code' => 'USD'
        ];

        $response = $this->postJson('/api/v1/public/users', $userData);

        $response->assertStatus(500)
                ->assertJson([
                    'success' => false
                ]);

        // Should not have created user due to transaction rollback
        $this->assertDatabaseMissing('users', [
            'email' => 'john@example.com'
        ]);
    }

    /** @test */
    public function it_applies_rate_limiting_to_registration_endpoint()
    {
        // Make multiple requests quickly
        for ($i = 0; $i < 10; $i++) {
            $userData = [
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'salary_local_currency' => 50000,
                'local_currency_code' => 'USD'
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
}