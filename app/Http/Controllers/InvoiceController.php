<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Concerns\ResolvesTenantClient;
use App\Models\Client;
use App\Models\Invoice;
use App\Services\Billing\InvoicePdfRenderer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The purchases a client has made, with a PDF copy of each receipt.
 */
class InvoiceController extends Controller
{
    use ResolvesTenantClient;

    public function __construct(protected InvoicePdfRenderer $renderer) {}

    /**
     * List the invoices raised for the client, newest first.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Invoice::class);

        $client = $this->tenantClient($request);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'status' => $request->string('status')->toString(),
        ];

        /** @var LengthAwarePaginator<int, array<string, mixed>> $invoices */
        $invoices = Invoice::query()
            // A platform user who has not picked a client gets an empty page.
            ->where('client_id', $client?->id ?? 0)
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $term = '%'.$filters['search'].'%';

                $query->where(function (Builder $query) use ($term): void {
                    $query->where('number', 'like', $term)
                        ->orWhereHas('payment', fn (Builder $query) => $query->where('reference', 'like', $term));
                });
            })
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->with('payment:id,reference,channel,status,amount,currency,paid_at')
            ->latest('issued_at')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Invoice $invoice): array => $this->row($invoice));

        return Inertia::render('invoices/index', [
            'client' => $client === null ? null : ['id' => $client->id, 'name' => $client->name],
            'invoices' => $invoices,
            'filters' => $filters,
            'summary' => $this->summary($client),
            'statuses' => array_map(
                fn (InvoiceStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                InvoiceStatus::cases(),
            ),
            'currency' => (string) config('billing.currency', 'GHS'),
            'can' => [
                // `view` needs a concrete invoice, so the listing check stands in.
                'download' => $request->user()->can('viewAny', Invoice::class),
            ],
        ]);
    }

    /**
     * Download one invoice as a PDF.
     */
    public function download(Request $request, Invoice $invoice): HttpResponse
    {
        Gate::authorize('view', $invoice);

        $document = $this->renderer->render($invoice);

        return response($document, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($invoice).'"',
            'Content-Length' => (string) strlen($document),
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    /**
     * Present an invoice as a purchase row.
     *
     * @return array<string, mixed>
     */
    protected function row(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'status' => $invoice->status->value,
            'status_label' => $invoice->status->label(),
            'currency' => $invoice->currency,
            'subtotal' => (float) $invoice->subtotal,
            'tax' => (float) $invoice->tax,
            'total' => (float) $invoice->total,
            'description' => $this->description($invoice),
            'payment_reference' => $invoice->payment?->reference,
            'channel' => $invoice->payment?->channel ?? ($invoice->metadata['channel'] ?? null),
            'issued_at' => $invoice->issued_at?->toIso8601String(),
            'paid_at' => $invoice->paid_at?->toIso8601String(),
            'download_url' => route('invoices.download', $invoice),
        ];
    }

    /**
     * The one line summary of what was bought.
     */
    protected function description(Invoice $invoice): string
    {
        $lines = $invoice->lines ?? [];

        if ($lines === []) {
            return 'Wallet top up';
        }

        $first = (string) ($lines[0]['description'] ?? 'Wallet top up');

        return count($lines) === 1 ? $first : $first.' +'.(count($lines) - 1).' more';
    }

    /**
     * The totals shown above the table.
     *
     * @return array{currency: string, count: int, paid: int, spent: float}
     */
    protected function summary(?Client $client): array
    {
        $currency = (string) config('billing.currency', 'GHS');

        if ($client === null) {
            return ['currency' => $currency, 'count' => 0, 'paid' => 0, 'spent' => 0.0];
        }

        return [
            'currency' => $currency,
            'count' => Invoice::query()->where('client_id', $client->id)->count(),
            'paid' => Invoice::query()->where('client_id', $client->id)
                ->where('status', InvoiceStatus::Paid->value)
                ->count(),
            'spent' => round((float) Invoice::query()->where('client_id', $client->id)
                ->where('status', InvoiceStatus::Paid->value)
                ->sum('total'), 2),
        ];
    }

    /**
     * The name the browser saves the PDF under.
     */
    protected function filename(Invoice $invoice): string
    {
        return 'invoice-'.Str::slug($invoice->number ?: (string) $invoice->id).'.pdf';
    }
}
