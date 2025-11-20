<?php

namespace App\Services;

use App\Models\DriverProfile;
use App\Models\VerificationCode;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class KycVerificationService
{
    /**
     * Validate student email domain
     */
    public function isValidStudentEmail(string $email): bool
    {
        $allowedDomain = config('app.allowed_student_email_domain');

        if (! $allowedDomain) {
            return false;
        }

        return Str::endsWith($email, '@'.$allowedDomain);
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

        // Update driver profile if exists
        $driverProfile = DriverProfile::where('student_email', $email)->first();
        if ($driverProfile) {
            $driverProfile->update([
                'is_verified' => true,
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
        ?string $ktmUrl = null
    ): DriverProfile {
        return DriverProfile::updateOrCreate(
            ['user_id' => $userId],
            [
                'student_email' => $studentEmail,
                'student_id' => $studentId,
                'student_name' => $studentName,
                'vehicle_type' => $vehicleType,
                'vehicle_plate' => $vehiclePlate, // Fixed: was license_plate, should be vehicle_plate
                'vehicle_color' => $vehicleColor,
                'ktm_url' => $ktmUrl,
                'is_verified' => false, // Will be verified after email confirmation
            ]
        );
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

        return [
            'kyc_submitted' => ! empty($driverProfile->student_email),
            'email_verified' => $driverProfile->email_verified_at !== null,
            'is_verified' => $driverProfile->is_verified,
            'student_email' => $driverProfile->student_email,
            'student_id' => $driverProfile->student_id,
            'student_name' => $driverProfile->student_name,
            'vehicle_type' => $driverProfile->vehicle_type,
            'vehicle_plate' => $driverProfile->vehicle_plate, // Fixed: matches database column name
            'vehicle_color' => $driverProfile->vehicle_color,
            'ktm_url' => $driverProfile->ktm_url,
        ];
    }

    /**
     * Cleanup expired verification codes (can be run as scheduled task)
     */
    public function cleanupExpiredCodes(): int
    {
        return VerificationCode::expired()->delete();
    }
}
