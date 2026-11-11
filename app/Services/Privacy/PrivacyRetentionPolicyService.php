<?php

namespace App\Services\Privacy;

use App\Enums\PrivacyRetentionCategory;
use App\Models\PrivacyRetentionPolicy;

class PrivacyRetentionPolicyService
{
    /**
     * Return a retention window only when an administrator recorded approval.
     */
    public function approvedDays(PrivacyRetentionCategory $category): ?int
    {
        $policy = PrivacyRetentionPolicy::query()
            ->where('category', $category->value)
            ->whereNotNull('retention_days')
            ->whereNotNull('approved_by')
            ->whereNotNull('approved_at')
            ->first();

        return $policy?->retention_days;
    }
}
