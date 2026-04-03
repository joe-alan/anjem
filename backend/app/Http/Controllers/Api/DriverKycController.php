<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitKycRequest;
use App\Services\KycVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DriverKycController extends Controller
{
    public function __construct(
        private KycVerificationService $kycService
    ) {}

    /**
     * Submit KYC data for driver verification
     */
    public function submitKyc(SubmitKycRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            \Log::info('KYC submission started', [
                'user_id' => $user->id,
                'has_file' => $request->hasFile('ktm_photo'),
            ]);

            // Store KTM photo
            $ktmUrl = null;
            if ($request->hasFile('ktm_photo')) {
                $file = $request->file('ktm_photo');
                \Log::info('Processing KTM photo', [
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime' => $file->getMimeType(),
                ]);

                $ktmUrl = $this->kycService->storeKtmPhoto($file, $user->id);
                \Log::info('KTM photo stored', ['url' => $ktmUrl]);
            }

            // Submit KYC data
            $driverProfile = $this->kycService->submitKycData(
                $user->id,
                $request->student_email,
                $request->student_id,
                $request->student_name,
                $request->vehicle_type,
                $request->vehicle_plate,
                $request->vehicle_color,
                $ktmUrl
            );

            \Log::info('KYC submission successful', ['user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'KYC data submitted successfully. Please verify your student email.',
                'data' => [
                    'kyc_submitted' => true,
                    'email_verified' => false,
                    'is_verified' => false,
                    'student_email' => $driverProfile->student_email,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('KYC submission failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit KYC data',
                'error' => $e->getMessage(),
                'debug' => config('app.debug') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }

    /**
     * Check if student email is available for registration
     */
    public function checkEmailAvailability(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'student_email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $email = $request->student_email;

            // Validate email domain
            if (! $this->kycService->isValidStudentEmail($email)) {
                $domainsDisplay = $this->kycService->getAllowedDomainsDisplay();

                return response()->json([
                    'success' => false,
                    'available' => false,
                    'message' => "Email must be from one of: {$domainsDisplay}",
                    'reason' => 'invalid_domain',
                ], 200);  // Return 200 with available:false instead of error
            }

            // Check if email is already registered (excluding current user if updating)
            $userId = $request->user()?->id;
            $isAvailable = $this->kycService->isEmailAvailable($email, $userId);

            return response()->json([
                'success' => true,
                'available' => $isAvailable,
                'message' => $isAvailable
                    ? 'Email is available'
                    : 'This email is already registered',
                'reason' => $isAvailable ? null : 'already_registered',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check email availability',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send verification code to student email
     */
    public function sendVerificationCode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'student_email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $email = $request->student_email;

            // Validate email domain
            if (! $this->kycService->isValidStudentEmail($email)) {
                $domainsDisplay = $this->kycService->getAllowedDomainsDisplay();

                return response()->json([
                    'success' => false,
                    'message' => "Email must be from one of: {$domainsDisplay}",
                ], 400);
            }

            // Send verification code
            $verificationCode = $this->kycService->sendVerificationCode($email);

            return response()->json([
                'success' => true,
                'message' => 'Verification code sent to your student email',
                'data' => [
                    'expires_at' => $verificationCode->expires_at->toISOString(),
                    'expires_in_minutes' => 10,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification code',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify email with code
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'student_email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $verified = $this->kycService->verifyCode(
                $request->student_email,
                $request->code
            );

            if (! $verified) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired verification code',
                ], 400);
            }

            // Get updated KYC status
            $kycStatus = $this->kycService->getKycStatus($request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'Email verified successfully! You can now go online.',
                'data' => $kycStatus,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify email',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Revoke KYC data — deletes PII, resets to unverified
     */
    public function revokeKyc(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user->tokenCan('driver:go-online')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Driver permissions required',
                ], 403);
            }

            // Guard: driver must be offline
            $profile = \App\Models\DriverProfile::where('user_id', $user->id)->first();
            if ($profile && $profile->went_online_at !== null) {
                return response()->json([
                    'success' => false,
                    'message' => 'You must go offline before revoking KYC data',
                ], 409);
            }

            // Guard: no active ride
            $hasActiveRide = \App\Models\Ride::where('driver_id', $user->id)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->exists();
            if ($hasActiveRide) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot revoke KYC while you have an active ride',
                ], 409);
            }

            $revoked = $this->kycService->revokeKycData($user->id);

            if (! $revoked) {
                return response()->json([
                    'success' => false,
                    'message' => 'No KYC data found to revoke',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'KYC data revoked successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to revoke KYC data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get KYC status for current driver
     */
    public function getKycStatus(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Check driver permissions
            if (! $user->tokenCan('driver:go-online')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Driver permissions required',
                ], 403);
            }

            $kycStatus = $this->kycService->getKycStatus($user->id);

            return response()->json([
                'success' => true,
                'data' => $kycStatus,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get KYC status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
