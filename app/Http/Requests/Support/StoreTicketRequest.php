<?php

namespace App\Http\Requests\Support;

use App\Enums\SupportPriority;
use App\Models\WalletTransaction;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a ticket a signed-in client opens.
 *
 * The campaign and wallet movement are optional context, and each must belong to
 * the client's own organisation — an id from another tenant is rejected rather
 * than silently ignored, because it is a probe, not a typo.
 */
class StoreTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->client_id !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $clientId = $this->user()?->client_id;
        $maxFiles = (int) config('support.attachments.max_files', 3);
        $maxKilobytes = (int) config('support.attachments.max_kilobytes', 5120);
        $mimes = implode(',', (array) config('support.attachments.mimes', []));

        return [
            'category' => [
                'required',
                'string',
                Rule::exists('support_categories', 'slug')
                    ->where('is_active', true)
                    ->whereIn('audience', ['ticket', 'both']),
            ],
            'subject' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'priority' => ['nullable', Rule::enum(SupportPriority::class)],
            'campaign_id' => [
                'nullable',
                'integer',
                Rule::exists('campaigns', 'id')->where('client_id', $clientId),
            ],
            'wallet_transaction_id' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, Closure $fail) use ($clientId): void {
                    $owned = WalletTransaction::query()
                        ->whereKey($value)
                        ->whereHas('wallet', fn (Builder $query) => $query->where('client_id', $clientId))
                        ->exists();

                    if (! $owned) {
                        $fail('Choose a wallet movement that belongs to your organisation.');
                    }
                },
            ],
            'attachments' => ['nullable', 'array', 'max:'.$maxFiles],
            'attachments.*' => ['file', 'mimes:'.$mimes, 'max:'.$maxKilobytes],
        ];
    }

    /**
     * The message a client reads when a rule fails.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category.exists' => 'Choose one of the listed support categories.',
            'description.min' => 'Describe the problem in at least 20 characters so the team can act on it.',
        ];
    }
}
