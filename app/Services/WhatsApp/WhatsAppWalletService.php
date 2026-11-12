<?php

namespace App\Services\WhatsApp;

use App\Enums\WalletTransactionType;
use App\Exceptions\InsufficientWalletBalance;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppProviderEvent;
use App\Models\WhatsAppWalletReservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WhatsAppWalletService
{
    public function flagOverdueUnknownReservations(): int
    {
        return WhatsAppWalletReservation::query()
            ->where('status', 'held')
            ->whereNotNull('unknown_deadline_at')
            ->where('unknown_deadline_at', '<=', now())
            ->whereNull('exception_flagged_at')
            ->whereHas('campaign.recipients', fn ($query) => $query->where('status', 'unknown'))
            ->update(['exception_flagged_at' => now(), 'updated_at' => now()]);
    }

    public function reserve(WhatsAppCampaign $campaign): ?WhatsAppWalletReservation
    {
        $amount = round((float) $campaign->estimated_cost, 4);
        if ($amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($campaign, $amount): WhatsAppWalletReservation {
            $lockedCampaign = WhatsAppCampaign::query()->lockForUpdate()->findOrFail($campaign->id);
            if ($lockedCampaign->wallet_reservation_id !== null) {
                return WhatsAppWalletReservation::query()->findOrFail($lockedCampaign->wallet_reservation_id);
            }
            $wallet = Wallet::query()->firstOrCreate(['client_id' => $lockedCampaign->client_id], ['currency' => 'GHS', 'balance' => 0, 'reserved_balance' => 0, 'low_balance_threshold' => 0]);
            $wallet = Wallet::query()->lockForUpdate()->findOrFail($wallet->id);
            if ($wallet->currency !== 'GHS' || ! $wallet->canCover($amount)) {
                throw InsufficientWalletBalance::for($wallet, $amount);
            }
            $reservation = WhatsAppWalletReservation::query()->create([
                'campaign_id' => $lockedCampaign->id,
                'wallet_id' => $wallet->id,
                'reference' => (string) Str::uuid(),
                'amount' => $amount,
                'status' => 'held',
                'unknown_deadline_at' => now()->addDays((int) config('whatsapp.billing.unknown_hold_days', 30)),
            ]);
            $wallet->reserved_balance = round((float) $wallet->reserved_balance + $amount, 4);
            $wallet->save();
            $wallet->transactions()->create([
                'type' => WalletTransactionType::Reservation,
                'amount' => $amount,
                'balance_after' => $wallet->balance,
                'reference' => $reservation->reference,
                'description' => 'Funds reserved for WhatsApp campaign '.$lockedCampaign->name,
                'metadata' => ['channel' => 'whatsapp', 'campaign_id' => $lockedCampaign->id, 'reservation_id' => $reservation->id, 'price_snapshot' => $lockedCampaign->price_snapshot],
            ]);
            $lockedCampaign->forceFill(['wallet_reservation_id' => $reservation->id])->save();

            return $reservation;
        });
    }

    public function releaseIfNoBillableOutcomes(WhatsAppCampaign $campaign, string $reason): void
    {
        $reservation = WhatsAppWalletReservation::query()->where('campaign_id', $campaign->id)->first();
        if ($reservation === null) {
            return;
        }
        DB::transaction(function () use ($reservation, $campaign, $reason): void {
            $reservation = WhatsAppWalletReservation::query()->lockForUpdate()->findOrFail($reservation->id);
            if ($reservation->status !== 'held') {
                return;
            }
            if ($campaign->recipients()->whereIn('status', ['accepted', 'unknown', 'submitting', 'retry_wait'])->exists()) {
                return;
            }
            $held = $reservation->heldAmount();
            if ($held <= 0) {
                return;
            }
            $wallet = Wallet::query()->lockForUpdate()->findOrFail($reservation->wallet_id);
            $wallet->reserved_balance = max(round((float) $wallet->reserved_balance - $held, 4), 0);
            $wallet->save();
            $reservation->released_amount = round((float) $reservation->released_amount + $held, 4);
            $reservation->status = 'released';
            $reservation->save();
            $wallet->transactions()->create([
                'type' => WalletTransactionType::Release,
                'amount' => $held,
                'balance_after' => $wallet->balance,
                'reference' => (string) Str::uuid(),
                'description' => 'Unused WhatsApp reservation released: '.$reason,
                'metadata' => ['channel' => 'whatsapp', 'campaign_id' => $campaign->id, 'reservation_id' => $reservation->id],
            ]);
        });
    }

    /** Settlement is intentionally restricted to verified billable message evidence in Phase 6. */
    public function settleDelivered(WhatsAppCampaign $campaign, string $reference, float $amount, array $evidence): void
    {
        $event = isset($evidence['provider_event_id'])
            ? WhatsAppProviderEvent::query()->with('recipient')->whereKey($evidence['provider_event_id'])->where('signature_verified', true)->where('status', 'delivered')->where('billable', true)->first()
            : null;
        if (($evidence['verified_billable_delivery'] ?? false) !== true
            || $event === null
            || $event->recipient?->campaign_id !== $campaign->id
            || (float) $event->recipient?->unit_price !== round($amount, 4)
            || $reference !== 'wa-event-'.$event->id) {
            throw new \LogicException('WhatsApp charges require verified billable delivery evidence.');
        }
        $reservation = WhatsAppWalletReservation::query()->where('campaign_id', $campaign->id)->firstOrFail();
        DB::transaction(function () use ($reservation, $reference, $amount, $event): void {
            $reservation = WhatsAppWalletReservation::query()->lockForUpdate()->findOrFail($reservation->id);
            if (WalletTransaction::query()->where('reference', $reference)->exists()) {
                return;
            }
            $charge = round($amount, 4);
            if ($charge <= 0 || $reservation->heldAmount() < $charge) {
                throw new \LogicException('The WhatsApp reservation cannot cover this evidence-backed charge.');
            }
            $wallet = Wallet::query()->lockForUpdate()->findOrFail($reservation->wallet_id);
            $wallet->balance = round((float) $wallet->balance - $charge, 4);
            $wallet->reserved_balance = max(round((float) $wallet->reserved_balance - $charge, 4), 0);
            $wallet->save();
            $reservation->captured_amount = round((float) $reservation->captured_amount + $charge, 4);
            $reservation->status = $reservation->heldAmount() <= 0 ? 'captured' : 'held';
            $reservation->save();
            $wallet->transactions()->create([
                'billing_reservation_id' => null,
                'type' => WalletTransactionType::Debit,
                'amount' => $charge,
                'balance_after' => $wallet->balance,
                'reference' => $reference,
                'description' => 'Verified WhatsApp delivery charge',
                'metadata' => ['channel' => 'whatsapp', 'reservation_id' => $reservation->id, 'provider_event_id' => $event->id, 'provider_id' => $event->provider_id],
            ]);
        });
    }
}
