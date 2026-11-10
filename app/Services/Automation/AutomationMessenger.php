<?php

namespace App\Services\Automation;

use App\Enums\SenderIdChannel;
use App\Enums\SenderIdStatus;
use App\Models\Contact;
use App\Models\SenderId;
use App\Services\Email\EmailBodySanitizer;
use App\Services\Email\EmailGatewayManager;
use App\Services\Email\OutboundEmail;
use App\Services\MessagePersonalizer;
use App\Services\Sms\OutboundSms;
use App\Services\Sms\SmsGatewayManager;
use App\Services\Sms\SmsSegmenter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Delivers the message steps of a workflow on the channel the step names.
 *
 * Automation messages are transactional rather than broadcast: they are not tied
 * to a campaign, so they are submitted straight through the platform transport
 * and their outcome is written onto the run timeline instead of the campaign
 * delivery reports.
 */
class AutomationMessenger
{
    public function __construct(
        private readonly SmsGatewayManager $sms,
        private readonly EmailGatewayManager $email,
        private readonly SmsSegmenter $segmenter,
        private readonly MessagePersonalizer $personalizer,
        private readonly EmailBodySanitizer $sanitizer,
    ) {}

    /**
     * Text the contact, returning the outcome for the run timeline.
     *
     * @return array{accepted: bool, detail: string, payload: array<string, mixed>}
     */
    public function sms(Contact $contact, string $body, ?string $from = null): array
    {
        $to = trim((string) $contact->phone);
        $sender = $from ?? $this->approvedSender($contact, SenderIdChannel::Sms)
            ?? (string) config('automations.sms.from', 'OmniCast');

        if ($to === '' || trim($sender) === '') {
            return [
                'accepted' => false,
                'detail' => 'The contact has no number, or the tenant has no sender ID to send from.',
                'payload' => [],
            ];
        }

        $rendered = $this->personalizer->render($body, $contact);
        $reference = (string) Str::ulid();
        $result = $this->sms->driver()->send(new OutboundSms(
            reference: $reference,
            to: $to,
            from: trim($sender),
            body: $rendered,
            encoding: $this->segmenter->encodingFor($rendered),
            segments: $this->segmenter->segmentsFor($rendered),
        ));

        if (! $result->accepted) {
            Log::channel('sms')->warning('An automation SMS was rejected by the provider.', [
                'reference' => $reference,
                'recipient' => '***'.substr(preg_replace('/\D+/', '', $to) ?? '', -4),
            ]);

            return [
                'accepted' => false,
                'detail' => $result->message ?? 'The SMS provider rejected the message.',
                'payload' => ['reference' => $reference, 'provider' => $this->sms->driver()->name()],
            ];
        }

        return [
            'accepted' => true,
            'detail' => 'SMS accepted by '.$this->sms->driver()->name().' from '.trim($sender).'.',
            'payload' => [
                'reference' => $reference,
                'provider' => $this->sms->driver()->name(),
                'segments' => $this->segmenter->segmentsFor($rendered),
            ],
        ];
    }

    /**
     * Email the contact, returning the outcome for the run timeline.
     *
     * @return array{accepted: bool, detail: string, payload: array<string, mixed>}
     */
    public function email(Contact $contact, string $subject, string $body, ?string $from = null): array
    {
        $to = trim((string) $contact->email);
        $sender = $from
            ?? $this->approvedSender($contact, SenderIdChannel::Email)
            ?? (string) config('automations.email.from', config('email.from.address'));

        if ($to === '' || trim((string) $sender) === '') {
            return [
                'accepted' => false,
                'detail' => 'The contact has no email address, or the tenant has no from address.',
                'payload' => [],
            ];
        }

        $renderedSubject = $this->personalizer->render($subject, $contact);
        $renderedBody = $this->sanitizer->sanitize($this->personalizer->render($body, $contact));
        $reference = (string) Str::ulid();
        $clientName = trim((string) $contact->client?->name);

        $result = $this->email->driver()->send(new OutboundEmail(
            reference: $reference,
            to: $to,
            from: trim((string) $sender),
            subject: $renderedSubject === '' ? 'A message from '.($clientName === '' ? config('app.name') : $clientName) : $renderedSubject,
            body: $renderedBody,
            fromName: $clientName === '' ? null : $clientName,
        ));

        if (! $result->accepted) {
            return [
                'accepted' => false,
                'detail' => $result->message ?? 'The email transport rejected the message.',
                'payload' => ['reference' => $reference, 'provider' => $this->email->driver()->name()],
            ];
        }

        return [
            'accepted' => true,
            'detail' => 'Email accepted by '.$this->email->driver()->name().'.',
            'payload' => ['reference' => $reference, 'provider' => $this->email->driver()->name()],
        ];
    }

    /**
     * The tenant's approved identity for the channel, when it has one.
     */
    protected function approvedSender(Contact $contact, SenderIdChannel $channel): ?string
    {
        $name = SenderId::query()
            ->forChannel($channel)
            ->where('client_id', $contact->client_id)
            ->where('status', SenderIdStatus::Approved->value)
            ->orderByDesc('approved_at')
            ->value('name');

        return is_string($name) && trim($name) !== '' ? trim($name) : null;
    }
}
