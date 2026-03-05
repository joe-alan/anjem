<?php

namespace Tests\Feature\Api;

use App\Models\AdminAuditLog;
use App\Models\CreditTransaction;
use App\Models\DriverProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminKycCreditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $driver;
    private DriverProfile $driverProfile;
    private string $adminToken;
    private string $riderToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'email'     => 'admin@test.com',
            'role'      => 'admin',
            'is_active' => true,
        ]);

        $this->adminToken = $this->admin->createTokenWithAbilities(false, true);

        $rider = User::factory()->create([
            'email'     => 'rider@test.com',
            'role'      => 'rider',
            'is_active' => true,
        ]);
        $this->riderToken = $rider->createTokenWithAbilities(true, false);

        $this->driver = User::factory()->create([
            'email'     => 'driver@test.com',
            'role'      => 'driver',
            'is_active' => true,
        ]);

        $this->driverProfile = DriverProfile::create([
            'user_id'           => $this->driver->id,
            'vehicle_type'      => 'motorcycle',
            'vehicle_plate'     => 'B1234XYZ',
            'is_verified'       => false,
            'email_verified_at' => Carbon::now(),
            'credits_balance'   => 10,
            'credits_total_earned' => 10,
            'credits_total_spent'  => 0,
        ]);
    }

    // ==========================================================================
    // KYC TESTS
    // ==========================================================================

    public function test_admin_can_approve_kyc(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept'        => 'application/json',
        ])->postJson('/api/admin/drivers/'.$this->driver->id.'/kyc/approve', [
            'reason' => 'Documents verified',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'KYC approved successfully']);

        $this->driverProfile->refresh();
        $this->assertTrue($this->driverProfile->is_verified);

        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id'    => $this->admin->id,
            'action_type' => 'kyc_approve',
            'target_id'   => $this->driverProfile->id,
        ]);
    }

    public function test_admin_can_reject_kyc_with_reason(): void
    {
        // Give the driver a verified status first
        $this->driverProfile->update(['is_verified' => true]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept'        => 'application/json',
        ])->postJson('/api/admin/drivers/'.$this->driver->id.'/kyc/reject', [
            'reason' => 'Documents are blurry and cannot be verified clearly',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'KYC rejected successfully']);

        $this->driverProfile->refresh();
        $this->assertFalse($this->driverProfile->is_verified);
        $this->assertNull($this->driverProfile->email_verified_at);
    }

    public function test_kyc_reject_requires_reason(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept'        => 'application/json',
        ])->postJson('/api/admin/drivers/'.$this->driver->id.'/kyc/reject', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_kyc_reject_reason_must_be_at_least_10_chars(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept'        => 'application/json',
        ])->postJson('/api/admin/drivers/'.$this->driver->id.'/kyc/reject', [
            'reason' => 'Short',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_non_admin_cannot_approve_kyc(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->riderToken,
            'Accept'        => 'application/json',
        ])->postJson('/api/admin/drivers/'.$this->driver->id.'/kyc/approve');

        $response->assertStatus(403);
    }

    // ==========================================================================
    // CREDIT TESTS
    // ==========================================================================

    public function test_admin_can_grant_credits(): void
    {
        $initialBalance = $this->driverProfile->credits_balance;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept'        => 'application/json',
        ])->postJson('/api/admin/drivers/'.$this->driver->id.'/credits/grant', [
            'amount' => 5,
            'reason' => 'Bonus for completing 50 rides',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Credits granted successfully',
            ]);

        $this->driverProfile->refresh();
        $this->assertEquals($initialBalance + 5, $this->driverProfile->credits_balance);

        $this->assertDatabaseHas('credit_transactions', [
            'driver_id' => $this->driver->id,
            'type'      => 'admin_grant',
            'amount'    => 5,
        ]);
    }

    public function test_admin_can_deduct_credits(): void
    {
        $initialBalance = $this->driverProfile->credits_balance;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept'        => 'application/json',
        ])->postJson('/api/admin/drivers/'.$this->driver->id.'/credits/deduct', [
            'amount' => 3,
            'reason' => 'Penalty for policy violation',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Credits deducted successfully',
                'data'    => ['credits_balance' => $initialBalance - 3, 'amount_deducted' => 3],
            ]);

        $this->driverProfile->refresh();
        $this->assertEquals($initialBalance - 3, $this->driverProfile->credits_balance);

        $this->assertDatabaseHas('credit_transactions', [
            'driver_id' => $this->driver->id,
            'type'      => 'admin_deduction',
            'amount'    => -3,
        ]);
    }

    public function test_credit_deduct_cannot_go_negative(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept'        => 'application/json',
        ])->postJson('/api/admin/drivers/'.$this->driver->id.'/credits/deduct', [
            'amount' => 999,
            'reason' => 'Trying to over-deduct credits beyond balance',
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);

        // Balance should be unchanged
        $this->driverProfile->refresh();
        $this->assertEquals(10, $this->driverProfile->credits_balance);
    }

    public function test_credit_grant_validates_max_100(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept'        => 'application/json',
        ])->postJson('/api/admin/drivers/'.$this->driver->id.'/credits/grant', [
            'amount' => 101,
            'reason' => 'Too many credits at once',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    // ==========================================================================
    // AUDIT LOG TESTS
    // ==========================================================================

    public function test_audit_log_created_on_suspend(): void
    {
        $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept'        => 'application/json',
        ])->postJson('/api/admin/drivers/'.$this->driver->id.'/suspend', [
            'suspended' => true,
            'reason'    => 'Violating terms of service',
        ]);

        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id'    => $this->admin->id,
            'action_type' => 'driver_suspend',
            'target_id'   => $this->driver->id,
        ]);
    }

    public function test_audit_log_created_on_kyc_actions(): void
    {
        // Approve
        $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept'        => 'application/json',
        ])->postJson('/api/admin/drivers/'.$this->driver->id.'/kyc/approve');

        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id'    => $this->admin->id,
            'action_type' => 'kyc_approve',
        ]);

        // Reject
        $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept'        => 'application/json',
        ])->postJson('/api/admin/drivers/'.$this->driver->id.'/kyc/reject', [
            'reason' => 'Documents are blurry and cannot be read properly',
        ]);

        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id'    => $this->admin->id,
            'action_type' => 'kyc_reject',
        ]);
    }

    // ==========================================================================
    // DOCUMENT & AUDIT LOG ENDPOINT TESTS
    // ==========================================================================

    public function test_admin_can_get_driver_document(): void
    {
        $this->driverProfile->update(['ktm_url' => 'driver-documents/ktm_12345.jpg']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept'        => 'application/json',
        ])->getJson('/api/admin/drivers/'.$this->driver->id.'/document');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['driver_id', 'ktm_url']]);
    }

    public function test_admin_can_list_audit_logs(): void
    {
        // Create a log entry
        AdminAuditLog::create([
            'admin_id'    => $this->admin->id,
            'action_type' => 'kyc_approve',
            'target_type' => DriverProfile::class,
            'target_id'   => $this->driverProfile->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept'        => 'application/json',
        ])->getJson('/api/admin/audit-logs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);

        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_audit_logs_can_be_filtered_by_action_type(): void
    {
        AdminAuditLog::create([
            'admin_id'    => $this->admin->id,
            'action_type' => 'kyc_approve',
            'target_type' => DriverProfile::class,
            'target_id'   => $this->driverProfile->id,
        ]);

        AdminAuditLog::create([
            'admin_id'    => $this->admin->id,
            'action_type' => 'driver_suspend',
            'target_type' => User::class,
            'target_id'   => $this->driver->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'Accept'        => 'application/json',
        ])->getJson('/api/admin/audit-logs?action_type=kyc_approve');

        $response->assertStatus(200);

        foreach ($response->json('data') as $log) {
            $this->assertEquals('kyc_approve', $log['action_type']);
        }
    }
}
