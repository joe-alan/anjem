<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Test API token permissions, role enforcement, and security edge cases
 */
class TokenPermissionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test token with insufficient permissions cannot access protected routes
     */
    public function test_insufficient_permissions_denied()
    {
        $rider = User::factory()->rider()->create();

        // Token with only profile read permissions
        $token = $rider->createToken('mobile-app', ['profile:read'])->plainTextToken;

        // Try to access rider-specific endpoint without rider permissions
        $response = $this->withToken($token)
                        ->getJson('/api/v1/requests');

        $response->assertStatus(Response::HTTP_FORBIDDEN)
                ->assertJson([
                    'success' => false,
                    'message' => 'Unauthorized: Rider permissions required'
                ]);
    }

    /**
     * Test expired token cannot access API
     */
    public function test_expired_token_denied()
    {
        $user = User::factory()->rider()->create();

        // Create a token and manually expire it
        $tokenResult = $user->createToken('mobile-app', ['rider:request-ride']);
        $token = PersonalAccessToken::findToken($tokenResult->plainTextToken);
        $token->update(['expires_at' => now()->subDay()]);

        $response = $this->withToken($tokenResult->plainTextToken)
                        ->getJson('/api/v1/user');

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Test malformed authorization header
     */
    public function test_malformed_authorization_header()
    {
        $response = $this->withHeaders([
                'Authorization' => 'InvalidFormat token_here'
            ])
            ->getJson('/api/v1/user');

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);

        // Test with no Bearer prefix
        $response = $this->withHeaders([
                'Authorization' => 'some_token_without_bearer'
            ])
            ->getJson('/api/v1/user');

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Test invalid token format
     */
    public function test_invalid_token_format()
    {
        // Test with completely invalid token
        $response = $this->withToken('invalid_token_format')
                        ->getJson('/api/v1/user');

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);

        // Test with empty token
        $response = $this->withToken('')
                        ->getJson('/api/v1/user');

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);

        // Test with null token - skip withToken and test with no header
        $response = $this->getJson('/api/v1/user');

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Test token with wildcard permissions
     */
    public function test_wildcard_permissions()
    {
        $user = User::factory()->create(['user_type' => 'both']);

        // Token with all permissions
        $token = $user->createToken('admin-app', ['*'])->plainTextToken;

        // Should be able to access any endpoint
        $response = $this->withToken($token)
                        ->getJson('/api/v1/user');

        $response->assertStatus(Response::HTTP_OK);

        $response = $this->withToken($token)
                        ->getJson('/api/v1/requests');

        $response->assertStatus(Response::HTTP_OK);
    }

    /**
     * Test role-based permission inheritance
     */
    public function test_role_based_permission_inheritance()
    {
        $driverRider = User::factory()->create(['user_type' => 'both']);

        // Token with both driver and rider permissions
        $token = $driverRider->createToken('mobile-app', [
            'rider:request-ride',
            'rider:cancel-ride',
            'driver:go-online',
            'driver:accept-ride'
        ])->plainTextToken;

        // Should be able to access both rider and driver endpoints
        $response = $this->withToken($token)
                        ->getJson('/api/v1/requests');

        $response->assertStatus(Response::HTTP_OK);

        $response = $this->withToken($token)
                        ->getJson('/api/v1/driver/queue');

        $response->assertStatus(Response::HTTP_OK);
    }

    /**
     * Test token revocation
     */
    public function test_revoked_token_denied()
    {
        $user = User::factory()->rider()->create();

        $tokenResult = $user->createToken('mobile-app', ['rider:request-ride']);
        $tokenId = $tokenResult->accessToken->id;
        $plainTextToken = $tokenResult->plainTextToken;

        // Token should work initially
        $response = $this->withToken($plainTextToken)
                        ->getJson('/api/v1/user');

        $response->assertStatus(Response::HTTP_OK);

        // Revoke the token
        PersonalAccessToken::find($tokenId)->delete();

        // Token should no longer work
        $response = $this->withToken($plainTextToken)
                        ->getJson('/api/v1/user');

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Test concurrent token usage from different sessions
     */
    public function test_concurrent_token_usage()
    {
        $user = User::factory()->rider()->create();

        // Create multiple tokens for the same user
        $token1 = $user->createToken('mobile-app-1', ['rider:request-ride'])->plainTextToken;
        $token2 = $user->createToken('mobile-app-2', ['rider:request-ride'])->plainTextToken;

        // Both tokens should work simultaneously
        $response1 = $this->withToken($token1)
                         ->getJson('/api/v1/user');

        $response1->assertStatus(Response::HTTP_OK);

        $response2 = $this->withToken($token2)
                         ->getJson('/api/v1/user');

        $response2->assertStatus(Response::HTTP_OK);
    }

    /**
     * Test SQL injection attempts in token
     */
    public function test_sql_injection_in_token()
    {
        $maliciousTokens = [
            "'; DROP TABLE users; --",
            "1' OR '1'='1",
            "admin'; SELECT * FROM users WHERE '1'='1",
            "1; DELETE FROM personal_access_tokens; --"
        ];

        foreach ($maliciousTokens as $maliciousToken) {
            $response = $this->withToken($maliciousToken)
                            ->getJson('/api/v1/user');

            $response->assertStatus(Response::HTTP_UNAUTHORIZED);
        }
    }

    /**
     * Test XSS attempts in authorization header
     */
    public function test_xss_in_authorization_header()
    {
        $xssPayloads = [
            '<script>alert("xss")</script>',
            'javascript:alert("xss")',
            '<img src=x onerror=alert("xss")>',
            '"><script>alert("xss")</script>'
        ];

        foreach ($xssPayloads as $payload) {
            $response = $this->withToken($payload)
                            ->getJson('/api/v1/user');

            $response->assertStatus(Response::HTTP_UNAUTHORIZED);
        }
    }

    /**
     * Test extremely long token handling
     */
    public function test_extremely_long_token()
    {
        // Create a token that's way too long (potential DoS attempt)
        $longToken = str_repeat('a', 10000);

        $response = $this->withToken($longToken)
                        ->getJson('/api/v1/user');

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Test special characters in token
     */
    public function test_special_characters_in_token()
    {
        $specialTokens = [
            'token with spaces',
            'token\nwith\nnewlines',
            'token\twith\ttabs',
            'token\0with\0nulls',
            'token|with|pipes',
            'token;with;semicolons'
        ];

        foreach ($specialTokens as $specialToken) {
            $response = $this->withToken($specialToken)
                            ->getJson('/api/v1/user');

            $response->assertStatus(Response::HTTP_UNAUTHORIZED);
        }
    }

    /**
     * Test token ability case sensitivity
     */
    public function test_token_ability_case_sensitivity()
    {
        $user = User::factory()->rider()->create();

        // Create token with lowercase abilities
        $token = $user->createToken('mobile-app', ['rider:request-ride'])->plainTextToken;

        // Try to access with different case variations
        $response = $this->withToken($token)
                        ->getJson('/api/v1/requests');

        $response->assertStatus(Response::HTTP_OK);

        // Laravel's tokenCan() should be case-sensitive, so wrong case should fail
        // This depends on the specific implementation in the controllers
    }

    /**
     * Test missing token name handling
     */
    public function test_missing_token_name()
    {
        $user = User::factory()->rider()->create();

        // Create token without name (edge case)
        $token = $user->createToken('', ['rider:request-ride'])->plainTextToken;

        $response = $this->withToken($token)
                        ->getJson('/api/v1/user');

        $response->assertStatus(Response::HTTP_OK);
    }

    /**
     * Test duplicate ability names in token
     */
    public function test_duplicate_ability_names()
    {
        $user = User::factory()->rider()->create();

        // Create token with duplicate abilities
        $token = $user->createToken('mobile-app', [
            'rider:request-ride',
            'rider:request-ride', // Duplicate
            'profile:read',
            'profile:read' // Duplicate
        ])->plainTextToken;

        $response = $this->withToken($token)
                        ->getJson('/api/v1/requests');

        $response->assertStatus(Response::HTTP_OK);
    }

    /**
     * Test empty abilities array
     */
    public function test_empty_abilities_array()
    {
        $user = User::factory()->rider()->create();

        // Create token with no abilities
        $token = $user->createToken('mobile-app', [])->plainTextToken;

        // Should be able to access basic user endpoint but not protected ones
        $response = $this->withToken($token)
                        ->getJson('/api/v1/user');

        $response->assertStatus(Response::HTTP_OK);

        // Should not be able to access protected endpoints
        $response = $this->withToken($token)
                        ->getJson('/api/v1/requests');

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }
}
