<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LegalAcceptanceContext;
use App\Http\Controllers\Controller;
use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use App\Services\Legal\LegalLibrary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The acceptance register: who agreed to which version, when and from where.
 *
 * This is the screen a regulator asks for, so it reads the immutable rows
 * written by {@see LegalLibrary} and offers the same query
 * as a CSV download rather than an editable table.
 */
class LegalAcceptanceController extends Controller
{
    /**
     * List the acknowledgements matching the filters.
     */
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $acceptances = $this->query($filters)
            ->with(['document:id,title,slug', 'version:id,version', 'user:id,name,email', 'client:id,name,code'])
            ->latestFirst()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (LegalAcceptance $acceptance): array => [
                'id' => $acceptance->id,
                'document' => $acceptance->document?->title,
                'document_slug' => $acceptance->document?->slug,
                'version' => $acceptance->version?->label(),
                'context' => $acceptance->context->value,
                'context_label' => $acceptance->context->label(),
                'account' => $acceptance->user?->name,
                'email' => $acceptance->user?->email,
                'client' => $acceptance->client?->name,
                'client_code' => $acceptance->client?->code,
                'ip_address' => $acceptance->ip_address,
                'accepted_at' => $acceptance->accepted_at->toIso8601String(),
            ]);

        return Inertia::render('admin/legal/acceptances', [
            'acceptances' => $acceptances,
            'filters' => $filters,
            'stats' => [
                'total' => LegalAcceptance::query()->count(),
                'last_30_days' => LegalAcceptance::query()->where('accepted_at', '>=', now()->subDays(30))->count(),
                'organisations' => LegalAcceptance::query()->whereNotNull('client_id')->distinct()->count('client_id'),
                'documents' => LegalAcceptance::query()->distinct()->count('legal_document_id'),
            ],
            'documents' => LegalDocument::query()
                ->ordered()
                ->get(['slug', 'title'])
                ->map(fn (LegalDocument $document): array => [
                    'value' => $document->slug,
                    'label' => $document->title,
                ])
                ->all(),
            'contexts' => LegalAcceptanceContext::catalogue(),
        ]);
    }

    /**
     * Download the same register as a spreadsheet-ready file.
     */
    public function export(Request $request): StreamedResponse
    {
        $rows = $this->query($this->filters($request))
            ->with(['document:id,title,slug', 'version:id,version', 'user:id,name,email', 'client:id,name,code'])
            ->latestFirst()
            ->cursor();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'Document', 'Version', 'Organisation', 'Organisation code',
                'Account', 'Email', 'Context', 'IP address', 'Accepted at',
            ]);

            foreach ($rows as $acceptance) {
                fputcsv($handle, [
                    $acceptance->document?->title,
                    $acceptance->version?->version,
                    $acceptance->client?->name,
                    $acceptance->client?->code,
                    $acceptance->user?->name,
                    $acceptance->user?->email,
                    $acceptance->context->label(),
                    $acceptance->ip_address,
                    $acceptance->accepted_at->toIso8601String(),
                ]);
            }

            fclose($handle);
        }, 'legal-acceptances-'.now()->toDateString().'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * The filters shared by the screen and the download.
     *
     * @return array{document: string, context: string, search: string}
     */
    protected function filters(Request $request): array
    {
        return [
            'document' => $request->string('document')->toString(),
            'context' => $request->string('context')->toString(),
            'search' => $request->string('search')->toString(),
        ];
    }

    /**
     * Apply the shared filters to the register.
     *
     * @param  array{document: string, context: string, search: string}  $filters
     * @return Builder<LegalAcceptance>
     */
    protected function query(array $filters): Builder
    {
        return LegalAcceptance::query()
            ->when($filters['document'] !== '', fn (Builder $query) => $query->whereHas(
                'document',
                fn (Builder $query) => $query->where('slug', $filters['document']),
            ))
            ->when($filters['context'] !== '', fn (Builder $query) => $query->where('context', $filters['context']))
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(
                fn (Builder $query) => $query
                    ->whereHas('user', fn (Builder $query) => $query
                        ->where('email', 'like', '%'.$filters['search'].'%')
                        ->orWhere('name', 'like', '%'.$filters['search'].'%'))
                    ->orWhereHas('client', fn (Builder $query) => $query
                        ->where('name', 'like', '%'.$filters['search'].'%')
                        ->orWhere('code', 'like', '%'.$filters['search'].'%')),
            ));
    }
}
