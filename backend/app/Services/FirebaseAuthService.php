<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;

class FirebaseAuthService
{
    private Auth $auth;

    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    public function verifyToken(string $idToken): array
    {
        try {
            $verifiedIdToken = $this->auth->verifyIdToken($idToken);
            $uid = $verifiedIdToken->claims()->get('sub');
            $email = $verifiedIdToken->claims()->get('email');
            $emailVerified = $verifiedIdToken->claims()->get('email_verified', false);
            $name = $verifiedIdToken->claims()->get('name');

            if (! $emailVerified) {
                throw new \Exception('Email not verified');
            }

            return [
                'uid' => $uid,
                'email' => $email,
                'name' => $name,
                'email_verified' => $emailVerified,
            ];
        } catch (FailedToVerifyToken $e) {
            Log::error('Failed to verify Firebase token: '.$e->getMessage());
            throw new \Exception('Invalid Firebase token');
        }
    }

    public function getOrCreateUser(array $firebaseUser, string $deviceType): User
    {
        $user = User::where('email', $firebaseUser['email'])->first();

        if (! $user) {
            // Create new user with the device type they registered from
            $user = User::create([
                'name' => $firebaseUser['name'] ?? 'User',
                'email' => $firebaseUser['email'],
                'firebase_uid' => $firebaseUser['uid'],
                'email_verified_at' => now(),
                'user_type' => $deviceType, // 'rider' or 'driver'
            ]);

            Log::info('Created new user from Firebase auth', [
                'user_id' => $user->id,
                'email' => $user->email,
                'user_type' => $deviceType,
            ]);
        } else {
            // Update Firebase UID if not set
            if (! $user->firebase_uid) {
                $user->update(['firebase_uid' => $firebaseUser['uid']]);
            }

            // Upgrade user_type to 'both' if they sign in from different app
            if ($user->user_type !== 'both' && $user->user_type !== $deviceType) {
                $oldType = $user->user_type;
                $user->update(['user_type' => 'both']);

                Log::info('Upgraded user to both rider and driver', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'old_type' => $oldType,
                    'new_type' => 'both',
                    'signed_in_from' => $deviceType,
                ]);
            }

            // Update last active timestamp
            $user->update(['last_active_at' => now()]);

            Log::info('Existing user logged in via Firebase', [
                'user_id' => $user->id,
                'email' => $user->email,
                'user_type' => $user->user_type,
            ]);
        }

        return $user;
    }

    public function revokeRefreshTokens(string $uid): void
    {
        try {
            $this->auth->revokeRefreshTokens($uid);
            Log::info('Revoked Firebase refresh tokens for user', ['uid' => $uid]);
        } catch (\Exception $e) {
            Log::error('Failed to revoke Firebase refresh tokens: '.$e->getMessage());
        }
    }
}
