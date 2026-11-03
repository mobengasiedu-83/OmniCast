<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

/**
 * Reads and revokes rows from the database session store.
 *
 * Sessions are recorded in the database, so "sign out other devices" and the
 * administrator session monitor both operate on the `sessions` table.
 */
class SessionMonitor
{
    /**
     * Whether sessions are stored where this monitor can read them.
     */
    public function usesDatabaseDriver(): bool
    {
        return config('session.driver') === 'database';
    }

    /**
     * The sessions belonging to a single user, most recent first.
     *
     * @return Collection<int, array{session_id: string, ip_address: string|null, device: string, user_agent: string|null, last_activity: int, last_active_at: string, is_current: bool}>
     */
    public function forUser(User $user, ?string $currentSessionId = null): Collection
    {
        if (! $this->usesDatabaseDriver()) {
            return collect();
        }

        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn (stdClass $row): array => $this->presentOwn($row, $currentSessionId))
            ->values();
    }

    /**
     * Every live session across the platform, with its owner.
     *
     * @param  array{search?: string|null}  $filters
     * @return LengthAwarePaginator<int, array{session_id: string, user_id: int|null, user_name: string|null, user_email: string|null, client_name: string|null, ip_address: string|null, device: string, last_activity: int, last_active_at: string}>
     */
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $search = $filters['search'] ?? null;

        /** @var LengthAwarePaginator<int, array{session_id: string, user_id: int|null, user_name: string|null, user_email: string|null, client_name: string|null, ip_address: string|null, device: string, last_activity: int, last_active_at: string}> $sessions */
        $sessions = DB::table('sessions')
            ->leftJoin('users', 'users.id', '=', 'sessions.user_id')
            ->leftJoin('clients', 'clients.id', '=', 'users.client_id')
            ->whereNotNull('sessions.user_id')
            ->when(is_string($search) && $search !== '', function ($query) use ($search): void {
                $term = '%'.$search.'%';

                $query->where(function ($query) use ($term): void {
                    $query->where('users.name', 'like', $term)
                        ->orWhere('users.email', 'like', $term)
                        ->orWhere('clients.name', 'like', $term)
                        ->orWhere('sessions.ip_address', 'like', $term);
                });
            })
            ->select([
                'sessions.id',
                'sessions.user_id',
                'sessions.ip_address',
                'sessions.user_agent',
                'sessions.last_activity',
                'users.name as user_name',
                'users.email as user_email',
                'clients.name as client_name',
            ])
            ->orderByDesc('sessions.last_activity')
            ->paginate($perPage)
            ->through(fn (stdClass $row): array => [
                'session_id' => (string) $row->id,
                'user_id' => $row->user_id === null ? null : (int) $row->user_id,
                'user_name' => $row->user_name === null ? null : (string) $row->user_name,
                'user_email' => $row->user_email === null ? null : (string) $row->user_email,
                'client_name' => $row->client_name === null ? null : (string) $row->client_name,
                'ip_address' => $row->ip_address === null ? null : (string) $row->ip_address,
                'device' => self::describeDevice($row->user_agent === null ? null : (string) $row->user_agent),
                'last_activity' => (int) $row->last_activity,
                'last_active_at' => now()->setTimestamp((int) $row->last_activity)->toIso8601String(),
            ]);

        return $sessions;
    }

    /**
     * Revoke every session for a user, optionally keeping the current one.
     */
    public function revokeForUser(int $userId, ?string $exceptSessionId = null): int
    {
        if (! $this->usesDatabaseDriver()) {
            return 0;
        }

        $query = DB::table('sessions')->where('user_id', $userId);

        if ($exceptSessionId !== null) {
            $query->where('id', '<>', $exceptSessionId);
        }

        return $query->delete();
    }

    /**
     * Revoke a single session that belongs to the user.
     */
    public function revoke(int $userId, string $sessionId): bool
    {
        if (! $this->usesDatabaseDriver()) {
            return false;
        }

        return DB::table('sessions')
            ->where('user_id', $userId)
            ->where('id', $sessionId)
            ->delete() > 0;
    }

    /**
     * A short, human readable description of the client behind a user agent.
     */
    public static function describeDevice(?string $agent): string
    {
        if ($agent === null || trim($agent) === '') {
            return 'Unknown device';
        }

        $browser = match (true) {
            Str::contains($agent, ['Edg/', 'EdgA/']) => 'Edge',
            Str::contains($agent, ['OPR/', 'Opera']) => 'Opera',
            Str::contains($agent, 'Firefox') => 'Firefox',
            Str::contains($agent, 'Chrome') => 'Chrome',
            Str::contains($agent, 'Safari') => 'Safari',
            default => 'Browser',
        };

        $platform = match (true) {
            Str::contains($agent, 'iPhone') => 'iPhone',
            Str::contains($agent, 'iPad') => 'iPad',
            Str::contains($agent, 'Android') => 'Android',
            Str::contains($agent, 'Mac OS X') => 'macOS',
            Str::contains($agent, 'Windows') => 'Windows',
            Str::contains($agent, 'Linux') => 'Linux',
            default => 'Unknown OS',
        };

        return "{$browser} on {$platform}";
    }

    /**
     * Present a session row for the signed-in user's own account.
     *
     * @return array{session_id: string, ip_address: string|null, device: string, user_agent: string|null, last_activity: int, last_active_at: string, is_current: bool}
     */
    protected function presentOwn(stdClass $row, ?string $currentSessionId): array
    {
        $agent = $row->user_agent === null ? null : (string) $row->user_agent;

        return [
            'session_id' => (string) $row->id,
            'ip_address' => $row->ip_address === null ? null : (string) $row->ip_address,
            'device' => self::describeDevice($agent),
            'user_agent' => $agent,
            'last_activity' => (int) $row->last_activity,
            'last_active_at' => now()->setTimestamp((int) $row->last_activity)->toIso8601String(),
            'is_current' => $currentSessionId !== null && $currentSessionId === (string) $row->id,
        ];
    }
}
