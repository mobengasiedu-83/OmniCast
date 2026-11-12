<?php

namespace App\Console\Commands;

use App\Models\WhatsAppProvider;
use App\Services\WhatsApp\WhatsAppInvoiceReconciliationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('whatsapp:invoices:import {provider : WhatsApp provider ID} {file : JSON invoice export path}')]
#[Description('Import a provider invoice export and create traceable reconciliation results')]
class ImportWhatsAppProviderInvoice extends Command
{
    public function handle(WhatsAppInvoiceReconciliationService $reconciliation): int
    {
        $provider = WhatsAppProvider::query()->find($this->argument('provider'));
        if ($provider === null) {
            $this->error('WhatsApp provider was not found.');

            return self::FAILURE;
        }
        $path = (string) $this->argument('file');
        if (! File::isFile($path) || ! File::isReadable($path)) {
            $this->error('Invoice JSON file is not readable.');

            return self::FAILURE;
        }
        $data = json_decode(File::get($path), true);
        if (! is_array($data) || ! is_array($data['invoice'] ?? null) || ! is_array($data['lines'] ?? null)) {
            $this->error('Invoice file must contain invoice and lines objects.');

            return self::FAILURE;
        }

        $invoice = $reconciliation->import($provider, $data['invoice'], $data['lines']);
        $this->info("Imported invoice {$invoice->invoice_number}; reconciliation status: {$invoice->status}.");

        return self::SUCCESS;
    }
}
