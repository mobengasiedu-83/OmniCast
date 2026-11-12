<?php

namespace App\Services\Delivery;

use App\Enums\Channel;
use App\Models\Campaign;

/**
 * Maps campaign work onto the queue that owns it.
 *
 * A single-channel campaign runs on that channel's queue so SMS, voice and
 * email scale independently; a multi-channel campaign is orchestrated on the
 * shared `campaigns` queue because one pass drives both channels for the same
 * recipient. The queue names must line up with the Horizon supervisors.
 */
class QueueTopology
{
    /**
     * The queue a channel's work is submitted to.
     */
    public function queueForChannel(Channel $channel): string
    {
        $queue = config('queues.channels.'.$channel->value.'.queue');

        return is_string($queue) && $queue !== '' ? $queue : $channel->value;
    }

    /**
     * The distinct queues a campaign submits over.
     *
     * @return list<string>
     */
    public function queuesFor(Campaign $campaign): array
    {
        return array_values(array_unique(array_map(
            fn (Channel $channel): string => $this->queueForChannel($channel),
            $campaign->channel->deliveryChannels(),
        )));
    }

    /**
     * The queue a campaign's dispatch job is released onto.
     */
    public function queueForCampaign(Campaign $campaign): string
    {
        $queues = $this->queuesFor($campaign);

        return count($queues) === 1 ? $queues[0] : $this->campaignsQueue();
    }

    /**
     * The queue that orchestrates a campaign spanning several channels.
     */
    public function campaignsQueue(): string
    {
        return (string) config('queues.campaigns', 'campaigns');
    }

    /** The dedicated WhatsApp provider queue. */
    public function whatsappQueue(): string
    {
        $queue = config('queues.channels.whatsapp.queue');

        return is_string($queue) && $queue !== '' ? $queue : 'whatsapp';
    }

    /**
     * The worker policy for a channel's submissions.
     *
     * @return array{tries: int, backoff: list<int>, timeout: int}
     */
    public function retryPolicy(Channel $channel): array
    {
        $policy = (array) config('queues.channels.'.$channel->value, []);

        return [
            'tries' => max((int) ($policy['tries'] ?? 3), 1),
            'backoff' => array_values(array_map('intval', (array) ($policy['backoff'] ?? [60]))),
            'timeout' => max((int) ($policy['timeout'] ?? 120), 1),
        ];
    }

    /**
     * The worker policy for the campaign dispatcher itself.
     *
     * @return array{tries: int, backoff: list<int>, timeout: int}
     */
    public function dispatchPolicy(): array
    {
        $policy = (array) config('queues.jobs.campaign_dispatch', []);

        return [
            'tries' => max((int) ($policy['tries'] ?? 3), 1),
            'backoff' => array_values(array_map('intval', (array) ($policy['backoff'] ?? [60]))),
            'timeout' => max((int) ($policy['timeout'] ?? 900), 1),
        ];
    }

    /**
     * The submissions per second ceiling configured for a channel.
     */
    public function channelCps(Channel $channel): int
    {
        return max((int) config('queues.channels.'.$channel->value.'.cps', 0), 0);
    }

    /**
     * Every queue the application releases jobs onto, in supervisor order.
     *
     * @return list<array{name: string, purpose: string, channels: list<string>, supervisor: string|null, cps: int, policy: array{tries: int, backoff: list<int>, timeout: int}}>
     */
    public function queues(): array
    {
        $queues = [[
            'name' => $this->campaignsQueue(),
            'purpose' => 'Campaign orchestration, multi-channel passes and retries',
            'channels' => [Channel::MultiChannel->value],
            'supervisor' => $this->supervisorFor($this->campaignsQueue()),
            'cps' => 0,
            'policy' => $this->dispatchPolicy(),
        ]];

        foreach ([Channel::Sms, Channel::Voice, Channel::Email] as $channel) {
            $name = $this->queueForChannel($channel);

            $queues[] = [
                'name' => $name,
                'purpose' => $channel->label().' submissions and receipt-driven resends',
                'channels' => [$channel->value],
                'supervisor' => $this->supervisorFor($name),
                'cps' => $this->channelCps($channel),
                'policy' => $this->retryPolicy($channel),
            ];
        }

        $whatsapp = (array) config('queues.channels.whatsapp', []);
        $whatsappName = $this->whatsappQueue();

        $queues[] = [
            'name' => $whatsappName,
            'purpose' => 'WhatsApp provider work and retries',
            'channels' => ['whatsapp'],
            'supervisor' => $this->supervisorFor($whatsappName),
            'cps' => max((int) ($whatsapp['cps'] ?? 0), 0),
            'policy' => [
                'tries' => max((int) ($whatsapp['tries'] ?? 3), 1),
                'backoff' => array_values(array_map('intval', (array) ($whatsapp['backoff'] ?? [30, 120, 300]))),
                'timeout' => max((int) ($whatsapp['timeout'] ?? 120), 1),
            ],
        ];

        $queues[] = [
            'name' => 'default',
            'purpose' => 'Mailables, notifications and other background work',
            'channels' => [],
            'supervisor' => $this->supervisorFor('default'),
            'cps' => 0,
            'policy' => ['tries' => 3, 'backoff' => [60], 'timeout' => 60],
        ];

        return $queues;
    }

    /**
     * The Horizon supervisor that drains a queue, if one is configured.
     */
    public function supervisorFor(string $queue): ?string
    {
        foreach ((array) config('horizon.defaults', []) as $name => $supervisor) {
            $covered = (array) ($supervisor['queue'] ?? []);

            if (in_array($queue, $covered, true)) {
                return (string) $name;
            }
        }

        return null;
    }
}
