<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\FirebaseAuthService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Mockery;
use Tests\TestCase;

/**
 * Test AuthController for edge cases and security vulnerabilities
 */
class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private $mockFirebaseAuth;
    private $mockNotificationService;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock services to avoid Firebase dependency in tests
        $this->mockFirebaseAuth = Mockery::mock(FirebaseAuthService::class);
        $this->mockNotificationService = Mockery::mock(NotificationService::class);

        $this->app->instance(FirebaseAuthService::class, $this->mockFirebaseAuth);
        $this->app->instance(NotificationService::class, $this->mockNotificationService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test authentication with invalid Firebase token
     */
    public function test_authentication_with_invalid_firebase_token()
    {
        $this->mockFirebaseAuth
            ->shouldReceive('verifyToken')
            ->once()
            ->with('invalid_token')
            ->andThrow(new \Exception('Invalid token'));

        $response = $this->postJson('/api/v1/auth/firebase', [
            'firebase_token' => 'invalid_token',
            'device_type' => 'rider',
        ]);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED)
                ->assertJson([
                    'error' => 'Authentication failed',
                ]);
    }

    /**
     * Test authentication with missing required fields
     */
    public function test_authentication_with_missing_fields()
    {
        $response = $this->postJson('/api/v1/auth/firebase', [
            'firebase_token' => 'valid_token',
            // Missing device_type
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->assertJson([
                    'error' => 'Validation failed',
                    'messages' => [
                        'device_type' => ['The device type field is required.']
                    ]
                ]);
    }

    /**
     * Test authentication with invalid device type
     */
    public function test_authentication_with_invalid_device_type()
    {
        $response = $this->postJson('/api/v1/auth/firebase', [
            'firebase_token' => 'valid_token',
            'device_type' => 'invalid_type',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->assertJson([
                    'error' => 'Validation failed',
                    'messages' => [
                        'device_type' => ['The selected device type is invalid.']
                    ]
                ]);
    }

    /**
     * Test FCM token update without authentication
     */
    public function test_fcm_token_update_without_auth()
    {
        $response = $this->postJson('/api/v1/auth/fcm-token', [
            'fcm_token' => str_repeat('a', 152),
        ]);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Test token refresh without authentication
     */
    public function test_token_refresh_without_auth()
    {
        $response = $this->postJson('/api/v1/auth/refresh');

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Test logout without authentication
     */
    public function test_logout_without_auth()
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }
}
