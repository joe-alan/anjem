<?php

namespace App\Services;

use App\Models\Ride;
use App\Models\RideRequest;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

/**
 * NotificationService handles Firebase Cloud Messaging notifications
 *
 * Sends push notifications for ride-related events to riders and drivers.
 * Uses Firebase Cloud Messaging (FCM) for reliable delivery.
 */
class NotificationService
{
    private Messaging $messaging;

    public function __construct(Messaging $messaging)
    {
        $this->messaging = $messaging;
    }

    /**
     * Send ride matched notification to rider
     */
    public function sendRideMatchedToRider(Ride $ride): bool
    {
        $rider = $ride->rider;
        if (! $rider || ! $rider->fcm_token) {
            return false;
        }

        $message = [
            'title' => 'Driver Ditemukan!',
            'body' => "Driver Anda {$ride->driver->name} sedang dalam perjalanan. Estimasi penjemputan 5-8 menit.",
            'data' => [
                'type' => 'ride_matched',
                'ride_id' => (string) $ride->id,
                'driver_id' => (string) $ride->driver_id,
                'driver_name' => $ride->driver->name,
                'estimated_pickup_minutes' => '5-8',
            ],
        ];

        return $this->sendNotification($rider->fcm_token, $message);
    }

    /**
     * Send ride request notification to driver
     */
    public function sendRideRequestToDriver(Ride $ride): bool
    {
        $driver = $ride->driver;
        if (! $driver || ! $driver->fcm_token) {
            return false;
        }

        $message = [
            'title' => 'Permintaan Anjem Baru',
            'body' => "Anjem ke {$ride->destinationLocation->name}. Tarif: Rp ".number_format($ride->estimated_fare_rp),
            'data' => [
                'type' => 'ride_request',
                'ride_id' => (string) $ride->id,
                'rider_id' => (string) $ride->rider_id,
                'rider_name' => $ride->rider->name,
                'pickup_location' => $ride->pickupLocation->name,
                'destination_location' => $ride->destinationLocation->name,
                'estimated_fare_rp' => (string) $ride->estimated_fare_rp,
                'passenger_count' => (string) $ride->passenger_count,
            ],
        ];

        return $this->sendNotification($driver->fcm_token, $message);
    }

    public function sendNewRideRequestToDriver(RideRequest $rideRequest, User $driver): bool
    {
        $rideRequest->loadMissing(['pickupLocation', 'destinationLocation', 'rider']);

        if (! $driver->fcm_token) {
            return false;
        }

        $title = 'Permintaan Anjem Baru';
        $body = "Jemput: {$rideRequest->pickupLocation->name} → {$rideRequest->destinationLocation->name}. Tarif: Rp {$rideRequest->estimated_fare_rp}";

        $message = [
            'title' => $title,
            'body' => $body,
            'data' => [
                'type' => 'new_ride_request',
                'ride_request_id' => (string) $rideRequest->id,
                'rider_name' => $rideRequest->rider->name,
                'pickup_location' => $rideRequest->pickupLocation->name,
                'destination_location' => $rideRequest->destinationLocation->name,
                'estimated_fare_rp' => (string) $rideRequest->estimated_fare_rp,
                'passenger_count' => (string) $rideRequest->passenger_count,
            ],
        ];

        $result = $this->sendNotification($driver->fcm_token, $message, highPriority: true);

        if ($result) {
            Log::info('New ride request notification sent to driver', [
                'ride_request_id' => $rideRequest->id,
                'driver_id' => $driver->id,
            ]);
        } else {
            Log::warning('Failed to send new ride request notification to driver', [
                'ride_request_id' => $rideRequest->id,
                'driver_id' => $driver->id,
            ]);
        }

        return $result;
    }

    /**
     * Send ride accepted notification to rider
     */
    public function sendRideAcceptedToRider(Ride $ride): bool
    {
        $rider = $ride->rider;
        if (! $rider || ! $rider->fcm_token) {
            return false;
        }

        $message = [
            'title' => 'Anjem Diterima',
            'body' => "Driver Anda {$ride->driver->name} telah menerima Anjem dan sedang menuju lokasi penjemputan.",
            'data' => [
                'type' => 'ride_accepted',
                'ride_id' => (string) $ride->id,
                'driver_id' => (string) $ride->driver_id,
                'driver_name' => $ride->driver->name,
                'pickup_location' => $ride->pickupLocation->name,
            ],
        ];

        return $this->sendNotification($rider->fcm_token, $message);
    }

    /**
     * Send driver arrived notification to rider
     */
    public function sendDriverArrivedToRider(Ride $ride): bool
    {
        $rider = $ride->rider;
        if (! $rider || ! $rider->fcm_token) {
            return false;
        }

        $message = [
            'title' => 'Driver Tiba',
            'body' => "Driver Anda {$ride->driver->name} telah tiba di lokasi penjemputan.",
            'data' => [
                'type' => 'driver_arrived',
                'ride_id' => (string) $ride->id,
                'driver_id' => (string) $ride->driver_id,
                'driver_name' => $ride->driver->name,
            ],
        ];

        return $this->sendNotification($rider->fcm_token, $message);
    }

    /**
     * Send ride started notification to rider
     */
    public function sendRideStartedToRider(Ride $ride): bool
    {
        $rider = $ride->rider;
        if (! $rider || ! $rider->fcm_token) {
            return false;
        }

        $message = [
            'title' => 'Anjem Dimulai',
            'body' => "Anjem Anda ke {$ride->destinationLocation->name} telah dimulai. Selamat menikmati perjalanan!",
            'data' => [
                'type' => 'ride_started',
                'ride_id' => (string) $ride->id,
                'destination_location' => $ride->destinationLocation->name,
            ],
        ];

        return $this->sendNotification($rider->fcm_token, $message);
    }

    /**
     * Send ride completed notification to both rider and driver
     */
    public function sendRideCompletedNotifications(Ride $ride): array
    {
        $results = [
            'rider' => false,
            'driver' => false,
        ];

        // Notification to rider
        if ($ride->rider && $ride->rider->fcm_token) {
            $riderMessage = [
                'title' => 'Anjem Selesai',
                'body' => "Anda telah tiba di {$ride->destinationLocation->name}. Silakan beri rating untuk driver Anda!",
                'data' => [
                    'type' => 'ride_completed',
                    'ride_id' => (string) $ride->id,
                    'final_fare_rp' => (string) $ride->finalFare,
                    'can_rate' => 'true',
                ],
            ];
            $results['rider'] = $this->sendNotification($ride->rider->fcm_token, $riderMessage);
        }

        // Notification to driver
        if ($ride->driver && $ride->driver->fcm_token) {
            $driverMessage = [
                'title' => 'Anjem Selesai',
                'body' => 'Anjem selesai! Anda mendapatkan Rp '.number_format($ride->finalFare).'. Silakan beri rating untuk penumpang!',
                'data' => [
                    'type' => 'ride_completed',
                    'ride_id' => (string) $ride->id,
                    'final_fare_rp' => (string) $ride->finalFare,
                    'can_rate' => 'true',
                ],
            ];
            $results['driver'] = $this->sendNotification($ride->driver->fcm_token, $driverMessage);
        }

        return $results;
    }

    /**
     * Send ride cancelled notification
     */
    public function sendRideCancelledNotification(Ride $ride, int $cancelledByUserId, ?string $reason = null): array
    {
        $results = [
            'rider' => false,
            'driver' => false,
        ];

        $isCancelledByRider = $cancelledByUserId === $ride->rider_id;
        $cancelledBy = $isCancelledByRider ? 'rider' : 'driver';

        // Notification to the other party
        if ($isCancelledByRider && $ride->driver && $ride->driver->fcm_token) {
            $message = [
                'title' => 'Anjem Dibatalkan',
                'body' => 'Penumpang telah membatalkan Anjem.'.($reason ? " Alasan: {$reason}" : ''),
                'data' => [
                    'type' => 'ride_cancelled',
                    'ride_id' => (string) $ride->id,
                    'cancelled_by' => $cancelledBy,
                    'reason' => $reason ?? '',
                ],
            ];
            $results['driver'] = $this->sendNotification($ride->driver->fcm_token, $message);
        } elseif (! $isCancelledByRider && $ride->rider && $ride->rider->fcm_token) {
            $message = [
                'title' => 'Anjem Dibatalkan',
                'body' => 'Driver Anda telah membatalkan Anjem.'.($reason ? " Alasan: {$reason}" : ''),
                'data' => [
                    'type' => 'ride_cancelled',
                    'ride_id' => (string) $ride->id,
                    'cancelled_by' => $cancelledBy,
                    'reason' => $reason ?? '',
                ],
            ];
            $results['rider'] = $this->sendNotification($ride->rider->fcm_token, $message);
        }

        return $results;
    }

    /**
     * Send queue position update to driver
     */
    public function sendQueuePositionUpdate(User $driver, array $queueStatus): bool
    {
        if (! $driver->fcm_token) {
            return false;
        }

        $position = $queueStatus['position'] ?? 0;
        $estimatedWait = $queueStatus['estimated_wait_minutes'] ?? 0;

        $message = [
            'title' => 'Update Antrean',
            'body' => "Anda di posisi #${position} dalam antrean. Estimasi tunggu: ${estimatedWait} menit.",
            'data' => [
                'type' => 'queue_position_update',
                'position' => (string) $position,
                'estimated_wait_minutes' => (string) $estimatedWait,
                'beacon_id' => (string) ($queueStatus['beacon_id'] ?? ''),
            ],
        ];

        return $this->sendNotification($driver->fcm_token, $message);
    }

    /**
     * Send ride request timeout notification to rider
     */
    public function sendRideRequestTimeoutToRider(RideRequest $rideRequest): bool
    {
        $rider = $rideRequest->rider;
        if (! $rider || ! $rider->fcm_token) {
            return false;
        }

        $message = [
            'title' => 'Tidak Ada Driver Tersedia',
            'body' => 'Kami tidak dapat menemukan driver untuk Anjem Anda. Silakan coba lagi dalam beberapa menit.',
            'data' => [
                'type' => 'ride_request_timeout',
                'ride_request_id' => (string) $rideRequest->id,
                'pickup_location' => $rideRequest->pickupLocation->name ?? '',
            ],
        ];

        return $this->sendNotification($rider->fcm_token, $message);
    }

    /**
     * Send general announcement to all active users
     */
    public function sendAnnouncementToAllUsers(string $title, string $body, array $data = []): int
    {
        $users = User::active()
            ->whereNotNull('fcm_token')
            ->pluck('fcm_token')
            ->toArray();

        if (empty($users)) {
            return 0;
        }

        $message = [
            'title' => $title,
            'body' => $body,
            'data' => array_merge($data, ['type' => 'announcement']),
        ];

        $successCount = 0;
        foreach ($users as $token) {
            if ($this->sendNotification($token, $message)) {
                $successCount++;
            }
        }

        Log::info('Announcement sent', [
            'title' => $title,
            'total_users' => count($users),
            'successful_sends' => $successCount,
        ]);

        return $successCount;
    }

    /**
     * Send a generic notification to a specific user
     */
    public function sendToUser(User $user, string $title, string $body, array $data = [], bool $highPriority = false): bool
    {
        if (! $user->fcm_token) {
            return false;
        }

        $message = [
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ];

        return $this->sendNotification($user->fcm_token, $message, $highPriority);
    }

    /**
     * Send KYC approved notification to driver
     */
    public function sendKycApprovedToDriver(User $driver): void
    {
        if (! $driver->fcm_token) {
            return;
        }

        $message = [
            'title' => 'KYC Disetujui!',
            'body' => 'Verifikasi driver Anda telah disetujui. Anda sekarang dapat online dan mulai menerima Anjem.',
            'data' => ['type' => 'kyc_approved'],
        ];

        $this->sendNotification($driver->fcm_token, $message);
    }

    /**
     * Send KYC rejected notification to driver
     */
    public function sendKycRejectedToDriver(User $driver, string $reason): void
    {
        if (! $driver->fcm_token) {
            return;
        }

        $message = [
            'title' => 'Verifikasi KYC Ditolak',
            'body' => 'Verifikasi Anda tidak disetujui. Silakan buka aplikasi untuk detail dan kirim ulang dokumen Anda.',
            'data' => ['type' => 'kyc_rejected'],
        ];

        $this->sendNotification($driver->fcm_token, $message);
    }

    /**
     * Update user's FCM token
     */
    public function updateUserToken(int $userId, ?string $token): bool
    {
        try {
            $user = User::find($userId);
            if (! $user) {
                return false;
            }

            $user->updateFcmToken($token);

            Log::info('FCM token updated', [
                'user_id' => $userId,
                'token_provided' => ! empty($token),
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to update FCM token', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send notification using Firebase Cloud Messaging
     */
    private function sendNotification(string $fcmToken, array $messageData, bool $highPriority = false): bool
    {
        try {
            $notification = Notification::create(
                $messageData['title'],
                $messageData['body']
            );

            $message = CloudMessage::withTarget('token', $fcmToken)
                ->withNotification($notification)
                ->withData($messageData['data'] ?? []);

            if ($highPriority) {
                $androidConfig = AndroidConfig::fromArray(['priority' => 'high']);
                $message = $message->withAndroidConfig($androidConfig);
            }

            $this->messaging->send($message);

            Log::info('Notification sent successfully', [
                'title' => $messageData['title'],
                'type' => $messageData['data']['type'] ?? 'unknown',
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send notification', [
                'title' => $messageData['title'] ?? 'Unknown',
                'error' => $e->getMessage(),
                'token' => substr($fcmToken, 0, 20).'...', // Log partial token for debugging
            ]);

            return false;
        }
    }

    /**
     * Validate FCM token format
     */
    public function isValidToken(string $token): bool
    {
        // Basic FCM token validation
        return ! empty($token) && strlen($token) > 50 && preg_match('/^[a-zA-Z0-9_-]+$/', $token);
    }

    /**
     * Send test notification to verify setup
     */
    public function sendTestNotification(string $fcmToken): bool
    {
        $message = [
            'title' => 'Notifikasi Tes Anjem',
            'body' => 'Pengaturan notifikasi Anda berfungsi dengan baik!',
            'data' => [
                'type' => 'test',
                'timestamp' => now()->toISOString(),
            ],
        ];

        return $this->sendNotification($fcmToken, $message);
    }

    /**
     * Clean up invalid FCM tokens
     * Should be called periodically to remove stale tokens
     */
    public function cleanupInvalidTokens(): int
    {
        // For MVP, we'll just log this for manual review
        // In production, we'd parse FCM response errors to identify invalid tokens

        $expiredTokensCount = User::whereNotNull('fcm_token')
            ->where('last_active_at', '<', now()->subDays(30))
            ->update(['fcm_token' => null]);

        Log::info('Cleaned up inactive FCM tokens', ['count' => $expiredTokensCount]);

        return $expiredTokensCount;
    }
}
