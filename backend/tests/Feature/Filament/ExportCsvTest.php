<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\AuditLogResource;
use App\Filament\Resources\DriverResource;
use App\Filament\Resources\RideResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportCsvTest extends TestCase
{
    use RefreshDatabase;

    public function test_ride_export_columns_returns_correct_keys(): void
    {
        $columns = RideResource::getExportColumns();

        $this->assertArrayHasKey('id', $columns);
        $this->assertArrayHasKey('rider.name', $columns);
        $this->assertArrayHasKey('driver.name', $columns);
        $this->assertArrayHasKey('pickupLocation.name', $columns);
        $this->assertArrayHasKey('destinationLocation.name', $columns);
        $this->assertArrayHasKey('status', $columns);
        $this->assertArrayHasKey('actual_fare_rp', $columns);
        $this->assertArrayHasKey('actual_duration_minutes', $columns);
        $this->assertArrayHasKey('created_at', $columns);
        $this->assertCount(9, $columns);
    }

    public function test_driver_export_columns_returns_correct_keys(): void
    {
        $columns = DriverResource::getExportColumns();

        $this->assertArrayHasKey('name', $columns);
        $this->assertArrayHasKey('email', $columns);
        $this->assertArrayHasKey('phone_number', $columns);
        $this->assertArrayHasKey('driverProfile.is_verified', $columns);
        $this->assertArrayHasKey('driverProfile.credits_balance', $columns);
        $this->assertArrayHasKey('driverProfile.rating_average', $columns);
        $this->assertArrayHasKey('driverProfile.went_online_at', $columns);
        $this->assertArrayHasKey('created_at', $columns);
        $this->assertCount(8, $columns);
    }

    public function test_audit_log_export_columns_returns_correct_keys(): void
    {
        $columns = AuditLogResource::getExportColumns();

        $this->assertArrayHasKey('admin.name', $columns);
        $this->assertArrayHasKey('action_type', $columns);
        $this->assertArrayHasKey('target_type', $columns);
        $this->assertArrayHasKey('target_id', $columns);
        $this->assertArrayHasKey('reason', $columns);
        $this->assertArrayHasKey('ip_address', $columns);
        $this->assertArrayHasKey('created_at', $columns);
        $this->assertCount(7, $columns);
    }

    public function test_export_column_headers_are_human_readable(): void
    {
        $rideHeaders = array_values(RideResource::getExportColumns());
        $this->assertContains('Ride ID', $rideHeaders);
        $this->assertContains('Rider Name', $rideHeaders);
        $this->assertContains('Status', $rideHeaders);

        $driverHeaders = array_values(DriverResource::getExportColumns());
        $this->assertContains('Name', $driverHeaders);
        $this->assertContains('Email', $driverHeaders);
        $this->assertContains('KYC Status', $driverHeaders);

        $auditHeaders = array_values(AuditLogResource::getExportColumns());
        $this->assertContains('Admin', $auditHeaders);
        $this->assertContains('Action', $auditHeaders);
        $this->assertContains('Reason', $auditHeaders);
    }
}
