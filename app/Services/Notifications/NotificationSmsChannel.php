<?php

namespace App\Services\Notifications;

use App\Enums\NotificationCategory;
use App\Models\User;
use App\Services\Sms\OutboundSms;
use App\Services\Sms\SmsGatewayManager;
use App\Services\Sms\SmsSegmenter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * The third channel: a short copy of a notification, sent as an SMS.
 *
 * SMS is the only channel the platform pays for per recipient, so it refuses to
 * send unless the platform switched the channel on, the account has a phone
 * number and a sender is configured. A provider failure is logged rather than
 * thrown: a settled payment must not fail because a handset was unreachable.
 */
class NotificationSmsChannel
{
    public function __construct(
        protected SmsGatewayManager $gateways,
        protected SmsSegmenter $segmenter,
    ) {}

    /**
     * Submit the notification as an SMS and report whether it left the platform.
     */
    public function send(
        User $user,
        NotificationCategory $category,
        string $title,
        string $body,
        ?string $actionUrl = null,
    ): bool {
        if (! (bool) config('notifications.sms.enabled', false)) {
            return false;
        }

        $sender = trim((string) config('notifications.sms.sender', ''));

        if ($sender === '') {
            return false;
        }

        $phone = trim((string) $user->phone);

        if ($phone === '') {
            return false;
        }

        $message = $this->compose($title, $body, $actionUrl);
        $analysis = $this->segmenter->analyze($message);

        try {
            $result = $this->gateways->driver()->send(new OutboundSms(
                reference: 'notif-'.Str::uuid()->toString(),
                to: $phone,
                from: $sender,
                body: $message,
                encoding: $analysis['encoding'],
                segments: $analysis['segments'],
            ));
        } catch (Throwable $exception) {
            Log::warning('A notification SMS could not be submitted.', [
                'user_id' => $user->id,
                'category' => $category->value,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }

        if (! $result->accepted) {
            Log::warning('The SMS transport refused a notification.', [
                'user_id' => $user->id,
                'category' => $category->value,
                'reason' => $result->message,
            ]);

            return false;
        }

        return true;
    }

    /**
     * The short copy: the title, the body and the link, trimmed to fit.
     */
    public function compose(string $title, string $body, ?string $actionUrl = null): string
    {
        $message = trim($title).': '.trim($body);

        if ($actionUrl !== null && $actionUrl !== '') {
            $message .= ' '.$actionUrl;
        }

        $limit = max((int) config('notifications.sms.max_length', 320), 40);

        if (mb_strlen($message) <= $limit) {
            return $message;
        }

        return rtrim(mb_substr($message, 0, $limit - 1)).'…';
    }
}
