<?php

namespace App\Services\Delivery;

use App\Enums\Channel;
use App\Models\Client;
use App\Services\PlatformConfiguration;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Sleep;

/**
 * Holds every channel to its negotiated submissions per second.
 *
 * A carrier agreement is a ceiling, not a target: the dispatcher asks the pacer
 * before each submission and the pacer sleeps only when the last second has
 * already been filled. Zero means "as fast as the provider answers", which is
 * what the log transports and the test suite use.
 */
class DeliveryPacer
{
    /**
     * The resolved ceiling per channel and client, kept for this run only.
     *
     * @var array<string, int>
     */
    private array $limits = [];

    public function __construct(
        protected RateLimiter $limiter,
        protected PlatformConfiguration $configuration,
    ) {}

    /**
     * Wait, if needed, before the next submission on the channel.
     */
    public function await(Channel $channel, ?Client $client = null): void
    {
        $perSecond = $this->perSecond($channel, $client);

        if ($perSecond < 1) {
            return;
        }

        $key = $this->key($channel);

        if (! $this->limiter->tooManyAttempts($key, $perSecond)) {
            $this->limiter->hit($key, 1);

            return;
        }

        Sleep::for(max($this->limiter->availableIn($key), 1))->milliseconds();

        $this->limiter->clear($key);
        $this->limiter->hit($key, 1);
    }

    /**
     * The submissions per second ceiling for a channel and audience owner.
     */
    public function perSecond(Channel $channel, ?Client $client = null): int
    {
        $key = $this->key($channel).':'.($client?->getKey() ?? 'platform');

        return $this->limits[$key] ??= max(0, (int) $this->configuration->resolve(
            'rate_limits',
            $channel->value.'_cps',
            $this->channelDefault($channel),
            $client,
        ));
    }

    /**
     * Forget the ceilings resolved during this run.
     */
    public function reset(): void
    {
        $this->limits = [];
    }

    /**
     * The platform-wide ceiling configured for the channel.
     */
    protected function channelDefault(Channel $channel): int
    {
        return max((int) config('queues.channels.'.$channel->value.'.cps', 0), 0);
    }

    /**
     * The rate limiter key shared by every worker submitting on the channel.
     */
    protected function key(Channel $channel): string
    {
        return 'delivery-pace:'.$channel->value;
    }
}
