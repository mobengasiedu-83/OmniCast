<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppTemplate;

/** Validates documented Pro Plus bulk and per-recipient template payload shapes. */
class WhatsAppTemplatePayloadValidator
{
    /** @param array<string, mixed> $payload @return array<string, list<string>> */
    public function validate(WhatsAppTemplate $template, array $payload): array
    {
        $errors = [];
        if (! $template->isApproved()) {
            $errors['template'][] = 'Only provider-approved templates can be used.';
        }
        if (! isset($payload['MetaTemplateId']) || ! is_scalar($payload['MetaTemplateId']) || trim((string) $payload['MetaTemplateId']) === '') {
            $errors['MetaTemplateId'][] = 'A provider template identifier is required.';
        } elseif ((string) $payload['MetaTemplateId'] !== $template->meta_template_id) {
            $errors['MetaTemplateId'][] = 'The provider template identifier does not match the selected template.';
        }
        if (! isset($payload['SenderNumber']) || ! is_scalar($payload['SenderNumber']) || trim((string) $payload['SenderNumber']) === '') {
            $errors['SenderNumber'][] = 'A sender number is required.';
        }

        $components = is_array($template->components) ? $template->components : [];
        $bodyVariables = $this->variableCount($components, 'BODY');
        $headerVariables = $this->variableCount($components, 'HEADER');
        $headerFormat = $this->headerFormat($components);
        $recipientPayloads = [];
        if (isset($payload['BulkPayload'])) {
            if (! is_array($payload['BulkPayload']) || array_is_list($payload['BulkPayload'])) {
                $errors['BulkPayload'][] = 'BulkPayload must be an object.';
            } else {
                $recipientPayloads[] = $payload['BulkPayload'];
            }
        }
        if (isset($payload['CustomPayload'])) {
            if (! is_array($payload['CustomPayload']) || ! array_is_list($payload['CustomPayload']) || $payload['CustomPayload'] === []) {
                $errors['CustomPayload'][] = 'CustomPayload must be a non-empty list of recipient objects.';
            } else {
                foreach ($payload['CustomPayload'] as $index => $item) {
                    if (! is_array($item)) {
                        $errors['CustomPayload'][] = "CustomPayload item {$index} must be an object.";

                        continue;
                    }
                    $recipientPayloads[] = $item;
                }
            }
        }
        if ($recipientPayloads === []) {
            $errors['BulkPayload'][] = 'Provide BulkPayload or CustomPayload.';
        }

        foreach ($recipientPayloads as $index => $recipientPayload) {
            $prefix = isset($payload['CustomPayload']) ? "CustomPayload.{$index}" : 'BulkPayload';
            $effectivePayload = array_merge($payload, $recipientPayload);
            if (! isset($recipientPayload['RecieverNumber']) || ! is_scalar($recipientPayload['RecieverNumber']) || trim((string) $recipientPayload['RecieverNumber']) === '') {
                $errors[$prefix.'.RecieverNumber'][] = 'A recipient number is required.';
            }
            $this->validateVariables($recipientPayload, $bodyVariables, $headerVariables, $prefix, $errors);
            $this->validateHeader($effectivePayload, $headerFormat, $prefix, $errors);
            $this->validateLocation($effectivePayload, $recipientPayload, $prefix, $errors);
            $this->validateCarousel($effectivePayload, $prefix, $errors);
            $this->validateOtp($template->category, $components, $recipientPayload, $prefix, $errors);
        }

        if (isset($payload['CustomPayload']) && isset($payload['BulkPayload'])) {
            $errors['payload'][] = 'Use either BulkPayload or CustomPayload, not both.';
        }
        if (isset($payload['LocationDetail']) && ! is_array($payload['LocationDetail'])) {
            $errors['LocationDetail'][] = 'LocationDetail must be an object.';
        }
        if (isset($payload['MediaFilePath']) && ! $this->isHttpsUrl($payload['MediaFilePath'])) {
            $errors['MediaFilePath'][] = 'MediaFilePath must be an absolute HTTPS URL.';
        }
        if ($headerFormat === 'document' && isset($payload['MediaFilePath']) && empty($payload['FileName'])) {
            $errors['FileName'][] = 'FileName is required for document headers.';
        }

        return $errors;
    }

    /** @param list<array<string, mixed>> $components */
    private function variableCount(array $components, string $type): int
    {
        foreach ($components as $component) {
            if (strtoupper((string) ($component['type'] ?? '')) === $type) {
                preg_match_all('/\{\{\s*(?:\d+|[a-zA-Z_][a-zA-Z0-9_]*)\s*\}\}/', (string) ($component['text'] ?? ''), $matches);

                return count($matches[0]);
            }
        }

        return 0;
    }

    /** @param list<array<string, mixed>> $components */
    private function headerFormat(array $components): string
    {
        foreach ($components as $component) {
            if (strtoupper((string) ($component['type'] ?? '')) === 'HEADER') {
                return strtolower((string) ($component['format'] ?? 'text'));
            }
        }

        return 'text';
    }

    /** @param array<string,mixed> $item @param array<string,list<string>> $errors */
    private function validateVariables(array $item, int $bodyCount, int $headerCount, string $prefix, array &$errors): void
    {
        $body = $item['BodyVariables'] ?? [];
        if (! is_array($body) || count($body) < $bodyCount) {
            $errors[$prefix.'.BodyVariables'][] = "Provide {$bodyCount} body variable(s) in order.";
        }
        if ($headerCount > 0 && (! isset($item['HeaderVariable']) || ! is_scalar($item['HeaderVariable']) || trim((string) $item['HeaderVariable']) === '')) {
            $errors[$prefix.'.HeaderVariable'][] = 'A header variable is required.';
        }
        if ($headerCount > 1) {
            $errors[$prefix.'.HeaderVariable'][] = 'The provider payload supports one header variable parameter per recipient.';
        }
    }

    /** @param array<string,mixed> $item @param array<string,list<string>> $errors */
    private function validateHeader(array $item, string $format, string $prefix, array &$errors): void
    {
        if (in_array($format, ['image', 'video', 'document'], true)) {
            if (! $this->isHttpsUrl($item['MediaFilePath'] ?? null)) {
                $errors[$prefix.'.MediaFilePath'][] = "An HTTPS MediaFilePath is required for {$format} headers.";
            }
            if ($format === 'document' && empty($item['FileName'])) {
                $errors[$prefix.'.FileName'][] = 'FileName is required for document headers.';
            }
        }
        if ($format === 'location') {
            $location = $item['LocationDetail'] ?? null;
            if (! is_array($location) || ! is_numeric($location['Latitude'] ?? null) || ! is_numeric($location['Longitude'] ?? null) || empty($location['Name']) || empty($location['Address'])) {
                $errors[$prefix.'.LocationDetail'][] = 'Latitude, Longitude, Name, and Address are required for location headers.';
            }
        }
    }

    /** @param array<string,mixed> $payload @param array<string,mixed> $item @param array<string,list<string>> $errors */
    private function validateLocation(array $payload, array $item, string $prefix, array &$errors): void
    {
        if (isset($payload['LocationDetail'])) {
            $location = $payload['LocationDetail'];
            if (! is_array($location) || ! is_numeric($location['Latitude'] ?? null) || ! is_numeric($location['Longitude'] ?? null) || empty($location['Name']) || empty($location['Address'])) {
                $errors['LocationDetail'][] = 'Latitude, Longitude, Name, and Address are required for location headers.';
            }
        }
        if (isset($item['LocationDetail']) && ! is_array($item['LocationDetail'])) {
            $errors[$prefix.'.LocationDetail'][] = 'LocationDetail must be an object.';
        }
    }

    /** @param array<string,mixed> $item @param array<string,list<string>> $errors */
    private function validateCarousel(array $item, string $prefix, array &$errors): void
    {
        if (! isset($item['CarouselCards'])) {
            return;
        }
        if (! is_array($item['CarouselCards']) || ! array_is_list($item['CarouselCards']) || $item['CarouselCards'] === []) {
            $errors[$prefix.'.CarouselCards'][] = 'CarouselCards must be a non-empty list.';

            return;
        }
        foreach ($item['CarouselCards'] as $index => $card) {
            if (! is_array($card) || ! $this->isHttpsUrl($card['MediaFilePath'] ?? null)) {
                $errors[$prefix.".CarouselCards.{$index}.MediaFilePath"][] = 'Each carousel card needs an HTTPS media URL.';
            }
        }
    }

    /** @param list<array<string,mixed>> $components @param array<string,mixed> $item @param array<string,list<string>> $errors */
    private function validateOtp(?string $category, array $components, array $item, string $prefix, array &$errors): void
    {
        if (strtoupper((string) $category) === 'AUTHENTICATION' && empty($item['BodyVariables'][0])) {
            $errors[$prefix.'.BodyVariables'][] = 'An OTP value is required for an authentication template.';

            return;
        }
        foreach ($components as $component) {
            if (strtoupper((string) ($component['type'] ?? '')) === 'BUTTONS' && stripos((string) ($component['text'] ?? ''), 'otp') !== false && empty($item['BodyVariables'][0])) {
                $errors[$prefix.'.BodyVariables'][] = 'An OTP value is required for an authentication template.';

                return;
            }
        }
    }

    private function isHttpsUrl(mixed $value): bool
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false && str_starts_with(strtolower($value), 'https://');
    }
}
