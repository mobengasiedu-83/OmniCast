<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\RecordsActivity;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientResourceRequest;
use App\Models\PlatformSetting;
use App\Services\CountryCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    use RecordsActivity;

    private const GROUPS = ['company', 'channels', 'providers', 'countries', 'rate_limits', 'storage', 'feature_flags', 'plans'];

    public function index(): Response
    {
        Gate::authorize('users.manage');
        $settings = PlatformSetting::query()->get()->map(fn (PlatformSetting $item): array => [
            'group' => $item->group,
            'key' => $item->key,
            'value' => $this->isSecret($item->group, $item->key, $item->is_secret) ? null : $item->value,
            'is_secret' => $this->isSecret($item->group, $item->key, $item->is_secret),
            'has_value' => filled($item->value),
        ]);

        return Inertia::render('admin/settings/index', ['groups' => self::GROUPS, 'settings' => $settings]);
    }

    public function update(Request $request, CountryCatalog $countries): RedirectResponse
    {
        Gate::authorize('users.manage');
        $validated = $request->validate([
            'settings' => ['required', 'array', 'min:1'],
            'settings.*.group' => ['required', 'string', Rule::in(self::GROUPS)],
            'settings.*.key' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9_.-]+$/'],
            'settings.*.value' => ['nullable', 'string', 'max:10000'],
            'settings.*.is_secret' => ['required', 'boolean'],
        ]);
        $changed = [];
        foreach ($validated['settings'] as $attributes) {
            if ($attributes['group'] === 'countries' && $attributes['key'] === 'enabled' && filled($attributes['value'])) {
                $configuredCountries = array_map('trim', explode(',', strtoupper($attributes['value'])));
                $knownCountries = array_column($countries->supported(), 'code');
                if (array_diff($configuredCountries, $knownCountries) !== []) {
                    throw ValidationException::withMessages([
                        'settings' => 'Enabled countries must be valid two-letter ISO country codes.',
                    ]);
                }
            }

            if ($attributes['group'] === 'rate_limits' && filled($attributes['value']) && (! ctype_digit($attributes['value']) || (int) $attributes['value'] < 1)) {
                throw ValidationException::withMessages([
                    'settings' => 'Rate limit settings must be positive whole numbers.',
                ]);
            }

            if ($attributes['group'] === 'storage' && $attributes['key'] === 'max_upload_kb' && filled($attributes['value']) && (! ctype_digit($attributes['value']) || (int) $attributes['value'] < 1)) {
                throw ValidationException::withMessages(['settings' => 'The upload limit must be a positive whole number of kilobytes.']);
            }

            if ($attributes['group'] === 'storage' && $attributes['key'] === 'client_documents_disk' && filled($attributes['value'])) {
                $disk = config('filesystems.disks.'.$attributes['value']);
                if (! is_array($disk) || ($disk['visibility'] ?? 'private') === 'public') {
                    throw ValidationException::withMessages(['settings' => 'Client documents can only use configured private storage disks.']);
                }
            }

            if ($attributes['group'] === 'company' && $attributes['key'] === 'default_timezone' && filled($attributes['value']) && ! in_array($attributes['value'], timezone_identifiers_list(), true)) {
                throw ValidationException::withMessages(['settings' => 'The company default timezone must be a valid timezone identifier.']);
            }

            if (in_array($attributes['group'], ['channels', 'feature_flags'], true) && str_ends_with($attributes['key'], '.enabled') && filled($attributes['value']) && ! in_array(strtolower($attributes['value']), ['true', 'false', '1', '0'], true)) {
                throw ValidationException::withMessages(['settings' => 'Channel and feature flag values must be true or false.']);
            }

            if ($attributes['group'] === 'providers' && $attributes['key'] === 'sms.driver' && filled($attributes['value']) && ! in_array($attributes['value'], ['kannel', 'generic_json', 'txtconnect'], true)) {
                throw ValidationException::withMessages(['settings' => 'The SMS driver must be kannel, generic_json or txtconnect.']);
            }

            if ($attributes['group'] === 'providers' && $attributes['key'] === 'sms.endpoint' && filled($attributes['value'])) {
                $scheme = parse_url($attributes['value'], PHP_URL_SCHEME);
                if (! in_array($scheme, ['http', 'https'], true) || filter_var($attributes['value'], FILTER_VALIDATE_URL) === false) {
                    throw ValidationException::withMessages(['settings' => 'The SMS endpoint must be a valid HTTP or HTTPS URL.']);
                }
            }

            $setting = PlatformSetting::query()->firstOrNew(['group' => $attributes['group'], 'key' => $attributes['key']]);
            $isSecret = $this->isSecret($attributes['group'], $attributes['key'], $attributes['is_secret']);
            if (! $isSecret || filled($attributes['value'])) {
                $setting->value = $attributes['value'];
            }
            $setting->is_secret = $isSecret;
            $setting->updated_by = $request->user()->id;
            $setting->save();
            $changed[] = $attributes['group'].'.'.$attributes['key'];
        }
        $this->recordActivity('updated platform settings', properties: ['keys' => $changed]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Platform settings saved.']);
    }

    private function isSecret(string $group, string $key, bool $isSecret): bool
    {
        return $isSecret || ($group === 'providers' && preg_match('/(?:password|secret|token|api[_\.]?key)$/i', $key) === 1);
    }

    public function requests(): Response
    {
        Gate::authorize('users.manage');

        return Inertia::render('admin/settings/resource-requests', [
            'requests' => ClientResourceRequest::query()->with(['client:id,name', 'requester:id,name'])->latest()->paginate(20),
        ]);
    }

    public function plans(): Response
    {
        Gate::authorize('users.manage');
        $planCodes = PlatformSetting::query()->where('group', 'plans')->get()->map(
            fn (PlatformSetting $setting): string => explode('.', $setting->key, 2)[0],
        )->unique()->values()->prepend('default')->unique()->sort()->values();

        return Inertia::render('admin/settings/plans', [
            'plans' => $planCodes,
            'clients' => Client::query()->orderBy('name')->paginate(25)->through(fn (Client $client): array => [
                'id' => $client->id,
                'name' => $client->name,
                'plan_code' => $client->settings['plan_code'] ?? 'default',
            ]),
        ]);
    }

    public function assignPlan(Request $request, Client $client): RedirectResponse
    {
        Gate::authorize('users.manage');
        $planCodes = PlatformSetting::query()->where('group', 'plans')->get()->map(
            fn (PlatformSetting $setting): string => explode('.', $setting->key, 2)[0],
        )->unique()->prepend('default')->unique()->values()->all();
        $validated = $request->validate(['plan_code' => ['required', 'string', Rule::in($planCodes)]]);
        $settings = $client->settings ?? [];
        $settings['plan_code'] = $validated['plan_code'];
        $client->settings = $settings;
        $client->save();
        $this->recordActivity('assigned client plan', $client, ['plan_code' => $validated['plan_code']]);

        return back()->with('toast', ['type' => 'success', 'message' => "Plan assigned to {$client->name}."]);
    }

    public function reviewRequest(Request $request, ClientResourceRequest $resourceRequest): RedirectResponse
    {
        Gate::authorize('users.manage');
        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $resourceRequest->update([...$validated, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        $this->recordActivity('reviewed client resource request', $resourceRequest, ['status' => $validated['status']]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Resource request reviewed.']);
    }
}
