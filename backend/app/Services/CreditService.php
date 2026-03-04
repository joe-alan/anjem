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
    public function deductCredit(int $driverId, int $rideId): void
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

    public function getTransactions(int $driverId, int $limit = 50)
    {
        return CreditTransaction::where('driver_id', $driverId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
