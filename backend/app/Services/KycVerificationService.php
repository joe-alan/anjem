<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\DriverProfile;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class KycVerificationService
{
    /**
     * Validate student email domain against whitelisted domains
     */
    public function isValidStudentEmail(string $email): bool
    {
        $allowedDomains = config('app.allowed_student_email_domains', []);

        if (empty($allowedDomains)) {
            return false;
        }

        foreach ($allowedDomains as $domain) {
            if (Str::endsWith($email, '@'.$domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get allowed domains formatted for display in error messages
     */
    public function getAllowedDomainsDisplay(): string
    {
        $domains = config('app.allowed_student_email_domains', []);

        return implode(', ', array_map(fn ($d) => '@'.$d, $domains));
    }

    /**
     * Check if email is available for registration
     * Returns true if email is not already registered
     * Excludes current user's email if userId is provided (for updates)
     */
    public function isEmailAvailable(string $email, ?int $userId = null): bool
    {
        $query = DriverProfile::where('student_email', $email);

        // Exclude current user if updating their own KYC
        if ($userId) {
            $query->where('user_id', '!=', $userId);
        }

        return ! $query->exists();
    }

    /**
     * Generate a 6-digit verification code
     */
    public function generateCode(): string
    {
        return str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Create and send verification code to email
     */
    public function sendVerificationCode(string $email): VerificationCode
    {
        // Invalidate any existing codes for this email
        VerificationCode::where('email', $email)
            ->whereNull('verified_at')
            ->delete();

        // Generate new code
        $code = $this->generateCode();
        $expiresAt = now()->addMinutes(10);

        // Store the code
        $verificationCode = VerificationCode::create([
            'email' => $email,
            'code' => $code,
            'expires_at' => $expiresAt,
        ]);

        // Send email with code
        Mail::send('emails.verification-code', [
            'code' => $code,
            'expiresInMinutes' => 10,
        ], function ($message) use ($email) {
            $message->to($email)
                ->subject('Anjem - Email Verification Code');
        });

        return $verificationCode;
    }

    /**
     * Verify the code and update driver profile
     */
    public function verifyCode(string $email, string $code): bool
    {
        $verificationCode = VerificationCode::where('email', $email)
            ->where('code', $code)
            ->valid()
            ->first();

        if (! $verificationCode) {
            return false;
        }

        // Mark code as verified
        $verificationCode->markAsVerified();

        // Update driver profile if exists — email OTP only sets email_verified_at.
        // is_verified remains false until an admin explicitly approves the KYC.
        $driverProfile = DriverProfile::where('student_email', $email)->first();
        if ($driverProfile) {
            $driverProfile->update([
                'email_verified_at' => now(),
            ]);
        }

        return true;
    }

    /**
     * Store KTM photo and return the path
     */
    public function storeKtmPhoto($file, int $userId): string
    {
        $filename = 'ktm_'.$userId.'_'.time().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('ktm_photos', $filename, 'public');

        return '/storage/'.$path;
    }

    /**
     * Store profile photo and return the path
     */
    public function storeProfilePhoto($file, int $userId): string
    {
        $filename = 'avatar_'.$userId.'_'.time().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('avatars', $filename, 'public');

        return '/storage/'.$path;
    }

    /**
     * Create or update driver profile with KYC data
     */
    public function submitKycData(
        int $userId,
        string $studentEmail,
        string $studentId,
        string $studentName,
        string $vehicleType,
        string $vehiclePlate,
        string $vehicleColor,
        ?string $ktmUrl = null,
        ?string $phoneNumber = null,
        ?string $profilePhotoUrl = null
    ): DriverProfile {
        return DB::transaction(function () use (
            $userId, $studentEmail, $studentId, $studentName,
            $vehicleType, $vehiclePlate, $vehicleColor, $ktmUrl,
            $phoneNumber, $profilePhotoUrl
        ) {
            $profile = DriverProfile::updateOrCreate(
                ['user_id' => $userId],
                [
                    'student_email' => $studentEmail,
                    'student_id' => $studentId,
                    'student_name' => $studentName,
                    'vehicle_type' => $vehicleType,
                    'vehicle_plate' => $vehiclePlate,
                    'vehicle_color' => $vehicleColor,
                    'ktm_url' => $ktmUrl,
                    'is_verified' => false,
                ]
            );

            // Store phone and profile photo on the users table
            $userUpdates = [];
            if ($phoneNumber !== null) {
                $userUpdates['phone_number'] = $phoneNumber;
            }
            if ($profilePhotoUrl !== null) {
                $userUpdates['profile_picture'] = $profilePhotoUrl;
            }
            if (! empty($userUpdates)) {
                User::where('id', $userId)->update($userUpdates);
            }

            return $profile;
        });
    }

    /**
     * Get KYC status for a driver
     */
    public function getKycStatus(int $userId): array
    {
        $driverProfile = DriverProfile::where('user_id', $userId)->first();

        if (! $driverProfile) {
            return [
                'kyc_submitted' => false,
                'email_verified' => false,
                'is_verified' => false,
            ];
        }

        $kycSubmitted = ! empty($driverProfile->student_email);

        // Fetch rejection reason from audit log — only relevant when not submitted
        // (i.e., after admin clears the KYC data on rejection).
        $rejectionReason = null;
        if (! $kycSubmitted) {
            $rejectionReason = AdminAuditLog::where('action_type', 'kyc_reject')
                ->where('target_id', $driverProfile->id)
                ->latest()
                ->value('reason');
        }

        // Fetch latest suspend reason from audit log.
        $suspendReason = AdminAuditLog::where('action_type', 'driver_suspend')
            ->where('target_id', $userId)
            ->latest()
            ->value('reason');

        $user = User::find($userId);

        return [
            'kyc_submitted'    => $kycSubmitted,
            'email_verified'   => $driverProfile->email_verified_at !== null,
            'is_verified'      => $driverProfile->is_verified,
            'student_email'    => $driverProfile->student_email,
            'student_id'       => $driverProfile->student_id,
            'student_name'     => $driverProfile->student_name,
            'phone_number'     => $user?->phone_number,
            'vehicle_type'     => $driverProfile->vehicle_type,
            'vehicle_plate'    => $driverProfile->vehicle_plate,
            'vehicle_color'    => $driverProfile->vehicle_color,
            'ktm_url'          => $driverProfile->ktm_url,
            'profile_photo_url' => $user?->profile_picture,
            'rejection_reason' => $rejectionReason,
            'suspend_reason'   => $suspendReason,
        ];
    }

    /**
     * Revoke KYC data for a driver — nullifies PII fields, preserves stats.
     */
    public function revokeKycData(int $userId): bool
    {
        $profile = DriverProfile::where('user_id', $userId)->first();

        if (! $profile) {
            return false;
        }

        // Delete KTM photo from storage
        if ($profile->ktm_url) {
            $storagePath = str_replace('/storage/', '', $profile->ktm_url);
            \Storage::disk('public')->delete($storagePath);
        }

        // Delete profile photo from storage
        $user = User::find($userId);
        if ($user?->profile_picture && str_starts_with($user->profile_picture, '/storage/')) {
            $storagePath = str_replace('/storage/', '', $user->profile_picture);
            \Storage::disk('public')->delete($storagePath);
        }

        // Nullify KYC PII fields, reset verification
        $profile->update([
            'student_email'    => null,
            'student_id'       => null,
            'student_name'     => null,
            'vehicle_type'     => null,
            'vehicle_plate'    => null,
            'vehicle_color'    => null,
            'ktm_url'          => null,
            'email_verified_at' => null,
            'is_verified'      => false,
        ]);

        // Clear phone and profile picture from user record
        if ($user) {
            $user->update([
                'phone_number'    => null,
                'profile_picture' => null,
            ]);
        }

        return true;
    }

    /**
     * Cleanup expired verification codes (can be run as scheduled task)
     */
    public function cleanupExpiredCodes(): int
    {
        return VerificationCode::expired()->delete();
    }
}
