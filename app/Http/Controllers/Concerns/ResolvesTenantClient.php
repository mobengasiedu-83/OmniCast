<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Resolves the client tenant a request is scoped to.
 */
trait ResolvesTenantClient
{
    /**
     * Resolve the client account the current request is scoped to.
     */
    protected function tenantClient(Request $request): ?Client
    {
        $user = $request->user();

        if ($user->client_id !== null) {
            return $user->client;
        }

        $clientId = $request->integer('client_id');

        return $clientId > 0 ? Client::query()->find($clientId) : null;
    }

    /**
     * The client accounts a platform user may switch between.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    protected function selectableClients(Request $request): Collection
    {
        if (! $request->user()->isPlatformUser()) {
            return collect();
        }

        return Client::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Client $client): array => ['id' => $client->id, 'name' => $client->name]);
    }
}
