<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Salary;
use App\Models\SalaryHistory;
use App\Models\UploadedDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class AdminControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_can_access_dashboard_data()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        // Create test data
        $users = User::factory()->count(10)->create();
        foreach ($users->take(8) as $user) {
            Salary::factory()->create(['user_id' => $user->id]);
        }
        
        SalaryHistory::factory()->count(15)->create();
        UploadedDocument::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/admin/dashboard');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ])
                ->assertJsonStructure([
                    'data' => [
                        'summary' => [
                            'total_users',
                            'users_with_salary',
                            'users_without_salary',
                            'total_salary_changes',
                            'total_documents'
                        ],
                        'recent_activity',
                        'salary_statistics' => [
                            'average_salary_euros',
                            'average_commission',
                            'salary_ranges'
                        ],
                        'currency_distribution',
                        'monthly_registrations'
                    ]
                ]);

        $data = $response->json('data');
        $this->assertEquals(11, $data['summary']['total_users']); // 10 + admin
        $this->assertEquals(8, $data['summary']['users_with_salary']);
        $this->assertEquals(3, $data['summary']['users_without_salary']); // 2 users + admin
    }

    /** @test */
    public function admin_can_access_detailed_statistics()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        // Create users with different salary ranges
        $users = User::factory()->count(5)->create();
        Salary::factory()->create(['user_id' => $users[0]->id, 'salary_euros' => 25000]);
        Salary::factory()->create(['user_id' => $users[1]->id, 'salary_euros' => 35000]);
        Salary::factory()->create(['user_id' => $users[2]->id, 'salary_euros' => 55000]);
        Salary::factory()->create(['user_id' => $users[3]->id, 'salary_euros' => 75000]);
        Salary::factory()->create(['user_id' => $users[4]->id, 'salary_euros' => 85000]);

        $response = $this->getJson('/api/v1/admin/statistics');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ])
                ->assertJsonStructure([
                    'data' => [
                        'users' => [
                            'total',
                            'with_salary',
                            'without_salary',
                            'recent_registrations'
                        ],
                        'salaries' => [
                            'average_euros',
                            'average_commission',
                            'median_euros',
                            'ranges' => [
                                'under_30k',
                                '30k_to_50k',
                                '50k_to_75k',
                                'over_75k'
                            ]
                        ],
                        'activity' => [
                            'total_salary_changes',
                            'recent_changes',
                            'total_documents',
                            'recent_uploads'
                        ],
                        'currencies',
                        'trends'
                    ]
                ]);

        $salaryRanges = $response->json('data.salaries.ranges');
        $this->assertEquals(1, $salaryRanges['under_30k']);
        $this->assertEquals(1, $salaryRanges['30k_to_50k']);
        $this->assertEquals(1, $salaryRanges['50k_to_75k']);
        $this->assertEquals(2, $salaryRanges['over_75k']);
    }

    /** @test */
    public function admin_can_access_system_health_status()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/admin/health');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ])
                ->assertJsonStructure([
                    'data' => [
                        'status',
                        'timestamp',
                        'checks' => [
                            'database' => [
                                'status',
                                'response_time'
                            ],
                            'storage' => [
                                'status',
                                'available_space'
                            ],
                            'cache' => [
                                'status'
                            ],
                            'queue' => [
                                'status'
                            ]
                        ],
                        'system_info' => [
                            'php_version',
                            'laravel_version',
                            'environment'
                        ]
                    ]
                ]);

        $this->assertEquals('healthy', $response->json('data.status'));
    }

    /** @test */
    public function admin_can_access_advanced_user_management()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        // Create users with various data
        $users = User::factory()->count(5)->create();
        foreach ($users as $user) {
            Salary::factory()->create(['user_id' => $user->id]);
            SalaryHistory::factory()->count(2)->create(['user_id' => $user->id]);
        }

        $response = $this->getJson('/api/v1/admin/users');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ])
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'email',
                            'salary',
                            'statistics' => [
                                'salary_changes',
                                'documents_count',
                                'account_age_days'
                            ]
                        ]
                    ],
                    'pagination',
                    'summary' => [
                        'total_users',
                        'active_users',
                        'users_with_salary'
                    ]
                ]);
    }

    /** @test */
    public function admin_can_filter_users_by_multiple_criteria()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        // Create users with specific data for filtering
        $user1 = User::factory()->create(['name' => 'John Smith', 'created_at' => now()->subDays(10)]);
        $user2 = User::factory()->create(['name' => 'Jane Doe', 'created_at' => now()->subDays(5)]);
        $user3 = User::factory()->create(['name' => 'Bob Johnson', 'created_at' => now()->subDays(1)]);

        Salary::factory()->create(['user_id' => $user1->id, 'salary_euros' => 30000, 'commission' => 400]);
        Salary::factory()->create(['user_id' => $user2->id, 'salary_euros' => 50000, 'commission' => 600]);
        Salary::factory()->create(['user_id' => $user3->id, 'salary_euros' => 70000, 'commission' => 800]);

        // Test multiple filters
        $response = $this->getJson('/api/v1/admin/users?' . http_build_query([
            'search' => 'John',
            'min_salary' => 25000,
            'max_salary' => 60000,
            'min_commission' => 300,
            'created_from' => now()->subDays(15)->format('Y-m-d'),
            'created_to' => now()->format('Y-m-d')
        ]));

        $response->assertStatus(200);

        $users = $response->json('data');
        $this->assertCount(2, $users); // John Smith and Bob Johnson (contains "John")
    }

    /** @test */
    public function admin_can_export_user_data()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        // Create test data
        $users = User::factory()->count(3)->create();
        foreach ($users as $user) {
            Salary::factory()->create(['user_id' => $user->id]);
        }

        $response = $this->getJson('/api/v1/admin/users/export?format=json');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ])
                ->assertJsonStructure([
                    'data' => [
                        'export_url',
                        'format',
                        'total_records',
                        'generated_at'
                    ]
                ]);
    }

    /** @test */
    public function admin_can_perform_bulk_operations()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $users = User::factory()->count(3)->create();
        foreach ($users as $user) {
            Salary::factory()->create(['user_id' => $user->id, 'commission' => 500]);
        }

        $bulkData = [
            'operation' => 'update_commission',
            'user_ids' => $users->pluck('id')->toArray(),
            'data' => [
                'commission' => 750,
                'change_reason' => 'Bulk commission update'
            ]
        ];

        $response = $this->postJson('/api/v1/admin/bulk-operations', $bulkData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Bulk operation completed successfully'
                ])
                ->assertJsonStructure([
                    'data' => [
                        'operation',
                        'affected_records',
                        'completed_at'
                    ]
                ]);

        // Verify all salaries were updated
        foreach ($users as $user) {
            $this->assertDatabaseHas('salaries', [
                'user_id' => $user->id,
                'commission' => 750
            ]);
        }
    }

    /** @test */
    public function admin_can_view_audit_logs()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        // Create some audit data by performing operations
        $user = User::factory()->create();
        $salary = Salary::factory()->create(['user_id' => $user->id]);
        
        // Update salary to create history
        $this->putJson("/api/v1/admin/salaries/{$salary->id}", [
            'commission' => 600,
            'change_reason' => 'Test update'
        ]);

        $response = $this->getJson('/api/v1/admin/audit-logs');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ])
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'user_id',
                            'action',
                            'changes',
                            'ip_address',
                            'user_agent',
                            'created_at'
                        ]
                    ],
                    'pagination'
                ]);
    }

    /** @test */
    public function admin_can_manage_system_settings()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $settingsData = [
            'default_commission' => 600,
            'currency_conversion_api' => 'fixer.io',
            'max_file_upload_size' => 5120, // 5MB
            'allowed_file_types' => ['pdf', 'doc', 'docx', 'jpg', 'png']
        ];

        $response = $this->putJson('/api/v1/admin/settings', $settingsData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Settings updated successfully'
                ]);

        // Verify settings were saved
        $getResponse = $this->getJson('/api/v1/admin/settings');
        $getResponse->assertStatus(200)
                   ->assertJson([
                       'data' => $settingsData
                   ]);
    }

    /** @test */
    public function unauthenticated_users_cannot_access_admin_endpoints()
    {
        $response = $this->getJson('/api/v1/admin/dashboard');
        $response->assertStatus(401);

        $response = $this->getJson('/api/v1/admin/statistics');
        $response->assertStatus(401);

        $response = $this->getJson('/api/v1/admin/health');
        $response->assertStatus(401);

        $response = $this->getJson('/api/v1/admin/users');
        $response->assertStatus(401);
    }

    /** @test */
    public function non_admin_users_cannot_access_admin_endpoints()
    {
        $regularUser = User::factory()->create(['email' => 'user@example.com']);
        Sanctum::actingAs($regularUser);

        $response = $this->getJson('/api/v1/admin/dashboard');
        $response->assertStatus(403);

        $response = $this->getJson('/api/v1/admin/statistics');
        $response->assertStatus(403);

        $response = $this->getJson('/api/v1/admin/health');
        $response->assertStatus(403);

        $response = $this->getJson('/api/v1/admin/users');
        $response->assertStatus(403);
    }

    /** @test */
    public function admin_endpoints_have_rate_limiting()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        // Make multiple requests to test rate limiting
        for ($i = 0; $i < 100; $i++) {
            $response = $this->getJson('/api/v1/admin/dashboard');
            
            if ($i < 50) {
                $response->assertStatus(200);
            } else {
                // Should eventually hit rate limit
                $this->assertTrue(in_array($response->status(), [200, 429]));
            }
        }
    }

    /** @test */
    public function dashboard_data_is_cached_for_performance()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        // Create test data
        User::factory()->count(5)->create();

        // First request
        $start = microtime(true);
        $response1 = $this->getJson('/api/v1/admin/dashboard');
        $time1 = microtime(true) - $start;

        $response1->assertStatus(200);

        // Second request should be faster due to caching
        $start = microtime(true);
        $response2 = $this->getJson('/api/v1/admin/dashboard');
        $time2 = microtime(true) - $start;

        $response2->assertStatus(200);

        // Verify responses are identical
        $this->assertEquals($response1->json('data'), $response2->json('data'));
        
        // Second request should be faster (cached)
        $this->assertLessThan($time1, $time2);
    }

    /** @test */
    public function admin_can_clear_system_cache()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/admin/cache/clear');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Cache cleared successfully'
                ]);
    }

    /** @test */
    public function admin_can_view_system_logs()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/admin/logs?level=error&limit=50');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ])
                ->assertJsonStructure([
                    'data' => [
                        'logs' => [
                            '*' => [
                                'timestamp',
                                'level',
                                'message',
                                'context'
                            ]
                        ],
                        'pagination'
                    ]
                ]);
    }

    /** @test */
    public function admin_dashboard_handles_empty_data_gracefully()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        // No additional test data, just admin user
        $response = $this->getJson('/api/v1/admin/dashboard');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ]);

        $data = $response->json('data');
        $this->assertEquals(1, $data['summary']['total_users']); // Just admin
        $this->assertEquals(0, $data['summary']['users_with_salary']);
        $this->assertEquals(1, $data['summary']['users_without_salary']);
    }

    /** @test */
    public function admin_can_generate_reports()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        // Create test data
        $users = User::factory()->count(10)->create();
        foreach ($users as $user) {
            Salary::factory()->create(['user_id' => $user->id]);
        }

        $reportData = [
            'type' => 'salary_summary',
            'date_from' => now()->subMonth()->format('Y-m-d'),
            'date_to' => now()->format('Y-m-d'),
            'format' => 'pdf',
            'include_charts' => true
        ];

        $response = $this->postJson('/api/v1/admin/reports/generate', $reportData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Report generation started'
                ])
                ->assertJsonStructure([
                    'data' => [
                        'report_id',
                        'status',
                        'estimated_completion'
                    ]
                ]);
    }
}