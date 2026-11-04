<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The receipts raised for settled wallet top ups.
 */
class InvoiceController extends Controller
{
    /**
     * List the invoices, newest first.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Invoice::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'status' => $request->string('status')->toString(),
            'client_id' => $request->string('client_id')->toString(),
        ];

        /** @var LengthAwarePaginator<int, array<string, mixed>> $invoices */
        $invoices = $this->query($filters)
            ->with(['client:id,name', 'payment:id,reference,channel'])
            ->latest('issued_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Invoice $invoice): array => $this->row($invoice));

        return Inertia::render('admin/billing/invoices', [
            'invoices' => $invoices,
            'filters' => $filters,
            'stats' => $this->stats($filters),
            'statuses' => $this->statusOptions(),
            'clients' => $this->clientOptions(),
        ]);
    }

    /**
     * The filtered invoice query shared by the table and the statistics.
     *
     * @param  array<string, string>  $filters
     * @return Builder<Invoice>
     */
    protected function query(array $filters): Builder
    {
        return Invoice::query()
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $term = '%'.$filters['search'].'%';

                $query->where(function (Builder $query) use ($term): void {
                    $query->where('number', 'like', $term)
                        ->orWhereHas('payment', fn (Builder $query) => $query->where('reference', 'like', $term));
                });
            })
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['client_id'] !== '', fn (Builder $query) => $query->where('client_id', (int) $filters['client_id']));
    }

    /**
     * Present an invoice for the table.
     *
     * @return array<string, mixed>
     */
    protected function row(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'client_id' => $invoice->client_id,
            'client' => $invoice->client?->name,
            'payment_reference' => $invoice->payment?->reference,
            'channel' => $invoice->payment?->channel,
            'status' => $invoice->status->value,
            'status_label' => $invoice->status->label(),
            'currency' => $invoice->currency,
            'subtotal' => (float) $invoice->subtotal,
            'tax' => (float) $invoice->tax,
            'total' => (float) $invoice->total,
            'lines' => $invoice->lines ?? [],
            'issued_at' => $invoice->issued_at?->toIso8601String(),
            'paid_at' => $invoice->paid_at?->toIso8601String(),
        ];
    }

    /**
     * The headline figures for the invoices matching the filters.
     *
     * @param  array<string, string>  $filters
     * @return array{currency: string, total: int, paid: int, refunded: int, void: int, billed: float}
     */
    protected function stats(array $filters): array
    {
        $unfiltered = $filters;
        $unfiltered['status'] = '';

        return [
            'currency' => (string) config('billing.currency', 'GHS'),
            'total' => $this->query($unfiltered)->count(),
            'paid' => $this->query($unfiltered)->where('status', InvoiceStatus::Paid->value)->count(),
            'refunded' => $this->query($unfiltered)->where('status', InvoiceStatus::Refunded->value)->count(),
            'void' => $this->query($unfiltered)->where('status', InvoiceStatus::Void->value)->count(),
            'billed' => round((float) $this->query($unfiltered)
                ->where('status', InvoiceStatus::Paid->value)
                ->sum('total'), 2),
        ];
    }

    /**
     * The statuses an operator may filter on.
     *
     * @return list<array{value: string, label: string}>
     */
    protected function statusOptions(): array
    {
        return array_map(
            fn (InvoiceStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            InvoiceStatus::cases(),
        );
    }

    /**
     * The client accounts an operator may filter on.
     *
     * @return list<array{id: int, name: string}>
     */
    protected function clientOptions(): array
    {
        return Client::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Client $client): array => ['id' => $client->id, 'name' => $client->name])
            ->all();
    }
}
