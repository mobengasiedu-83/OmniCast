<?php

namespace App\Services\Voice;

/**
 * The socket conversation the Asterisk Manager Interface needs.
 *
 * Split out from the gateway so the originate action can be asserted in tests
 * without an Asterisk server: the gateway only ever sees responses.
 */
interface AmiTransport
{
    /**
     * Open the socket and authenticate with the manager account.
     */
    public function connect(): bool;

    /**
     * Send one action and return the response headers, or null on failure.
     *
     * @param  array<string, string|list<string>>  $headers
     * @return array<string, string>|null
     */
    public function execute(string $action, array $headers = []): ?array;

    /**
     * Close the socket.
     */
    public function disconnect(): void;
}
