<?php

namespace App\Services\Smpp;

use App\Enums\SmppCommand;
use App\Enums\SmppMessageStatus;
use App\Enums\SmppStatus;
use App\Models\SmppAccount;
use App\Models\SmppDelivery;
use App\Models\SmppSession;
use App\Services\PhoneNumber;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * The SMPP server loop: accept binds, ingest `submit_sm`, deliver receipts.
 *
 * One tick does everything the daemon must do before it may sleep: accept new
 * ESMEs, read the sockets that have something to say, hand queued receipts to
 * the sessions that are bound, and drop the sessions that went quiet. Keeping a
 * tick callable is what lets the tests drive the protocol without a socket.
 */
class SmppServer
{
    /**
     * The bound and half-bound sockets, keyed by resource id.
     *
     * @var array<int, SmppConnection>
     */
    protected array $connections = [];

    /**
     * The listening socket, once `listen()` has run.
     *
     * @var resource|null
     */
    protected mixed $listener = null;

    protected bool $running = false;

    public function __construct(
        protected SmppMessageIngestor $ingestor,
        protected SmppReceiptRecorder $receipts,
        protected PhoneNumber $phoneNumbers,
    ) {}

    /**
     * Open the listening socket and report the address it bound to.
     */
    public function listen(?string $host = null, ?int $port = null): string
    {
        $host ??= (string) config('smpp.host', '0.0.0.0');
        $port ??= (int) config('smpp.port', 2775);

        $context = stream_context_create([
            'socket' => [
                'backlog' => 128,
                'so_reuseaddr' => true,
            ],
        ]);

        $listener = @stream_socket_server(
            "tcp://{$host}:{$port}",
            $errorCode,
            $errorMessage,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            $context,
        );

        if ($listener === false) {
            throw new RuntimeException(sprintf(
                'The SMPP server could not listen on %s:%d (%s).',
                $host,
                $port,
                $errorMessage !== '' ? $errorMessage : 'error '.$errorCode,
            ));
        }

        stream_set_blocking($listener, false);
        $this->listener = $listener;
        $this->running = true;

        return "{$host}:{$port}";
    }

    /**
     * Whether the loop should keep going.
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Ask the loop to stop after the current tick.
     */
    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * The connections currently attached to the server.
     *
     * @return array<int, SmppConnection>
     */
    public function connections(): array
    {
        return $this->connections;
    }

    /**
     * Take ownership of a connection, as `accept()` does and the tests reuse.
     */
    public function attach(SmppConnection $connection): void
    {
        $this->connections[(int) $connection->stream] = $connection;
    }

    /**
     * Drop a connection and close its session.
     */
    public function detach(SmppConnection $connection): void
    {
        unset($this->connections[(int) $connection->stream]);
        $connection->close();
    }

    /**
     * Run the loop until it is stopped or the deadline passes.
     */
    public function run(?callable $shouldStop = null, ?float $maxSeconds = null): void
    {
        $deadline = $maxSeconds === null ? null : microtime(true) + $maxSeconds;
        $this->running = true;

        while ($this->running) {
            $this->tick();

            if ($shouldStop !== null && $shouldStop()) {
                break;
            }

            if ($deadline !== null && microtime(true) >= $deadline) {
                break;
            }

            usleep(100_000);
        }

        $this->shutdown();
    }

    /**
     * One pass of the loop: accept, read, flush receipts, drop quiet sessions.
     */
    public function tick(): void
    {
        $this->acceptPending();

        foreach ($this->readableConnections() as $connection) {
            $this->receive($connection);
        }

        $this->flushReceipts();
        $this->reapIdleSessions();
    }

    /**
     * Accept every peer waiting in the backlog, up to the session cap.
     */
    protected function acceptPending(): void
    {
        if ($this->listener === null) {
            return;
        }

        $max = max((int) config('smpp.limits.max_sessions', 100), 1);

        while (count($this->connections) < $max) {
            $stream = @stream_socket_accept($this->listener, 0, $peer);

            if ($stream === false) {
                return;
            }

            stream_set_blocking($stream, false);
            $connection = new SmppConnection($stream, $peer === false ? 'unknown' : (string) $peer);
            $this->attach($connection);

            Log::channel('sms')->info('SMPP connection accepted.', ['remote' => $connection->remoteAddress]);
        }
    }

    /**
     * The connections with bytes waiting, as `stream_select` reports them.
     *
     * @return list<SmppConnection>
     */
    protected function readableConnections(): array
    {
        $streams = [];

        foreach ($this->connections as $connection) {
            if (is_resource($connection->stream)) {
                $streams[(int) $connection->stream] = $connection->stream;
            }
        }

        if ($streams === []) {
            return [];
        }

        $read = array_values($streams);
        $write = null;
        $except = null;
        $ready = @stream_select($read, $write, $except, 0, 0);

        if ($ready === false || $ready === 0) {
            return [];
        }

        $connections = [];

        foreach ($read as $stream) {
            $connection = $this->connections[(int) $stream] ?? null;

            if ($connection !== null) {
                $connections[] = $connection;
            }
        }

        return $connections;
    }

    /**
     * Read one connection, handle every whole PDU and keep the partial tail.
     */
    protected function receive(SmppConnection $connection): void
    {
        $chunk = $connection->read();

        if ($chunk === null) {
            Log::channel('sms')->info('SMPP connection closed by the peer.', [
                'remote' => $connection->remoteAddress,
            ]);

            $this->detach($connection);

            return;
        }

        if ($chunk === '') {
            return;
        }

        $connection->buffer .= $chunk;

        try {
            [$pdus, $remainder] = SmppPdu::frames($connection->buffer);
        } catch (InvalidArgumentException $exception) {
            Log::channel('sms')->warning('SMPP stream could not be decoded.', [
                'remote' => $connection->remoteAddress,
                'error' => $exception->getMessage(),
            ]);

            $this->detach($connection);

            return;
        }

        $connection->buffer = $remainder;

        foreach ($pdus as $pdu) {
            if ($connection->session !== null) {
                $connection->session->forceFill([
                    'pdus_in' => $connection->session->pdus_in + 1,
                    'last_seen_at' => now(),
                ])->save();
            }

            $this->handle($connection, $pdu);

            if (! $connection->isOpen()) {
                return;
            }
        }
    }

    /**
     * Answer one PDU.
     */
    public function handle(SmppConnection $connection, SmppPdu $pdu): void
    {
        $mode = $pdu->command->bindMode();

        if ($mode !== null) {
            $this->handleBind($connection, $pdu);

            return;
        }

        match ($pdu->command) {
            SmppCommand::SubmitSm => $this->handleSubmit($connection, $pdu),
            SmppCommand::DeliverSm => $connection->send(SmppPdu::deliverSmResponse($pdu->sequence)),
            SmppCommand::EnquireLink => $connection->send(SmppPdu::enquireLinkResponse($pdu->sequence)),
            SmppCommand::Unbind => $this->handleUnbind($connection, $pdu),
            SmppCommand::EnquireLinkResp => null,
            default => $connection->send(SmppPdu::genericNack($pdu->sequence, SmppStatus::InvalidCommandId)),
        };
    }

    /**
     * Verify a bind against the issued account and open its session.
     */
    protected function handleBind(SmppConnection $connection, SmppPdu $pdu): void
    {
        $mode = $pdu->command->bindMode();

        if ($mode === null) {
            return;
        }

        $systemId = trim((string) $pdu->field('system_id', ''));
        $password = (string) $pdu->field('password', '');

        if (! (bool) config('smpp.enabled', false)) {
            $this->refuseBind($connection, $pdu, $systemId, SmppStatus::BindFailed, 'The SMPP server is switched off.');

            return;
        }

        $account = SmppAccount::query()->where('system_id', $systemId)->first();

        if ($account === null) {
            $this->refuseBind($connection, $pdu, $systemId, SmppStatus::InvalidSystemId, 'The system id was not issued.');

            return;
        }

        if (! Hash::check($password, $account->password_hash)) {
            $this->refuseBind($connection, $pdu, $systemId, SmppStatus::InvalidPassword, 'The password did not match.');

            return;
        }

        if (! $account->allowsBindMode($mode)) {
            $this->refuseBind($connection, $pdu, $systemId, SmppStatus::BindFailed, 'The account may not open this bind.');

            return;
        }

        $session = SmppSession::query()->create([
            'smpp_account_id' => $account->id,
            'system_id' => $account->system_id,
            'mode' => $mode,
            'remote_address' => $connection->remoteAddress,
            'bound_at' => now(),
            'last_seen_at' => now(),
        ]);

        $connection->account = $account;
        $connection->session = $session;

        $account->forceFill(['last_bound_at' => now()])->save();

        $connection->send(SmppPdu::bindResponse($pdu->command, $pdu->sequence, $account->system_id));

        Log::channel('sms')->info('SMPP bind accepted.', [
            'system_id' => $account->system_id,
            'mode' => $mode->value,
            'remote' => $connection->remoteAddress,
        ]);
    }

    /**
     * Refuse a bind, saying why so the ESME operator can fix their side.
     */
    protected function refuseBind(
        SmppConnection $connection,
        SmppPdu $pdu,
        string $systemId,
        SmppStatus $status,
        string $reason,
    ): void {
        $connection->send(SmppPdu::bindResponse($pdu->command, $pdu->sequence, $systemId, $status));

        Log::channel('sms')->warning('SMPP bind refused.', [
            'system_id' => $systemId,
            'status' => $status->label(),
            'remote' => $connection->remoteAddress,
            'reason' => $reason,
        ]);
    }

    /**
     * Ingest a `submit_sm` after checking the session may send it.
     */
    protected function handleSubmit(SmppConnection $connection, SmppPdu $pdu): void
    {
        $account = $connection->account;
        $mode = $connection->bindMode();

        if ($account === null || $mode === null || ! $mode->allowsSubmit()) {
            $connection->send(SmppPdu::submitSmResponse($pdu->sequence, '', SmppStatus::InvalidBindStatus));

            return;
        }

        $destination = trim((string) $pdu->field('destination_addr', ''));
        $source = trim((string) $pdu->field('source_addr', ''));
        $source = $source !== '' ? $source : $this->ingestor->defaultSource();
        $body = (string) $pdu->field('short_message', '');

        if ($body === '') {
            $connection->send(SmppPdu::submitSmResponse($pdu->sequence, '', SmppStatus::InvalidMessageLength));

            return;
        }

        if (! $this->phoneNumbers->isValid($destination)) {
            $connection->send(SmppPdu::submitSmResponse($pdu->sequence, '', SmppStatus::InvalidDestinationAddress));

            return;
        }

        $destination = $this->phoneNumbers->normalize($destination);

        if (! $account->allowsSource($source)) {
            $connection->send(SmppPdu::submitSmResponse($pdu->sequence, '', SmppStatus::InvalidSourceAddress));

            return;
        }

        if (! $connection->allowsSubmit($account->throughput_per_second)) {
            $connection->send(SmppPdu::submitSmResponse($pdu->sequence, '', SmppStatus::Throttled));

            return;
        }

        try {
            $message = $this->ingestor->ingest($account, $connection->session, $source, $destination, $body);
        } catch (Throwable $exception) {
            Log::channel('sms')->error('SMPP submission could not be stored.', [
                'system_id' => $account->system_id,
                'error' => $exception->getMessage(),
            ]);

            $connection->send(SmppPdu::submitSmResponse($pdu->sequence, '', SmppStatus::SystemError));

            return;
        }

        $connection->send(SmppPdu::submitSmResponse($pdu->sequence, $message->message_id));
    }

    /**
     * Acknowledge a teardown and close the session.
     */
    protected function handleUnbind(SmppConnection $connection, SmppPdu $pdu): void
    {
        $connection->send(SmppPdu::unbindResponse($pdu->sequence));
        $this->detach($connection);
    }

    /**
     * Hand every queued receipt to the session that is bound for it.
     *
     * A receipt whose `deliver_sm` is answered is marked sent; an ESME that is
     * offline keeps its receipts queued, so the receipt is not lost because the
     * integration happened to be restarting when the network confirmed a message.
     */
    public function flushReceipts(): int
    {
        $sessions = [];

        foreach ($this->connections as $connection) {
            if ($connection->isBound() && $connection->account !== null) {
                $sessions[$connection->account->system_id][] = $connection;
            }
        }

        if ($sessions === []) {
            return 0;
        }

        $sent = 0;

        $deliveries = SmppDelivery::query()
            ->pending()
            ->whereIn('system_id', array_keys($sessions))
            ->orderBy('id')
            ->limit(500)
            ->get();

        foreach ($deliveries as $delivery) {
            $target = $sessions[$delivery->system_id][0] ?? null;
            $message = $delivery->message;

            if ($target === null || $message === null) {
                continue;
            }

            $status = SmppMessageStatus::fromStat($delivery->stat) ?? SmppMessageStatus::Failed;

            $target->send(SmppPdu::deliverSm(
                sequence: $target->nextSequence(),
                from: $message->destination_address,
                to: $message->source_address,
                receipt: (string) $delivery->text,
                receiptedMessageId: $message->message_id,
                messageState: $status->messageState(),
            ));

            $delivery->forceFill(['sent_at' => now()])->save();
            $sent++;
        }

        return $sent;
    }

    /**
     * Probe quiet sessions and drop the ones that stopped answering.
     */
    protected function reapIdleSessions(): void
    {
        $interval = max((int) config('smpp.limits.enquire_link_interval', 30), 1);
        $idleTimeout = max((int) config('smpp.limits.idle_timeout', 90), 1);

        foreach ($this->connections as $connection) {
            $idle = $connection->idleSeconds();

            if ($idle >= $idleTimeout) {
                Log::channel('sms')->info('SMPP session dropped as idle.', [
                    'remote' => $connection->remoteAddress,
                    'system_id' => $connection->account?->system_id,
                    'idle_seconds' => (int) $idle,
                ]);

                $this->detach($connection);

                continue;
            }

            $sinceProbe = $connection->lastEnquireAt === null ? $idle : microtime(true) - $connection->lastEnquireAt;

            if ($idle >= $interval && $sinceProbe >= $interval) {
                $connection->lastEnquireAt = microtime(true);
                $connection->send(SmppPdu::enquireLink($connection->nextSequence()));
            }
        }
    }

    /**
     * Close every socket, leaving the sessions recorded as closed.
     */
    public function shutdown(): void
    {
        foreach (array_values($this->connections) as $connection) {
            $this->detach($connection);
        }

        if (is_resource($this->listener)) {
            @fclose($this->listener);
        }

        $this->listener = null;
        $this->running = false;
    }
}
