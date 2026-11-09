<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LegalDocumentType;
use App\Enums\OrganisationDpaStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Legal\OrganisationDpaRequest;
use App\Models\Client;
use App\Models\LegalDocument;
use App\Models\OrganisationDpa;
use App\Services\Legal\LegalLibrary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Data processing agreements, tracked per organisation.
 *
 * The list starts from the client accounts rather than from the DPA rows, so an
 * organisation that has not asked for an agreement is still visible — the gap is
 * the point of the screen. Recording a signature links the agreement to the
 * published DPA version it was signed against.
 */
class OrganisationDpaController extends Controller
{
    public function __construct(protected LegalLibrary $legal) {}

    /**
     * List every client account with its agreement status.
     */
    public function index(Request $request): Response
    {
        $filters = [
            'status' => $request->string('status')->toString(),
            'search' => $request->string('search')->toString(),
        ];

        $clients = Client::query()
            ->with(['organisationDpa.version'])
            ->when($filters['status'] === OrganisationDpaStatus::NotStarted->value, fn (Builder $query) => $query->whereDoesntHave('organisationDpa'))
            ->when($filters['status'] !== '' && $filters['status'] !== OrganisationDpaStatus::NotStarted->value, fn (Builder $query) => $query->whereHas(
                'organisationDpa',
                fn (Builder $query) => $query->where('status', $filters['status']),
            ))
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(
                fn (Builder $query) => $query
                    ->where('name', 'like', '%'.$filters['search'].'%')
                    ->orWhere('code', 'like', '%'.$filters['search'].'%'),
            ))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(function (Client $client): array {
                $dpa = $client->organisationDpa;
                $status = $dpa?->status ?? OrganisationDpaStatus::NotStarted;

                return [
                    'client_id' => $client->id,
                    'client' => $client->name,
                    'code' => $client->code,
                    'status' => $status->value,
                    'status_label' => $status->label(),
                    'status_tone' => $status->tone(),
                    'version' => $dpa?->version?->label(),
                    'signatory_name' => $dpa?->signatory_name,
                    'signatory_email' => $dpa?->signatory_email,
                    'requested_at' => $dpa?->requested_at?->toIso8601String(),
                    'signed_at' => $dpa?->signed_at?->toIso8601String(),
                    'notes' => $dpa?->notes,
                ];
            });

        return Inertia::render('admin/legal/dpas', [
            'clients' => $clients,
            'filters' => $filters,
            'statuses' => OrganisationDpaStatus::catalogue(),
            'stats' => [
                'signed' => OrganisationDpa::query()->where('status', OrganisationDpaStatus::Signed->value)->count(),
                'awaiting' => OrganisationDpa::query()->whereIn('status', [
                    OrganisationDpaStatus::Requested->value,
                    OrganisationDpaStatus::AwaitingSignature->value,
                ])->count(),
                'declined' => OrganisationDpa::query()->where('status', OrganisationDpaStatus::Declined->value)->count(),
                'without' => Client::query()->whereDoesntHave('organisationDpa')->count(),
            ],
            'document' => $this->legal->notice(LegalDocumentType::DataProcessingAgreement),
        ]);
    }

    /**
     * Record where an organisation's agreement stands.
     */
    public function update(OrganisationDpaRequest $request, Client $client): RedirectResponse
    {
        $status = $request->status();
        $dpa = OrganisationDpa::query()->firstOrNew(['client_id' => $client->id]);
        $document = LegalDocument::query()->where('slug', LegalDocumentType::DataProcessingAgreement->value)->first();

        $dpa->fill([
            'legal_document_id' => $dpa->legal_document_id ?? $document?->id,
            'status' => $status,
            'signatory_name' => $request->validated('signatory_name'),
            'signatory_email' => $request->validated('signatory_email'),
            'notes' => $request->validated('notes'),
            'signed_at' => $status->isSigned()
                ? ($request->date('signed_at') ?? $dpa->signed_at ?? now())
                : null,
        ]);

        if ($document !== null && $dpa->legal_document_version_id === null) {
            $dpa->legal_document_version_id = $this->legal->currentVersion($document)?->id;
        }

        $dpa->save();

        activity()
            ->causedBy($request->user())
            ->performedOn($dpa)
            ->withProperties(['client' => $client->code, 'status' => $status->value])
            ->log('updated an organisation data processing agreement');

        return back()->with('toast', [
            'type' => 'success',
            'message' => "The data processing agreement for {$client->name} is now \"{$status->label()}\".",
        ]);
    }
}
