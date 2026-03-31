<?php

namespace Tests\Unit\Widgets;

use App\Filament\Widgets\StatsOverviewWidget;
use App\Models\DriverProfile;
use App\Models\Location;
use App\Models\Ride;
use App\Models\RideRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Tests\TestCase;

class StatsOverviewWidgetTest extends TestCase
{
    use RefreshDatabase;

    private StatsOverviewWidget $widget;

    protected function setUp(): void
    {
        parent::setUp();
        $this->widget = new StatsOverviewWidget();
    }

    private function createRide(array $overrides = []): Ride
    {
        $rider = User::factory()->create(['role' => 'rider']);
        $driver = User::factory()->create(['role' => 'driver']);

        $pickup = Location::firstOrCreate(
            ['name' => 'Test Pickup'],
            ['name' => 'Test Pickup', 'coordinates' => new Point(-6.9, 107.6)]
        );
        $dest = Location::firstOrCreate(
            ['name' => 'Test Dest'],
            ['name' => 'Test Dest', 'coordinates' => new Point(-6.91, 107.61)]
        );

        $rideRequest = RideRequest::create([
            'rider_id'               => $rider->id,
            'pickup_location_id'     => $pickup->id,
            'destination_location_id'=> $dest->id,
            'estimated_fare_rp'      => 10000,
            'passenger_count'        => 1,
            'status'                 => 'matched',
        ]);

        return Ride::create(array_merge([
            'ride_request_id'        => $rideRequest->id,
            'rider_id'               => $rider->id,
            'driver_id'              => $driver->id,
            'pickup_location_id'     => $pickup->id,
            'destination_location_id'=> $dest->id,
            'status'                 => 'completed',
            'estimated_fare_rp'      => 10000,
            'actual_fare_rp'         => 12000,
            'driver_accepted_at'     => now()->subMinutes(3),
            'dropoff_time'           => now(),
            'created_at'             => now()->subMinutes(10),
        ], $overrides));
    }

    public function test_acceptance_and_cancellation_rates_with_known_distribution(): void
    {
        // 6 accepted/completed, 2 cancelled, 2 matched (pending)
        for ($i = 0; $i < 6; $i++) {
            $this->createRide(['status' => 'completed', 'dropoff_time' => now()]);
        }
        for ($i = 0; $i < 2; $i++) {
            $this->createRide(['status' => 'cancelled', 'dropoff_time' => null, 'actual_fare_rp' => null]);
        }
        for ($i = 0; $i < 2; $i++) {
            $this->createRide(['status' => 'matched', 'driver_accepted_at' => null, 'dropoff_time' => null, 'actual_fare_rp' => null]);
        }

        $kpis = $this->widget->getOperationalKpis();

        $this->assertEquals(60.0, $kpis['acceptance_rate']);   // 6/10
        $this->assertEquals(20.0, $kpis['cancellation_rate']); // 2/10
    }

    public function test_average_wait_time_with_known_timestamps(): void
    {
        $acceptedAt = now();

        // Ride 1: 4 min wait, Ride 2: 6 min wait → avg = 5
        $ride1 = $this->createRide(['driver_accepted_at' => $acceptedAt]);
        Ride::withoutTimestamps(fn () =>
            Ride::where('id', $ride1->id)->update(['created_at' => $acceptedAt->copy()->subMinutes(4)])
        );

        $ride2 = $this->createRide(['driver_accepted_at' => $acceptedAt]);
        Ride::withoutTimestamps(fn () =>
            Ride::where('id', $ride2->id)->update(['created_at' => $acceptedAt->copy()->subMinutes(6)])
        );

        $kpis = $this->widget->getOperationalKpis();

        $this->assertEqualsWithDelta(5.0, $kpis['avg_wait_minutes'], 0.5);
    }

    public function test_completed_count_scoped_to_7_days(): void
    {
        // 3 completed within 7 days
        for ($i = 0; $i < 3; $i++) {
            $this->createRide(['status' => 'completed', 'dropoff_time' => now()->subDays(2)]);
        }
        // 2 completed outside 7-day window
        for ($i = 0; $i < 2; $i++) {
            $this->createRide([
                'status'      => 'completed',
                'dropoff_time' => now()->subDays(10),
                'created_at'   => now()->subDays(10),
            ]);
        }

        $kpis = $this->widget->getOperationalKpis();

        $this->assertEquals(3, $kpis['completed_count']);
    }

    public function test_average_fare_calculation(): void
    {
        $this->createRide(['status' => 'completed', 'actual_fare_rp' => 10000, 'dropoff_time' => now()]);
        $this->createRide(['status' => 'completed', 'actual_fare_rp' => 20000, 'dropoff_time' => now()]);
        $this->createRide(['status' => 'completed', 'actual_fare_rp' => 15000, 'dropoff_time' => now()]);

        $kpis = $this->widget->getOperationalKpis();

        $this->assertEquals(15000, $kpis['avg_fare']);
    }

    public function test_zero_data_returns_graceful_defaults(): void
    {
        $kpis = $this->widget->getOperationalKpis();

        $this->assertEquals(0, $kpis['acceptance_rate']);
        $this->assertEquals(0, $kpis['cancellation_rate']);
        $this->assertEquals(0, $kpis['avg_wait_minutes']);
        $this->assertEquals(0, $kpis['completed_count']);
        $this->assertEquals(0, $kpis['avg_fare']);
    }
}
