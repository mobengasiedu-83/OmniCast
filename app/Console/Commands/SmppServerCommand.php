<?php

namespace App\Console\Commands;

use App\Services\Smpp\SmppServer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Runs the SMPP server that lets an integration submit messages over SMPP.
 *
 * The loop is a daemon: it is meant to run under supervisor next to the queue
 * workers, which is why it logs to the `sms` channel and answers `SIGTERM` by
 * closing every socket instead of being killed mid-PDU.
 */
class SmppServerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:smpp-server
        {--host= : The interface to listen on, overriding the configured host}
        {--port= : The port to listen on, overriding the configured port}
        {--once : Run a single pass of the loop and exit, which is how the deployment checks the bind}
        {--max-seconds=0 : Stop after this many seconds; 0 runs until the process is stopped}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listen for SMPP binds and submit_sm traffic from approved ESMEs';

    /**
     * Execute the console command.
     */
    public function handle(SmppServer $server): int
    {
        if (! (bool) config('smpp.enabled', false)) {
            $this->warn('The SMPP server is switched off. Set SMPP_SERVER_ENABLED=true to start it.');

            return self::FAILURE;
        }

        try {
            $address = $server->listen(
                $this->option('host') !== null ? (string) $this->option('host') : null,
                $this->option('port') !== null ? (int) $this->option('port') : null,
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("SMPP server listening on {$address}.");

        Log::channel('sms')->info('SMPP server started.', [
            'address' => $address,
            'max_sessions' => (int) config('smpp.limits.max_sessions', 100),
        ]);

        if ((bool) $this->option('once')) {
            $server->tick();
            $server->shutdown();

            $this->info('SMPP server completed a single pass.');

            return self::SUCCESS;
        }

        $this->listenForShutdownSignals($stopping);

        try {
            $server->run(
                shouldStop: fn (): bool => (bool) $stopping[0],
                maxSeconds: max((int) $this->option('max-seconds'), 0) ?: null,
            );
        } catch (Throwable $exception) {
            Log::channel('sms')->error('SMPP server stopped unexpectedly.', [
                'error' => $exception->getMessage(),
            ]);

            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('SMPP server stopped.');

        Log::channel('sms')->info('SMPP server stopped.');

        return self::SUCCESS;
    }

    /**
     * Ask the loop to stop when the supervisor sends a termination signal.
     *
     * @param  array{0: bool}  $stopping
     */
    protected function listenForShutdownSignals(array &$stopping): void
    {
        $stopping = [false];

        if (! function_exists('pcntl_signal')) {
            return;
        }

        $stop = static function () use (&$stopping): void {
            $stopping[0] = true;
        };

        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, $stop);
        pcntl_signal(SIGINT, $stop);
    }
}
