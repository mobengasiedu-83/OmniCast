<?php

namespace App\Services\Legal;

use App\Enums\AnnouncementSeverity;
use App\Enums\LegalAcceptanceContext;
use App\Enums\LegalDocumentType;
use App\Enums\NotificationCategory;
use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use App\Models\LegalDocumentVersion;
use App\Models\LegalNotification;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The one place that answers legal questions for the rest of the application.
 *
 * The library resolves which version is live, what an account has already
 * accepted, and records fresh acceptances. It is deliberately the only writer of
 * `legal_acceptances`: every screen that collects consent goes through
 * {@see LegalLibrary::recordAcceptance()}, so the evidence rows keep one shape
 * and one rule — an account is covered by its own acceptance or by a teammate's
 * on the same organisation.
 */
class LegalLibrary
{
    public function __construct(protected NotificationDispatcher $notifications) {}

    /**
     * Every document the public site can show, with its live version loaded.
     *
     * @return Collection<int, LegalDocument>
     */
    public function documents(): Collection
    {
        return LegalDocument::query()
            ->active()
            ->published()
            ->ordered()
            ->with(['publishedVersion'])
            ->get();
    }

    /**
     * Find an active document by its slug.
     */
    public function document(string $slug): ?LegalDocument
    {
        return LegalDocument::query()
            ->active()
            ->with(['publishedVersion'])
            ->where('slug', $slug)
            ->first();
    }

    /**
     * Find one of the five documents the platform ships with.
     */
    public function documentOfType(LegalDocumentType $type): ?LegalDocument
    {
        return $this->document($type->value);
    }

    /**
     * The version customers read today, if anything is published.
     */
    public function currentVersion(LegalDocument $document): ?LegalDocumentVersion
    {
        return $document->versions()->published()->first();
    }

    /**
     * The documents a given activity asks the customer to acknowledge.
     *
     * @return Collection<int, LegalDocument>
     */
    public function documentsFor(LegalAcceptanceContext $context): Collection
    {
        $column = match ($context) {
            LegalAcceptanceContext::Registration => 'requires_acceptance_at_registration',
            LegalAcceptanceContext::CampaignSubmission => 'requires_acceptance_on_campaign_submission',
            LegalAcceptanceContext::WalletTopUp => 'requires_acceptance_on_wallet_top_up',
            LegalAcceptanceContext::OrganisationSettings,
            LegalAcceptanceContext::Dpa => null,
        };

        return LegalDocument::query()
            ->active()
            ->published()
            ->ordered()
            ->when($column !== null, fn (Builder $query) => $query->where($column, true))
            ->with(['publishedVersion'])
            ->get();
    }

    /**
     * The documents the account application collects consent for.
     *
     * @return list<array{slug: string, title: string, summary: string, url: string, version: string, version_id: int}>
     */
    public function registrationDocuments(): array
    {
        return $this->documentsFor(LegalAcceptanceContext::Registration)
            ->filter(fn (LegalDocument $document): bool => $document->publishedVersion !== null)
            ->map(fn (LegalDocument $document): array => $this->documentPayload($document))
            ->values()
            ->all();
    }

    /**
     * The notice a screen prints above its action button.
     *
     * @return array{slug: string, title: string, summary: string, url: string, version: string, version_id: int}|null
     */
    public function notice(LegalDocumentType $type): ?array
    {
        $document = $this->documentOfType($type);

        if ($document === null || $document->publishedVersion === null) {
            return null;
        }

        return $this->documentPayload($document);
    }

    /**
     * The version the account — or a teammate on the same organisation — accepted.
     */
    public function acceptedVersion(User $user, LegalDocument $document): ?LegalDocumentVersion
    {
        $version = $this->currentVersion($document);

        if ($version === null) {
            return null;
        }

        $accepted = $this->acceptanceQuery($user)
            ->where('legal_document_version_id', $version->id)
            ->exists();

        return $accepted ? $version : null;
    }

    /**
     * Whether the account is already covered for the live version.
     */
    public function hasAccepted(User $user, LegalDocument $document): bool
    {
        return $this->acceptedVersion($user, $document) !== null;
    }

    /**
     * The documents still waiting for the account's acknowledgement.
     *
     * @return Collection<int, LegalDocument>
     */
    public function outstanding(User $user): Collection
    {
        return $this->documents()
            ->reject(fn (LegalDocument $document): bool => $this->hasAccepted($user, $document))
            ->values();
    }

    /**
     * Record one acceptance of the live version.
     *
     * Repeated acceptance of the same version on the same screen returns the row
     * that already exists, so a double submit is never mistaken for evidence.
     */
    public function recordAcceptance(
        User $user,
        LegalDocument $document,
        LegalAcceptanceContext $context,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): ?LegalAcceptance {
        $version = $this->currentVersion($document);

        if ($version === null) {
            return null;
        }

        $acceptance = LegalAcceptance::query()->firstOrCreate(
            [
                'legal_document_version_id' => $version->id,
                'user_id' => $user->id,
                'context' => $context->value,
            ],
            [
                'legal_document_id' => $document->id,
                'client_id' => $user->client_id,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent === null ? null : Str::limit($userAgent, 255, ''),
                'accepted_at' => now(),
            ],
        );

        LegalNotification::query()
            ->where('legal_document_version_id', $version->id)
            ->where('user_id', $user->id)
            ->whereNull('acknowledged_at')
            ->update(['acknowledged_at' => now()]);

        return $acceptance;
    }

    /**
     * Record the account's acceptance of several documents at once.
     *
     * @param  iterable<LegalDocument>  $documents
     */
    public function recordAcceptances(
        User $user,
        iterable $documents,
        LegalAcceptanceContext $context,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): int {
        $recorded = 0;

        foreach ($documents as $document) {
            if ($this->recordAcceptance($user, $document, $context, $ipAddress, $userAgent) !== null) {
                $recorded++;
            }
        }

        return $recorded;
    }

    /**
     * Accept every live document the account still owes an answer for.
     */
    public function acceptOutstanding(
        User $user,
        LegalAcceptanceContext $context,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): int {
        return $this->recordAcceptances(
            $user,
            $this->outstanding($user)->filter(fn (LegalDocument $document): bool => $document->publishedVersion !== null),
            $context,
            $ipAddress,
            $userAgent,
        );
    }

    /**
     * Announce a published version to every account on a client tenant.
     *
     * Each message also writes a {@see LegalNotification} row, which is what the
     * compliance report counts. The fan-out runs in chunks so announcing to the
     * whole platform does not hold a single request open.
     */
    public function notifyClients(LegalDocumentVersion $version, User $actor): int
    {
        $document = $version->document;
        $sent = 0;

        User::query()
            ->whereNotNull('client_id')
            ->where('is_active', true)
            ->whereNull('suspended_at')
            ->orderBy('id')
            ->chunkById(200, function (Collection $users) use ($version, $document, &$sent): void {
                foreach ($users as $user) {
                    $delivery = $this->notifications->send(
                        $user,
                        NotificationCategory::Compliance,
                        'Policy updated: '.$document->title,
                        sprintf(
                            '%s was updated to version %s%s. Please review the wording and acknowledge it.',
                            $document->title,
                            $version->version,
                            $version->effective_on === null ? '' : ' effective '.$version->effective_on->toFormattedDateString(),
                        ),
                        [
                            'action_url' => route('legal.settings.index'),
                            'action_label' => 'Review the update',
                            'meta' => [
                                'legal_document_id' => $document->id,
                                'legal_document_version_id' => $version->id,
                            ],
                            'severity' => AnnouncementSeverity::Warning,
                        ],
                    );

                    if ($delivery === null) {
                        continue;
                    }

                    LegalNotification::query()->create([
                        'legal_document_id' => $document->id,
                        'legal_document_version_id' => $version->id,
                        'user_id' => $user->id,
                        'client_id' => $user->client_id,
                        'notification_id' => $delivery->notification?->id,
                        'sent_at' => now(),
                    ]);

                    $sent++;
                }
            });

        $version->markNotified();

        activity()
            ->causedBy($actor)
            ->performedOn($version)
            ->withProperties([
                'legal_document' => $document->slug,
                'version' => $version->version,
                'recipients' => $sent,
            ])
            ->log('announced a policy version to client accounts');

        return $sent;
    }

    /**
     * The acceptance history of one account, and of its organisation.
     *
     * @return Collection<int, LegalAcceptance>
     */
    public function history(User $user): Collection
    {
        return $this->acceptanceQuery($user)
            ->with(['document', 'version'])
            ->latestFirst()
            ->limit(50)
            ->get();
    }

    /**
     * Acceptances recorded by an account or by its organisation.
     *
     * @return Builder<LegalAcceptance>
     */
    protected function acceptanceQuery(User $user): Builder
    {
        return LegalAcceptance::query()->where(
            fn (Builder $query) => $query
                ->where('user_id', $user->id)
                ->orWhere(fn (Builder $query) => $query
                    ->whereNotNull('client_id')
                    ->where('client_id', $user->client_id)),
        );
    }

    /**
     * The screen shape of a document and its live version.
     *
     * @return array{slug: string, title: string, summary: string, url: string, version: string, version_id: int}
     */
    protected function documentPayload(LegalDocument $document): array
    {
        $version = $document->publishedVersion ?? $this->currentVersion($document);

        return [
            'slug' => $document->slug,
            'title' => $document->title,
            'summary' => (string) ($document->summary ?? ''),
            'url' => $document->url(),
            'version' => $version === null ? '' : $version->version,
            'version_id' => $version?->id ?? 0,
        ];
    }
}
