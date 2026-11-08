<?php

namespace App\Services\Smpp;

use App\Enums\SmppBindMode;
use App\Models\SmppAccount;
use App\Models\SmppSession;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One accepted ESME socket and everything the server knows about it.
 *
 * The connection owns the read buffer, the bind it was granted and the submit
 * window used for throttling, so the server loop stays free of per-socket state.
 */
class SmppConnection
{
    /**
     * Bytes read but not yet a complete PDU.
     */
    public string $buffer = '';

    public ?SmppAccount $account = null;

    public ?SmppSession $session = null;

    /**
     * When the socket last carried traffic, in `microtime(true)` seconds.
     */
    public float $lastActivityAt;

    /**
     * When the server last probed the session with `enquire_link`.
     */
    public ?float $lastEnquireAt = null;

    /**
     * The sequence number the server will use next.
     */
    protected int $sequence = 0;

    /**
     * The timestamps of the recent submissions, for the throughput window.
     *
     * @var list<float>
     */
    protected array $submitWindow = [];

    /**
     * @param  resource  $stream
     */
    public function __construct(
        public readonly mixed $stream,
        public readonly string $remoteAddress,
    ) {
        $this->lastActivityAt = microtime(true);
    }

    /**
     * The sequence number for the next server-initiated PDU.
     */
    public function nextSequence(): int
    {
        return ++$this->sequence;
    }

    /**
     * The bind mode the session was granted.
     */
    public function bindMode(): ?SmppBindMode
    {
        return $this->session?->mode;
    }

    /**
     * Whether the ESME has bound and may submit or receive messages.
     */
    public function isBound(): bool
    {
        return $this->session !== null && $this->session->unbound_at === null;
    }

    /**
     * Note that the socket carried traffic.
     */
    public function touch(): void
    {
        $this->lastActivityAt = microtime(true);
    }

    /**
     * Seconds since the socket last carried traffic.
     */
    public function idleSeconds(): float
    {
        return max(microtime(true) - $this->lastActivityAt, 0.0);
    }

    /**
     * Whether the socket is still usable.
     */
    public function isOpen(): bool
    {
        return is_resource($this->stream) && ! feof($this->stream);
    }

    /**
     * Write one PDU, counting it against the session for the operations screen.
     */
    public function send(SmppPdu $pdu): bool
    {
        if (! $this->isOpen()) {
            return false;
        }

        $bytes = $pdu->toBytes();
        $length = strlen($bytes);
        $written = 0;

        try {
            while ($written < $length) {
                $chunk = @fwrite($this->stream, substr($bytes, $written));

                if ($chunk === false || $chunk === 0) {
                    return false;
                }

                $written += $chunk;
            }
        } catch (Throwable $exception) {
            Log::channel('sms')->warning('SMPP write failed.', [
                'remote' => $this->remoteAddress,
                'command' => $pdu->command->label(),
                'error' => $exception->getMessage(),
            ]);

            return false;
        }

        $this->touch();

        if ($this->session !== null) {
            $this->session->forceFill(['pdus_out' => $this->session->pdus_out + 1])->save();
        }

        return true;
    }

    /**
     * Read whatever is available, or null when the peer closed the socket.
     */
    public function read(): ?string
    {
        if (! $this->isOpen()) {
            return null;
        }

        $chunk = @fread($this->stream, max((int) config('smpp.limits.read_chunk_bytes', 65536), 1024));

        if ($chunk === false) {
            return null;
        }

        if ($chunk === '') {
            return feof($this->stream) ? null : '';
        }

        $this->touch();

        return $chunk;
    }

    /**
     * Whether the account's throughput ceiling allows one more submission now.
     */
    public function allowsSubmit(int $perSecond): bool
    {
        $now = microtime(true);

        $this->submitWindow = array_values(array_filter(
            $this->submitWindow,
            fn (float $at): bool => $now - $at < 1.0,
        ));

        if ($perSecond > 0 && count($this->submitWindow) >= $perSecond) {
            return false;
        }

        $this->submitWindow[] = $now;

        return true;
    }

    /**
     * Close the session row and the socket.
     */
    public function close(): void
    {
        $this->session?->close();

        if (is_resource($this->stream)) {
            @fclose($this->stream);
        }

        $this->buffer = '';
    }
}
