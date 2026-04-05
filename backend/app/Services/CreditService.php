<?php

namespace App\Services;

use App\Exceptions\InsufficientCreditsException;
use App\Models\CreditTransaction;
use App\Models\DriverProfile;
use Illuminate\Support\Facades\DB;

class CreditService
{
    public function getBalance(int $driverId): int
    {
        return DriverProfile::where('user_id', $driverId)->value('credits_balance') ?? 0;
    }

    public function canGoOnline(int $driverId): bool
    {
        return $this->getBalance($driverId) >= 1;
    }

    public function canAcceptRide(int $driverId): bool
    {
        return $this->getBalance($driverId) >= 1;
    }

    /**
     * Deduct one credit for accepting a ride.
     * Must be called inside an existing DB transaction.
     */
    public function deductCredit(int $driverId, ?int $rideId = null): void
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('deductCredit must be called within a DB transaction');
        }

        $profile = DriverProfile::where('user_id', $driverId)->lockForUpdate()->first();

        if (! $profile || $profile->credits_balance < 1) {
            throw new InsufficientCreditsException($profile?->credits_balance ?? 0);
        }

        $balanceBefore = $profile->credits_balance;
        $balanceAfter  = $balanceBefore - 1;

        $profile->credits_balance     = $balanceAfter;
        $profile->credits_total_spent = $profile->credits_total_spent + 1;
        $profile->save();

        CreditTransaction::create([
            'driver_id'      => $driverId,
            'type'           => 'deduction',
            'amount'         => -1,
            'balance_before' => $balanceBefore,
            'balance_after'  => $balanceAfter,
            'ride_id'        => $rideId,
            'description'    => 'Ride acceptance',
        ]);
    }

    /**
     * Refund one credit to a driver after a rider-initiated pre-pickup cancellation.
     */
    public function refundCredit(int $driverId, int $rideId): void
    {
        DB::transaction(function () use ($driverId, $rideId) {
            // Idempotency: skip if already refunded for this ride
            $alreadyRefunded = CreditTransaction::where('ride_id', $rideId)
                ->where('type', 'refund')
                ->exists();

            if ($alreadyRefunded) {
                return;
            }

            $profile = DriverProfile::where('user_id', $driverId)->lockForUpdate()->first();
            if (! $profile) {
                throw new \RuntimeException("Driver profile not found for user {$driverId}");
            }

            $balanceBefore = $profile->credits_balance;
            $profile->credits_balance     = $balanceBefore + 1;
            $profile->credits_total_spent = max(0, $profile->credits_total_spent - 1);
            $profile->save();

            CreditTransaction::create([
                'driver_id'      => $driverId,
                'type'           => 'refund',
                'amount'         => 1,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceBefore + 1,
                'ride_id'        => $rideId,
                'description'    => 'Rider-initiated cancellation refund',
            ]);
        });
    }

    /**
     * Add credits to a driver (admin grant or refund).
     */
    public function addCredits(int $driverId, int $amount, string $description = 'Admin grant'): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        DB::transaction(function () use ($driverId, $amount, $description) {
            $profile = DriverProfile::where('user_id', $driverId)->lockForUpdate()->first();

            if (! $profile) {
                throw new \RuntimeException("Driver profile not found for user {$driverId}");
            }

            $balanceBefore = $profile->credits_balance;
            $balanceAfter  = $balanceBefore + $amount;

            $profile->credits_balance      = $balanceAfter;
            $profile->credits_total_earned = $profile->credits_total_earned + $amount;
            $profile->save();

            CreditTransaction::create([
                'driver_id'      => $driverId,
                'type'           => 'admin_grant',
                'amount'         => $amount,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'description'    => $description,
            ]);
        });
    }

    /**
     * Deduct credits by admin (penalty, correction, etc.).
     * Self-contained: wraps in its own DB transaction.
     *
     * @return array{balance_before: int, balance_after: int}
     */
    public function adminDeductCredits(DriverProfile $profile, int $amount, string $reason): array
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        return DB::transaction(function () use ($profile, $amount, $reason) {
            $locked = DriverProfile::where('id', $profile->id)->lockForUpdate()->first();

            if (! $locked) {
                throw new \RuntimeException("Driver profile not found for id {$profile->id}");
            }

            if ($locked->credits_balance < $amount) {
                throw new \RuntimeException('Insufficient credits for deduction');
            }

            $balanceBefore = $locked->credits_balance;
            $balanceAfter  = $balanceBefore - $amount;

            $locked->credits_balance     = $balanceAfter;
            $locked->credits_total_spent = $locked->credits_total_spent + $amount;
            $locked->save();

            CreditTransaction::create([
                'driver_id'      => $locked->user_id,
                'type'           => 'admin_deduction',
                'amount'         => -$amount,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'description'    => $reason,
            ]);

            return ['balance_before' => $balanceBefore, 'balance_after' => $balanceAfter];
        });
    }

    public function getTransactions(int $driverId, int $limit = 50)
    {
        return CreditTransaction::where('driver_id', $driverId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
