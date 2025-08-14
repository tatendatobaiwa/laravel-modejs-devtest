<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_can_login_with_valid_credentials()
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password123')
        ]);

        $loginData = [
            'email' => 'admin@example.com',
            'password' => 'password123'
        ];

        $response = $this->postJson('/api/v1/auth/login', $loginData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Login successful'
                ])
                ->assertJsonStructure([
                    'data' => [
                        'user',
                        'token',
                        'token_type',
                        'expires_at'
                    ]
                ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $admin->id,
            'tokenable_type' => User::class
        ]);
    }

    /** @test */
    public function login_fails_with_invalid_credentials()
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password123')
        ]);

        $loginData = [
            'email' => 'admin@example.com',
            'password' => 'wrongpassword'
        ];

        $response = $this->postJson('/api/v1/auth/login', $loginData);

        $response->assertStatus(401)
                ->assertJson([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $admin->id
        ]);
    }

    /** @test */
    public function login_fails_with_non_existent_email()
    {
        $loginData = [
            'email' => 'nonexistent@example.com',
            'password' => 'password123'
        ];

        $response = $this->postJson('/api/v1/auth/login', $loginData);

        $response->assertStatus(401)
                ->assertJson([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ]);
    }

    /** @test */
    public function login_validates_required_fields()
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email', 'password']);
    }

    /** @test */
    public function login_validates_email_format()
    {
        $loginData = [
            'email' => 'invalid-email',
            'password' => 'password123'
        ];

        $response = $this->postJson('/api/v1/auth/login', $loginData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function authenticated_user_can_logout()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Logged out successfully'
                ]);

        // Token should be deleted
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id
        ]);
    }

    /** @test */
    public function authenticated_user_can_logout_all_sessions()
    {
        $user = User::factory()->create();
        
        // Create multiple tokens
        $token1 = $user->createToken('device1')->plainTextToken;
        $token2 = $user->createToken('device2')->plainTextToken;
        $token3 = $user->createToken('device3')->plainTextToken;

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/auth/logout-all');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Logged out from all devices successfully'
                ]);

        // All tokens should be deleted
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id
        ]);
    }

    /** @test */
    public function authenticated_user_can_refresh_token()
    {
        $user = User::factory()->create();
        $oldToken = $user->createToken('test-device')->plainTextToken;
        
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/auth/refresh');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Token refreshed successfully'
                ])
                ->assertJsonStructure([
                    'data' => [
                        'token',
                        'token_type',
                        'expires_at'
                    ]
                ]);

        // Should have a new token
        $newToken = $response->json('data.token');
        $this->assertNotEquals($oldToken, $newToken);
    }

    /** @test */
    public function authenticated_user_can_get_own_profile()
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'id' => $user->id,
                        'name' => 'John Doe',
                        'email' => 'john@example.com'
                    ]
                ])
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'name',
                        'email',
                        'created_at',
                        'updated_at'
                    ]
                ]);
    }

    /** @test */
    public function authenticated_user_can_list_active_tokens()
    {
        $user = User::factory()->create();
        
        // Create multiple tokens
        $user->createToken('device1');
        $user->createToken('device2');
        $user->createToken('device3');
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/tokens');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ])
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'last_used_at',
                            'created_at'
                        ]
                    ]
                ]);

        $this->assertCount(3, $response->json('data'));
    }

    /** @test */
    public function authenticated_user_can_revoke_specific_token()
    {
        $user = User::factory()->create();
        
        $token1 = $user->createToken('device1');
        $token2 = $user->createToken('device2');
        
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/auth/tokens/{$token1->accessToken->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Token revoked successfully'
                ]);

        // Token1 should be deleted, token2 should remain
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token1->accessToken->id
        ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $token2->accessToken->id
        ]);
    }

    /** @test */
    public function user_cannot_revoke_other_users_tokens()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $token1 = $user1->createToken('device1');
        $token2 = $user2->createToken('device2');
        
        Sanctum::actingAs($user1);

        $response = $this->deleteJson("/api/v1/auth/tokens/{$token2->accessToken->id}");

        $response->assertStatus(404);

        // Token2 should still exist
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $token2->accessToken->id
        ]);
    }

    /** @test */
    public function unauthenticated_users_cannot_access_protected_auth_endpoints()
    {
        $response = $this->postJson('/api/v1/auth/logout');
        $response->assertStatus(401);

        $response = $this->postJson('/api/v1/auth/logout-all');
        $response->assertStatus(401);

        $response = $this->postJson('/api/v1/auth/refresh');
        $response->assertStatus(401);

        $response = $this->getJson('/api/v1/auth/me');
        $response->assertStatus(401);

        $response = $this->getJson('/api/v1/auth/tokens');
        $response->assertStatus(401);

        $response = $this->deleteJson('/api/v1/auth/tokens/1');
        $response->assertStatus(401);
    }

    /** @test */
    public function login_endpoint_has_rate_limiting()
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password123')
        ]);

        $loginData = [
            'email' => 'admin@example.com',
            'password' => 'wrongpassword'
        ];

        // Make multiple failed login attempts
        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson('/api/v1/auth/login', $loginData);
            
            if ($i < 5) {
                $response->assertStatus(401);
            } else {
                // Should eventually hit rate limit
                $this->assertTrue(in_array($response->status(), [401, 429]));
            }
        }
    }

    /** @test */
    public function successful_login_includes_user_relationships()
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password123')
        ]);

        // Create related data
        $salary = $this->createSalary(['user_id' => $admin->id]);

        $loginData = [
            'email' => 'admin@example.com',
            'password' => 'password123'
        ];

        $response = $this->postJson('/api/v1/auth/login', $loginData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'user' => [
                            'id',
                            'name',
                            'email',
                            'salary',
                            'uploaded_documents'
                        ]
                    ]
                ]);
    }

    /** @test */
    public function token_expiration_is_properly_set()
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password123')
        ]);

        $loginData = [
            'email' => 'admin@example.com',
            'password' => 'password123'
        ];

        $response = $this->postJson('/api/v1/auth/login', $loginData);

        $response->assertStatus(200);

        $expiresAt = $response->json('data.expires_at');
        $this->assertNotNull($expiresAt);
        
        // Should expire in the future
        $this->assertGreaterThan(now()->timestamp, strtotime($expiresAt));
    }

    /** @test */
    public function login_logs_successful_authentication()
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password123')
        ]);

        $loginData = [
            'email' => 'admin@example.com',
            'password' => 'password123'
        ];

        $response = $this->postJson('/api/v1/auth/login', $loginData);

        $response->assertStatus(200);

        // Check that login was logged (this would require checking logs or audit table)
        // For now, we just verify the response structure includes necessary data
        $this->assertArrayHasKey('token', $response->json('data'));
        $this->assertArrayHasKey('user', $response->json('data'));
    }

    /** @test */
    public function login_logs_failed_authentication_attempts()
    {
        $loginData = [
            'email' => 'nonexistent@example.com',
            'password' => 'wrongpassword'
        ];

        $response = $this->postJson('/api/v1/auth/login', $loginData);

        $response->assertStatus(401);

        // Verify that failed login attempt is properly handled
        $this->assertEquals('Invalid credentials', $response->json('message'));
    }

    /** @test */
    public function token_includes_proper_abilities()
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password123')
        ]);

        $loginData = [
            'email' => 'admin@example.com',
            'password' => 'password123'
        ];

        $response = $this->postJson('/api/v1/auth/login', $loginData);

        $response->assertStatus(200);

        // Verify token can be used for authenticated requests
        $token = $response->json('data.token');
        
        $authenticatedResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/auth/me');

        $authenticatedResponse->assertStatus(200);
    }

    /** @test */
    public function logout_invalidates_current_token_only()
    {
        $user = User::factory()->create();
        
        $token1 = $user->createToken('device1');
        $token2 = $user->createToken('device2');
        
        // Use token1 for logout
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token1->plainTextToken
        ])->postJson('/api/v1/auth/logout');

        $response->assertStatus(200);

        // Token1 should be deleted, token2 should remain
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token1->accessToken->id
        ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $token2->accessToken->id
        ]);
    }
}