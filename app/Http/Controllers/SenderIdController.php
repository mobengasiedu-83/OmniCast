<?php

namespace App\Http\Controllers;

use App\Enums\SenderIdStatus;
use App\Http\Controllers\Concerns\ResolvesTenantClient;
use App\Http\Requests\SenderId\SenderIdRequest;
use App\Models\CallerId;
use App\Models\SenderId;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SenderIdController extends Controller
{
    use ResolvesTenantClient;

    /**
     * List the sender IDs requested by the client.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', SenderId::class);

        $client = $this->tenantClient($request);

        $senderIds = SenderId::query()
            ->forUser($request->user())
            ->when($client !== null, fn ($query) => $query->where('client_id', $client->id))
            ->latest()
            ->get()
            ->map(fn (SenderId $senderId): array => [
                'id' => $senderId->id,
                'name' => $senderId->name,
                'country' => $senderId->country,
                'purpose' => $senderId->purpose,
                'status' => $senderId->status->value,
                'status_label' => $senderId->status->label(),
                'requested_at' => $senderId->requested_at?->toIso8601String(),
                'approved_at' => $senderId->approved_at?->toIso8601String(),
                'rejection_reason' => $senderId->rejection_reason,
            ]);

        $callerIds = CallerId::query()
            ->forUser($request->user())
            ->when($client !== null, fn ($query) => $query->where('client_id', $client->id))
            ->orderBy('number')
            ->get()
            ->map(fn (CallerId $callerId): array => [
                'id' => $callerId->id,
                'number' => $callerId->number,
                'label' => $callerId->label,
                'status' => $callerId->status->value,
                'status_label' => $callerId->status->label(),
            ]);

        return Inertia::render('sender-ids/index', [
            'senderIds' => $senderIds,
            'callerIds' => $callerIds,
            'statuses' => array_map(
                fn (SenderIdStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                SenderIdStatus::cases(),
            ),
            'can' => [
                'create' => Gate::allows('create', SenderId::class),
                'approve' => $request->user()->can('sender-ids.approve'),
            ],
            'clients' => $this->selectableClients($request),
        ]);
    }

    /**
     * Submit a new sender ID request.
     */
    public function store(SenderIdRequest $request): RedirectResponse
    {
        $senderId = SenderId::query()->create([
            ...$request->validated(),
            'status' => SenderIdStatus::Pending,
            'requested_at' => now(),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Sender ID {$senderId->name} submitted for approval.",
        ]);
    }

    /**
     * Approve a pending sender ID request.
     */
    public function approve(Request $request, SenderId $senderId): RedirectResponse
    {
        Gate::authorize('approve', $senderId);

        $senderId->forceFill([
            'status' => SenderIdStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
            'rejection_reason' => null,
        ])->save();

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Sender ID {$senderId->name} approved.",
        ]);
    }

    /**
     * Reject a pending sender ID request.
     */
    public function reject(Request $request, SenderId $senderId): RedirectResponse
    {
        Gate::authorize('approve', $senderId);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $senderId->forceFill([
            'status' => SenderIdStatus::Rejected,
            'rejection_reason' => $validated['reason'],
            'approved_by' => $request->user()->id,
        ])->save();

        return back()->with('toast', [
            'type' => 'warning',
            'message' => "Sender ID {$senderId->name} rejected.",
        ]);
    }

    /**
     * Remove a sender ID that has not been approved.
     */
    public function destroy(SenderId $senderId): RedirectResponse
    {
        Gate::authorize('delete', $senderId);

        $senderId->delete();

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Sender ID request withdrawn.',
        ]);
    }
}
