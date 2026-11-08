<?php

namespace App\Jobs;

use App\Exceptions\WebhookDeliveryFailed;
use App\Models\WebhookDelivery;
use App\Services\Webhooks\WebhookSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Posts one webhook delivery, with the queue owning the retry policy.
 *
 * The job fails upwards so Laravel's backoff applies; `failed()` is what marks
 * the delivery terminal and counts the failure against the subscription.
 */
class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $deliveryId) {}

    /**
     * The number of attempts the worker will make.
     */
    public function tries(): int
    {
        return max((int) config('webhooks.delivery.tries', 5), 1);
    }

    /**
     * The delay between attempts, in seconds.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return array_values(array_map('intval', (array) config('webhooks.delivery.backoff', [30, 120, 600])));
    }

    /**
     * The number of seconds the worker may spend on the request.
     */
    public function timeout(): int
    {
        return max((int) config('webhooks.delivery.timeout', 15), 1) * 2;
    }

    /**
     * Send the payload and let the queue retry a rejection.
     */
    public function handle(WebhookSender $sender): void
    {
        $delivery = WebhookDelivery::query()->find($this->deliveryId);

        if ($delivery === null || $delivery->status->isTerminal()) {
            return;
        }

        if (! $sender->send($delivery)) {
            throw new WebhookDeliveryFailed('The subscriber did not accept the delivery.');
        }
    }

    /**
     * Store the final failure once the worker has run out of attempts.
     */
    public function failed(?Throwable $exception): void
    {
        $delivery = WebhookDelivery::query()->find($this->deliveryId);

        if ($delivery === null || $delivery->status->isTerminal()) {
            return;
        }

        app(WebhookSender::class)->markFinalFailure($delivery);
    }
}
