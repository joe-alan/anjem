<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CreditController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\PlaceController;
use App\Http\Controllers\Api\RequestController;
use App\Http\Controllers\Api\RideController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// V1 API Routes
Route::prefix('v1')->group(function () {

    // Authentication routes (no auth required) - Strict rate limiting
    Route::prefix('auth')->middleware('throttle:10,1')->group(function () {
        Route::post('firebase', [AuthController::class, 'authenticateWithFirebase']);
        Route::get('google', [AuthController::class, 'googleRedirect']);
        Route::get('google/callback', [AuthController::class, 'googleCallback']);
        Route::post('refresh', [AuthController::class, 'refreshToken'])->middleware('auth:sanctum');
        Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
        Route::post('fcm-token', [AuthController::class, 'updateFcmToken'])->middleware('auth:sanctum');
    });

    // Health check
    Route::get('health', function () {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now(),
            'version' => '1.0.0',
        ]);
    });

    // Public place search endpoint (no auth required)
    Route::get('places/search', [PlaceController::class, 'search'])->middleware('throttle:60,1');

    // Resolve (find or create) a location — requires auth to prevent abuse
    Route::post('places/resolve', [PlaceController::class, 'resolve'])->middleware(['auth:sanctum', 'throttle:30,1']);

    // Protected routes - General rate limiting
    Route::middleware(['auth:sanctum', 'throttle:100,1'])->group(function () {

        // User profile
        Route::get('user', function (Request $request) {
            return response()->json([
                'success' => true,
                'data' => new \App\Http\Resources\UserResource($request->user()->load('driverProfile')),
            ]);
        });

        Route::patch('user', [UserController::class, 'update']);
        Route::post('user/avatar', [UserController::class, 'updateAvatar']);
        Route::delete('user', [UserController::class, 'destroy']);

        Route::get('session/resume', [SessionController::class, 'resume']);

        // Rider routes
        Route::prefix('requests')->group(function () {
            Route::get('estimates', [RequestController::class, 'getEstimates']);
            Route::post('/', [RequestController::class, 'store']);
            Route::get('{ride_request}', [RequestController::class, 'show']);
            Route::get('{ride_request}/nearby-drivers', [RequestController::class, 'nearbyDrivers']);
            Route::patch('{ride_request}/cancel', [RequestController::class, 'cancel']);
            Route::get('/', [RequestController::class, 'index']);
        });

        // Driver routes
        Route::prefix('driver')->group(function () {
            // KYC verification routes
            Route::prefix('kyc')->group(function () {
                Route::post('check-email', [\App\Http\Controllers\Api\DriverKycController::class, 'checkEmailAvailability']);
                Route::post('submit', [\App\Http\Controllers\Api\DriverKycController::class, 'submitKyc']);
                Route::post('send-code', [\App\Http\Controllers\Api\DriverKycController::class, 'sendVerificationCode']);
                Route::post('verify-email', [\App\Http\Controllers\Api\DriverKycController::class, 'verifyEmail']);
                Route::get('status', [\App\Http\Controllers\Api\DriverKycController::class, 'getKycStatus']);
            });

            Route::post('online', [DriverController::class, 'goOnline']);
            Route::post('offline', [DriverController::class, 'goOffline']);
            Route::get('queue-position', [DriverController::class, 'getQueuePosition']);
            Route::get('current-request', [DriverController::class, 'getCurrentRequest']);
            Route::patch('settings', [DriverController::class, 'updateSettings']);
            // Higher rate limit for location updates (real-time)
            Route::post('location', [DriverController::class, 'updateLocation'])->middleware('throttle:200,1');
            Route::get('statistics', [DriverController::class, 'getStatistics']);
        });

        Route::prefix('driver/credits')->group(function () {
            Route::get('balance',      [CreditController::class, 'getBalance']);
            Route::get('transactions', [CreditController::class, 'getTransactions']);
        });

        // Ride management
        Route::prefix('rides')->group(function () {
            Route::post('{rideRequest}/accept', [RideController::class, 'accept']);
            Route::post('{rideRequest}/decline', [RideController::class, 'decline']);
            Route::patch('{ride}/status', [RideController::class, 'updateStatus']);
            Route::post('{ride}/rate', [RideController::class, 'rate']);
            Route::get('{ride}', [RideController::class, 'show']);
            Route::get('/', [RideController::class, 'index']);
        });

        // Locations/Beacons (read-only for now)
        Route::get('locations', function () {
            $locations = \App\Models\Location::active()->get();

            return response()->json([
                'success' => true,
                // Use resolve() to flatten the resource collection and avoid double-nesting
                'data' => \App\Http\Resources\LocationResource::collection($locations)->resolve(),
            ]);
        });
    });
});

// Admin Dashboard Routes - Protected by auth:sanctum + admin middleware
Route::prefix('admin')->middleware(['auth:sanctum', 'admin', 'throttle:100,1'])->group(function () {

    // Driver Management
    Route::get('drivers', [AdminController::class, 'listDrivers']);
    Route::get('drivers/{id}', [AdminController::class, 'getDriver']);
    Route::post('drivers/{id}/suspend', [AdminController::class, 'suspendDriver']);
    Route::post('drivers/{id}/kyc/approve', [AdminController::class, 'approveKyc']);
    Route::post('drivers/{id}/kyc/reject', [AdminController::class, 'rejectKyc']);
    Route::post('drivers/{id}/credits/grant', [AdminController::class, 'grantCredits']);
    Route::post('drivers/{id}/credits/deduct', [AdminController::class, 'deductCredits']);
    Route::get('drivers/{id}/document', [AdminController::class, 'getDriverDocument']);

    // Rider Management
    Route::get('riders', [AdminController::class, 'listRiders']);
    Route::get('riders/{id}', [AdminController::class, 'getRider']);
    Route::post('riders/{id}/suspend', [AdminController::class, 'suspendRider']);

    // Analytics & Statistics
    Route::get('analytics/overview', [AdminController::class, 'getOverview']);
    Route::get('analytics/rides', [AdminController::class, 'getRideAnalytics']);
    Route::get('analytics/popular-routes', [AdminController::class, 'getPopularRoutes']);
    Route::get('analytics/driver-performance', [AdminController::class, 'getDriverPerformance']);

    // Real-time Monitoring
    Route::get('monitoring/active-rides', [AdminController::class, 'getActiveRides']);
    Route::get('monitoring/online-drivers', [AdminController::class, 'getOnlineDrivers']);
    Route::get('monitoring/pending-requests', [AdminController::class, 'getPendingRequests']);

    // Ride Management (Admin Override)
    Route::get('rides/stuck', [AdminController::class, 'listStuckRides']);
    Route::get('rides/{ride}', [AdminController::class, 'getRide']);
    Route::post('rides/{ride}/force-status', [AdminController::class, 'forceUpdateStatus']);

    // Admin Actions (Legacy - consider deprecating in favor of force-status)
    Route::delete('monitoring/requests/{id}', [AdminController::class, 'cancelRequest']);
    Route::post('monitoring/rides/{id}/cancel', [AdminController::class, 'cancelRide']);
    Route::post('monitoring/rides/{id}/complete', [AdminController::class, 'completeRide']);

    // Audit Logs
    Route::get('audit-logs', [AdminController::class, 'getAuditLogs']);
});
