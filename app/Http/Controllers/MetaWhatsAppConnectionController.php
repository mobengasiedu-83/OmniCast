<?php

namespace App\Http\Controllers;

use App\Http\Requests\WhatsApp\CompleteMetaWhatsAppSignupRequest;
use App\Services\WhatsApp\MetaWhatsAppEmbeddedSignup;
use App\Services\WhatsApp\WhatsAppAuditLogger;
use Illuminate\Http\RedirectResponse;

class MetaWhatsAppConnectionController extends Controller
{
    public function complete(
        CompleteMetaWhatsAppSignupRequest $request,
        MetaWhatsAppEmbeddedSignup $signup,
        WhatsAppAuditLogger $audit,
    ): RedirectResponse {
        $user = $request->user();
        $client = $user->client;
        abort_if($client === null, 403);

        $result = $signup->complete($client, $user, $request->validated());
        $audit->record('meta_account.connected', $user, $client, $result['connector'], [
            'waba_id' => $result['connector']->waba_id,
            'phone_number_id' => $result['connector']->external_id,
            'templates_synced' => $result['templates_synced'],
        ]);

        $message = $result['templates_sync_failed']
            ? 'Meta account connected. Template sync needs attention; retry it after confirming Meta permissions.'
            : "Meta account connected. {$result['templates_synced']} template(s) synchronized.";

        return to_route('whatsapp.index')->with('toast', ['type' => 'success', 'message' => $message]);
    }
}
