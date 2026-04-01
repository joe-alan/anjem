<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    /**
     * Update the authenticated user's profile.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone_number' => 'nullable|string|max:20',
        ]);

        $user = $request->user();
        $user->update($validated);

        return response()->json([
            'success' => true,
            'data' => new UserResource($user->load('driverProfile')),
        ]);
    }

    /**
     * Upload a new avatar for the authenticated user.
     */
    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|max:2048',
        ]);

        $user = $request->user();

        // Delete old avatar if it exists and is a local file
        if ($user->profile_picture) {
            $oldPath = str_replace('/storage/', '', $user->profile_picture);
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['profile_picture' => '/storage/' . $path]);

        return response()->json([
            'success' => true,
            'data' => new UserResource($user->load('driverProfile')),
        ]);
    }

    /**
     * Soft-delete the authenticated user's account.
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        // Reject if user has an active ride (as rider or driver)
        $hasActiveRide = $user->rideRequests()
            ->whereIn('status', ['pending', 'matched'])
            ->exists();

        if (!$hasActiveRide && $user->isDriver()) {
            $hasActiveRide = $user->driverRides()
                ->whereIn('status', ['accepted', 'arriving', 'arrived', 'in_progress'])
                ->exists();
        }

        if ($hasActiveRide) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete account while you have an active ride.',
            ], 422);
        }

        // If driver is online, go offline
        if ($user->isDriverOnline()) {
            $user->driverProfile->update(['went_online_at' => null]);
        }

        // Revoke all tokens and clear FCM token
        $user->tokens()->delete();
        $user->update(['fcm_token' => null]);

        // Soft-delete
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Account deleted successfully.',
        ]);
    }
}
