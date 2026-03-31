<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\KycResource;
use App\Models\DriverProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class KycArchivalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $driver;
    private DriverProfile $driverProfile;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();

        $this->admin = User::factory()->create([
            'role'      => 'admin',
            'is_active' => true,
        ]);

        $this->driver = User::factory()->create([
            'role'      => 'driver',
            'is_active' => true,
        ]);

        $this->driverProfile = DriverProfile::create([
            'user_id'           => $this->driver->id,
            'vehicle_type'      => 'motorcycle',
            'vehicle_plate'     => 'B1234XYZ',
            'vehicle_color'     => 'Red',
            'student_email'     => 'student@university.ac.id',
            'student_id'        => 'STU-001',
            'student_name'      => 'Test Student',
            'ktm_url'           => '/storage/driver-documents/ktm_test.jpg',
            'is_verified'       => false,
            'email_verified_at' => Carbon::now(),
            'credits_balance'   => 0,
            'credits_total_earned' => 0,
            'credits_total_spent'  => 0,
        ]);
    }

    public function test_kyc_approval_preserves_all_fields(): void
    {
        $this->actingAs($this->admin);

        // Capture original values
        $originalData = [
            'student_email' => $this->driverProfile->student_email,
            'student_id'    => $this->driverProfile->student_id,
            'student_name'  => $this->driverProfile->student_name,
            'vehicle_plate' => $this->driverProfile->vehicle_plate,
            'vehicle_color' => $this->driverProfile->vehicle_color,
            'ktm_url'       => $this->driverProfile->ktm_url,
        ];

        // Invoke the approve method via reflection (private static)
        $method = new \ReflectionMethod(KycResource::class, 'approveKyc');
        $method->setAccessible(true);
        $method->invoke(null, $this->driver, null);

        $this->driverProfile->refresh();

        // Verify approval happened
        $this->assertTrue($this->driverProfile->is_verified);

        // Verify all document fields preserved
        $this->assertEquals($originalData['student_email'], $this->driverProfile->student_email);
        $this->assertEquals($originalData['student_id'], $this->driverProfile->student_id);
        $this->assertEquals($originalData['student_name'], $this->driverProfile->student_name);
        $this->assertEquals($originalData['vehicle_plate'], $this->driverProfile->vehicle_plate);
        $this->assertEquals($originalData['vehicle_color'], $this->driverProfile->vehicle_color);
        $this->assertEquals($originalData['ktm_url'], $this->driverProfile->ktm_url);
    }

    public function test_kyc_rejection_preserves_document_fields(): void
    {
        $this->actingAs($this->admin);

        // Start with a verified driver
        $this->driverProfile->update(['is_verified' => true]);

        // Capture original document values
        $originalData = [
            'student_email' => $this->driverProfile->student_email,
            'student_id'    => $this->driverProfile->student_id,
            'student_name'  => $this->driverProfile->student_name,
            'vehicle_plate' => $this->driverProfile->vehicle_plate,
            'vehicle_color' => $this->driverProfile->vehicle_color,
            'ktm_url'       => $this->driverProfile->ktm_url,
        ];

        // Invoke the reject method via reflection (private static)
        $method = new \ReflectionMethod(KycResource::class, 'rejectKyc');
        $method->setAccessible(true);
        $method->invoke(null, $this->driver, 'Documents are blurry and need to be re-uploaded');

        $this->driverProfile->refresh();

        // Verify rejection happened
        $this->assertFalse($this->driverProfile->is_verified);
        $this->assertNull($this->driverProfile->email_verified_at);

        // Verify all document fields preserved
        $this->assertEquals($originalData['student_email'], $this->driverProfile->student_email);
        $this->assertEquals($originalData['student_id'], $this->driverProfile->student_id);
        $this->assertEquals($originalData['student_name'], $this->driverProfile->student_name);
        $this->assertEquals($originalData['vehicle_plate'], $this->driverProfile->vehicle_plate);
        $this->assertEquals($originalData['vehicle_color'], $this->driverProfile->vehicle_color);
        $this->assertEquals($originalData['ktm_url'], $this->driverProfile->ktm_url);
    }
}
