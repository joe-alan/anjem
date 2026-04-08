<?php

namespace App\Services;

use App\Exceptions\RateLimitExceededException;
use App\Models\AdminAuditLog;
use App\Models\DriverProfile;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class KycVerificationService
{
    /** Seconds a user must wait between resend requests */
    public const RESEND_COOLDOWN_SECONDS = 60;

    /** Max verification codes a non-admin user can request per hour */
    public const MAX_SENDS_PER_HOUR = 5;

    public function __construct(
        private FirebaseStorageService $storageService,
        private ImageCompressionService $compressionService,
    ) {}

    /**
     * Validate that the email belongs to an academic institution (*.ac.id).
     */
    public function isValidStudentEmail(string $email): bool
    {
        return (bool) preg_match('/@.+\.ac\.id$/i', $email);
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
     * Get remaining cooldown seconds for an email.
     * Returns 0 if no cooldown is active.
     */
    public function getResendCooldown(string $email): int
    {
        $latest = VerificationCode::where('email', $email)
            ->whereNull('verified_at')
            ->latest()
            ->first();

        if (! $latest) {
            return 0;
        }

        $elapsed = now()->diffInSeconds($latest->created_at, absolute: true);
        $remaining = self::RESEND_COOLDOWN_SECONDS - $elapsed;

        return max(0, (int) $remaining);
    }

    /**
     * Get how many sends a user has left this hour.
     * Returns null (unlimited) for admins.
     */
    public function getHourlyRemaining(int $userId): ?int
    {
        $user = User::find($userId);
        if ($user?->is_admin) {
            return null; // unlimited
        }

        $sentThisHour = VerificationCode::where('user_id', $userId)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        return max(0, self::MAX_SENDS_PER_HOUR - $sentThisHour);
    }

    /**
     * Create and send verification code to email
     */
    public function sendVerificationCode(string $email, int $userId): VerificationCode
    {
        $cooldown = $this->getResendCooldown($email);
        if ($cooldown > 0) {
            throw new RateLimitExceededException("Please wait {$cooldown} seconds before requesting a new code.");
        }

        // Hourly cap (skip for admins)
        $remaining = $this->getHourlyRemaining($userId);
        if ($remaining !== null && $remaining <= 0) {
            throw new RateLimitExceededException('You have reached the maximum number of verification emails this hour. Please try again later.');
        }

        // Invalidate any existing codes for this user
        VerificationCode::where('user_id', $userId)
            ->whereNull('verified_at')
            ->delete();

        // Generate new code
        $code = $this->generateCode();
        $expiresAt = now()->addMinutes(10);

        // Store the code
        $verificationCode = VerificationCode::create([
            'user_id' => $userId,
            'email' => $email,
            'code' => $code,
            'expires_at' => $expiresAt,
        ]);

        // Send email with code (queued via Redis)
        Mail::queue('emails.verification-code', [
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
     * Store KTM photo: compress and upload to Firebase Storage.
     */
    public function storeKtmPhoto($file, int $userId): string
    {
        $compressed = $this->compressionService->compress(
            $file->getRealPath(), 1920, 1080, 80
        );
        $objectPath = 'ktm_photos/ktm_' . $userId . '_' . time() . '.jpg';

        return $this->storageService->upload($objectPath, $compressed, 'image/jpeg');
    }

    /**
     * Store profile photo: compress and upload to Firebase Storage.
     */
    public function storeProfilePhoto($file, int $userId): string
    {
        $compressed = $this->compressionService->compress(
            $file->getRealPath(), 512, 512, 80
        );
        $objectPath = 'avatars/avatar_' . $userId . '_' . time() . '.jpg';

        return $this->storageService->upload($objectPath, $compressed, 'image/jpeg');
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

        // Delete KTM photo from Firebase Storage
        if ($profile->ktm_url) {
            $objectPath = $this->storageService->extractObjectPath($profile->ktm_url);
            if ($objectPath) {
                $this->storageService->delete($objectPath);
            }
        }

        // Delete profile photo from Firebase Storage
        $user = User::find($userId);
        if ($user?->profile_picture) {
            $objectPath = $this->storageService->extractObjectPath($user->profile_picture);
            if ($objectPath) {
                $this->storageService->delete($objectPath);
            }
        }

        // Nullify KYC PII fields, reset verification
        // vehicle_type has a NOT NULL constraint — reset to default instead of null
        $profile->update([
            'student_email'    => null,
            'student_id'       => null,
            'student_name'     => null,
            'vehicle_type'     => 'motorcycle',
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
