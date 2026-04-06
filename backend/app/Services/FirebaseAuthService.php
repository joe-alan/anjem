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

            $picture = $verifiedIdToken->claims()->get('picture');

            return [
                'uid' => $uid,
                'email' => $email,
                'name' => $name,
                'email_verified' => $emailVerified,
                'picture' => $picture,
            ];
        } catch (FailedToVerifyToken $e) {
            Log::error('Failed to verify Firebase token: '.$e->getMessage());
            throw new \Exception('Invalid Firebase token');
        }
    }

    public function getOrCreateUser(array $firebaseUser, string $deviceType): User
    {
        $user = User::where('email', $firebaseUser['email'])->first();
        $picture = $firebaseUser['picture'] ?? null;

        if (! $user) {
            // Anonymize any soft-deleted user with this email to free the unique constraint
            User::onlyTrashed()
                ->where('email', $firebaseUser['email'])
                ->each(fn (User $u) => $u->update(['email' => "deleted_{$u->id}@removed"]));

            // Create new user with the device type they registered from
            $userData = [
                'name' => $firebaseUser['name'] ?? 'User',
                'email' => $firebaseUser['email'],
                'firebase_uid' => $firebaseUser['uid'],
                'email_verified_at' => now(),
                'role' => $deviceType, // 'rider' or 'driver'
            ];

            if ($picture) {
                $userData['profile_picture'] = $picture;
            }

            $user = User::create($userData);

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

            // Upgrade role to 'both' if they sign in from different app
            if ($user->role !== 'both' && $user->role !== $deviceType) {
                $oldRole = $user->role;
                $user->update(['role' => 'both']);

                Log::info('Upgraded user to both rider and driver', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'old_role' => $oldRole,
                    'new_role' => 'both',
                    'signed_in_from' => $deviceType,
                ]);
            }

            // Update profile picture from Google if user hasn't uploaded their own
            // Skip overwrite for user-uploaded avatars stored in Firebase Storage
            if ($picture && (! $user->profile_picture || (str_starts_with($user->profile_picture, 'https://') && ! str_contains($user->profile_picture, 'storage.googleapis.com')))) {
                $user->update(['profile_picture' => $picture]);
            }

            // Update last active timestamp
            $user->update(['last_active_at' => now()]);

            Log::info('Existing user logged in via Firebase', [
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
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
