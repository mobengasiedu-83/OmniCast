<?php

namespace App\Services\Reporting;

use App\Models\Campaign;
use App\Models\CampaignContact;
use App\Models\Client;
use App\Models\Contact;
use App\Models\EmailMessage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SmsMessage;
use App\Models\User;
use App\Models\VoiceCall;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Builder;

/**
 * The rows a report may read.
 *
 * A client user is pinned to their own tenant; a platform user reads every
 * client, or one client when the screen narrows the scope. Every reporting
 * query goes through this object so an analytics screen can never widen its
 * reach by forgetting a `where`.
 */
class ReportScope
{
    public function __construct(
        public ?int $clientId,
        public bool $platform,
    ) {}

    /**
     * Resolve the scope a request is allowed to read.
     */
    public static function forUser(User $user, ?int $clientId = null): self
    {
        if ($user->isPlatformUser()) {
            return new self($clientId, true);
        }

        return new self($user->client_id, false);
    }

    /**
     * Resolve the scope a stored export was generated for.
     */
    public static function fromExport(?int $clientId, bool $platform): self
    {
        return new self($clientId, $platform);
    }

    /**
     * Whether the scope reads every client.
     */
    public function isPlatformWide(): bool
    {
        return $this->clientId === null;
    }

    /**
     * @return Builder<Campaign>
     */
    public function campaigns(): Builder
    {
        return $this->clientColumn(Campaign::query());
    }

    /**
     * @return Builder<CampaignContact>
     */
    public function campaignContacts(): Builder
    {
        return CampaignContact::query()->whereIn('campaign_id', $this->campaigns()->select('id'));
    }

    /**
     * @return Builder<SmsMessage>
     */
    public function sms(): Builder
    {
        return $this->clientColumn(SmsMessage::query());
    }

    /**
     * @return Builder<VoiceCall>
     */
    public function voice(): Builder
    {
        return $this->clientColumn(VoiceCall::query());
    }

    /**
     * @return Builder<EmailMessage>
     */
    public function email(): Builder
    {
        return $this->clientColumn(EmailMessage::query());
    }

    /**
     * @return Builder<Contact>
     */
    public function contacts(): Builder
    {
        return $this->clientColumn(Contact::query());
    }

    /**
     * @return Builder<Client>
     */
    public function clients(): Builder
    {
        return $this->clientColumn(Client::query());
    }

    /**
     * @return Builder<Payment>
     */
    public function payments(): Builder
    {
        return $this->clientColumn(Payment::query());
    }

    /**
     * @return Builder<Invoice>
     */
    public function invoices(): Builder
    {
        return $this->clientColumn(Invoice::query());
    }

    /**
     * @return Builder<Wallet>
     */
    public function wallets(): Builder
    {
        return $this->clientColumn(Wallet::query());
    }

    /**
     * @return Builder<WalletTransaction>
     */
    public function walletTransactions(): Builder
    {
        return WalletTransaction::query()->when(
            $this->clientId !== null,
            fn (Builder $query): Builder => $query->whereHas(
                'wallet',
                fn (Builder $wallet): Builder => $wallet->where('client_id', $this->clientId),
            ),
        );
    }

    /**
     * Narrow a query to the tenant the scope belongs to.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function clientColumn(Builder $query, string $column = 'client_id'): Builder
    {
        if ($this->clientId === null) {
            return $query;
        }

        return $query->where($query->qualifyColumn($column), $this->clientId);
    }
}
