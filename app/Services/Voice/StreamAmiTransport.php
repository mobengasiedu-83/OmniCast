<?php

namespace App\Services\Voice;

use Illuminate\Support\Facades\Log;

/**
 * Talks to the Asterisk Manager Interface over a TCP socket.
 *
 * Every value written to the socket is flattened to a single line first, so a
 * campaign script or IVR prompt can never inject an extra AMI header.
 */
class StreamAmiTransport implements AmiTransport
{
    /**
     * @var resource|null
     */
    protected $socket = null;

    public function __construct(
        protected string $host = '127.0.0.1',
        protected int $port = 5038,
        protected ?string $username = null,
        protected ?string $secret = null,
        protected int $timeout = 5,
    ) {}

    /**
     * Open the socket and authenticate with the manager account.
     */
    public function connect(): bool
    {
        if (is_resource($this->socket)) {
            return true;
        }

        if ($this->username === null || $this->secret === null) {
            Log::warning('Voice AMI credentials are not configured.');

            return false;
        }

        $socket = @stream_socket_client(
            "tcp://{$this->host}:{$this->port}",
            $errorCode,
            $errorMessage,
            $this->timeout,
        );

        if ($socket === false) {
            Log::warning('Voice AMI connection failed', [
                'host' => $this->host,
                'port' => $this->port,
                'error' => $errorMessage ?? 'unknown error',
                'code' => $errorCode ?? 0,
            ]);

            return false;
        }

        stream_set_timeout($socket, $this->timeout);
        $this->socket = $socket;

        // Asterisk greets with its banner before it accepts any action.
        if ($this->readMessage() === null) {
            $this->disconnect();

            return false;
        }

        $this->write('Login', [
            'Username' => $this->username,
            'Secret' => $this->secret,
            'Events' => 'off',
        ]);

        $response = $this->readMessage();

        if (($response['Response'] ?? null) !== 'Success') {
            Log::warning('Voice AMI login was refused', ['response' => $response]);
            $this->disconnect();

            return false;
        }

        return true;
    }

    /**
     * Send one action and return the response headers, or null on failure.
     *
     * @param  array<string, string|list<string>>  $headers
     * @return array<string, string>|null
     */
    public function execute(string $action, array $headers = []): ?array
    {
        if (! $this->connect()) {
            return null;
        }

        if (! $this->write($action, $headers)) {
            $this->disconnect();

            return null;
        }

        return $this->readMessage();
    }

    /**
     * Close the socket.
     */
    public function disconnect(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }

        $this->socket = null;
    }

    /**
     * Close the socket when the transport goes out of scope.
     */
    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Write a single AMI action, repeating a header for every list value.
     *
     * @param  array<string, string|list<string>>  $headers
     */
    protected function write(string $action, array $headers = []): bool
    {
        if (! is_resource($this->socket)) {
            return false;
        }

        $lines = ['Action: '.$this->sanitise($action)];

        foreach ($headers as $name => $value) {
            foreach ((array) $value as $item) {
                $lines[] = $this->sanitise((string) $name).': '.$this->sanitise((string) $item);
            }
        }

        $lines[] = '';

        return fwrite($this->socket, implode("\r\n", $lines)."\r\n") !== false;
    }

    /**
     * Read one AMI message, which ends with a blank line.
     *
     * @return array<string, string>|null
     */
    protected function readMessage(): ?array
    {
        if (! is_resource($this->socket)) {
            return null;
        }

        $message = [];

        while (($line = fgets($this->socket)) !== false) {
            $line = rtrim($line, "\r\n");

            if ($line === '') {
                if ($message !== []) {
                    return $message;
                }

                continue;
            }

            [$key, $value] = array_pad(explode(':', $line, 2), 2, '');
            $message[trim($key)] = trim($value);
        }

        return $message === [] ? null : $message;
    }

    /**
     * Flatten a value onto one line so it cannot start a new AMI header.
     */
    protected function sanitise(string $value): string
    {
        return trim(str_replace(["\r", "\n"], ' ', $value));
    }
}
