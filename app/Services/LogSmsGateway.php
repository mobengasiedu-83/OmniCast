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
        Log::channel('sms')->info('SMS accepted by the development transport.', [
            'recipient' => $this->maskedNumber($to),
            'sender_id' => $from,
            'message_length' => mb_strlen($body),
        ]);

        return true;
    }

    private function maskedNumber(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return strlen($digits) > 4 ? '***'.substr($digits, -4) : '***';
    }
}
