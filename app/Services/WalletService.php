<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Enums\WalletTransactionType;
use App\Exceptions\InsufficientWalletBalance;
use App\Models\BillingReservation;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Owns every movement of money on a client wallet: top ups, reservations, captures and releases.
 */
class WalletService
{
    /**
     * Get the wallet for a client, creating it on first use.
     */
    public function walletFor(Client $client): Wallet
    {
        return Wallet::query()->firstOrCreate(
            ['client_id' => $client->id],
            ['currency' => 'GHS', 'balance' => 0, 'reserved_balance' => 0, 'low_balance_threshold' => 0],
        );
    }

    /**
     * Credit the wallet with additional funds.
     */
    public function topUp(
        Wallet $wallet,
        float $amount,
        ?User $user = null,
        string $description = 'Wallet top up',
        ?string $reference = null,
    ): WalletTransaction {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new InvalidArgumentException('A top up amount must be greater than zero.');
        }

        return DB::transaction(function () use ($amount, $description, $reference, $user, $wallet): WalletTransaction {
            $wallet = $this->lock($wallet);

            $wallet->balance = round((float) $wallet->balance + $amount, 2);
            $wallet->save();

            return $wallet->transactions()->create([
                'type' => WalletTransactionType::Credit,
                'amount' => $amount,
                'balance_after' => $wallet->balance,
                'description' => $description,
                'reference' => $reference,
                'created_by' => $user?->id,
            ]);
        });
    }

    /**
     * Hold funds for a campaign that is about to be reviewed and dispatched.
     */
    public function reserve(Campaign $campaign, float $amount, ?User $user = null): ?BillingReservation
    {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($amount, $campaign, $user): BillingReservation {
            $wallet = $this->lock($this->walletFor($campaign->client));

            if (! $wallet->canCover($amount)) {
                throw InsufficientWalletBalance::for($wallet, $amount);
            }

            $reservation = BillingReservation::query()->create([
                'campaign_id' => $campaign->id,
                'wallet_id' => $wallet->id,
                'amount' => $amount,
                'captured_amount' => 0,
                'released_amount' => 0,
                'status' => ReservationStatus::Held,
            ]);

            $wallet->reserved_balance = round((float) $wallet->reserved_balance + $amount, 2);
            $wallet->save();

            $wallet->transactions()->create([
                'campaign_id' => $campaign->id,
                'billing_reservation_id' => $reservation->id,
                'type' => WalletTransactionType::Reservation,
                'amount' => $amount,
                'balance_after' => $wallet->balance,
                'description' => 'Funds reserved for campaign '.$campaign->name,
                'created_by' => $user?->id,
            ]);

            $campaign->forceFill(['reserved_amount' => $amount])->save();

            return $reservation->refresh();
        });
    }

    /**
     * Debit the wallet for what a campaign actually spent.
     */
    public function capture(BillingReservation $reservation, float $amount, string $description): ?WalletTransaction
    {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($amount, $description, $reservation): WalletTransaction {
            $reservation = $this->lockReservation($reservation);
            $wallet = $this->lock($reservation->wallet);

            $captured = round((float) $reservation->captured_amount + $amount, 2);

            $wallet->balance = round((float) $wallet->balance - $amount, 2);
            $wallet->reserved_balance = max(round((float) $wallet->reserved_balance - $amount, 2), 0);
            $wallet->save();

            $reservation->captured_amount = $captured;
            $reservation->status = $captured >= (float) $reservation->amount
                ? ReservationStatus::Captured
                : ReservationStatus::Held;
            $reservation->save();

            return $wallet->transactions()->create([
                'campaign_id' => $reservation->campaign_id,
                'billing_reservation_id' => $reservation->id,
                'type' => WalletTransactionType::Debit,
                'amount' => $amount,
                'balance_after' => $wallet->balance,
                'description' => $description,
            ]);
        });
    }

    /**
     * Give back whatever a campaign did not spend.
     */
    public function release(BillingReservation $reservation, string $description = 'Reserved funds released'): void
    {
        DB::transaction(function () use ($description, $reservation): void {
            $reservation = $this->lockReservation($reservation);

            $held = $reservation->heldAmount();

            if ($held > 0) {
                $wallet = $this->lock($reservation->wallet);

                $wallet->reserved_balance = max(round((float) $wallet->reserved_balance - $held, 2), 0);
                $wallet->save();

                $reservation->released_amount = round((float) $reservation->released_amount + $held, 2);

                $wallet->transactions()->create([
                    'campaign_id' => $reservation->campaign_id,
                    'billing_reservation_id' => $reservation->id,
                    'type' => WalletTransactionType::Release,
                    'amount' => $held,
                    'balance_after' => $wallet->balance,
                    'description' => $description,
                ]);
            }

            $reservation->status = (float) $reservation->captured_amount > 0
                ? ReservationStatus::Captured
                : ReservationStatus::Released;
            $reservation->save();
        });
    }

    /**
     * Lock a wallet row for the duration of the surrounding transaction.
     */
    protected function lock(Wallet $wallet): Wallet
    {
        return Wallet::query()->lockForUpdate()->findOrFail($wallet->id);
    }

    /**
     * Lock a reservation row for the duration of the surrounding transaction.
     */
    protected function lockReservation(BillingReservation $reservation): BillingReservation
    {
        return BillingReservation::query()->lockForUpdate()->findOrFail($reservation->id);
    }
}
