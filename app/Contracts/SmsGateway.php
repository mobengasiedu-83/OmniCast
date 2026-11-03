<?php

namespace App\Contracts;

/**
 * The outbound SMS transport used when a campaign is dispatched.
 */
interface SmsGateway
{
    /**
     * Send an SMS and report whether the network accepted it.
     */
    public function send(string $to, string $from, string $body): bool;
}
