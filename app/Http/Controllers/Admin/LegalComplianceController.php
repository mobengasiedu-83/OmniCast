<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LegalAcceptanceContext;
use App\Enums\LegalDocumentStatus;
use App\Http\Controllers\Controller;
use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use App\Models\LegalDocumentVersion;
use App\Models\LegalNotification;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The compliance side of the legal menu: versions, where acceptance is
 * collected, what customers were told and how much of the estate has agreed.
 *
 * The four screens read the same tables the rest of the platform writes, so the
 * numbers here are always the numbers an acceptance record would produce.
 */
class LegalComplianceController extends Controller
{
    /**
     * Every version of every document, with the publication state that dates it.
     */
    public function versions(Request $request): Response
    {
        $filters = [
            'document' => $request->string('document')->toString(),
            'status' => $request->string('status')->toString(),
        ];

        $versions = LegalDocumentVersion::query()
            ->with(['document:id,title,slug'])
            ->withCount(['acceptances', 'notifications'])
            ->when($filters['document'] !== '', fn ($query) => $query->whereHas(
                'document',
                fn ($query) => $query->where('slug', $filters['document']),
            ))
            ->when(
                in_array($filters['status'], LegalDocumentStatus::values(), true),
                fn ($query) => $query->where('status', $filters['status']),
            )
            ->latestFirst()
            ->paginate(20)
            ->withQueryString()
            ->through(function (LegalDocumentVersion $version): array {
                $editable = $version->canBeEdited();

                return [
                    'id' => $version->id,
                    'document' => $version->document?->title,
                    'document_slug' => $version->document?->slug,
                    'version' => $version->version,
                    'status' => $version->status->value,
                    'status_label' => $version->status->label(),
                    'status_tone' => $version->status->tone(),
                    'change_summary' => $version->change_summary,
                    'effective_on' => $version->effective_on?->toDateString(),
                    'published_at' => $version->published_at?->toIso8601String(),
                    'notified_at' => $version->notified_at?->toIso8601String(),
                    'requires_reacceptance' => $version->requires_reacceptance,
                    'acceptances_count' => (int) ($version->acceptances_count ?? 0),
                    'notifications_count' => (int) ($version->notifications_count ?? 0),
                    'can_edit' => $editable,
                    'can_delete' => $version->canBeDeleted(),
                    'body' => $editable ? $version->body : null,
                ];
            });

        return Inertia::render('admin/legal/versions', [
            'versions' => $versions,
            'filters' => $filters,
            'documents' => $this->documentOptions(),
            'statuses' => $this->statusOptions(),
            'stats' => [
                'versions' => LegalDocumentVersion::query()->count(),
                'published' => LegalDocumentVersion::query()->where('status', LegalDocumentStatus::Published->value)->count(),
                'drafts' => LegalDocumentVersion::query()->where('status', LegalDocumentStatus::Draft->value)->count(),
                'archived' => LegalDocumentVersion::query()->where('status', LegalDocumentStatus::Archived->value)->count(),
            ],
        ]);
    }

    /**
     * Where each policy's acceptance is collected, as a grid the team can edit.
     */
    public function settings(): Response
    {
        $documents = LegalDocument::query()
            ->ordered()
            ->with(['publishedVersion'])
            ->withCount('acceptances')
            ->get()
            ->map(fn (LegalDocument $document): array => [
                'id' => $document->id,
                'slug' => $document->slug,
                'title' => $document->title,
                'summary' => $document->summary,
                'is_active' => $document->is_active,
                'url' => $document->url(),
                'version' => $document->publishedVersion?->label(),
                'acceptances_count' => (int) ($document->acceptances_count ?? 0),
                'requires' => [
                    'registration' => (bool) $document->requires_acceptance_at_registration,
                    'campaign' => (bool) $document->requires_acceptance_on_campaign_submission,
                    'top_up' => (bool) $document->requires_acceptance_on_wallet_top_up,
                ],
            ])
            ->values()
            ->all();

        return Inertia::render('admin/legal/compliance-settings', [
            'documents' => $documents,
            'stats' => [
                'documents' => count($documents),
                'registration' => count(array_filter($documents, fn (array $document): bool => $document['requires']['registration'])),
                'campaign' => count(array_filter($documents, fn (array $document): bool => $document['requires']['campaign'])),
                'top_up' => count(array_filter($documents, fn (array $document): bool => $document['requires']['top_up'])),
            ],
        ]);
    }

    /**
     * The record of which accounts were told about which policy version.
     */
    public function notifications(Request $request): Response
    {
        $filters = [
            'document' => $request->string('document')->toString(),
            'state' => $request->string('state')->toString(),
        ];

        $notifications = LegalNotification::query()
            ->with([
                'document:id,title,slug',
                'version:id,version',
                'user:id,name,email',
                'client:id,name,code',
            ])
            ->when($filters['document'] !== '', fn ($query) => $query->whereHas(
                'document',
                fn ($query) => $query->where('slug', $filters['document']),
            ))
            ->when($filters['state'] === 'acknowledged', fn ($query) => $query->whereNotNull('acknowledged_at'))
            ->when($filters['state'] === 'pending', fn ($query) => $query->whereNull('acknowledged_at'))
            ->latest('sent_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (LegalNotification $notification): array => [
                'id' => $notification->id,
                'document' => $notification->document?->title,
                'document_slug' => $notification->document?->slug,
                'version' => $notification->version?->label(),
                'account' => $notification->user?->name,
                'email' => $notification->user?->email,
                'client' => $notification->client?->name,
                'client_code' => $notification->client?->code,
                'sent_at' => $notification->sent_at?->toIso8601String(),
                'acknowledged_at' => $notification->acknowledged_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/legal/notifications', [
            'notifications' => $notifications,
            'filters' => $filters,
            'documents' => $this->documentOptions(),
            'stats' => [
                'total' => LegalNotification::query()->count(),
                'last_30_days' => LegalNotification::query()->where('sent_at', '>=', now()->subDays(30))->count(),
                'acknowledged' => LegalNotification::query()->whereNotNull('acknowledged_at')->count(),
                'pending' => LegalNotification::query()->whereNull('acknowledged_at')->count(),
            ],
        ]);
    }

    /**
     * How much of the estate has accepted the policy that is live today.
     *
     * Coverage is counted against active client accounts, which is the
     * population a regulator would expect a policy to reach; it is a reading of
     * the register, not a target the platform enforces.
     */
    public function reports(): Response
    {
        $activeAccounts = User::query()
            ->whereNotNull('client_id')
            ->where('is_active', true)
            ->count();

        $byVersion = LegalAcceptance::query()
            ->selectRaw('legal_document_version_id, count(*) as total, count(distinct user_id) as accounts')
            ->groupBy('legal_document_version_id')
            ->get()
            ->keyBy('legal_document_version_id');

        $byContext = LegalAcceptance::query()
            ->selectRaw('context, count(*) as total')
            ->groupBy('context')
            ->pluck('total', 'context');

        $documents = LegalDocument::query()
            ->ordered()
            ->with(['publishedVersion'])
            ->get()
            ->map(function (LegalDocument $document) use ($byVersion, $activeAccounts): array {
                $version = $document->publishedVersion;
                $row = $version === null ? null : $byVersion->get($version->id);
                $accounts = (int) ($row->accounts ?? 0);

                return [
                    'slug' => $document->slug,
                    'title' => $document->title,
                    'is_active' => $document->is_active,
                    'version' => $version?->label(),
                    'effective_on' => $version?->effective_on?->toDateString(),
                    'acceptances' => (int) ($row->total ?? 0),
                    'accounts' => $accounts,
                    'coverage' => $activeAccounts === 0
                        ? 0
                        : (int) round($accounts / $activeAccounts * 100),
                    'requires' => [
                        'registration' => (bool) $document->requires_acceptance_at_registration,
                        'campaign' => (bool) $document->requires_acceptance_on_campaign_submission,
                        'top_up' => (bool) $document->requires_acceptance_on_wallet_top_up,
                    ],
                ];
            })
            ->values()
            ->all();

        return Inertia::render('admin/legal/reports', [
            'documents' => $documents,
            'contexts' => collect(LegalAcceptanceContext::catalogue())
                ->map(fn (array $context): array => [
                    ...$context,
                    'total' => (int) ($byContext[$context['value']] ?? 0),
                ])
                ->values()
                ->all(),
            'stats' => [
                'acceptances' => LegalAcceptance::query()->count(),
                'accounts_covered' => LegalAcceptance::query()->distinct()->count('user_id'),
                'active_accounts' => $activeAccounts,
                'last_accepted_at' => LegalAcceptance::query()->latest('accepted_at')->value('accepted_at'),
            ],
        ]);
    }

    /**
     * The documents, as filter options.
     *
     * @return list<array{value: string, label: string}>
     */
    protected function documentOptions(): array
    {
        return LegalDocument::query()
            ->ordered()
            ->get(['slug', 'title'])
            ->map(fn (LegalDocument $document): array => [
                'value' => $document->slug,
                'label' => $document->title,
            ])
            ->values()
            ->all();
    }

    /**
     * The version statuses, as filter options.
     *
     * @return list<array{value: string, label: string}>
     */
    protected function statusOptions(): array
    {
        return array_map(fn (LegalDocumentStatus $status): array => [
            'value' => $status->value,
            'label' => $status->label(),
        ], LegalDocumentStatus::cases());
    }
}
