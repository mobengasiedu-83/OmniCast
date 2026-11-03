<?php

namespace App\Services;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Log;

/**
 * Development SMS transport: writes the message to the log instead of hitting a network.
 *
 * Swap this binding for the real SMPP/HTTP provider driver in AppServiceProvider.
 */
class LogSmsGateway implements SmsGateway
{
    /**
     * Send an SMS and report whether the network accepted it.
     */
    public function send(string $to, string $from, string $body): bool
    {
        Log::info('SMS dispatched', [
            'to' => $to,
            'from' => $from,
            'body' => $body,
        ]);

        return true;
    }
}
