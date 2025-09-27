<?php

namespace App\Services;

use App\Models\OtpVerification;
use Carbon\Carbon;
use Illuminate\Support\Str;

class OtpService
{
    public function generateOtp(string $email, string $deviceType, ?string $deviceId = null, ?string $ipAddress = null): OtpVerification
    {
        // Rate limiting: 5 requests per 30 minutes per IP
        $recentOtps = OtpVerification::where('ip_address', $ipAddress)
            ->where('created_at', '>=', Carbon::now()->subMinutes(30))
            ->count();

        if ($recentOtps >= 5) {
            throw new \Exception('Too many OTP requests. Please try again later.');
        }

        // Deactivate existing OTPs for this email
        OtpVerification::where('email', $email)
            ->where('device_type', $deviceType)
            ->where('expires_at', '>', Carbon::now())
            ->update(['expires_at' => Carbon::now()]);

        // Generate new OTP
        $otp = OtpVerification::create([
            'id' => Str::uuid(),
            'email' => $email,
            'otp_code' => sprintf('%06d', random_int(100000, 999999)),
            'device_type' => $deviceType,
            'device_id' => $deviceId,
            'ip_address' => $ipAddress,
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        // TODO: Send OTP via email/SMS
        // For development, you can return the OTP code

        return $otp;
    }

    public function verifyOtp(string $requestId, string $otpCode): bool
    {
        $otp = OtpVerification::where('id', $requestId)
            ->where('otp_code', $otpCode)
            ->where('expires_at', '>', Carbon::now())
            ->where('is_verified', false)
            ->first();

        if (! $otp) {
            return false;
        }

        // Increment attempts
        $otp->increment('attempts');

        if ($otp->attempts > 5) {
            throw new \Exception('Too many verification attempts.');
        }

        // Mark as verified
        $otp->update([
            'is_verified' => true,
            'verified_at' => Carbon::now(),
        ]);

        return true;
    }
}
