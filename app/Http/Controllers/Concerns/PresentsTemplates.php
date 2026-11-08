<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\Channel;
use App\Enums\TemplateStatus;
use App\Models\MessageTemplate;
use App\Models\TemplateCategory;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Shapes a template for the library grids, the admin catalogue and the editor.
 *
 * Both the client library and the platform catalogue describe a template the
 * same way, so the two sides never disagree about what a channel, a variable or
 * an action means.
 */
trait PresentsTemplates
{
    /**
     * @return array<string, mixed>
     */
    protected function templateRow(MessageTemplate $template, User $user): array
    {
        $template->loadMissing(['category:id,name,slug', 'creator:id,name', 'client:id,name']);

        return [
            'id' => $template->id,
            'name' => $template->name,
            'channel' => $template->channel->value,
            'channel_label' => $template->channel->label(),
            'status' => $template->status->value,
            'status_label' => $template->status->label(),
            'description' => $template->description,
            'subject' => $template->subject,
            'body' => $template->body,
            'email_format' => $template->email_format->value,
            'language' => $template->language,
            'variables' => $template->variableTags(),
            'category' => $template->category?->only(['id', 'name', 'slug']),
            'client' => $template->client_id === null ? null : $template->client?->name,
            'client_id' => $template->client_id,
            'is_system' => $template->isSystem(),
            'usage_count' => (int) $template->usage_count,
            'campaigns_count' => (int) ($template->getAttribute('campaigns_count') ?? 0),
            'author' => $template->creator?->name,
            'approved_at' => $template->approved_at?->toIso8601String(),
            'rejection_reason' => $template->rejection_reason,
            'updated_at' => $template->updated_at?->toIso8601String(),
            'is_favourite' => $template->relationLoaded('favouritedBy')
                ? $template->favouritedBy->isNotEmpty()
                : false,
            'can' => [
                'update' => Gate::allows('update', $template),
                'delete' => Gate::allows('delete', $template),
                'approve' => Gate::allows('approve', $template),
                'use' => $template->status->isUsable(),
            ],
        ];
    }

    /**
     * The compact shape the campaign editor needs to offer a starting point:
     * just enough to preview the copy and copy it into the campaign.
     *
     * @return array<string, mixed>
     */
    protected function templatePickerRow(MessageTemplate $template): array
    {
        $template->loadMissing('category:id,name,slug');

        return [
            'id' => $template->id,
            'name' => $template->name,
            'channel' => $template->channel->value,
            'channel_label' => $template->channel->label(),
            'subject' => $template->subject,
            'body' => $template->body,
            'email_format' => $template->email_format->value,
            'category' => $template->category?->name,
            'variables' => $template->variableTags(),
            'is_system' => $template->isSystem(),
            'usage_count' => (int) $template->usage_count,
        ];
    }

    /**
     * The categories the account may file a template under, in library order.
     *
     * @return list<array<string, mixed>>
     */
    protected function templateCategoryOptions(?int $clientId): array
    {
        return TemplateCategory::query()
            ->visibleTo($clientId)
            ->withCount('templates')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (TemplateCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'sort_order' => (int) $category->sort_order,
                'is_system' => $category->isSystem(),
                'templates_count' => (int) $category->getAttribute('templates_count'),
            ])
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function templateChannelOptions(): array
    {
        return array_map(
            fn (Channel $channel): array => ['value' => $channel->value, 'label' => $channel->label()],
            Channel::cases(),
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function templateStatusOptions(): array
    {
        return array_map(
            fn (TemplateStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            TemplateStatus::cases(),
        );
    }
}
