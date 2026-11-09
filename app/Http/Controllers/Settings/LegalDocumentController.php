<?php

namespace App\Http\Controllers\Settings;

use App\Enums\LegalAcceptanceContext;
use App\Enums\LegalDocumentType;
use App\Enums\OrganisationDpaStatus;
use App\Http\Controllers\Controller;
use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use App\Models\OrganisationDpa;
use App\Models\User;
use App\Services\Legal\LegalLibrary;
use App\Services\Notifications\SystemNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The tenant's copy of every policy, what it has accepted and its DPA status.
 *
 * The screen is the "Legal & policies" item in settings. It never writes an
 * acceptance for a document the customer has not read: the button posts a
 * request that records the live version of everything still outstanding, with
 * the address and user agent of the person who clicked it.
 */
class LegalDocumentController extends Controller
{
    public function __construct(
        protected LegalLibrary $legal,
        protected SystemNotifier $notifications,
    ) {}

    /**
     * Show the policies, the acceptance history and the organisation's DPA.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $accepted = $this->legal->history($user)
            ->keyBy(fn (LegalAcceptance $acceptance): string => $acceptance->legal_document_id.'-'.$acceptance->legal_document_version_id);

        $documents = $this->legal->documents()
            ->map(function (LegalDocument $document) use ($user, $accepted): array {
                $version = $document->publishedVersion;
                $acceptance = $version === null ? null : $accepted->get($document->id.'-'.$version->id);

                return [
                    'slug' => $document->slug,
                    'title' => $document->title,
                    'summary' => $document->summary,
                    'url' => $document->url(),
                    'version' => $version?->label(),
                    'effective_on' => $version?->effective_on?->toDateString(),
                    'accepted' => $this->legal->hasAccepted($user, $document),
                    'accepted_at' => $acceptance?->accepted_at->toIso8601String(),
                    'accepted_by' => match (true) {
                        $acceptance === null => null,
                        $acceptance->user_id === $user->id => 'You',
                        default => $acceptance->user?->name,
                    },
                ];
            })
            ->values();

        $record = $user->client === null
            ? new OrganisationDpa
            : OrganisationDpa::query()->firstOrNew(['client_id' => $user->client->id]);
        $status = $record->status ?? OrganisationDpaStatus::NotStarted;

        return Inertia::render('settings/legal', [
            'documents' => $documents->all(),
            'outstanding' => $documents->where('accepted', false)->count(),
            'history' => $this->legal->history($user)
                ->map(fn (LegalAcceptance $acceptance): array => [
                    'id' => $acceptance->id,
                    'document' => $acceptance->document?->title,
                    'version' => $acceptance->version?->label(),
                    'context' => $acceptance->context->value,
                    'context_label' => $acceptance->context->label(),
                    'accepted_at' => $acceptance->accepted_at->toIso8601String(),
                    'accepted_by' => $acceptance->user?->name,
                    'ip_address' => $acceptance->ip_address,
                ])
                ->values()
                ->all(),
            'dpa' => [
                'status' => $status->value,
                'status_label' => $status->label(),
                'tone' => $status->tone(),
                'signatory_name' => $record->signatory_name,
                'signed_at' => $record->signed_at?->toIso8601String(),
                'requested_at' => $record->requested_at?->toIso8601String(),
                'notes' => $record->notes,
                'url' => $this->legal->notice(LegalDocumentType::DataProcessingAgreement)['url'] ?? null,
            ],
        ]);
    }

    /**
     * Acknowledge every policy still waiting for this account.
     */
    public function accept(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $recorded = $this->legal->acceptOutstanding(
            $user,
            LegalAcceptanceContext::OrganisationSettings,
            $request->ip(),
            $request->userAgent(),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => $recorded === 1
                ? 'One policy acknowledged. Thank you.'
                : "{$recorded} policies acknowledged. Thank you.",
        ]);
    }

    /**
     * Ask the platform team for a countersigned data processing agreement.
     */
    public function requestDpa(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->client !== null, 403);

        $document = $this->legal->documentOfType(LegalDocumentType::DataProcessingAgreement);
        $dpa = OrganisationDpa::query()->firstOrNew(['client_id' => $user->client->id]);

        if ($dpa->status === OrganisationDpaStatus::Signed) {
            return back()->with('toast', [
                'type' => 'warning',
                'message' => 'Your organisation already has a signed data processing agreement.',
            ]);
        }

        $dpa->fill([
            'legal_document_id' => $document?->id,
            'signatory_name' => $dpa->signatory_name ?? $user->name,
            'signatory_email' => $dpa->signatory_email ?? $user->email,
        ]);
        $dpa->markRequested($document === null ? null : $this->legal->currentVersion($document));

        $this->notifications->dpaRequested($dpa);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'The platform team has been asked for a data processing agreement for your organisation.',
        ]);
    }
}
