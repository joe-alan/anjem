<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\RequestController;
use App\Http\Controllers\Api\RideController;
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

    // Authentication routes (no auth required)
    Route::prefix('auth')->group(function () {
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

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {

        // User profile
        Route::get('user', function (Request $request) {
            return response()->json([
                'success' => true,
                'data' => new \App\Http\Resources\UserResource($request->user()->load('driverProfile')),
            ]);
        });

        // Rider routes
        Route::prefix('requests')->group(function () {
            Route::get('estimates', [RequestController::class, 'getEstimates']);
            Route::post('/', [RequestController::class, 'store']);
            Route::get('{request}', [RequestController::class, 'show']);
            Route::patch('{request}/cancel', [RequestController::class, 'cancel']);
            Route::get('/', [RequestController::class, 'index']);
        });

        // Driver routes
        Route::prefix('driver')->group(function () {
            Route::post('online', [DriverController::class, 'goOnline']);
            Route::post('offline', [DriverController::class, 'goOffline']);
            Route::get('queue', [DriverController::class, 'getQueue']);
            Route::post('location', [DriverController::class, 'updateLocation']);
            Route::get('beacons', [DriverController::class, 'getAvailableBeacons']);
            Route::get('statistics', [DriverController::class, 'getStatistics']);
        });

        // Ride management
        Route::prefix('rides')->group(function () {
            Route::post('{request}/accept', [RideController::class, 'accept']);
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
                'data' => \App\Http\Resources\LocationResource::collection($locations),
            ]);
        });
    });
});
