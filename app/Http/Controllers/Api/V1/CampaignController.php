<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CampaignContactStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CampaignResource;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The read side of the campaign API.
 *
 * Callers are bearer-token integrations rather than browsers, so the scope comes
 * from the token's user: a tenant token only ever sees its own client's
 * campaigns, while a platform token may filter across clients explicitly.
 */
class CampaignController extends Controller
{
    /**
     * List the campaigns the token may read.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:32'],
            'channel' => ['nullable', 'string', 'max:16'],
            'client_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('api.pagination.max_per_page', 100)],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Campaign::query()
            ->withCount([
                'campaignContacts',
                'campaignContacts as delivered_count' => fn (Builder $query): Builder => $query
                    ->where('status', CampaignContactStatus::Delivered->value),
                'campaignContacts as failed_count' => fn (Builder $query): Builder => $query
                    ->where('status', CampaignContactStatus::Failed->value),
            ])
            ->forUser($user)
            ->latest('id');

        if ($user->isPlatformUser() && isset($validated['client_id'])) {
            $query->where('client_id', (int) $validated['client_id']);
        }

        $query
            ->when($validated['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->when($validated['channel'] ?? null, fn (Builder $query, string $channel): Builder => $query->where('channel', $channel))
            ->when($validated['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%");
                });
            });

        return CampaignResource::collection(
            $query->paginate((int) ($validated['per_page'] ?? config('api.pagination.per_page', 25)))
                ->withQueryString(),
        );
    }

    /**
     * Show one campaign.
     */
    public function show(Request $request, Campaign $campaign): CampaignResource
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless(
            $user->isPlatformUser() || (int) $campaign->client_id === (int) $user->client_id,
            404,
        );

        $campaign->loadCount([
            'campaignContacts',
            'campaignContacts as delivered_count' => fn (Builder $query): Builder => $query
                ->where('status', CampaignContactStatus::Delivered->value),
            'campaignContacts as failed_count' => fn (Builder $query): Builder => $query
                ->where('status', CampaignContactStatus::Failed->value),
        ]);

        return new CampaignResource($campaign);
    }
}
