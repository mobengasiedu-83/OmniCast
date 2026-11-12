<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppProvider;
use App\Models\WhatsAppProviderInvoice;
use App\Models\WhatsAppUsageRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WhatsAppInvoiceReconciliationService
{
    /** @param array<string, mixed> $invoiceData @param list<array<string, mixed>> $lines */
    public function import(WhatsAppProvider $provider, array $invoiceData, array $lines, ?int $importedBy = null): WhatsAppProviderInvoice
    {
        validator(['invoice' => $invoiceData, 'lines' => $lines], [
            'invoice.invoice_number' => ['required', 'string', 'max:191'],
            'invoice.period_start' => ['required', 'date'],
            'invoice.period_end' => ['required', 'date', 'after_or_equal:invoice.period_start'],
            'invoice.currency' => ['required', 'string', 'size:3', 'uppercase'],
            'invoice.total_amount' => ['required', 'numeric', 'min:0'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.reference' => ['required', 'string', 'max:191', 'distinct'],
            'lines.*.amount' => ['required', 'numeric', 'min:0'],
        ])->validate();

        return DB::transaction(function () use ($provider, $invoiceData, $lines, $importedBy): WhatsAppProviderInvoice {
            $invoice = WhatsAppProviderInvoice::query()->firstOrCreate(
                ['provider_id' => $provider->id, 'invoice_number' => $invoiceData['invoice_number']],
                [
                    'period_start' => $invoiceData['period_start'],
                    'period_end' => $invoiceData['period_end'],
                    'currency' => strtoupper($invoiceData['currency']),
                    'total_amount' => $invoiceData['total_amount'],
                    'status' => 'imported',
                    'source_metadata' => ['line_count' => count($lines)],
                    'imported_by' => $importedBy,
                ],
            );
            if (! $invoice->wasRecentlyCreated && $invoice->reconciliations()->exists()) {
                throw ValidationException::withMessages(['invoice_number' => 'This provider invoice has already been imported and reconciled.']);
            }

            $lineTotal = 0.0;
            $internalTotal = 0.0;
            foreach ($lines as $line) {
                $reference = (string) $line['reference'];
                $providerAmount = round((float) $line['amount'], 4);
                $lineTotal += $providerAmount;
                $usage = WhatsAppUsageRecord::query()->where('provider_id', $provider->id)->where('provider_usage_id', $reference)->first();
                $internalAmount = $usage?->internal_cost === null ? 0.0 : (float) $usage->internal_cost;
                $internalTotal += $internalAmount;
                $variance = round($providerAmount - $internalAmount, 4);
                $currencyComparable = strtoupper($invoice->currency) === 'GHS';
                $invoice->reconciliations()->firstOrCreate(
                    ['provider_reference' => $reference],
                    [
                        'usage_record_id' => $usage?->id,
                        'provider_amount' => $providerAmount,
                        'internal_amount' => $internalAmount,
                        'variance' => $variance,
                        'status' => $usage !== null && $usage->internal_cost !== null && $variance === 0.0 && $currencyComparable ? 'matched' : 'review_required',
                        'details' => ['reason' => $usage === null ? 'No internal provider usage record matched this invoice line.' : ($usage->internal_cost === null ? 'Internal provider cost could not be derived from the saved rate snapshot.' : (! $currencyComparable ? 'Invoice currency is not GHS; no approved FX reconciliation input is available.' : ($variance === 0.0 ? null : 'Provider and internal wholesale cost amounts differ.')))],
                    ],
                );
                if ($usage !== null) {
                    $usage->forceFill(['invoice_id' => $invoice->id])->save();
                }
            }

            $totalVariance = round((float) $invoice->total_amount - $lineTotal, 4);
            $invoice->reconciliations()->firstOrCreate(
                ['provider_reference' => 'invoice-total:'.$invoice->invoice_number],
                [
                    'provider_amount' => $invoice->total_amount,
                    'internal_amount' => $internalTotal,
                    'variance' => $totalVariance,
                    'status' => $totalVariance === 0.0 && $internalTotal === $lineTotal && strtoupper($invoice->currency) === 'GHS' ? 'matched' : 'review_required',
                    'details' => ['line_total' => round($lineTotal, 4), 'internal_usage_total' => round($internalTotal, 4)],
                ],
            );
            $hasVariance = $invoice->reconciliations()->where('status', 'review_required')->exists();
            $invoice->forceFill(['status' => $hasVariance ? 'review_required' : 'reconciled'])->save();

            return $invoice->load('reconciliations');
        });
    }
}
