<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlaceSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * PlaceController handles location search endpoints
 */
class PlaceController extends Controller
{
    private PlaceSearchService $placeSearchService;

    public function __construct(PlaceSearchService $placeSearchService)
    {
        $this->placeSearchService = $placeSearchService;
    }

    /**
     * Search for places (beacons + P2P destinations)
     */
    public function search(Request $request): JsonResponse
    {
        // Validate request
        // Query is optional when coordinates are provided (for nearby search)
        $validator = Validator::make($request->all(), [
            'q' => 'nullable|string|min:1|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'radius' => 'nullable|numeric|min:0.1|max:50',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = $request->input('q', ''); // Default to empty string for nearby search
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');
        $radius = $request->input('radius', 5.0); // Default 5km
        $limit = $request->input('limit', 10); // Default 10 results

        // Validation: Must have either query OR coordinates
        if (empty($query) && ($latitude === null || $longitude === null)) {
            return response()->json([
                'success' => false,
                'message' => 'Either search query (q) or location coordinates (latitude, longitude) must be provided',
            ], 422);
        }

        // Both latitude and longitude must be provided together
        if (($latitude !== null && $longitude === null) || ($latitude === null && $longitude !== null)) {
            return response()->json([
                'success' => false,
                'message' => 'Both latitude and longitude must be provided together',
            ], 422);
        }

        try {
            // Search for places
            $results = $this->placeSearchService->search(
                $query,
                $latitude ? (float) $latitude : null,
                $longitude ? (float) $longitude : null,
                (float) $radius,
                (int) $limit
            );

            return response()->json([
                'success' => true,
                'data' => $results,
                'meta' => [
                    'query' => $query,
                    'count' => count($results),
                    'has_proximity' => $latitude !== null && $longitude !== null,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search places',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
