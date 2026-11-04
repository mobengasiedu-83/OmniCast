<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Imports a contact book from a CSV export of the client's CRM or spreadsheet.
 *
 * Only the MSISDN is required: uploaded contacts are assumed to have consented
 * already, so the importer never asks for consent, only for an optional DND flag.
 */
class ContactImportService
{
    /**
     * The header columns of the downloadable upload template.
     *
     * @var list<string>
     */
    public const TEMPLATE_HEADERS = [
        'phone', 'first_name', 'last_name', 'email', 'company', 'account_number',
        'customer_id', 'location', 'language', 'timezone', 'dnd', 'tags', 'custom_loyalty_tier',
    ];

    /**
     * Header names that hold the mandatory MSISDN, in priority order.
     *
     * @var list<string>
     */
    public const PHONE_COLUMNS = ['phone', 'phone_number', 'msisdn', 'mobile'];

    public function __construct(
        protected PhoneNumber $phones,
        protected ContactDndService $dnd,
    ) {}

    /**
     * The upload template with a header row and one example row.
     */
    public static function templateCsv(): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new InvalidArgumentException('The contact template could not be generated.');
        }

        fputcsv($handle, self::TEMPLATE_HEADERS, ',', '"', '\\');
        fputcsv($handle, [
            '+233241234567', 'Ama', 'Mensah', 'ama@example.com', 'Acme Ltd', 'ACC-000123',
            'CUST-1001', 'Accra', 'en', 'Africa/Accra', 'no', 'VIP, Loyalty', 'gold',
        ], ',', '"', '\\');

        rewind($handle);

        $contents = (string) stream_get_contents($handle);

        fclose($handle);

        return $contents;
    }

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

            $rawPhone = (string) ($this->firstFilled($data, self::PHONE_COLUMNS) ?? '');
            $phone = $this->phones->normalize($rawPhone);

            if (! $this->phones->isValid($phone)) {
                $skipped++;
                $errors[] = "Line {$line}: \"{$rawPhone}\" is not a valid phone number.";

                continue;
            }

            $email = $this->value($data, 'email');
            $customFields = collect($data)->filter(fn (string $value, string $key): bool => str_starts_with($key, 'custom_') && $value !== '')
                ->mapWithKeys(fn (string $value, string $key): array => [substr($key, 7) => mb_substr($value, 0, 2000)])
                ->all();
            $attributes = [
                'first_name' => $this->value($data, 'first_name') ?? $this->value($data, 'name'),
                'last_name' => $this->value($data, 'last_name'),
                'original_phone' => $rawPhone !== '' ? $rawPhone : null,
                'email' => $email === null ? null : mb_strtolower($email),
                'company' => $this->value($data, 'company'),
                'account_number' => $this->value($data, 'account_number'),
                'customer_id' => $this->value($data, 'customer_id'),
                'location' => $this->value($data, 'location'),
                'language' => $this->value($data, 'language') ?? 'en',
                'timezone' => $this->value($data, 'timezone') ?? $client->timezone,
                'created_by' => $user?->id,
            ];

            if ($customFields !== []) {
                $attributes['custom_fields'] = $customFields;
            }

            $contact = Contact::withTrashed()
                ->where('client_id', $client->id)
                ->where('phone', $phone)
                ->first();

            if ($contact === null) {
                $contact = new Contact(['client_id' => $client->id, 'phone' => $phone]);
            }

            if ($contact->trashed()) {
                $contact->restore();
            }

            $contact->fill($attributes)->save();

            $dnd = $this->boolean($this->firstFilled($data, ['dnd', 'is_dnd', 'do_not_disturb']));

            if ($dnd !== null) {
                $this->dnd->sync($contact, $dnd, 'csv_import', $user);
            }

            $contact->wasRecentlyCreated ? $created++ : $updated++;

            if ($list instanceof ContactList) {
                $list->contacts()->syncWithoutDetaching([$contact->id => ['added_at' => now()]]);
            }

            $tagNames = array_filter(array_map('trim', explode(',', $data['tags'] ?? '')));
            foreach ($tagNames as $tagName) {
                $tag = Tag::query()->firstOrCreate(['client_id' => $client->id, 'name' => mb_substr($tagName, 0, 100)]);
                $contact->tags()->syncWithoutDetaching([$tag->id]);
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
     * The first non-empty value among the candidate columns.
     *
     * @param  array<string, string>  $data
     * @param  list<string>  $keys
     */
    protected function firstFilled(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $this->value($data, $key);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
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
     * Read a yes/no column, or null when the column was not supplied.
     */
    protected function boolean(?string $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'y', 'on'], true);
    }
}
