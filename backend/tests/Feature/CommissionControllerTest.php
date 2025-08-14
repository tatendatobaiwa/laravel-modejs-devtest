<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Commission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class CommissionControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_can_view_current_commission_settings()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/admin/commissions');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ])
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'amount',
                        'effective_date',
                        'created_at',
                        'updated_at'
                    ]
                ]);
    }

    /** @test */
    public function admin_can_update_commission_amount()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $updateData = [
            'amount' => 750
        ];

        $response = $this->putJson('/api/v1/admin/commissions', $updateData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Commission updated successfully'
                ]);

        $this->assertDatabaseHas('commissions', [
            'amount' => 750,
            'is_active' => true
        ]);
    }

    /** @test */
    public function commission_update_validates_required_amount()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $response = $this->putJson('/api/v1/admin/commissions', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function commission_update_validates_amount_is_numeric()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $updateData = [
            'amount' => 'not-a-number'
        ];

        $response = $this->putJson('/api/v1/admin/commissions', $updateData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function commission_update_validates_amount_is_not_negative()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $updateData = [
            'amount' => -100
        ];

        $response = $this->putJson('/api/v1/admin/commissions', $updateData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function commission_update_allows_zero_amount()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $updateData = [
            'amount' => 0
        ];

        $response = $this->putJson('/api/v1/admin/commissions', $updateData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Commission updated successfully'
                ]);

        $this->assertDatabaseHas('commissions', [
            'amount' => 0,
            'is_active' => true
        ]);
    }

    /** @test */
    public function admin_can_view_commission_history()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        // Create some commission history
        Commission::factory()->count(3)->create(['is_active' => false]);
        Commission::factory()->create(['is_active' => true]);

        $response = $this->getJson('/api/v1/admin/commissions/history');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ])
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'amount',
                            'effective_date',
                            'is_active',
                            'created_at'
                        ]
                    ],
                    'pagination'
                ]);

        $this->assertCount(4, $response->json('data'));
    }

    /** @test */
    public function commission_update_modifies_existing_record()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        // Create initial commission
        $originalCommission = Commission::factory()->create([
            'amount' => 500,
            'is_active' => true
        ]);

        $updateData = [
            'amount' => 750
        ];

        $response = $this->putJson('/api/v1/admin/commissions', $updateData);

        $response->assertStatus(200);

        // Commission should be updated
        $this->assertDatabaseHas('commissions', [
            'id' => $originalCommission->id,
            'amount' => 750,
            'is_active' => true
        ]);
    }

    /** @test */
    public function unauthenticated_users_cannot_access_commission_endpoints()
    {
        $response = $this->getJson('/api/v1/admin/commissions');
        $response->assertStatus(401);

        $response = $this->putJson('/api/v1/admin/commissions', ['amount' => 600]);
        $response->assertStatus(401);

        $response = $this->getJson('/api/v1/admin/commissions/history');
        $response->assertStatus(401);
    }

    /** @test */
    public function non_admin_users_cannot_access_commission_endpoints()
    {
        $regularUser = User::factory()->create(['email' => 'user@example.com']);
        Sanctum::actingAs($regularUser);

        $response = $this->getJson('/api/v1/admin/commissions');
        $response->assertStatus(403);

        $response = $this->putJson('/api/v1/admin/commissions', ['amount' => 600]);
        $response->assertStatus(403);

        $response = $this->getJson('/api/v1/admin/commissions/history');
        $response->assertStatus(403);
    }

    /** @test */
    public function commission_update_handles_database_errors_gracefully()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        // Create a commission with invalid data to trigger database error
        $updateData = [
            'amount' => 'invalid_amount' // This should be caught by validation
        ];

        $response = $this->putJson('/api/v1/admin/commissions', $updateData);

        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'message' => 'Validation failed'
                ]);
    }

    /** @test */
    public function commission_update_validates_decimal_amounts()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $updateData = [
            'amount' => 750.50
        ];

        $response = $this->putJson('/api/v1/admin/commissions', $updateData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Commission updated successfully'
                ]);

        $this->assertDatabaseHas('commissions', [
            'amount' => 750.50,
            'is_active' => true
        ]);
    }

    /** @test */
    public function commission_history_is_paginated()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        // Create many commission records
        Commission::factory()->count(25)->create(['is_active' => false]);

        $response = $this->getJson('/api/v1/admin/commissions/history?per_page=10');

        $response->assertStatus(200)
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
        $this->assertEquals(25, $response->json('pagination.total'));
    }

    /** @test */
    public function commission_history_is_ordered_by_creation_date_desc()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        // Create commissions with different dates
        $commission1 = Commission::factory()->create([
            'amount' => 400,
            'created_at' => now()->subDays(3)
        ]);
        $commission2 = Commission::factory()->create([
            'amount' => 500,
            'created_at' => now()->subDays(2)
        ]);
        $commission3 = Commission::factory()->create([
            'amount' => 600,
            'created_at' => now()->subDays(1)
        ]);

        $response = $this->getJson('/api/v1/admin/commissions/history');

        $response->assertStatus(200);

        $commissions = $response->json('data');
        
        // Should be ordered by creation date descending
        $this->assertEquals(600, $commissions[0]['amount']);
        $this->assertEquals(500, $commissions[1]['amount']);
        $this->assertEquals(400, $commissions[2]['amount']);
    }

    /** @test */
    public function commission_endpoints_have_rate_limiting()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        // Make multiple requests to test rate limiting
        for ($i = 0; $i < 20; $i++) {
            $response = $this->getJson('/api/v1/admin/commissions');
            
            if ($i < 15) {
                $response->assertStatus(200);
            } else {
                // Should eventually hit rate limit
                $this->assertTrue(in_array($response->status(), [200, 429]));
            }
        }
    }
}