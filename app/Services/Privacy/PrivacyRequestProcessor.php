<?php

namespace App\Services\Privacy;

use App\Enums\AutomationRunStatus;
use App\Enums\ClientStatus;
use App\Enums\InvitationStatus;
use App\Enums\PrivacyRequestAction;
use App\Enums\PrivacyRequestStatus;
use App\Enums\PrivacySubjectType;
use App\Enums\ReportType;
use App\Models\AutomationRun;
use App\Models\Client;
use App\Models\Contact;
use App\Models\PrivacyRequest;
use App\Models\ReportExport;
use App\Models\SmppAccount;
use App\Models\User;
use App\Models\UserInvitation;
use App\Models\WebhookEndpoint;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignRecipient;
use App\Models\WhatsAppClientConnector;
use App\Models\WhatsAppContactConsent;
use App\Models\WhatsAppMediaAsset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivacyRequestProcessor
{
    /**
     * Stream an approved access export without persisting it on a public disk.
     */
    public function streamExport(PrivacyRequest $privacyRequest, int $actorId): StreamedResponse
    {
        abort_unless(
            $privacyRequest->action === PrivacyRequestAction::Access
                && $privacyRequest->status === PrivacyRequestStatus::Approved,
            409,
        );
        abort_unless((int) $privacyRequest->requested_by !== $actorId, 403);

        return response()->streamDownload(function () use ($privacyRequest, $actorId): void {
            $client = Client::query()->findOrFail($privacyRequest->client_id);
            $contact = $privacyRequest->subject_type === PrivacySubjectType::Contact
                ? Contact::withTrashed()
                    ->where('client_id', $client->id)
                    ->findOrFail($privacyRequest->contact_id)
                : null;

            echo '{"request":'.$this->encode([
                'reference' => $privacyRequest->reference,
                'action' => $privacyRequest->action->value,
                'subject_type' => $privacyRequest->subject_type->value,
                'created_at' => $privacyRequest->created_at?->toIso8601String(),
            ]).',"client":'.$this->encode([
                'id' => $client->id,
                'name' => $client->name,
                'account_type' => $client->account_type->value,
                'code' => $client->code,
                'email' => $client->email,
                'phone' => $client->phone,
                'timezone' => $client->timezone,
                'status' => $client->status->value,
                'created_at' => $client->created_at?->toIso8601String(),
            ]);

            if ($contact !== null) {
                echo ',"contact":'.$this->encode($this->contactData($contact));
            } else {
                echo ',"users":[';
                $first = true;
                User::query()
                    ->where('client_id', $client->id)
                    ->with('roles')
                    ->orderBy('id')
                    ->lazyById(100)
                    ->each(function (User $user) use (&$first): void {
                        $this->writeArrayItem($this->userData($user), $first);
                    });
                echo '],"contacts":[';
                $first = true;
                $client->contacts()
                    ->withTrashed()
                    ->orderBy('id')
                    ->lazyById(100)
                    ->each(function (Contact $contact) use (&$first): void {
                        $this->writeArrayItem($this->contactData($contact), $first);
                    });
                echo ']';
            }

            echo ',"processing_history":[';
            $first = true;
            $contactIds = $contact === null
                ? $client->contacts()->withTrashed()->select('contacts.id')
                : [$contact->id];
            $history = DB::table('campaign_contacts')
                ->join('campaigns', 'campaigns.id', '=', 'campaign_contacts.campaign_id')
                ->whereIn('campaign_contacts.contact_id', $contactIds)
                ->select([
                    'campaign_contacts.id',
                    'campaign_contacts.campaign_id',
                    'campaigns.channel',
                    'campaign_contacts.status',
                    'campaign_contacts.attempts',
                    'campaign_contacts.sent_at',
                    'campaign_contacts.delivered_at',
                    'campaign_contacts.failed_at',
                    'campaign_contacts.cost',
                ])
                ->orderBy('campaign_contacts.id');

            foreach ($history->lazyById(100, 'campaign_contacts.id') as $row) {
                $this->writeArrayItem([
                    'campaign_id' => $row->campaign_id,
                    'channel' => $row->channel,
                    'status' => $row->status,
                    'attempts' => $row->attempts,
                    'sent_at' => $row->sent_at,
                    'delivered_at' => $row->delivered_at,
                    'failed_at' => $row->failed_at,
                    'cost' => $row->cost,
                ], $first);
            }

            echo '],"whatsapp_history":[';
            $first = true;
            $whatsappRecipients = DB::table('whatsapp_campaign_recipients as recipients')
                ->join('whatsapp_campaigns as campaigns', 'campaigns.id', '=', 'recipients.campaign_id')
                ->where('campaigns.client_id', $client->id)
                ->when($contact !== null, fn ($query) => $query->where('recipients.contact_id', $contact->id))
                ->select(['recipients.id', 'recipients.phone', 'recipients.status', 'recipients.provider_message_id', 'recipients.variables_json', 'recipients.snapshot_json', 'recipients.unit_price', 'recipients.unit_price_snapshot', 'recipients.submitted_at', 'recipients.accepted_at', 'recipients.delivered_at', 'recipients.read_at', 'recipients.failed_at', 'campaigns.name as campaign_name'])
                ->orderBy('recipients.id');

            foreach ($whatsappRecipients->lazyById(100, 'recipients.id', 'id') as $recipient) {
                $this->writeArrayItem(['type' => 'message', ...(array) $recipient], $first);
            }

            $recipientIds = DB::table('whatsapp_campaign_recipients as recipients')
                ->join('whatsapp_campaigns as campaigns', 'campaigns.id', '=', 'recipients.campaign_id')
                ->where('campaigns.client_id', $client->id)
                ->when($contact !== null, fn ($query) => $query->where('recipients.contact_id', $contact->id))
                ->select('recipients.id');
            foreach (DB::table('whatsapp_message_attempts')->whereIn('recipient_id', $recipientIds)->orderBy('id')->lazyById(100) as $attempt) {
                $this->writeArrayItem(['type' => 'attempt', ...(array) $attempt], $first);
            }
            foreach (DB::table('whatsapp_provider_events')->whereIn('recipient_id', $recipientIds)->orderBy('id')->lazyById(100) as $event) {
                $this->writeArrayItem(['type' => 'provider_event', ...(array) $event], $first);
            }
            foreach (DB::table('whatsapp_usage_records')->whereIn('recipient_id', $recipientIds)->orderBy('id')->lazyById(100) as $usage) {
                $this->writeArrayItem(['type' => 'billable_usage', ...(array) $usage], $first);
            }
            $usageIds = DB::table('whatsapp_usage_records')->whereIn('recipient_id', $recipientIds)->select('id');
            foreach (DB::table('whatsapp_invoice_reconciliations as reconciliations')
                ->join('whatsapp_provider_invoices as invoices', 'invoices.id', '=', 'reconciliations.invoice_id')
                ->whereIn('reconciliations.usage_record_id', $usageIds)
                ->select(['reconciliations.id', 'invoices.invoice_number', 'invoices.currency', 'invoices.status as invoice_status', 'reconciliations.provider_reference', 'reconciliations.provider_amount', 'reconciliations.internal_amount', 'reconciliations.variance', 'reconciliations.status as review_status', 'reconciliations.review_note'])
                ->orderBy('reconciliations.id')->lazyById(100, 'reconciliations.id', 'id') as $reconciliation) {
                $this->writeArrayItem(['type' => 'invoice_reconciliation', ...(array) $reconciliation], $first);
            }
            if ($contact === null) {
                foreach (DB::table('wallet_transactions as transactions')
                    ->join('wallets', 'wallets.id', '=', 'transactions.wallet_id')
                    ->where('wallets.client_id', $client->id)
                    ->where('transactions.metadata->channel', 'whatsapp')
                    ->select(['transactions.id', 'transactions.type', 'transactions.amount', 'transactions.balance_after', 'transactions.reference', 'transactions.description', 'transactions.metadata', 'transactions.created_at'])
                    ->orderBy('transactions.id')->lazyById(100, 'transactions.id', 'id') as $transaction) {
                    $this->writeArrayItem(['type' => 'wallet_ledger', ...(array) $transaction], $first);
                }
            }
            if ($contact !== null) {
                foreach (WhatsAppContactConsent::query()->where('contact_id', $contact->id)->orderBy('id')->lazyById(100) as $consent) {
                    $this->writeArrayItem(['type' => 'consent_evidence', ...$consent->attributesToArray()], $first);
                }
            }
            echo ']}';

            PrivacyRequest::query()
                ->whereKey($privacyRequest->id)
                ->where('status', PrivacyRequestStatus::Approved->value)
                ->update([
                    'status' => PrivacyRequestStatus::Completed->value,
                    'completed_by' => $actorId,
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);

            activity()
                ->causedBy(User::query()->findOrFail($actorId))
                ->performedOn($privacyRequest)
                ->withProperties(['reference' => $privacyRequest->reference])
                ->log('completed an approved privacy data export');
        }, 'privacy-request-'.$privacyRequest->reference.'.json', [
            'Cache-Control' => 'private, no-store',
            'Content-Type' => 'application/json; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => 'sandbox',
        ]);
    }

    /**
     * Apply guarded anonymisation while retaining legal, billing and audit rows.
     */
    public function erase(PrivacyRequest $privacyRequest, int $actorId): void
    {
        DB::transaction(function () use ($privacyRequest, $actorId): void {
            $lockedRequest = PrivacyRequest::query()
                ->lockForUpdate()
                ->findOrFail($privacyRequest->id);

            abort_unless(
                $lockedRequest->action === PrivacyRequestAction::Erasure
                    && $lockedRequest->status === PrivacyRequestStatus::Approved
                    && (int) $lockedRequest->requested_by !== $actorId,
                409,
            );

            $client = Client::query()->lockForUpdate()->findOrFail($lockedRequest->client_id);

            if ($lockedRequest->subject_type === PrivacySubjectType::Contact) {
                $contact = Contact::withTrashed()
                    ->where('client_id', $client->id)
                    ->lockForUpdate()
                    ->findOrFail($lockedRequest->contact_id);

                $this->anonymiseContacts(Contact::withTrashed()->whereKey($contact->id), $client->id);
            } else {
                $this->anonymiseClient($client);
            }

            $lockedRequest->forceFill([
                'status' => PrivacyRequestStatus::Completed,
                'completed_by' => $actorId,
                'completed_at' => now(),
            ])->save();
        });
    }

    /**
     * Replace account and user identifiers while retaining financial and legal records.
     */
    private function anonymiseClient(Client $client): void
    {
        $client->forceFill([
            'name' => 'Anonymised account '.$client->id,
            'email' => null,
            'phone' => null,
            'notes' => null,
            'settings' => [],
            'status' => ClientStatus::Archived,
            'status_reason' => 'Anonymised following an approved privacy request.',
            'status_changed_at' => now(),
            'archived_at' => $client->archived_at ?? now(),
        ])->save();

        WebhookEndpoint::query()
            ->where('client_id', $client->id)
            ->orderBy('id')
            ->chunkById(100, function ($endpoints): void {
                foreach ($endpoints as $endpoint) {
                    $endpoint->forceFill([
                        'secret' => Str::random(64),
                        'is_active' => false,
                        'disabled_at' => now(),
                    ])->save();
                }
            });

        SmppAccount::query()
            ->where('client_id', $client->id)
            ->orderBy('id')
            ->chunkById(100, function ($accounts): void {
                foreach ($accounts as $account) {
                    $account->forceFill([
                        'password_hash' => Hash::make(Str::random(64)),
                        'is_active' => false,
                    ])->save();
                    $account->sessions()->delete();
                }
            });

        $client->users()
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $user->tokens()->delete();
                    $user->passkeys()->delete();
                    $user->forceFill([
                        'name' => 'Anonymised user '.$user->id,
                        'email' => 'erased-user-'.$user->id.'@privacy.invalid',
                        'phone' => null,
                        'job_title' => null,
                        'is_active' => false,
                        'suspended_at' => now(),
                        'suspension_reason' => 'Anonymised following an approved privacy request.',
                        'last_login_ip' => null,
                        'email_verified_at' => null,
                        'phone_verified_at' => null,
                        'password' => Str::random(64),
                        'two_factor_secret' => null,
                        'two_factor_recovery_codes' => null,
                        'two_factor_confirmed_at' => null,
                        'remember_token' => null,
                    ])->save();
                }
            });

        if (config('session.driver') === 'database' && Schema::hasTable('sessions')) {
            DB::table('sessions')
                ->whereIn('user_id', $client->users()->select('users.id'))
                ->delete();
        }

        UserInvitation::query()
            ->where('client_id', $client->id)
            ->orderBy('id')
            ->chunkById(100, function ($invitations): void {
                foreach ($invitations as $invitation) {
                    $invitation->forceFill([
                        'email' => 'erased-invitation-'.$invitation->id.'@privacy.invalid',
                        'name' => null,
                        'token' => UserInvitation::hashToken(Str::random(64)),
                        'status' => InvitationStatus::Revoked,
                        'expires_at' => now(),
                        'revoked_at' => now(),
                    ])->save();
                }
            });

        $userIds = $client->users()->select('users.id');
        DB::table('notifications')
            ->where('notifiable_type', (new User)->getMorphClass())
            ->whereIn('notifiable_id', $userIds)
            ->update([
                'data' => json_encode(
                    ['message' => 'Notification content was redacted after an approved privacy request.'],
                    JSON_THROW_ON_ERROR,
                ),
            ]);

        $this->anonymiseContacts($client->contacts()->withTrashed(), $client->id);
        WhatsAppClientConnector::query()->where('client_id', $client->id)->update(['enabled' => false]);
        WhatsAppCampaign::query()->where('client_id', $client->id)->whereNotIn('status', ['completed', 'cancelled', 'failed'])
            ->update(['status' => 'cancelled', 'control_state' => 'cancelled', 'updated_at' => now()]);
        WhatsAppCampaign::query()->where('client_id', $client->id)->orderBy('id')->chunkById(100, function ($campaigns): void {
            foreach ($campaigns as $campaign) {
                WhatsAppCampaign::withoutEvents(fn () => $campaign->forceFill(['name' => 'Archived WhatsApp campaign '.$campaign->id, 'variable_mapping' => []])->save());
            }
        });
        WhatsAppMediaAsset::query()->where('client_id', $client->id)->orderBy('id')->chunkById(100, function ($assets): void {
            foreach ($assets as $asset) {
                Storage::disk($asset->storage_disk)->delete($asset->path);
                $asset->delete();
            }
        });
    }

    /**
     * Remove contact identifiers and recipient payload copies in bounded batches.
     *
     * @param  Builder<Contact>|HasMany<Contact, Client>  $contacts
     */
    private function anonymiseContacts(Builder|HasMany $contacts, int $clientId): void
    {
        $contacts
            ->orderBy('id')
            ->chunkById(100, function ($contactBatch): void {
                $contactIds = $contactBatch->modelKeys();

                foreach ($contactBatch as $contact) {
                    $contact->forceFill([
                        'first_name' => null,
                        'last_name' => null,
                        'phone' => 'ERASED-'.$contact->id,
                        'original_phone' => null,
                        'alternative_phone' => null,
                        'email' => null,
                        'company' => null,
                        'account_number' => null,
                        'customer_id' => null,
                        'location' => null,
                        'timezone' => 'UTC',
                        'dnd' => true,
                        'dnd_at' => now(),
                        'custom_fields' => null,
                        'deleted_at' => $contact->deleted_at ?? now(),
                    ])->save();
                }

                $this->redactAutomationRuns($contactIds);
                $campaignContactIds = fn () => DB::table('campaign_contacts')
                    ->select('id')
                    ->whereIn('contact_id', $contactIds);

                DB::table('campaign_contacts')
                    ->whereIn('contact_id', $contactIds)
                    ->update(['recipient_snapshot' => null, 'error_message' => null]);

                DB::table('sms_message_events')
                    ->whereIn('sms_message_id', DB::table('sms_messages')
                        ->select('id')
                        ->whereIn('campaign_contact_id', $campaignContactIds()))
                    ->update(['payload' => null, 'error_message' => null]);

                DB::table('sms_messages')
                    ->whereIn('campaign_contact_id', $campaignContactIds())
                    ->update([
                        'to_number' => 'ERASED',
                        'body' => '[redacted]',
                        'error_message' => null,
                        'last_event' => null,
                    ]);

                DB::table('email_message_events')
                    ->whereIn('email_message_id', DB::table('email_messages')
                        ->select('id')
                        ->whereIn('campaign_contact_id', $campaignContactIds()))
                    ->update(['payload' => null, 'error_message' => null]);

                DB::table('email_messages')
                    ->whereIn('campaign_contact_id', $campaignContactIds())
                    ->update([
                        'to_address' => 'redacted@privacy.invalid',
                        'subject' => '[redacted]',
                        'body' => '[redacted]',
                        'error_message' => null,
                        'last_event' => null,
                    ]);

                DB::table('voice_call_events')
                    ->whereIn('voice_call_id', DB::table('voice_calls')
                        ->select('id')
                        ->whereIn('campaign_contact_id', $campaignContactIds()))
                    ->update(['digit' => null, 'payload' => null]);

                DB::table('voice_calls')
                    ->whereIn('campaign_contact_id', $campaignContactIds())
                    ->update([
                        'to_number' => 'ERASED',
                        'dtmf_digits' => null,
                        'error_message' => null,
                        'last_event' => null,
                    ]);

                WhatsAppContactConsent::query()->whereIn('contact_id', $contactIds)->whereNull('revoked_at')->update(['revoked_at' => now(), 'updated_at' => now()]);
                $recipientIds = WhatsAppCampaignRecipient::query()->whereIn('contact_id', $contactIds)->select('id');
                WhatsAppCampaignRecipient::query()->whereIn('id', $recipientIds)->orderBy('id')->chunkById(100, function ($recipients): void {
                    foreach ($recipients as $recipient) {
                        $snapshot = (array) $recipient->snapshot_json;
                        foreach (['name', 'phone', 'custom_fields', 'tags'] as $key) {
                            unset($snapshot[$key]);
                        }
                        WhatsAppCampaignRecipient::withoutEvents(fn () => $recipient->forceFill([
                            'phone' => 'ERASED-'.$recipient->id,
                            'variables_json' => null,
                            'errors_json' => [],
                            'snapshot_json' => [...$snapshot, 'privacy_redacted' => true],
                            'last_error' => null,
                        ])->save());
                    }
                });
                DB::table('whatsapp_message_attempts')->whereIn('recipient_id', $recipientIds)->update([
                    'error_message' => null,
                    'request_payload_redacted' => null,
                    'response_payload_redacted' => null,
                    'updated_at' => now(),
                ]);
            });

        $this->expireRecipientReportExports($clientId);
    }

    /**
     * Stop queued contact automations and remove their copied subject data.
     *
     * @param  list<int>  $contactIds
     */
    private function redactAutomationRuns(array $contactIds): void
    {
        AutomationRun::query()
            ->whereIn('contact_id', $contactIds)
            ->orderBy('id')
            ->chunkById(100, function ($runs): void {
                $runIds = $runs->modelKeys();

                DB::table('automation_run_steps')
                    ->whereIn('automation_run_id', $runIds)
                    ->update(['detail' => null, 'payload' => null]);

                foreach ($runs as $run) {
                    $run->forceFill([
                        'contact_id' => null,
                        'context' => null,
                        'detail' => null,
                        'status' => $run->status->isOpen()
                            ? AutomationRunStatus::Cancelled
                            : $run->status,
                        'resume_at' => null,
                        'finished_at' => $run->finished_at ?? now(),
                    ])->save();
                }
            });
    }

    /**
     * Remove stored recipient-level report files that contain the subject's identifiers.
     */
    private function expireRecipientReportExports(int $clientId): void
    {
        $disk = Storage::disk((string) config('reports.exports.disk', 'local'));

        ReportExport::query()
            ->whereIn('report', [
                ReportType::Contacts->value,
                ReportType::Voice->value,
                ReportType::Sms->value,
                ReportType::Email->value,
            ])
            ->where(function (Builder $query) use ($clientId): void {
                $query->where('client_id', $clientId)
                    ->orWhereNull('client_id');
            })
            ->whereNotNull('file_path')
            ->orderBy('id')
            ->chunkById(100, function ($exports) use ($disk): void {
                foreach ($exports as $export) {
                    if ($export->file_path !== null
                        && $disk->exists($export->file_path)
                        && ! $disk->delete($export->file_path)) {
                        throw new RuntimeException("The recipient report [{$export->file_path}] could not be invalidated.");
                    }

                    $export->forceFill([
                        'file_path' => null,
                        'error_message' => 'The report file was invalidated after an approved privacy erasure request.',
                    ])->save();
                }
            });
    }

    /**
     * Shape user details without authentication secrets.
     *
     * @return array<string, mixed>
     */
    private function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'job_title' => $user->job_title,
            'is_active' => $user->is_active,
            'roles' => $user->getRoleNames()->values()->all(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }

    /**
     * Shape contact details without internal model or delivery payload fields.
     *
     * @return array<string, mixed>
     */
    private function contactData(Contact $contact): array
    {
        return [
            'id' => $contact->id,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'phone' => $contact->phone,
            'original_phone' => $contact->original_phone,
            'alternative_phone' => $contact->alternative_phone,
            'email' => $contact->email,
            'company' => $contact->company,
            'account_number' => $contact->account_number,
            'customer_id' => $contact->customer_id,
            'location' => $contact->location,
            'language' => $contact->language,
            'timezone' => $contact->timezone,
            'dnd' => $contact->dnd,
            'custom_fields' => $contact->custom_fields,
            'created_at' => $contact->created_at?->toIso8601String(),
            'deleted_at' => $contact->deleted_at?->toIso8601String(),
        ];
    }

    /**
     * Write one valid JSON value and track separators for a streamed array.
     *
     * @param  array<string, mixed>  $value
     */
    private function writeArrayItem(array $value, bool &$first): void
    {
        if (! $first) {
            echo ',';
        }

        echo $this->encode($value);
        $first = false;
    }

    /**
     * Encode export data with invalid byte substitution instead of truncating the stream.
     *
     * @param  array<string, mixed>  $value
     */
    private function encode(array $value): string
    {
        return json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE,
        );
    }
}
