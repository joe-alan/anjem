<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\RideStatusUpdated;
use App\Http\Resources\RideResource;
use App\Models\AdminAuditLog;
use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\RideRequest;
use App\Models\RouteCache;
use App\Models\User;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * AdminController handles all admin dashboard operations
 *
 * Provides endpoints for:
 * - Driver/rider management
 * - Analytics and statistics
 * - Real-time monitoring
 * - Route cache analytics
 */
class AdminController extends Controller
{
    // ==========================================================================
    // DRIVER MANAGEMENT
    // ==========================================================================

    /**
     * GET /api/admin/drivers
     * List all drivers with filtering and pagination
     */
    public function listDrivers(Request $request): JsonResponse
    {
        $query = User::with('driverProfile')
            ->whereHas('driverProfile')
            ->where('is_active', true);

        // Filter by KYC/verification status
        if ($request->has('kyc_status')) {
            $status = $request->kyc_status;
            $query->whereHas('driverProfile', function ($q) use ($status) {
                if ($status === 'approved') {
                    $q->where('is_verified', true)->whereNotNull('email_verified_at');
                } elseif ($status === 'pending') {
                    $q->where('is_verified', false)->whereNull('email_verified_at');
                } elseif ($status === 'email_verified') {
                    $q->whereNotNull('email_verified_at')->where('is_verified', false);
                }
            });
        }

        // Filter by online status
        if ($request->has('online')) {
            $online = filter_var($request->online, FILTER_VALIDATE_BOOLEAN);
            $query->whereHas('driverProfile', function ($q) use ($online) {
                if ($online) {
                    $q->whereNotNull('went_online_at');
                } else {
                    $q->whereNull('went_online_at');
                }
            });
        }

        // Search by name or email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('email', 'ILIKE', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $drivers = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $drivers->map(function ($driver) {
                return $this->formatDriverResponse($driver);
            }),
            'meta' => [
                'current_page' => $drivers->currentPage(),
                'last_page' => $drivers->lastPage(),
                'per_page' => $drivers->perPage(),
                'total' => $drivers->total(),
            ],
        ]);
    }

    /**
     * GET /api/admin/drivers/{id}
     * Get detailed driver information
     */
    public function getDriver(int $id): JsonResponse
    {
        $driver = User::with(['driverProfile', 'driverRides', 'ratingsReceived'])
            ->whereHas('driverProfile')
            ->findOrFail($id);

        $stats = [
            'total_rides' => $driver->driverRides()->where('status', 'completed')->count(),
            'total_earnings' => $driver->driverRides()->where('status', 'completed')->sum('actual_fare_rp'),
            'average_rating' => $driver->driverProfile->rating_average ?? 5.0,
            'rating_count' => $driver->driverProfile->rating_count ?? 0,
            'acceptance_rate' => $this->calculateAcceptanceRate($driver),
            'cancellation_rate' => $this->calculateCancellationRate($driver),
            'recent_rides' => $driver->driverRides()
                ->with(['rider', 'pickupLocation', 'destinationLocation'])
                ->latest()
                ->limit(10)
                ->get()
                ->map(function ($ride) {
                    return [
                        'id' => $ride->id,
                        'rider_name' => $ride->rider->name,
                        'pickup' => $ride->pickupLocation->name,
                        'destination' => $ride->destinationLocation->name,
                        'fare' => $ride->actual_fare_rp,
                        'status' => $ride->status,
                        'completed_at' => $ride->dropoff_time?->toISOString(),
                    ];
                }),
        ];

        return response()->json([
            'success' => true,
            'data' => array_merge($this->formatDriverResponse($driver), ['stats' => $stats]),
        ]);
    }

    /**
     * POST /api/admin/drivers/{id}/suspend
     * Suspend or unsuspend a driver
     */
    public function suspendDriver(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'suspended' => 'required|boolean',
            'reason' => 'nullable|string|max:500',
        ]);

        $driver = User::whereHas('driverProfile')->findOrFail($id);

        $driver->update([
            'is_active' => ! $request->suspended,
        ]);

        // If suspending, force driver offline
        if ($request->suspended && $driver->driverProfile) {
            $driver->driverProfile->update(['went_online_at' => null]);
        }

        Log::info('Driver suspension status updated', [
            'driver_id' => $id,
            'suspended' => $request->suspended,
            'reason' => $request->reason,
            'admin_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => $request->suspended ? 'Driver suspended successfully' : 'Driver unsuspended successfully',
            'data' => $this->formatDriverResponse($driver->fresh(['driverProfile'])),
        ]);
    }

    // ==========================================================================
    // RIDER MANAGEMENT
    // ==========================================================================

    /**
     * GET /api/admin/riders
     * List all riders with filtering and pagination
     */
    public function listRiders(Request $request): JsonResponse
    {
        $query = User::where('is_active', true)
            ->whereIn('role', ['rider', 'both', 'admin']);

        // Search by name or email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('email', 'ILIKE', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $riders = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $riders->map(function ($rider) {
                return $this->formatRiderResponse($rider);
            }),
            'meta' => [
                'current_page' => $riders->currentPage(),
                'last_page' => $riders->lastPage(),
                'per_page' => $riders->perPage(),
                'total' => $riders->total(),
            ],
        ]);
    }

    /**
     * GET /api/admin/riders/{id}
     * Get detailed rider information
     */
    public function getRider(int $id): JsonResponse
    {
        $rider = User::with(['riderRides', 'ratingsGiven'])->findOrFail($id);

        $stats = [
            'total_rides' => $rider->riderRides()->where('status', 'completed')->count(),
            'total_spent' => $rider->riderRides()->where('status', 'completed')->sum('actual_fare_rp'),
            'average_rating_given' => $rider->ratingsGiven()->avg('rating') ?? 0,
            'recent_rides' => $rider->riderRides()
                ->with(['driver', 'pickupLocation', 'destinationLocation'])
                ->latest()
                ->limit(10)
                ->get()
                ->map(function ($ride) {
                    return [
                        'id' => $ride->id,
                        'driver_name' => $ride->driver->name,
                        'pickup' => $ride->pickupLocation->name,
                        'destination' => $ride->destinationLocation->name,
                        'fare' => $ride->actual_fare_rp,
                        'status' => $ride->status,
                        'completed_at' => $ride->dropoff_time?->toISOString(),
                    ];
                }),
        ];

        return response()->json([
            'success' => true,
            'data' => array_merge($this->formatRiderResponse($rider), ['stats' => $stats]),
        ]);
    }

    /**
     * POST /api/admin/riders/{id}/suspend
     * Suspend or unsuspend a rider
     */
    public function suspendRider(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'suspended' => 'required|boolean',
            'reason' => 'nullable|string|max:500',
        ]);

        $rider = User::findOrFail($id);

        $rider->update([
            'is_active' => ! $request->suspended,
        ]);

        Log::info('Rider suspension status updated', [
            'rider_id' => $id,
            'suspended' => $request->suspended,
            'reason' => $request->reason,
            'admin_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => $request->suspended ? 'Rider suspended successfully' : 'Rider unsuspended successfully',
            'data' => $this->formatRiderResponse($rider),
        ]);
    }

    // ==========================================================================
    // ANALYTICS & STATISTICS
    // ==========================================================================

    /**
     * GET /api/admin/analytics/overview
     * Get platform overview statistics
     */
    public function getOverview(Request $request): JsonResponse
    {
        $dateFrom = $request->get('from', Carbon::now()->subDays(30)->toDateString());
        $dateTo = $request->get('to', Carbon::now()->toDateString());

        $stats = [
            // User stats
            'total_users' => User::count(),
            'total_riders' => User::whereIn('role', ['rider', 'both'])->count(),
            'total_drivers' => User::whereHas('driverProfile')->count(),
            'active_users' => User::where('is_active', true)->count(),

            // Ride stats
            'total_rides' => Ride::count(),
            'completed_rides' => Ride::where('status', 'completed')->count(),
            'cancelled_rides' => Ride::where('status', 'cancelled')->count(),
            'rides_in_period' => Ride::whereBetween('created_at', [$dateFrom, $dateTo])->count(),

            // Revenue stats
            'total_revenue' => Ride::where('status', 'completed')->sum('actual_fare_rp'),
            'revenue_in_period' => Ride::where('status', 'completed')
                ->whereBetween('dropoff_time', [$dateFrom, $dateTo])
                ->sum('actual_fare_rp'),

            // Current activity
            'online_drivers' => DriverProfile::whereNotNull('went_online_at')->count(),
            'active_rides' => Ride::whereIn('status', ['accepted', 'driver_arrived', 'in_progress'])->count(),
            'pending_requests' => RideRequest::where('status', 'pending')->count(),

            // Cache stats (from Phase 1!)
            'route_cache' => RouteCache::getCacheStats(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * GET /api/admin/analytics/rides
     * Get ride statistics with filters
     */
    public function getRideAnalytics(Request $request): JsonResponse
    {
        $dateFrom = $request->get('from', Carbon::now()->subDays(30)->toDateString());
        $dateTo = $request->get('to', Carbon::now()->toDateString());

        // Daily ride counts
        $dailyRides = Ride::selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(actual_fare_rp) as revenue')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->where('status', 'completed')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Rides by status
        $ridesByStatus = Ride::selectRaw('status, COUNT(*) as count')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');

        // Average metrics
        $avgStats = Ride::where('status', 'completed')
            ->whereBetween('dropoff_time', [$dateFrom, $dateTo])
            ->selectRaw('
                AVG(actual_fare_rp) as avg_fare,
                AVG(actual_distance_km) as avg_distance,
                AVG(actual_duration_minutes) as avg_duration
            ')
            ->first();

        $totalRides = $ridesByStatus->sum();

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_rides' => $totalRides,
                    'avg_fare' => round($avgStats->avg_fare ?? 0, 2),
                    'avg_distance' => round($avgStats->avg_distance ?? 0, 2),
                    'avg_duration' => round($avgStats->avg_duration ?? 0, 2),
                ],
                'daily_rides' => $dailyRides,
                'rides_by_status' => $ridesByStatus,
            ],
        ]);
    }

    /**
     * GET /api/admin/analytics/popular-routes
     * Get most popular routes (using RouteCache data!)
     */
    public function getPopularRoutes(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);

        // Get route cache popular routes
        $cacheRoutes = app(\App\Services\RouteCacheService::class)->getPopularRoutes($limit);

        // Also get actual ride counts
        $rideRoutes = Ride::selectRaw('pickup_location_id, destination_location_id, COUNT(*) as ride_count')
            ->where('status', 'completed')
            ->with(['pickupLocation', 'destinationLocation'])
            ->groupBy('pickup_location_id', 'destination_location_id')
            ->orderByDesc('ride_count')
            ->limit($limit)
            ->get()
            ->map(function ($route) {
                return [
                    'pickup' => $route->pickupLocation->name,
                    'destination' => $route->destinationLocation->name,
                    'ride_count' => $route->ride_count,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'cached_routes' => $cacheRoutes,
                'actual_rides' => $rideRoutes,
            ],
        ]);
    }

    /**
     * GET /api/admin/analytics/driver-performance
     * Get driver performance metrics
     */
    public function getDriverPerformance(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);
        $sortBy = $request->get('sort_by', 'total_rides'); // total_rides, rating, earnings

        $query = User::with('driverProfile')
            ->whereHas('driverProfile')
            ->withCount(['driverRides as total_rides' => function ($q) {
                $q->where('status', 'completed');
            }])
            ->withSum(['driverRides as total_earnings' => function ($q) {
                $q->where('status', 'completed');
            }], 'actual_fare_rp');

        if ($sortBy === 'rating') {
            $query->join('driver_profiles', 'users.id', '=', 'driver_profiles.user_id')
                ->orderByDesc('driver_profiles.rating_average');
        } elseif ($sortBy === 'earnings') {
            $query->orderByDesc('total_earnings');
        } else {
            $query->orderByDesc('total_rides');
        }

        $drivers = $query->limit($limit)->get()->map(function ($driver) {
            return [
                'id' => $driver->id,
                'name' => $driver->name,
                'total_rides' => $driver->total_rides ?? 0,
                'total_earnings' => $driver->total_earnings ?? 0,
                'rating' => $driver->driverProfile->rating_average ?? 5.0,
                'rating_count' => $driver->driverProfile->rating_count ?? 0,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $drivers,
        ]);
    }

    // ==========================================================================
    // REAL-TIME MONITORING
    // ==========================================================================

    /**
     * GET /api/admin/monitoring/active-rides
     * Get all currently active rides
     */
    public function getActiveRides(): JsonResponse
    {
        $activeRides = Ride::whereIn('status', ['accepted', 'driver_arrived', 'in_progress'])
            ->with(['driver', 'rider', 'pickupLocation', 'destinationLocation'])
            ->get()
            ->map(function ($ride) {
                return [
                    'id' => $ride->id,
                    'driver' => [
                        'id' => $ride->driver->id,
                        'name' => $ride->driver->name,
                    ],
                    'rider' => [
                        'id' => $ride->rider->id,
                        'name' => $ride->rider->name,
                    ],
                    'pickup' => $ride->pickupLocation->name,
                    'destination' => $ride->destinationLocation->name,
                    'status' => $ride->status,
                    'fare' => $ride->estimated_fare_rp,
                    'accepted_at' => $ride->driver_accepted_at?->toISOString(),
                    'duration_minutes' => $ride->driver_accepted_at ?
                        Carbon::now()->diffInMinutes($ride->driver_accepted_at) : 0,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $activeRides,
            'count' => $activeRides->count(),
        ]);
    }

    /**
     * GET /api/admin/monitoring/online-drivers
     * Get all currently online drivers
     */
    public function getOnlineDrivers(): JsonResponse
    {
        $onlineDrivers = User::with('driverProfile')
            ->whereHas('driverProfile', function ($q) {
                $q->whereNotNull('went_online_at');
            })
            ->get()
            ->map(function ($driver) {
                return [
                    'id' => $driver->id,
                    'name' => $driver->name,
                    'went_online_at' => $driver->driverProfile->went_online_at?->toISOString(),
                    'online_duration_minutes' => $driver->driverProfile->went_online_at ?
                        Carbon::now()->diffInMinutes($driver->driverProfile->went_online_at) : 0,
                    'current_location' => [
                        'latitude' => $driver->driverProfile->current_location?->latitude,
                        'longitude' => $driver->driverProfile->current_location?->longitude,
                    ],
                    'has_active_ride' => Ride::where('driver_id', $driver->id)
                        ->whereIn('status', ['accepted', 'driver_arrived', 'in_progress'])
                        ->exists(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $onlineDrivers,
            'count' => $onlineDrivers->count(),
        ]);
    }

    /**
     * GET /api/admin/monitoring/pending-requests
     * Get all pending ride requests
     */
    public function getPendingRequests(): JsonResponse
    {
        $pendingRequests = RideRequest::where('status', 'pending')
            ->with(['rider', 'pickupLocation', 'destinationLocation'])
            ->get()
            ->map(function ($request) {
                return [
                    'id' => $request->id,
                    'rider' => [
                        'id' => $request->rider->id,
                        'name' => $request->rider->name,
                    ],
                    'pickup' => $request->pickupLocation->name,
                    'destination' => $request->destinationLocation->name,
                    'passenger_count' => $request->passenger_count,
                    'estimated_fare' => $request->estimated_fare_rp,
                    'created_at' => $request->created_at->toISOString(),
                    'waiting_time_minutes' => Carbon::now()->diffInMinutes($request->created_at),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $pendingRequests,
            'count' => $pendingRequests->count(),
        ]);
    }

    /**
     * DELETE /api/admin/monitoring/requests/{id}
     * Force cancel a pending ride request
     */
    public function cancelRequest(int $requestId): JsonResponse
    {
        $request = RideRequest::find($requestId);

        if (! $request) {
            return response()->json([
                'success' => false,
                'message' => 'Ride request not found.',
            ], 404);
        }

        if ($request->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Can only cancel pending requests.',
            ], 400);
        }

        $request->update(['status' => 'cancelled']);

        return response()->json([
            'success' => true,
            'message' => 'Ride request cancelled by admin.',
        ]);
    }

    /**
     * POST /api/admin/monitoring/rides/{id}/cancel
     * Force cancel an active ride
     */
    public function cancelRide(int $rideId): JsonResponse
    {
        $ride = Ride::find($rideId);

        if (! $ride) {
            return response()->json([
                'success' => false,
                'message' => 'Ride not found.',
            ], 404);
        }

        if (! in_array($ride->status, ['accepted', 'driver_arrived', 'in_progress'])) {
            return response()->json([
                'success' => false,
                'message' => 'Can only cancel active rides.',
            ], 400);
        }

        $ride->update([
            'status' => 'cancelled',
            'dropoff_time' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ride cancelled by admin.',
        ]);
    }

    /**
     * POST /api/admin/monitoring/rides/{id}/complete
     * Force complete an active ride
     */
    public function completeRide(int $rideId): JsonResponse
    {
        $ride = Ride::find($rideId);

        if (! $ride) {
            return response()->json([
                'success' => false,
                'message' => 'Ride not found.',
            ], 404);
        }

        if (! in_array($ride->status, ['accepted', 'driver_arrived', 'in_progress'])) {
            return response()->json([
                'success' => false,
                'message' => 'Can only complete active rides.',
            ], 400);
        }

        $ride->update([
            'status' => 'completed',
            'dropoff_time' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ride marked as completed by admin.',
        ]);
    }

    // ==========================================================================
    // HELPER METHODS
    // ==========================================================================

    private function formatDriverResponse(User $driver): array
    {
        return [
            'id' => $driver->id,
            'name' => $driver->name,
            'email' => $driver->email,
            'phone' => $driver->phone_number,
            'is_active' => $driver->is_active,
            'role' => $driver->role,
            'kyc_status' => $this->getKycStatus($driver->driverProfile),
            'vehicle_type' => $driver->driverProfile->vehicle_type ?? null,
            'vehicle_plate' => $driver->driverProfile->vehicle_plate ?? null,
            'vehicle_color' => $driver->driverProfile->vehicle_color ?? null,
            'ktm_url' => $driver->driverProfile->ktm_url ?? null,
            'student_email' => $driver->driverProfile->student_email ?? null,
            'email_verified_at' => $driver->driverProfile->email_verified_at?->toISOString(),
            'rating' => $driver->driverProfile->rating_average ?? 5.0,
            'rating_count' => $driver->driverProfile->rating_count ?? 0,
            'is_online' => $driver->driverProfile->went_online_at !== null,
            'went_online_at' => $driver->driverProfile->went_online_at?->toISOString(),
            'created_at' => $driver->created_at->toISOString(),
        ];
    }

    private function formatRiderResponse(User $rider): array
    {
        return [
            'id' => $rider->id,
            'name' => $rider->name,
            'email' => $rider->email,
            'phone' => $rider->phone_number,
            'is_active' => $rider->is_active,
            'role' => $rider->role,
            'total_rides' => $rider->riderRides()->where('status', 'completed')->count(),
            'created_at' => $rider->created_at->toISOString(),
        ];
    }

    private function calculateAcceptanceRate(User $driver): float
    {
        $totalOffers = Ride::where('driver_id', $driver->id)->count();
        $accepted = Ride::where('driver_id', $driver->id)
            ->whereIn('status', ['accepted', 'driver_arrived', 'in_progress', 'completed'])
            ->count();

        return $totalOffers > 0 ? round(($accepted / $totalOffers) * 100, 2) : 100.0;
    }

    private function calculateCancellationRate(User $driver): float
    {
        $totalRides = Ride::where('driver_id', $driver->id)->count();
        $cancelled = Ride::where('driver_id', $driver->id)
            ->where('status', 'cancelled')
            ->count();

        return $totalRides > 0 ? round(($cancelled / $totalRides) * 100, 2) : 0.0;
    }

    /**
     * Determine KYC status from driver profile verification fields
     */
    private function getKycStatus($driverProfile): string
    {
        if (! $driverProfile) {
            return 'pending';
        }

        if ($driverProfile->is_verified && $driverProfile->email_verified_at) {
            return 'approved';
        }

        if ($driverProfile->email_verified_at && ! $driverProfile->is_verified) {
            return 'email_verified';
        }

        return 'pending';
    }

    // ==========================================================================
    // RIDE MANAGEMENT (Admin Override)
    // ==========================================================================

    /**
     * GET /api/admin/rides/{id}
     * View detailed ride information for admin intervention
     */
    public function getRide(Ride $ride): JsonResponse
    {
        $ride->load(['rider', 'driver', 'pickupLocation', 'destinationLocation', 'ratings']);

        return response()->json([
            'success' => true,
            'data' => new RideResource($ride),
        ]);
    }

    /**
     * POST /api/admin/rides/{id}/force-status
     * Force update ride status (admin override for stuck rides)
     */
    public function forceUpdateStatus(Request $request, Ride $ride): JsonResponse
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:completed,cancelled',
            'reason' => 'required|string|min:10|max:500',
            'notify_users' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $admin = $request->user();
        $previousStatus = $ride->status;
        $newStatus = $request->status;
        $reason = $request->reason;
        $notifyUsers = $request->boolean('notify_users', true);

        try {
            DB::beginTransaction();

            // Update ride status
            $ride->update(['status' => $newStatus]);

            // Create audit log
            AdminAuditLog::create([
                'admin_id' => $admin->id,
                'action_type' => 'force_ride_status',
                'target_type' => Ride::class,
                'target_id' => $ride->id,
                'changes' => [
                    'old_status' => $previousStatus,
                    'new_status' => $newStatus,
                ],
                'reason' => $reason,
                'metadata' => [
                    'ride_id' => $ride->id,
                    'rider_id' => $ride->rider_id,
                    'driver_id' => $ride->driver_id,
                    'notify_users' => $notifyUsers,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Broadcast status update with admin override flag
            broadcast(new RideStatusUpdated(
                $ride,
                $previousStatus,
                'admin',
                true, // adminOverride
                $reason // adminReason
            ));

            // Send notifications if requested
            if ($notifyUsers) {
                $notificationService = app(NotificationService::class);
                $message = "Your ride status has been updated by admin: {$reason}";

                try {
                    $notificationService->sendToUser($ride->rider_id, 'Ride Status Updated', $message);
                    $notificationService->sendToUser($ride->driver_id, 'Ride Status Updated', $message);
                } catch (\Exception $e) {
                    Log::warning('Failed to send admin override notifications', [
                        'error' => $e->getMessage(),
                        'ride_id' => $ride->id,
                    ]);
                }
            }

            DB::commit();

            $ride->load(['rider', 'driver', 'pickupLocation', 'destinationLocation']);

            return response()->json([
                'success' => true,
                'message' => 'Ride status force updated successfully',
                'data' => new RideResource($ride),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Admin force status update failed', [
                'error' => $e->getMessage(),
                'ride_id' => $ride->id,
                'admin_id' => $admin->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update ride status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/admin/rides/stuck
     * List potentially stuck rides (in_progress for > 2 hours, etc.)
     */
    public function listStuckRides(Request $request): JsonResponse
    {
        $hoursThreshold = $request->get('hours', 2);
        $thresholdTime = Carbon::now()->subHours($hoursThreshold);

        // Find rides that are stuck in non-terminal states
        $stuckRides = Ride::with(['rider', 'driver', 'pickupLocation', 'destinationLocation'])
            ->whereIn('status', ['matched', 'accepted', 'driver_arrived', 'in_progress'])
            ->where('updated_at', '<', $thresholdTime)
            ->orderBy('updated_at', 'asc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => RideResource::collection($stuckRides),
            'meta' => [
                'current_page' => $stuckRides->currentPage(),
                'last_page' => $stuckRides->lastPage(),
                'per_page' => $stuckRides->perPage(),
                'total' => $stuckRides->total(),
                'threshold_hours' => $hoursThreshold,
            ],
        ]);
    }
}
