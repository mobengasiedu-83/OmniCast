<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SmppBindMode;
use App\Enums\SmppMessageStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\SmppAccount;
use App\Models\SmppDelivery;
use App\Models\SmppMessage;
use App\Models\SmppSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The SMPP accounts an ESME may bind with, and what they did with them.
 *
 * A password is shown once, when it is issued: the server only ever keeps the
 * hash, so an operator who loses it has to rotate rather than look it up.
 */
class SmppController extends Controller
{
    /**
     * List the accounts, the sessions still bound and the latest submissions.
     */
    public function index(Request $request): Response
    {
        $clientId = $request->integer('client_id') ?: null;

        return Inertia::render('admin/smpp/index', [
            'accounts' => SmppAccount::query()
                ->with('client:id,name')
                ->when($clientId !== null, fn ($query) => $query->where('client_id', $clientId))
                ->orderBy('name')
                ->get()
                ->map(fn (SmppAccount $account): array => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'system_id' => $account->system_id,
                    'bind_mode' => $account->bind_mode->value,
                    'bind_mode_label' => $account->bind_mode->label(),
                    'allowed_sources' => $account->allowed_sources ?? [],
                    'throughput_per_second' => $account->throughput_per_second,
                    'is_active' => $account->is_active,
                    'last_bound_at' => $account->last_bound_at?->toIso8601String(),
                    'client' => $account->client === null
                        ? null
                        : ['id' => $account->client->id, 'name' => $account->client->name],
                    'messages_count' => $account->messages()->count(),
                ])
                ->values()
                ->all(),
            'sessions' => SmppSession::query()
                ->with('account:id,name,system_id')
                ->active()
                ->latest('bound_at')
                ->limit(25)
                ->get()
                ->map(fn (SmppSession $session): array => [
                    'id' => $session->id,
                    'system_id' => $session->system_id,
                    'mode' => $session->mode->value,
                    'remote_address' => $session->remote_address,
                    'bound_at' => $session->bound_at?->toIso8601String(),
                    'last_seen_at' => $session->last_seen_at?->toIso8601String(),
                    'pdus_in' => $session->pdus_in,
                    'pdus_out' => $session->pdus_out,
                    'account' => $session->account === null
                        ? null
                        : ['id' => $session->account->id, 'name' => $session->account->name],
                ])
                ->values()
                ->all(),
            'messages' => SmppMessage::query()
                ->with('account:id,name')
                ->latest('id')
                ->limit(25)
                ->get()
                ->map(fn (SmppMessage $message): array => [
                    'id' => $message->id,
                    'reference' => $message->reference,
                    'message_id' => $message->message_id,
                    'source_address' => $message->source_address,
                    'destination_address' => $message->destination_address,
                    'status' => $message->status->value,
                    'status_label' => $message->status->label(),
                    'segments' => $message->segments,
                    'provider' => $message->provider,
                    'account' => $message->account?->name,
                    'submitted_at' => $message->submitted_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'summary' => [
                'accounts' => SmppAccount::query()->count(),
                'active_sessions' => SmppSession::query()->active()->count(),
                'pending_receipts' => SmppDelivery::query()->pending()->count(),
                'accepted_today' => SmppMessage::query()
                    ->whereDate('created_at', now()->toDateString())
                    ->count(),
                'failed_today' => SmppMessage::query()
                    ->whereDate('created_at', now()->toDateString())
                    ->where('status', SmppMessageStatus::Failed->value)
                    ->count(),
            ],
            'bindModes' => SmppBindMode::values(),
            'clients' => Client::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Client $client): array => ['id' => $client->id, 'name' => $client->name])
                ->values()
                ->all(),
            'filters' => ['client_id' => $clientId],
            'server' => [
                'enabled' => (bool) config('smpp.enabled', false),
                'port' => (int) config('smpp.port', 2775),
                'max_sessions' => (int) config('smpp.limits.max_sessions', 100),
            ],
            'newPassword' => $request->session()->get('new_smpp_password'),
        ]);
    }

    /**
     * Issue an SMPP account and show its password once.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateAccount($request);
        $password = Str::password(16, symbols: false);

        $account = SmppAccount::query()->create([
            'client_id' => (int) $validated['client_id'],
            'name' => (string) $validated['name'],
            'system_id' => strtoupper((string) $validated['system_id']),
            'password_hash' => Hash::make($password),
            'bind_mode' => SmppBindMode::from((string) $validated['bind_mode']),
            'allowed_sources' => $this->sources($validated['allowed_sources'] ?? null),
            'throughput_per_second' => (int) ($validated['throughput_per_second'] ?? 10),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'created_by' => $request->user()?->id,
        ]);

        return back()
            ->with('new_smpp_password', [
                'system_id' => $account->system_id,
                'password' => $password,
            ])
            ->with('toast', [
                'type' => 'success',
                'message' => "{$account->system_id} can bind now. Copy the password, it is not shown again.",
            ]);
    }

    /**
     * Change what an existing account may do.
     */
    public function update(Request $request, SmppAccount $account): RedirectResponse
    {
        $validated = $this->validateAccount($request, $account);

        $account->forceFill([
            'name' => (string) $validated['name'],
            'bind_mode' => SmppBindMode::from((string) $validated['bind_mode']),
            'allowed_sources' => $this->sources($validated['allowed_sources'] ?? null),
            'throughput_per_second' => (int) ($validated['throughput_per_second'] ?? 10),
            'is_active' => (bool) ($validated['is_active'] ?? $account->is_active),
        ])->save();

        return back()->with('toast', [
            'type' => 'success',
            'message' => "{$account->system_id} was updated.",
        ]);
    }

    /**
     * Issue a new bind password, invalidating the old one immediately.
     */
    public function rotate(SmppAccount $account): RedirectResponse
    {
        $password = Str::password(16, symbols: false);

        $account->forceFill(['password_hash' => Hash::make($password)])->save();

        return back()
            ->with('new_smpp_password', [
                'system_id' => $account->system_id,
                'password' => $password,
            ])
            ->with('toast', [
                'type' => 'success',
                'message' => "A new password was issued for {$account->system_id}. Existing binds keep working until they reconnect.",
            ]);
    }

    /**
     * Switch an account on or off.
     */
    public function toggle(SmppAccount $account): RedirectResponse
    {
        $account->forceFill(['is_active' => ! $account->is_active])->save();

        return back()->with('toast', [
            'type' => 'success',
            'message' => $account->is_active
                ? "{$account->system_id} may bind again."
                : "{$account->system_id} will be refused at its next bind.",
        ]);
    }

    /**
     * Validate an account form.
     *
     * @return array<string, mixed>
     */
    protected function validateAccount(Request $request, ?SmppAccount $account = null): array
    {
        return $request->validate([
            'client_id' => [
                Rule::requiredIf($account === null),
                'nullable',
                'integer',
                Rule::exists('clients', 'id'),
            ],
            'name' => ['required', 'string', 'max:80'],
            'system_id' => [
                Rule::requiredIf($account === null),
                'nullable',
                'string',
                'max:16',
                'alpha_num',
                Rule::unique('smpp_accounts', 'system_id')->ignore($account?->id),
            ],
            'bind_mode' => ['required', 'string', Rule::in(SmppBindMode::values())],
            'allowed_sources' => ['nullable', 'string', 'max:255'],
            'throughput_per_second' => ['nullable', 'integer', 'min:1', 'max:500'],
            'is_active' => ['boolean'],
        ]);
    }

    /**
     * Split the comma separated sender list into the stored array.
     *
     * @return list<string>|null
     */
    protected function sources(?string $sources): ?array
    {
        if ($sources === null || trim($sources) === '') {
            return null;
        }

        return array_values(array_filter(array_map(
            static fn (string $source): string => strtoupper(trim($source)),
            explode(',', $sources),
        )));
    }
}
