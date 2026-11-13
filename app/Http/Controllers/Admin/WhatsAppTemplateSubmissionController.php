<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\WhatsAppTemplateSubmission;
use App\Services\WhatsApp\WhatsAppAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppTemplateSubmissionController extends Controller
{
    public function index(): Response
    {
        Gate::authorize(Permission::ApproveWhatsAppTemplates->value);

        return Inertia::render('admin/whatsapp/template-submissions', [
            'submissions' => WhatsAppTemplateSubmission::query()
                ->where('review_status', 'pending')
                ->with(['client:id,name', 'connector:id,connector_name,sender_number', 'creator:id,name'])
                ->orderBy('submitted_at')
                ->get()
                ->map(fn (WhatsAppTemplateSubmission $submission): array => [
                    'id' => $submission->id,
                    'client' => $submission->client->name,
                    'connector' => $submission->connector->connector_name ?: $submission->connector->sender_number,
                    'creator' => $submission->creator?->name,
                    'name' => $submission->name,
                    'language' => $submission->language,
                    'category' => $submission->category,
                    'components' => $submission->components,
                    'submitted_at' => $submission->submitted_at?->toIso8601String(),
                ]),
        ]);
    }

    public function approve(Request $request, WhatsAppTemplateSubmission $submission, WhatsAppAuditLogger $audit): RedirectResponse
    {
        Gate::authorize(Permission::ApproveWhatsAppTemplates->value);
        abort_unless($submission->review_status === 'pending', 409);

        $submission->forceFill([
            'review_status' => 'approved',
            'provider_status' => 'awaiting_submission',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ])->save();
        $audit->record('template.reviewed', $request->user(), $submission->client, $submission, ['submission_id' => $submission->id, 'review_status' => 'approved']);

        return back()->with('toast', ['type' => 'success', 'message' => 'Template approved by OmniCast. Submit it through the provider and sync templates to confirm provider approval.']);
    }

    public function reject(Request $request, WhatsAppTemplateSubmission $submission, WhatsAppAuditLogger $audit): RedirectResponse
    {
        Gate::authorize(Permission::ApproveWhatsAppTemplates->value);
        abort_unless($submission->review_status === 'pending', 409);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $submission->forceFill([
            'review_status' => 'rejected',
            'provider_status' => 'not_submitted',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => $validated['reason'],
        ])->save();
        $audit->record('template.reviewed', $request->user(), $submission->client, $submission, ['submission_id' => $submission->id, 'review_status' => 'rejected']);

        return back()->with('toast', ['type' => 'success', 'message' => 'Template returned to the client with a review reason.']);
    }
}
