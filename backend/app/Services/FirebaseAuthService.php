<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Auth as FirebaseAuth;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Kreait\Firebase\Factory;

class FirebaseAuthService
{
    private FirebaseAuth $auth;

    public function __construct()
    {
        $firebase = (new Factory)
            ->withServiceAccount([
                'type' => 'service_account',
                'project_id' => config('services.firebase.project_id'),
                'private_key_id' => config('services.firebase.private_key_id'),
                'private_key' => str_replace('\\n', "\n", config('services.firebase.private_key')),
                'client_email' => config('services.firebase.client_email'),
                'client_id' => config('services.firebase.client_id'),
                'auth_uri' => config('services.firebase.auth_uri'),
                'token_uri' => config('services.firebase.token_uri'),
            ]);

        $this->auth = $firebase->createAuth();
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
            $user = User::create([
                'name' => $firebaseUser['name'] ?? 'User',
                'email' => $firebaseUser['email'],
                'firebase_uid' => $firebaseUser['uid'],
                'email_verified_at' => now(),
                'role' => $deviceType, // 'rider' or 'driver'
            ]);

            Log::info('Created new user from Firebase auth', [
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $deviceType,
            ]);
        } else {
            // Update Firebase UID if not set
            if (! $user->firebase_uid) {
                $user->update(['firebase_uid' => $firebaseUser['uid']]);
            }

            Log::info('Existing user logged in via Firebase', [
                'user_id' => $user->id,
                'email' => $user->email,
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
