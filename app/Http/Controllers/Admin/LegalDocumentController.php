<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LegalDocumentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Legal\LegalDocumentRequest;
use App\Http\Requests\Legal\LegalDocumentVersionRequest;
use App\Http\Requests\Legal\LegalRequirementsRequest;
use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use App\Models\LegalDocumentVersion;
use App\Models\LegalNotification;
use App\Services\Legal\LegalLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Legal document management for the platform team.
 *
 * The screen keeps three things apart that are easy to conflate: the document (a
 * permanent identity), its versions (the wording, dated), and publication (which
 * version customers actually read). A new version is written as a draft, so the
 * live policy never changes halfway through a customer reading it, and only a
 * published version can be announced.
 */
class LegalDocumentController extends Controller
{
    public function __construct(protected LegalLibrary $legal) {}

    /**
     * List the documents, their versions and how much has been accepted.
     */
    public function index(Request $request): Response
    {
        $documents = LegalDocument::query()
            ->ordered()
            ->with(['versions' => fn ($query) => $query->withCount(['acceptances', 'notifications'])])
            ->withCount('acceptances')
            ->get()
            ->map(fn (LegalDocument $document): array => $this->payload($document))
            ->values()
            ->all();

        return Inertia::render('admin/legal/documents', [
            'documents' => $documents,
            'stats' => [
                'documents' => count($documents),
                'published' => LegalDocumentVersion::query()->where('status', LegalDocumentStatus::Published->value)->count(),
                'drafts' => LegalDocumentVersion::query()->where('status', LegalDocumentStatus::Draft->value)->count(),
                'acceptances' => LegalAcceptance::query()->count(),
                'notified' => LegalNotification::query()->count(),
            ],
        ]);
    }

    /**
     * Register a new policy document.
     */
    public function store(LegalDocumentRequest $request): RedirectResponse
    {
        $document = LegalDocument::query()->create($request->documentAttributes());

        activity()
            ->causedBy($request->user())
            ->performedOn($document)
            ->log('created a policy document');

        return back()->with('toast', [
            'type' => 'success',
            'message' => "\"{$document->title}\" was created. Write its first version next.",
        ]);
    }

    /**
     * Update the document's identity and acceptance rules.
     */
    public function update(LegalDocumentRequest $request, LegalDocument $document): RedirectResponse
    {
        $document->update($request->documentAttributes());

        activity()
            ->causedBy($request->user())
            ->performedOn($document)
            ->withProperties(['slug' => $document->slug])
            ->log('updated a policy document');

        return back()->with('toast', [
            'type' => 'success',
            'message' => "\"{$document->title}\" was updated.",
        ]);
    }

    /**
     * Change where acceptance of the document is collected.
     *
     * The three switches describe the platform's behaviour rather than the
     * wording a customer agreed to, so flipping them never rewrites a version.
     */
    public function updateRequirements(LegalRequirementsRequest $request, LegalDocument $document): RedirectResponse
    {
        $attributes = $request->requirementAttributes();

        $document->update($attributes);

        activity()
            ->causedBy($request->user())
            ->performedOn($document)
            ->withProperties($attributes)
            ->log('changed where a policy is accepted');

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Where \"{$document->title}\" is accepted was updated.",
        ]);
    }

    /**
     * Delete a document that no account has accepted or been told about.
     *
     * Anything with a compliance trail is kept: withdrawing the document takes
     * it off the public site while the acceptances stay readable.
     */
    public function destroy(Request $request, LegalDocument $document): RedirectResponse
    {
        if (! $document->canBeDeleted()) {
            return back()->with('toast', [
                'type' => 'warning',
                'message' => "\"{$document->title}\" has acceptances or announcements on record, so it cannot be deleted. Withdraw it instead to take it off the public site.",
            ]);
        }

        $title = $document->title;
        $slug = $document->slug;

        DB::transaction(function () use ($document): void {
            $document->versions()->delete();
            $document->delete();
        });

        activity()
            ->causedBy($request->user())
            ->withProperties(['slug' => $slug, 'title' => $title])
            ->log('deleted a policy document');

        return back()->with('toast', [
            'type' => 'success',
            'message' => "\"{$title}\" and its versions were deleted.",
        ]);
    }

    /**
     * Take a document off the public site, or put it back.
     */
    public function toggle(Request $request, LegalDocument $document): RedirectResponse
    {
        $document->forceFill(['is_active' => ! $document->is_active])->save();

        activity()
            ->causedBy($request->user())
            ->performedOn($document)
            ->log($document->is_active ? 'restored a policy document' : 'withdrew a policy document');

        return back()->with('toast', [
            'type' => 'success',
            'message' => $document->is_active
                ? "\"{$document->title}\" is published on the public site again."
                : "\"{$document->title}\" was withdrawn from the public site.",
        ]);
    }

    /**
     * Write a new version of a document, optionally publishing it straight away.
     */
    public function storeVersion(LegalDocumentVersionRequest $request, LegalDocument $document): RedirectResponse
    {
        $version = $document->versions()->create([
            ...$request->versionAttributes(),
            'status' => LegalDocumentStatus::Draft,
        ]);

        activity()
            ->causedBy($request->user())
            ->performedOn($version)
            ->withProperties(['legal_document' => $document->slug, 'version' => $version->version])
            ->log('drafted a policy version');

        if (! $request->publishesImmediately()) {
            return back()->with('toast', [
                'type' => 'success',
                'message' => "Version {$version->version} was saved as a draft. Publish it when the wording is final.",
            ]);
        }

        $version->publish($request->user());

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Version {$version->version} is now live on the public site.",
        ]);
    }

    /**
     * Correct a version's wording while nobody has relied on it yet.
     *
     * Once a version has been accepted or announced the stored text is the
     * evidence, so the only route to a change is a new version.
     */
    public function updateVersion(LegalDocumentVersionRequest $request, LegalDocumentVersion $version): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        if (! $version->canBeEdited()) {
            return back()->with('toast', [
                'type' => 'warning',
                'message' => "Version {$version->version} has been accepted or announced, so its text is now evidence. Write a new version instead.",
            ]);
        }

        $version->update($request->versionAttributes());

        activity()
            ->causedBy($user)
            ->performedOn($version)
            ->withProperties([
                'legal_document' => $version->document?->slug,
                'version' => $version->version,
            ])
            ->log('edited a policy version');

        return back()->with('toast', [
            'type' => 'success',
            'message' => $version->isPublished()
                ? "Version {$version->version} was updated and the public page shows the new text immediately."
                : "Version {$version->version} was updated. It is still a draft.",
        ]);
    }

    /**
     * Make a draft the live version, archiving what was live before.
     */
    public function publish(Request $request, LegalDocumentVersion $version): RedirectResponse
    {
        if ($version->isPublished()) {
            return back()->with('toast', [
                'type' => 'warning',
                'message' => 'That version is already the live one.',
            ]);
        }

        $version->publish($request->user());

        activity()
            ->causedBy($request->user())
            ->performedOn($version)
            ->withProperties(['version' => $version->version])
            ->log('published a policy version');

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Version {$version->version} is now live. Announce it once you are ready for customers to acknowledge it.",
        ]);
    }

    /**
     * Retire a version without replacing it.
     */
    public function archive(Request $request, LegalDocumentVersion $version): RedirectResponse
    {
        $version->archive();

        activity()
            ->causedBy($request->user())
            ->performedOn($version)
            ->withProperties(['version' => $version->version])
            ->log('archived a policy version');

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Version {$version->version} was archived. Customers keep the acceptance they gave.",
        ]);
    }

    /**
     * Remove a version nothing depends on.
     *
     * A version that has been accepted or announced is evidence and can only be
     * archived; a draft, or an announcement that never went out, can go.
     */
    public function destroyVersion(Request $request, LegalDocumentVersion $version): RedirectResponse
    {
        if (! $version->canBeDeleted()) {
            return back()->with('toast', [
                'type' => 'warning',
                'message' => "Version {$version->version} has acceptances or announcements on record, so it cannot be deleted. Archive it instead.",
            ]);
        }

        $label = $version->version;
        $document = $version->document?->title;

        $version->delete();

        activity()
            ->causedBy($request->user())
            ->withProperties(['legal_document' => $document, 'version' => $label])
            ->log('deleted a policy version');

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Version {$label} of \"{$document}\" was deleted.",
        ]);
    }

    /**
     * Announce a published version to every client account.
     */
    public function notify(Request $request, LegalDocumentVersion $version): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        if (! $version->isPublished()) {
            return back()->with('toast', [
                'type' => 'warning',
                'message' => 'Only a published version can be announced. Publish it first.',
            ]);
        }

        $recipients = $this->legal->notifyClients($version, $user);

        return back()->with('toast', [
            'type' => 'success',
            'message' => $recipients === 0
                ? 'No client accounts are active, so nothing was sent.'
                : "{$recipients} client accounts were told about version {$version->version}.",
        ]);
    }

    /**
     * The screen shape of one document and its version history.
     *
     * @return array<string, mixed>
     */
    protected function payload(LegalDocument $document): array
    {
        $published = $document->versions->firstWhere('status', LegalDocumentStatus::Published);

        return [
            'id' => $document->id,
            'slug' => $document->slug,
            'title' => $document->title,
            'summary' => $document->summary,
            'sort_order' => $document->sort_order,
            'is_active' => $document->is_active,
            'url' => $document->url(),
            'print_url' => $document->printUrl(),
            'can_delete' => $document->canBeDeleted(),
            'requires' => [
                'registration' => $document->requires_acceptance_at_registration,
                'campaign' => $document->requires_acceptance_on_campaign_submission,
                'top_up' => $document->requires_acceptance_on_wallet_top_up,
            ],
            'acceptances_count' => $document->acceptances_count ?? 0,
            'published' => $published === null ? null : [
                'id' => $published->id,
                'version' => $published->version,
                'effective_on' => $published->effective_on?->toDateString(),
                'published_at' => $published->published_at?->toIso8601String(),
                'notified_at' => $published->notified_at?->toIso8601String(),
                'requires_reacceptance' => $published->requires_reacceptance,
            ],
            'versions' => $document->versions
                ->map(function (LegalDocumentVersion $version): array {
                    $editable = $version->canBeEdited();

                    return [
                        'id' => $version->id,
                        'version' => $version->version,
                        'status' => $version->status->value,
                        'status_label' => $version->status->label(),
                        'status_tone' => $version->status->tone(),
                        'effective_on' => $version->effective_on?->toDateString(),
                        'published_at' => $version->published_at?->toIso8601String(),
                        'notified_at' => $version->notified_at?->toIso8601String(),
                        'requires_reacceptance' => $version->requires_reacceptance,
                        'change_summary' => $version->change_summary,
                        'acceptances_count' => (int) ($version->acceptances_count ?? 0),
                        'notifications_count' => (int) ($version->notifications_count ?? 0),
                        'can_edit' => $editable,
                        'can_delete' => $version->canBeDeleted(),
                        'body' => $editable ? $version->body : null,
                    ];
                })
                ->values()
                ->all(),
        ];
    }
}
