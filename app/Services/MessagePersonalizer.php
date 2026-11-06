<?php

namespace App\Services;

use App\Models\Contact;

/**
 * Fills the {{tag}} placeholders in campaign copy with the values held for the
 * recipient the message is about to go to.
 *
 * A placeholder the recipient has no value for is dropped rather than delivered
 * as literal braces, and the gaps it leaves are tidied, so a contact without a
 * first name receives "Hello, your balance is due." instead of
 * "Hello {{first_name}}, your balance is due.".
 */
class MessagePersonalizer
{
    /**
     * The placeholders the campaign editor offers, keyed by tag with the label
     * shown to operators.
     *
     * @var array<string, string>
     */
    public const TAGS = [
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'full_name' => 'Full name',
        'phone' => 'Phone number',
        'email' => 'Email address',
        'company' => 'Company',
        'location' => 'Location',
        'account_number' => 'Account number',
        'customer_id' => 'Customer ID',
    ];

    /**
     * Replace every placeholder in the given copy with the contact's value.
     */
    public function render(string $text, Contact $contact): string
    {
        if (! str_contains($text, '{{')) {
            return $text;
        }

        $rendered = (string) preg_replace_callback(
            '/\{\{\s*([A-Za-z0-9_.-]+)\s*\}\}/',
            fn (array $matches): string => $this->value($matches[1], $contact),
            $text,
        );

        return $this->tidy($rendered);
    }

    /**
     * Resolve one placeholder for the contact, or an empty string when it holds
     * nothing the recipient can be addressed with.
     */
    protected function value(string $tag, Contact $contact): string
    {
        $key = strtolower(trim($tag));

        $value = match ($key) {
            'first_name', 'firstname' => $contact->first_name,
            'last_name', 'lastname' => $contact->last_name,
            'full_name', 'fullname', 'name' => $contact->full_name,
            'phone', 'phone_number', 'msisdn' => $contact->phone,
            'email', 'email_address' => $contact->email,
            'company', 'organisation', 'organization' => $contact->company,
            'location', 'city' => $contact->location,
            'account_number' => $contact->account_number,
            'customer_id' => $contact->customer_id,
            default => $this->customField($key, $contact),
        };

        return trim((string) $value);
    }

    /**
     * Look a placeholder up in the contact's own metadata, so a campaign can
     * address a contact by a field this application does not model itself.
     */
    protected function customField(string $key, Contact $contact): string
    {
        foreach (['custom_fields.', 'custom_field.', 'metadata.', 'meta.'] as $prefix) {
            if (str_starts_with($key, $prefix)) {
                $key = substr($key, strlen($prefix));

                break;
            }
        }

        foreach ($contact->custom_fields ?? [] as $field => $value) {
            if (strtolower((string) $field) === $key) {
                return is_scalar($value) ? (string) $value : '';
            }
        }

        return '';
    }

    /**
     * Close the gaps a removed placeholder leaves behind.
     */
    protected function tidy(string $text): string
    {
        $text = (string) preg_replace('/^[ \t]*[,.;:!?]+[ \t]*/u', '', $text);
        $text = (string) preg_replace('/[ \t]{2,}/u', ' ', $text);
        $text = (string) preg_replace('/[ \t]+([,.!?;:])/u', '$1', $text);

        return trim($text);
    }
}
