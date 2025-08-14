<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Salary;
use App\Models\SalaryHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class SalaryControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_can_list_all_salaries_with_pagination()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        // Create users with salaries
        $users = User::factory()->count(15)->create();
        foreach ($users as $user) {
            Salary::factory()->create(['user_id' => $user->id]);
        }

        $response = $this->getJson('/api/v1/admin/salaries?per_page=10');

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
        $this->assertEquals(15, $response->json('pagination.total'));
    }

    /** @test */
    public function admin_can_create_new_salary_record()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $user = User::factory()->create();

        $salaryData = [
            'user_id' => $user->id,
            'salary_local_currency' => 60000,
            'local_currency_code' => 'USD',
            'commission' => 750,
            'notes' => 'Initial salary setup'
        ];

        $response = $this->postJson('/api/v1/admin/salaries', $salaryData);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'Salary created successfully'
                ]);

        $this->assertDatabaseHas('salaries', [
            'user_id' => $user->id,
            'salary_local_currency' => 60000,
            'commission' => 750
        ]);
    }

    /** @test */
    public function admin_can_view_specific_salary_details()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $user = User::factory()->create();
        $salary = Salary::factory()->create(['user_id' => $user->id]);

        $response = $this->getJson("/api/v1/admin/salaries/{$salary->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ])
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'user_id',
                        'salary_local_currency',
                        'salary_euros',
                        'commission',
                        'displayed_salary',
                        'user'
                    ]
                ]);
    }

    /** @test */
    public function admin_can_update_salary_record()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $user = User::factory()->create();
        $salary = Salary::factory()->create([
            'user_id' => $user->id,
            'salary_local_currency' => 50000,
            'commission' => 500
        ]);

        $updateData = [
            'salary_local_currency' => 55000,
            'local_currency_code' => 'USD',
            'commission' => 600,
            'notes' => 'Salary increase'
        ];

        $response = $this->putJson("/api/v1/admin/salaries/{$salary->id}", $updateData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Salary updated successfully'
                ]);

        $this->assertDatabaseHas('salaries', [
            'id' => $salary->id,
            'salary_local_currency' => 55000,
            'commission' => 600
        ]);
    }

    /** @test */
    public function salary_update_creates_history_record()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $user = User::factory()->create();
        $salary = Salary::factory()->create([
            'user_id' => $user->id,
            'salary_local_currency' => 50000,
            'commission' => 500
        ]);

        $updateData = [
            'salary_local_currency' => 55000,
            'commission' => 600,
            'change_reason' => 'Performance review increase'
        ];

        $response = $this->putJson("/api/v1/admin/salaries/{$salary->id}", $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('salary_histories', [
            'user_id' => $user->id,
            'old_salary_local_currency' => 50000,
            'new_salary_local_currency' => 55000,
            'old_commission' => 500,
            'new_commission' => 600,
            'change_reason' => 'Performance review increase'
        ]);
    }

    /** @test */
    public function admin_can_delete_salary_record()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $user = User::factory()->create();
        $salary = Salary::factory()->create(['user_id' => $user->id]);

        $response = $this->deleteJson("/api/v1/admin/salaries/{$salary->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Salary deleted successfully'
                ]);

        $this->assertSoftDeleted('salaries', ['id' => $salary->id]);
    }

    /** @test */
    public function admin_can_view_salary_history()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $user = User::factory()->create();
        $salary = Salary::factory()->create(['user_id' => $user->id]);
        
        // Create multiple history records
        SalaryHistory::factory()->count(5)->create([
            'user_id' => $user->id,
            'salary_id' => $salary->id
        ]);

        $response = $this->getJson("/api/v1/admin/salaries/{$salary->id}/history");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ])
                ->assertJsonStructure([
                    'data',
                    'pagination'
                ]);

        $this->assertEquals(5, count($response->json('data')));
    }

    /** @test */
    public function it_validates_required_fields_for_salary_creation()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/admin/salaries', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors([
                    'user_id',
                    'salary_local_currency',
                    'local_currency_code'
                ]);
    }

    /** @test */
    public function it_validates_salary_is_positive_number()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $user = User::factory()->create();

        $salaryData = [
            'user_id' => $user->id,
            'salary_local_currency' => -1000,
            'local_currency_code' => 'USD'
        ];

        $response = $this->postJson('/api/v1/admin/salaries', $salaryData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['salary_local_currency']);
    }

    /** @test */
    public function it_validates_commission_is_positive_number()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $user = User::factory()->create();

        $salaryData = [
            'user_id' => $user->id,
            'salary_local_currency' => 50000,
            'local_currency_code' => 'USD',
            'commission' => -100
        ];

        $response = $this->postJson('/api/v1/admin/salaries', $salaryData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['commission']);
    }

    /** @test */
    public function it_validates_currency_code_format()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $user = User::factory()->create();

        $salaryData = [
            'user_id' => $user->id,
            'salary_local_currency' => 50000,
            'local_currency_code' => 'INVALID'
        ];

        $response = $this->postJson('/api/v1/admin/salaries', $salaryData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['local_currency_code']);
    }

    /** @test */
    public function it_prevents_duplicate_salary_records_for_same_user()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $user = User::factory()->create();
        Salary::factory()->create(['user_id' => $user->id]);

        $salaryData = [
            'user_id' => $user->id,
            'salary_local_currency' => 60000,
            'local_currency_code' => 'USD'
        ];

        $response = $this->postJson('/api/v1/admin/salaries', $salaryData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['user_id']);
    }

    /** @test */
    public function it_calculates_displayed_salary_correctly()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $user = User::factory()->create();

        $salaryData = [
            'user_id' => $user->id,
            'salary_local_currency' => 50000,
            'local_currency_code' => 'USD',
            'commission' => 750
        ];

        $response = $this->postJson('/api/v1/admin/salaries', $salaryData);

        $response->assertStatus(201);

        $salary = Salary::where('user_id', $user->id)->first();
        $expectedDisplayedSalary = $salary->salary_euros + $salary->commission;

        $this->assertEquals($expectedDisplayedSalary, $salary->displayed_salary);
    }

    /** @test */
    public function it_uses_default_commission_when_not_provided()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $user = User::factory()->create();

        $salaryData = [
            'user_id' => $user->id,
            'salary_local_currency' => 50000,
            'local_currency_code' => 'USD'
            // No commission provided
        ];

        $response = $this->postJson('/api/v1/admin/salaries', $salaryData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('salaries', [
            'user_id' => $user->id,
            'commission' => 500 // Default commission
        ]);
    }

    /** @test */
    public function authenticated_user_can_view_own_salary()
    {
        $user = User::factory()->create();
        $salary = Salary::factory()->create(['user_id' => $user->id]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/salaries/{$salary->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ])
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'salary_local_currency',
                        'salary_euros',
                        'commission',
                        'displayed_salary'
                    ]
                ]);
    }

    /** @test */
    public function user_cannot_view_other_users_salary()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $salary = Salary::factory()->create(['user_id' => $user2->id]);
        
        Sanctum::actingAs($user1);

        $response = $this->getJson("/api/v1/salaries/{$salary->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function unauthenticated_users_cannot_access_salary_endpoints()
    {
        $user = User::factory()->create();
        $salary = Salary::factory()->create(['user_id' => $user->id]);

        $response = $this->getJson('/api/v1/admin/salaries');
        $response->assertStatus(401);

        $response = $this->getJson("/api/v1/admin/salaries/{$salary->id}");
        $response->assertStatus(401);

        $response = $this->postJson('/api/v1/admin/salaries', []);
        $response->assertStatus(401);

        $response = $this->putJson("/api/v1/admin/salaries/{$salary->id}", []);
        $response->assertStatus(401);

        $response = $this->deleteJson("/api/v1/admin/salaries/{$salary->id}");
        $response->assertStatus(401);
    }

    /** @test */
    public function non_admin_users_cannot_access_admin_salary_endpoints()
    {
        $regularUser = User::factory()->create(['email' => 'user@example.com']);
        $salary = Salary::factory()->create(['user_id' => $regularUser->id]);
        
        Sanctum::actingAs($regularUser);

        $response = $this->getJson('/api/v1/admin/salaries');
        $response->assertStatus(403);

        $response = $this->postJson('/api/v1/admin/salaries', []);
        $response->assertStatus(403);

        $response = $this->putJson("/api/v1/admin/salaries/{$salary->id}", []);
        $response->assertStatus(403);

        $response = $this->deleteJson("/api/v1/admin/salaries/{$salary->id}");
        $response->assertStatus(403);
    }

    /** @test */
    public function it_handles_currency_conversion_correctly()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $user = User::factory()->create();

        // Test with different currencies
        $currencies = [
            ['code' => 'USD', 'amount' => 50000],
            ['code' => 'GBP', 'amount' => 40000],
            ['code' => 'EUR', 'amount' => 45000]
        ];

        foreach ($currencies as $currency) {
            $salaryData = [
                'user_id' => $user->id,
                'salary_local_currency' => $currency['amount'],
                'local_currency_code' => $currency['code'],
                'commission' => 500
            ];

            $response = $this->postJson('/api/v1/admin/salaries', $salaryData);

            if ($currency['code'] === 'EUR') {
                $response->assertStatus(422); // Duplicate user
            } else {
                $response->assertStatus(201);
                
                $salary = Salary::where('user_id', $user->id)->first();
                $this->assertNotNull($salary->salary_euros);
                $this->assertTrue($salary->salary_euros > 0);
            }
            
            // Clean up for next iteration
            if ($currency['code'] !== 'EUR') {
                Salary::where('user_id', $user->id)->delete();
            }
        }
    }

    /** @test */
    public function it_handles_bulk_salary_updates()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $users = User::factory()->count(3)->create();
        $salaries = [];
        
        foreach ($users as $user) {
            $salaries[] = Salary::factory()->create(['user_id' => $user->id]);
        }

        $bulkUpdateData = [
            'updates' => [
                [
                    'id' => $salaries[0]->id,
                    'commission' => 600,
                    'change_reason' => 'Bulk update 1'
                ],
                [
                    'id' => $salaries[1]->id,
                    'commission' => 700,
                    'change_reason' => 'Bulk update 2'
                ]
            ]
        ];

        $response = $this->putJson('/api/v1/admin/salaries/bulk-update', $bulkUpdateData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Bulk salary update completed successfully'
                ]);

        $this->assertDatabaseHas('salaries', [
            'id' => $salaries[0]->id,
            'commission' => 600
        ]);

        $this->assertDatabaseHas('salaries', [
            'id' => $salaries[1]->id,
            'commission' => 700
        ]);
    }

    /** @test */
    public function it_handles_database_errors_gracefully()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        // Try to create salary for non-existent user
        $salaryData = [
            'user_id' => 99999,
            'salary_local_currency' => 50000,
            'local_currency_code' => 'USD'
        ];

        $response = $this->postJson('/api/v1/admin/salaries', $salaryData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['user_id']);
    }
}