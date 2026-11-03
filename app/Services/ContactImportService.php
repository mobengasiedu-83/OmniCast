<?php

namespace App\Services;

use App\Enums\ContactConsentStatus;
use App\Models\Client;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Imports a contact book from a CSV export of the client's CRM or spreadsheet.
 */
class ContactImportService
{
    public function __construct(protected PhoneNumber $phones) {}

    /**
     * Import the rows of a CSV file into the client's contact book.
     *
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    public function import(
        Client $client,
        UploadedFile $file,
        ?ContactList $list = null,
        ?User $user = null,
    ): array {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw new InvalidArgumentException('The uploaded file could not be read.');
        }

        /** @var list<string>|null $headers */
        $headers = null;
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $line = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $line++;

            $row = array_map(
                fn (mixed $value): string => is_string($value) ? trim($value) : '',
                $row
            );

            if (implode('', $row) === '') {
                continue;
            }

            if ($headers === null) {
                $headers = array_map(
                    fn (string $header): string => Str::snake(str_replace([' ', '-'], '_', $header)),
                    $row
                );

                continue;
            }

            /** @var array<string, string> $data */
            $data = [];

            foreach ($headers as $index => $header) {
                $data[$header] = $row[$index] ?? '';
            }

            $rawPhone = $data['phone'] ?? $data['phone_number'] ?? $data['mobile'] ?? '';
            $phone = $this->phones->normalize($rawPhone);

            if (! $this->phones->isValid($phone)) {
                $skipped++;
                $errors[] = "Line {$line}: \"{$rawPhone}\" is not a valid phone number.";

                continue;
            }

            $firstName = $data['first_name'] ?? $data['name'] ?? '';

            if ($firstName === '') {
                $skipped++;
                $errors[] = "Line {$line}: a first name is required.";

                continue;
            }

            $contact = Contact::query()->updateOrCreate(
                ['client_id' => $client->id, 'phone' => $phone],
                [
                    'first_name' => $firstName,
                    'last_name' => $this->value($data, 'last_name'),
                    'original_phone' => $rawPhone !== '' ? $rawPhone : null,
                    'email' => $this->value($data, 'email'),
                    'company' => $this->value($data, 'company'),
                    'account_number' => $this->value($data, 'account_number'),
                    'customer_id' => $this->value($data, 'customer_id'),
                    'location' => $this->value($data, 'location'),
                    'language' => $this->value($data, 'language') ?? 'en',
                    'timezone' => $this->value($data, 'timezone') ?? $client->timezone,
                    'consent_status' => $this->consent($data['consent_status'] ?? null),
                    'is_do_not_contact' => in_array(strtolower($data['is_do_not_contact'] ?? ''), ['1', 'true', 'yes'], true),
                    'created_by' => $user?->id,
                ],
            );

            $contact->wasRecentlyCreated ? $created++ : $updated++;

            if ($list instanceof ContactList) {
                $list->contacts()->syncWithoutDetaching([$contact->id => ['added_at' => now()]]);
            }
        }

        fclose($handle);

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => array_slice($errors, 0, 20),
        ];
    }

    /**
     * Read an optional column value from the current CSV row.
     *
     * @param  array<string, string>  $data
     */
    protected function value(array $data, string $key): ?string
    {
        $value = $data[$key] ?? '';

        return $value === '' ? null : $value;
    }

    /**
     * Map the CSV consent column onto the consent enum.
     */
    protected function consent(?string $value): ContactConsentStatus
    {
        return ContactConsentStatus::tryFrom(strtolower(trim((string) $value))) ?? ContactConsentStatus::Unknown;
    }
}
