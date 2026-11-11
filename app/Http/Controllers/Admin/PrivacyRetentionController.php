<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PrivacyRetentionCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Privacy\UpdatePrivacyRetentionPolicy;
use App\Models\PrivacyRetentionPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PrivacyRetentionController extends Controller
{
    /**
     * Show supported data-retention controls and their approval state.
     */
    public function index(): Response
    {
        $policies = PrivacyRetentionPolicy::query()
            ->with('approver:id,name')
            ->get()
            ->keyBy(fn (PrivacyRetentionPolicy $policy): string => $policy->category->value);

        return Inertia::render('admin/legal/privacy-retention', [
            'policies' => array_map(function (PrivacyRetentionCategory $category) use ($policies): array {
                $policy = $policies->get($category->value);

                return [
                    'category' => $category->value,
                    'label' => $category->label(),
                    'description' => $category->description(),
                    'days' => $policy?->retention_days,
                    'approved_at' => $policy?->approved_at?->toIso8601String(),
                    'approved_by' => $policy?->approver?->name,
                ];
            }, PrivacyRetentionCategory::cases()),
        ]);
    }

    /**
     * Record a legally approved retention window for a category.
     */
    public function update(
        UpdatePrivacyRetentionPolicy $request,
        string $category,
    ): RedirectResponse {
        $retentionCategory = PrivacyRetentionCategory::tryFrom($category);
        abort_if($retentionCategory === null, 404);

        $policy = PrivacyRetentionPolicy::query()->firstOrNew([
            'category' => $retentionCategory->value,
        ]);

        $policy->forceFill([
            'retention_days' => (int) $request->validated('days'),
            'approved_by' => $request->user()?->getAuthIdentifier(),
            'approved_at' => now(),
        ])->save();

        activity()
            ->causedBy($request->user())
            ->performedOn($policy)
            ->withProperties([
                'category' => $retentionCategory->value,
                'retention_days' => $policy->retention_days,
                'approved' => true,
            ])
            ->log('updated a privacy retention policy');

        return back()->with('toast', [
            'type' => 'success',
            'message' => "{$retentionCategory->label()} retention is approved for {$policy->retention_days} days.",
        ]);
    }

    /**
     * Clear legal approval and disable pruning for a category.
     */
    public function destroy(Request $request, string $category): RedirectResponse
    {
        $retentionCategory = PrivacyRetentionCategory::tryFrom($category);
        abort_if($retentionCategory === null, 404);

        $policy = PrivacyRetentionPolicy::query()->firstOrNew([
            'category' => $retentionCategory->value,
        ]);
        $policy->forceFill([
            'retention_days' => null,
            'approved_by' => null,
            'approved_at' => null,
        ])->save();

        activity()
            ->causedBy($request->user())
            ->performedOn($policy)
            ->withProperties(['category' => $retentionCategory->value, 'approved' => false])
            ->log('disabled a privacy retention policy');

        return back()->with('toast', [
            'type' => 'success',
            'message' => "{$retentionCategory->label()} pruning is disabled.",
        ]);
    }
}
