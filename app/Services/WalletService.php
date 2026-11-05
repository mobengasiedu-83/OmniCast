<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Enums\WalletTransactionType;
use App\Exceptions\InsufficientWalletBalance;
use App\Models\BillingReservation;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Payment;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Owns every movement of money on a client wallet: top ups, reservations,
 * the per-unit spends a running service makes and the release of what it never
 * used.
 *
 * Money is held when a service starts and debited one billable unit at a time
 * (an SMS part, a call, an email), so a wallet always shows what has actually
 * been consumed rather than what the campaign hoped to send.
 */
class WalletService
{
    /**
     * The number of decimals client money is remembered to.
     *
     * Rates are quoted to four decimals (GHS 0.0350 an SMS part), so a wallet
     * that only remembered two would round every single-part message up.
     */
    public const PRECISION = 4;

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
        ?Payment $payment = null,
    ): WalletTransaction {
        $amount = round($amount, self::PRECISION);

        if ($amount <= 0) {
            throw new InvalidArgumentException('A top up amount must be greater than zero.');
        }

        return DB::transaction(function () use ($amount, $description, $payment, $reference, $user, $wallet): WalletTransaction {
            $wallet = $this->lock($wallet);

            $wallet->balance = round((float) $wallet->balance + $amount, self::PRECISION);
            $wallet->save();

            return $wallet->transactions()->create([
                'payment_id' => $payment?->id,
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
     * Hold funds for the whole of a campaign before it starts delivering.
     *
     * This is where the deduction starts: nothing is debited yet, but the money
     * can no longer be committed to another campaign.
     */
    public function reserve(Campaign $campaign, float $amount, ?User $user = null): ?BillingReservation
    {
        $amount = round($amount, self::PRECISION);

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

            $wallet->reserved_balance = round((float) $wallet->reserved_balance + $amount, self::PRECISION);
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
     * Hold the money a service is about to spend, reusing the hold it already has.
     *
     * A campaign approved before the prices existed, or one being retried after
     * a network failure, still starts its deduction here.
     */
    public function startBilling(Campaign $campaign, float $amount, ?User $user = null): ?BillingReservation
    {
        $held = $campaign->reservations()
            ->where('status', ReservationStatus::Held->value)
            ->latest('id')
            ->first();

        if ($held instanceof BillingReservation) {
            return $held;
        }

        return $this->reserve($campaign, $amount, $user);
    }

    /**
     * Whether the wallet can pay for the next unit of a service.
     */
    public function canAfford(BillingReservation $reservation, float $amount): bool
    {
        $amount = round($amount, self::PRECISION);
        $held = $reservation->fresh()?->heldAmount() ?? 0.0;

        if ($held >= $amount) {
            return true;
        }

        $wallet = Wallet::query()->find($reservation->wallet_id);

        return $wallet instanceof Wallet && $wallet->canCover(round($amount - $held, self::PRECISION));
    }

    /**
     * Debit the wallet for one billable unit as the service consumes it.
     *
     * The held funds pay for the unit first; when it costs more than the hold
     * still carries (a retry, a longer message) the hold is extended by the
     * difference, so nothing is delivered without being paid for. False means
     * the tenant cannot pay for this unit.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function spend(BillingReservation $reservation, float $amount, string $description, array $metadata = []): bool
    {
        $amount = round($amount, self::PRECISION);

        if ($amount <= 0) {
            return true;
        }

        $spent = DB::transaction(function () use ($amount, $description, $metadata, $reservation): bool {
            $locked = $this->lockReservation($reservation);
            $wallet = $this->lock($locked->wallet);

            $shortfall = round($amount - $locked->heldAmount(), self::PRECISION);

            if ($shortfall > 0) {
                if (! $wallet->canCover($shortfall)) {
                    return false;
                }

                $locked->amount = round((float) $locked->amount + $shortfall, self::PRECISION);
                $wallet->reserved_balance = round((float) $wallet->reserved_balance + $shortfall, self::PRECISION);
            }

            $wallet->balance = round((float) $wallet->balance - $amount, self::PRECISION);
            $wallet->reserved_balance = max(round((float) $wallet->reserved_balance - $amount, self::PRECISION), 0);
            $wallet->save();

            $locked->captured_amount = round((float) $locked->captured_amount + $amount, self::PRECISION);
            $locked->status = $locked->heldAmount() <= 0
                ? ReservationStatus::Captured
                : ReservationStatus::Held;
            $locked->save();

            $wallet->transactions()->create([
                'campaign_id' => $locked->campaign_id,
                'billing_reservation_id' => $locked->id,
                'type' => WalletTransactionType::Debit,
                'amount' => $amount,
                'balance_after' => $wallet->balance,
                'description' => $description,
                'metadata' => $metadata === [] ? null : $metadata,
            ]);

            return true;
        });

        $reservation->refresh();

        return $spent;
    }

    /**
     * Everything the campaign has actually been charged, retries included.
     */
    public function chargedFor(Campaign $campaign): float
    {
        return round((float) $campaign->walletTransactions()
            ->where('type', WalletTransactionType::Debit->value)
            ->sum('amount'), self::PRECISION);
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

                $wallet->reserved_balance = max(round((float) $wallet->reserved_balance - $held, self::PRECISION), 0);
                $wallet->save();

                $reservation->released_amount = round((float) $reservation->released_amount + $held, self::PRECISION);

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
