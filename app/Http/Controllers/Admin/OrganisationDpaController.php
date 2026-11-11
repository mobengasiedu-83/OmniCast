<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LegalDocumentType;
use App\Enums\OrganisationDpaStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Legal\OrganisationDpaRequest;
use App\Http\Requests\Legal\UploadOrganisationDpaEvidenceRequest;
use App\Models\Client;
use App\Models\LegalDocument;
use App\Models\OrganisationDpa;
use App\Models\OrganisationDpaEvidence;
use App\Services\Legal\LegalLibrary;
use App\Services\PlatformConfiguration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

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
            ->with(['organisationDpa.version', 'organisationDpa.evidence.uploader'])
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
                    'evidence' => $dpa?->evidence->map(fn (OrganisationDpaEvidence $evidence): array => [
                        'id' => $evidence->id,
                        'name' => $evidence->original_name,
                        'size_bytes' => $evidence->size_bytes,
                        'sha256' => $evidence->sha256,
                        'uploaded_at' => $evidence->created_at?->toIso8601String(),
                        'uploaded_by' => $evidence->uploader?->name,
                    ])->values()->all() ?? [],
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

    /**
     * Store a countersigned PDF on a configured private disk.
     */
    public function storeEvidence(
        UploadOrganisationDpaEvidenceRequest $request,
        Client $client,
        PlatformConfiguration $configuration,
    ): RedirectResponse {
        $dpa = OrganisationDpa::query()->whereBelongsTo($client)->firstOrFail();
        abort_unless($dpa->status === OrganisationDpaStatus::Signed, 422, 'A signed agreement must be recorded before its evidence can be uploaded.');

        $file = $request->file('document');
        if ($file === null) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Choose a PDF to upload.',
            ]);
        }

        $disk = (string) $configuration->resolve('storage', 'client_documents_disk', 'local', $client);
        $diskConfiguration = config('filesystems.disks.'.$disk);
        abort_unless(is_array($diskConfiguration) && ($diskConfiguration['visibility'] ?? 'private') !== 'public', 422, 'DPA evidence storage must use a configured private disk.');

        $path = $file->store('organisation-dpa-evidence/'.$client->id, $disk);
        if (! is_string($path) || $path === '') {
            throw new RuntimeException('The DPA evidence file could not be stored.');
        }

        $originalName = preg_replace('/[\x00-\x1F\x7F]/u', '', $file->getClientOriginalName()) ?: 'signed-dpa.pdf';
        $originalName = mb_substr($originalName, 0, 255);
        $localPath = $file->getRealPath();
        $sha256 = is_string($localPath) ? hash_file('sha256', $localPath) : false;
        if (! is_string($sha256)) {
            Storage::disk($disk)->delete($path);

            throw new RuntimeException('The DPA evidence file could not be fingerprinted.');
        }

        try {
            $evidence = DB::transaction(fn (): OrganisationDpaEvidence => $dpa->evidence()->create([
                'storage_disk' => $disk,
                'path' => $path,
                'original_name' => $originalName,
                'mime_type' => 'application/pdf',
                'size_bytes' => (int) $file->getSize(),
                'sha256' => $sha256,
                'uploaded_by' => $request->user()?->getAuthIdentifier(),
            ]));
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }

        activity()
            ->causedBy($request->user())
            ->performedOn($evidence)
            ->withProperties(['client_id' => $client->id, 'sha256' => $sha256])
            ->log('uploaded signed data processing agreement evidence');

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Signed DPA evidence uploaded to private storage.',
        ]);
    }

    /**
     * Download evidence only through the platform's authenticated legal area.
     */
    public function downloadEvidence(Client $client, OrganisationDpaEvidence $evidence): StreamedResponse
    {
        abort_unless($evidence->organisationDpa()->where('client_id', $client->id)->exists(), 404);

        $diskConfiguration = config('filesystems.disks.'.$evidence->storage_disk);
        abort_unless(is_array($diskConfiguration) && ($diskConfiguration['visibility'] ?? 'private') !== 'public', 404);
        abort_unless(Storage::disk($evidence->storage_disk)->exists($evidence->path), 404);

        return Storage::disk($evidence->storage_disk)->download(
            $evidence->path,
            $evidence->original_name,
            [
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => 'sandbox',
            ],
        );
    }
}
