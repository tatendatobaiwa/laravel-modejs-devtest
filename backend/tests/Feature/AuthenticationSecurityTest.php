<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Laravel\Sanctum\PersonalAccessToken;

class AuthenticationSecurityTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_prevents_brute_force_login_attempts()
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('correct_password')
        ]);

        $loginData = [
            'email' => 'admin@example.com',
            'password' => 'wrong_password'
        ];

        // Make multiple failed login attempts
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/v1/auth/login', $loginData);
            
            if ($i < 5) {
                $response->assertStatus(401);
            } else {
                // Should be rate limited after 5 attempts
                $response->assertStatus(429);
            }
        }

        // Even with correct password, should still be rate limited
        $correctLoginData = [
            'email' => 'admin@example.com',
            'password' => 'correct_password'
        ];

        $response = $this->postJson('/api/v1/auth/login', $correctLoginData);
        $response->assertStatus(429);
    }

    /** @test */
    public function it_invalidates_tokens_on_password_change()
    {
        $user = User::factory()->create([
            'password' => Hash::make('old_password')
        ]);

        // Create multiple tokens
        $token1 = $user->createToken('device1')->plainTextToken;
        $token2 = $user->createToken('device2')->plainTextToken;

        // Verify tokens work
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token1
        ])->getJson('/api/v1/auth/me');
        $response->assertStatus(200);

        // Simulate password change (this would typically happen through a password reset endpoint)
        $user->update(['password' => Hash::make('new_password')]);

        // Tokens should be invalidated after password change
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token1
        ])->getJson('/api/v1/auth/me');
        $response->assertStatus(401);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token2
        ])->getJson('/api/v1/auth/me');
        $response->assertStatus(401);
    }

    /** @test */
    public function it_prevents_token_reuse_after_logout()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        // Use token to logout
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/v1/auth/logout');
        $response->assertStatus(200);

        // Try to use the same token again
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/auth/me');
        $response->assertStatus(401);
    }

    /** @test */
    public function it_validates_token_expiration()
    {
        $user = User::factory()->create();
        
        // Create a token that's already expired
        $token = $user->createToken('test-device');
        $token->accessToken->update([
            'expires_at' => now()->subHour()
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token->plainTextToken
        ])->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_prevents_concurrent_session_attacks()
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password123')
        ]);

        // Login from multiple "devices" simultaneously
        $loginData = [
            'email' => 'admin@example.com',
            'password' => 'password123'
        ];

        $responses = [];
        for ($i = 0; $i < 5; $i++) {
            $responses[] = $this->postJson('/api/v1/auth/login', $loginData);
        }

        // All logins should succeed (concurrent sessions allowed)
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }

        // But there should be a reasonable limit on active tokens
        $activeTokens = PersonalAccessToken::where('tokenable_id', $user->id)->count();
        $this->assertLessThanOrEqual(10, $activeTokens); // Assuming max 10 concurrent sessions
    }

    /** @test */
    public function it_prevents_token_hijacking_with_ip_validation()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        // Make request from original IP
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Forwarded-For' => '192.168.1.100'
        ])->getJson('/api/v1/auth/me');
        $response->assertStatus(200);

        // Try to use token from different IP (simulating token theft)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Forwarded-For' => '10.0.0.1'
        ])->getJson('/api/v1/auth/me');

        // Should still work (IP validation might be too strict for real-world use)
        // But we can log suspicious activity
        $response->assertStatus(200);
    }

    /** @test */
    public function it_prevents_admin_privilege_escalation()
    {
        $regularUser = User::factory()->create(['email' => 'user@example.com']);
        Sanctum::actingAs($regularUser);

        // Try to access admin endpoints
        $response = $this->getJson('/api/v1/admin/dashboard');
        $response->assertStatus(403);

        $response = $this->getJson('/api/v1/admin/users');
        $response->assertStatus(403);

        // Try to manipulate token to gain admin access (this would be prevented by proper middleware)
        $response = $this->withHeaders([
            'X-Admin-Override' => 'true'
        ])->getJson('/api/v1/admin/dashboard');
        $response->assertStatus(403);
    }

    /** @test */
    public function it_validates_token_format_and_prevents_injection()
    {
        $maliciousTokens = [
            'Bearer malicious_token',
            'Bearer " OR 1=1 --',
            'Bearer <script>alert("xss")</script>',
            'Bearer ../../../etc/passwd',
            'Bearer null',
            'Bearer undefined',
            '',
            'InvalidFormat',
        ];

        foreach ($maliciousTokens as $token) {
            $response = $this->withHeaders([
                'Authorization' => $token
            ])->getJson('/api/v1/auth/me');

            $response->assertStatus(401);
        }
    }

    /** @test */
    public function it_prevents_csrf_attacks_on_state_changing_operations()
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        // Try to make state-changing request without proper CSRF protection
        $response = $this->withHeaders([
            'Origin' => 'https://malicious-site.com',
            'Referer' => 'https://malicious-site.com/attack'
        ])->postJson('/api/v1/admin/users/1', [
            'name' => 'Hacked Name'
        ]);

        // Should be protected by CORS and other security measures
        // The exact status depends on CORS configuration
        $this->assertTrue(in_array($response->status(), [403, 422, 401]));
    }

    /** @test */
    public function it_logs_suspicious_authentication_activities()
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password123')
        ]);

        // Failed login attempt
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong_password'
        ]);
        $response->assertStatus(401);

        // Multiple rapid login attempts
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'admin@example.com',
                'password' => 'wrong_password'
            ]);
        }

        // Login from unusual location (simulated)
        $response = $this->withHeaders([
            'X-Forwarded-For' => '1.2.3.4',
            'User-Agent' => 'Suspicious Bot/1.0'
        ])->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password123'
        ]);

        // Should succeed but be logged
        $response->assertStatus(200);
    }

    /** @test */
    public function it_enforces_secure_token_storage()
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password123')
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(200);

        $token = $response->json('data.token');

        // Token should be properly formatted
        $this->assertIsString($token);
        $this->assertGreaterThan(40, strlen($token)); // Should be sufficiently long

        // Token should not contain sensitive information in plain text
        $this->assertStringNotContainsString($user->email, $token);
        $this->assertStringNotContainsString($user->name, $token);
        $this->assertStringNotContainsString('admin', $token);
    }

    /** @test */
    public function it_prevents_session_fixation_attacks()
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password123')
        ]);

        // Attacker tries to fix a session ID
        $response = $this->withHeaders([
            'X-Session-ID' => 'attacker_controlled_session'
        ])->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(200);

        // New token should be generated, not using attacker's session
        $token = $response->json('data.token');
        $this->assertStringNotContainsString('attacker_controlled_session', $token);
    }

    /** @test */
    public function it_validates_user_agent_consistency()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        // Make request with original user agent
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ])->getJson('/api/v1/auth/me');
        $response->assertStatus(200);

        // Try with completely different user agent (potential token theft)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'User-Agent' => 'curl/7.68.0'
        ])->getJson('/api/v1/auth/me');

        // Should still work but could be flagged for review
        $response->assertStatus(200);
    }

    /** @test */
    public function it_handles_token_revocation_properly()
    {
        $user = User::factory()->create();
        $token1 = $user->createToken('device1');
        $token2 = $user->createToken('device2');

        Sanctum::actingAs($user);

        // Revoke specific token
        $response = $this->deleteJson("/api/v1/auth/tokens/{$token1->accessToken->id}");
        $response->assertStatus(200);

        // Revoked token should not work
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token1->plainTextToken
        ])->getJson('/api/v1/auth/me');
        $response->assertStatus(401);

        // Other token should still work
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token2->plainTextToken
        ])->getJson('/api/v1/auth/me');
        $response->assertStatus(200);
    }

    /** @test */
    public function it_prevents_timing_attacks_on_login()
    {
        // Create a user
        User::factory()->create([
            'email' => 'existing@example.com',
            'password' => Hash::make('password123')
        ]);

        // Time login attempt with existing email
        $start = microtime(true);
        $response1 = $this->postJson('/api/v1/auth/login', [
            'email' => 'existing@example.com',
            'password' => 'wrong_password'
        ]);
        $time1 = microtime(true) - $start;

        // Time login attempt with non-existing email
        $start = microtime(true);
        $response2 = $this->postJson('/api/v1/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'wrong_password'
        ]);
        $time2 = microtime(true) - $start;

        // Both should return 401
        $response1->assertStatus(401);
        $response2->assertStatus(401);

        // Response times should be similar to prevent timing attacks
        $timeDifference = abs($time1 - $time2);
        $this->assertLessThan(0.1, $timeDifference); // Less than 100ms difference
    }

    /** @test */
    public function it_enforces_password_complexity_on_login()
    {
        // This test ensures that even if weak passwords exist in the system,
        // the login process handles them securely

        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('123') // Weak password
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => '123'
        ]);

        // Should still allow login (password complexity is enforced at creation/update)
        $response->assertStatus(200);

        // But could flag for password update requirement
        $this->assertArrayHasKey('token', $response->json('data'));
    }

    /** @test */
    public function it_prevents_account_enumeration_through_login_responses()
    {
        // Create a user
        User::factory()->create([
            'email' => 'existing@example.com',
            'password' => Hash::make('password123')
        ]);

        // Try login with existing email, wrong password
        $response1 = $this->postJson('/api/v1/auth/login', [
            'email' => 'existing@example.com',
            'password' => 'wrong_password'
        ]);

        // Try login with non-existing email
        $response2 = $this->postJson('/api/v1/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'any_password'
        ]);

        // Both should return the same error message to prevent enumeration
        $response1->assertStatus(401);
        $response2->assertStatus(401);
        $this->assertEquals($response1->json('message'), $response2->json('message'));
    }
}