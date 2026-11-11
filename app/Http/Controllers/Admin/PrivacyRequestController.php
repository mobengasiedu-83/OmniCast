<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PrivacyRequestAction;
use App\Enums\PrivacyRequestStatus;
use App\Enums\PrivacySubjectType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Privacy\CreatePrivacyRequest;
use App\Http\Requests\Privacy\ErasePrivacyRequest;
use App\Http\Requests\Privacy\ReviewPrivacyRequest;
use App\Models\Client;
use App\Models\PrivacyRequest as PrivacyRequestModel;
use App\Services\Privacy\PrivacyRequestProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivacyRequestController extends Controller
{
    /**
     * Show the subject request register to authorised compliance staff.
     */
    public function index(Request $request): Response
    {
        $actorId = (int) $request->user()?->getAuthIdentifier();

        return Inertia::render('admin/legal/privacy-requests', [
            'clients' => Client::query()
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
                ->map(fn (Client $client): array => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'code' => $client->code,
                ])
                ->all(),
            'requests' => PrivacyRequestModel::query()
                ->with([
                    'client:id,name,code',
                    'contact:id,client_id,first_name,last_name,phone,email,deleted_at',
                    'requester:id,name',
                    'reviewer:id,name',
                    'completer:id,name',
                ])
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (PrivacyRequestModel $privacyRequest): array => [
                    'id' => $privacyRequest->id,
                    'reference' => $privacyRequest->reference,
                    'client' => $privacyRequest->client->name,
                    'client_code' => $privacyRequest->client->code,
                    'subject_type' => $privacyRequest->subject_type->value,
                    'subject_label' => $privacyRequest->subject_type->label(),
                    'subject' => $privacyRequest->subject_type === PrivacySubjectType::Contact
                        ? ($privacyRequest->contact?->full_name ?? 'Contact '.$privacyRequest->contact_id)
                        : $privacyRequest->client->name,
                    'contact_id' => $privacyRequest->contact_id,
                    'action' => $privacyRequest->action->value,
                    'action_label' => $privacyRequest->action->label(),
                    'status' => $privacyRequest->status->value,
                    'status_label' => $privacyRequest->status->label(),
                    'requested_by' => $privacyRequest->requester?->name ?? 'Former user',
                    'reviewed_by' => $privacyRequest->reviewer?->name,
                    'completed_by' => $privacyRequest->completer?->name,
                    'created_at' => $privacyRequest->created_at?->toIso8601String(),
                    'reviewed_at' => $privacyRequest->reviewed_at?->toIso8601String(),
                    'completed_at' => $privacyRequest->completed_at?->toIso8601String(),
                    'can_review' => $privacyRequest->status === PrivacyRequestStatus::Requested
                        && $privacyRequest->requested_by !== $actorId,
                    'can_export' => $privacyRequest->action === PrivacyRequestAction::Access
                        && $privacyRequest->status === PrivacyRequestStatus::Approved
                        && $privacyRequest->requested_by !== $actorId,
                    'can_erase' => $privacyRequest->action === PrivacyRequestAction::Erasure
                        && $privacyRequest->status === PrivacyRequestStatus::Approved
                        && $privacyRequest->requested_by !== $actorId,
                ]),
        ]);
    }

    /**
     * Record an incoming access or erasure request without copying subject PII.
     */
    public function store(CreatePrivacyRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $privacyRequest = PrivacyRequestModel::query()->create([
            'reference' => (string) Str::uuid(),
            'client_id' => (int) $data['client_id'],
            'contact_id' => isset($data['contact_id']) ? (int) $data['contact_id'] : null,
            'subject_type' => PrivacySubjectType::from($data['subject_type']),
            'action' => PrivacyRequestAction::from($data['action']),
            'status' => PrivacyRequestStatus::Requested,
            'requested_by' => $request->user()?->getAuthIdentifier(),
        ]);

        activity()
            ->causedBy($request->user())
            ->performedOn($privacyRequest)
            ->withProperties([
                'reference' => $privacyRequest->reference,
                'client_id' => $privacyRequest->client_id,
                'subject_type' => $privacyRequest->subject_type->value,
                'action' => $privacyRequest->action->value,
            ])
            ->log('recorded a data subject privacy request');

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Privacy request recorded and queued for independent review.',
        ]);
    }

    /**
     * Approve or reject a request; the requester cannot review their own entry.
     */
    public function review(
        ReviewPrivacyRequest $request,
        PrivacyRequestModel $privacyRequest,
    ): RedirectResponse {
        $actorId = (int) $request->user()?->getAuthIdentifier();
        $decision = (string) $request->validated('decision');

        $privacyRequest = DB::transaction(function () use ($privacyRequest, $actorId, $decision): PrivacyRequestModel {
            $lockedRequest = PrivacyRequestModel::query()
                ->lockForUpdate()
                ->findOrFail($privacyRequest->id);

            abort_unless(
                $lockedRequest->status === PrivacyRequestStatus::Requested
                    && (int) $lockedRequest->requested_by !== $actorId,
                409,
            );

            $lockedRequest->forceFill([
                'status' => $decision === 'approve'
                    ? PrivacyRequestStatus::Approved
                    : PrivacyRequestStatus::Rejected,
                'reviewed_by' => $actorId,
                'reviewed_at' => now(),
            ])->save();

            return $lockedRequest;
        });

        activity()
            ->causedBy($request->user())
            ->performedOn($privacyRequest)
            ->withProperties(['reference' => $privacyRequest->reference, 'decision' => $decision])
            ->log($decision === 'approve' ? 'approved a privacy request' : 'rejected a privacy request');

        return back()->with('toast', [
            'type' => $decision === 'approve' ? 'success' : 'warning',
            'message' => $decision === 'approve'
                ? 'Privacy request approved for fulfilment.'
                : 'Privacy request rejected.',
        ]);
    }

    /**
     * Download a one-time streamed export after independent approval.
     */
    public function export(
        Request $request,
        PrivacyRequestModel $privacyRequest,
        PrivacyRequestProcessor $processor,
    ): StreamedResponse {
        return $processor->streamExport(
            $privacyRequest,
            (int) $request->user()?->getAuthIdentifier(),
        );
    }

    /**
     * Confirm and complete approved anonymisation without deleting legal evidence.
     */
    public function erase(
        ErasePrivacyRequest $request,
        PrivacyRequestModel $privacyRequest,
        PrivacyRequestProcessor $processor,
    ): RedirectResponse {
        $actorId = (int) $request->user()?->getAuthIdentifier();
        $processor->erase($privacyRequest, $actorId);

        activity()
            ->causedBy($request->user())
            ->performedOn($privacyRequest)
            ->withProperties(['reference' => $privacyRequest->reference])
            ->log('completed an approved privacy anonymisation');

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Approved privacy anonymisation completed. Legal, billing, suppression and audit evidence was retained.',
        ]);
    }
}
