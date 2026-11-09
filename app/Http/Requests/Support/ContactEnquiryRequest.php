<?php

namespace App\Http\Requests\Support;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the public contact form.
 *
 * The category must be one the operator has published for the contact form, so a
 * forged slug can never route an enquiry to a team that was not meant to receive
 * it. Attachment limits come from `config/support.php` so the form and the server
 * agree on what is acceptable.
 */
class ContactEnquiryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxFiles = (int) config('support.attachments.max_files', 3);
        $maxKilobytes = (int) config('support.attachments.max_kilobytes', 5120);
        $mimes = implode(',', (array) config('support.attachments.mimes', []));

        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'string', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'company' => ['nullable', 'string', 'max:160'],
            'category' => [
                'required',
                'string',
                Rule::exists('support_categories', 'slug')
                    ->where('is_active', true)
                    ->whereIn('audience', ['contact', 'both']),
            ],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'min:20', 'max:5000'],
            'consent' => ['accepted'],
            'attachments' => ['nullable', 'array', 'max:'.$maxFiles],
            'attachments.*' => ['file', 'mimes:'.$mimes, 'max:'.$maxKilobytes],
        ];
    }

    /**
     * The message a visitor reads when a rule fails.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'consent.accepted' => 'Please confirm that we may use your details to answer this enquiry.',
            'category.exists' => 'Choose one of the listed contact reasons.',
            'message.min' => 'Tell us a little more so the team can help — at least 20 characters.',
        ];
    }
}
