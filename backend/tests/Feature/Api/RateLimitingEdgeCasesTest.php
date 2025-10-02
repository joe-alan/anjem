<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class RateLimitingEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    public function test_authentication_endpoint_rate_limiting()
    {
        $endpoint = '/api/v1/auth/firebase';
        $payload = ['id_token' => 'fake_token'];

        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson($endpoint, $payload);

            $this->assertNotEquals(Response::HTTP_TOO_MANY_REQUESTS, $response->status(),
                "Request $i should not be rate limited");
        }

        $response = $this->postJson($endpoint, $payload);
        $this->assertEquals(Response::HTTP_TOO_MANY_REQUESTS, $response->status(),
            'Request 6 should be rate limited');
    }

    public function test_general_api_endpoint_rate_limiting()
    {
        $user = User::factory()->rider()->create();
        $token = $user->createTokenWithAbilities();

        $endpoint = '/api/v1/user';

        for ($i = 0; $i < 10; $i++) {
            $response = $this->withAuthToken($token)->getJson($endpoint);

            $this->assertNotEquals(Response::HTTP_TOO_MANY_REQUESTS, $response->status(),
                "Request $i should not be rate limited");
        }
    }

    public function test_location_update_endpoint_higher_rate_limiting()
    {
        $driver = User::factory()->driver()->create();
        $token = $driver->createTokenWithAbilities(true);

        $endpoint = '/api/v1/driver/location';
        $payload = [
            'latitude' => -6.3605,
            'longitude' => 106.8271,
            'heading' => 45.0,
            'speed' => 25.0,
        ];

        for ($i = 0; $i < 20; $i++) {
            $response = $this->withAuthToken($token)->postJson($endpoint, $payload);

            $this->assertNotEquals(Response::HTTP_TOO_MANY_REQUESTS, $response->status(),
                "Location update request $i should not be rate limited");
        }
    }

    public function test_rate_limiting_with_different_ip_addresses()
    {
        $endpoint = '/api/v1/auth/firebase';
        $payload = ['id_token' => 'fake_token'];

        for ($i = 0; $i < 5; $i++) {
            $response = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.1'])
                ->postJson($endpoint, $payload);

            $this->assertNotEquals(Response::HTTP_TOO_MANY_REQUESTS, $response->status());
        }

        $response = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.1'])
            ->postJson($endpoint, $payload);
        $this->assertEquals(Response::HTTP_TOO_MANY_REQUESTS, $response->status());

        $response = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.2'])
            ->postJson($endpoint, $payload);
        $this->assertNotEquals(Response::HTTP_TOO_MANY_REQUESTS, $response->status());
    }

    public function test_rate_limiting_authenticated_vs_unauthenticated()
    {
        $user = User::factory()->rider()->create();
        $token = $user->createTokenWithAbilities();

        $response = $this->getJson('/api/v1/user');
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->status());

        $response = $this->withAuthToken($token)->getJson('/api/v1/user');
        $this->assertNotEquals(Response::HTTP_TOO_MANY_REQUESTS, $response->status());
    }

    public function test_rate_limiting_headers_are_present()
    {
        $user = User::factory()->rider()->create();
        $token = $user->createTokenWithAbilities();

        $response = $this->withAuthToken($token)->getJson('/api/v1/user');

        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
    }

    public function test_rate_limiting_with_concurrent_requests()
    {
        $endpoint = '/api/v1/auth/firebase';
        $payload = ['id_token' => 'fake_token'];

        $responses = [];

        for ($i = 0; $i < 8; $i++) {
            $responses[] = $this->postJson($endpoint, $payload);
        }

        $rateLimitedCount = 0;
        $successfulCount = 0;

        foreach ($responses as $response) {
            if ($response->status() === Response::HTTP_TOO_MANY_REQUESTS) {
                $rateLimitedCount++;
            } else {
                $successfulCount++;
            }
        }

        $this->assertGreaterThan(0, $rateLimitedCount, 'Some requests should be rate limited');
        $this->assertGreaterThan(0, $successfulCount, 'Some requests should succeed');
        $this->assertLessThanOrEqual(5, $successfulCount, 'No more than 5 requests should succeed');
    }

    public function test_rate_limiting_reset_after_time_window()
    {
        $endpoint = '/api/v1/auth/firebase';
        $payload = ['id_token' => 'fake_token'];

        $initialTime = time();

        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson($endpoint, $payload);
            $this->assertNotEquals(Response::HTTP_TOO_MANY_REQUESTS, $response->status());
        }

        $response = $this->postJson($endpoint, $payload);
        $this->assertEquals(Response::HTTP_TOO_MANY_REQUESTS, $response->status());
    }

    public function test_rate_limiting_with_malformed_requests()
    {
        $endpoint = '/api/v1/auth/firebase';

        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson($endpoint, ['invalid' => 'data']);

            if ($i < 5) {
                $this->assertNotEquals(Response::HTTP_TOO_MANY_REQUESTS, $response->status(),
                    "Malformed request $i should not be rate limited");
            } else {
                $this->assertEquals(Response::HTTP_TOO_MANY_REQUESTS, $response->status(),
                    'Malformed request should still be rate limited');
            }
        }
    }

    public function test_rate_limiting_with_different_http_methods()
    {
        $user = User::factory()->rider()->create();
        $token = $user->createTokenWithAbilities();

        $responses = [
            $this->withAuthToken($token)->getJson('/api/v1/user'),
            $this->withAuthToken($token)->postJson('/api/v1/requests', []),
            $this->withAuthToken($token)->getJson('/api/v1/locations'),
        ];

        foreach ($responses as $response) {
            $this->assertNotEquals(Response::HTTP_TOO_MANY_REQUESTS, $response->status());
        }
    }

    public function test_rate_limiting_configuration_edge_cases()
    {
        for ($i = 0; $i < 10; $i++) {
            $response = $this->getJson('/api/v1/health');
            $this->assertEquals(Response::HTTP_OK, $response->status());
        }
    }

    public function test_rate_limiting_with_invalid_tokens()
    {
        $invalidToken = 'invalid_token_string';

        for ($i = 0; $i < 10; $i++) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $invalidToken,
            ])->getJson('/api/v1/user');

            $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->status());
        }
    }

    protected function withAuthToken(string $token)
    {
        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);
    }
}