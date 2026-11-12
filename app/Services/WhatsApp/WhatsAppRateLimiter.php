<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Redis;

class WhatsAppRateLimiter
{
    /** Atomically consume provider, connector and client rolling-window permits. */
    public function acquire(string $providerKey, int $connectorId, int $clientId): bool
    {
        $buckets = [
            ['key' => 'wa:provider:'.$providerKey, 'limit' => max((int) config('whatsapp.dispatch.provider_per_second', 0), 0), 'window' => 1],
            ['key' => 'wa:connector:'.$connectorId, 'limit' => max((int) config('whatsapp.dispatch.connector_per_minute', 0), 0), 'window' => 60],
            ['key' => 'wa:client:'.$clientId, 'limit' => max((int) config('whatsapp.dispatch.client_per_minute', 0), 0), 'window' => 60],
        ];
        if (collect($buckets)->every(fn (array $bucket): bool => $bucket['limit'] === 0)) {
            return true;
        }
        $script = <<<'LUA'
local now = tonumber(ARGV[1])
local token = ARGV[2]
for i = 1, 3 do
    local limit = tonumber(ARGV[2 + (i - 1) * 2 + 1])
    local window = tonumber(ARGV[2 + (i - 1) * 2 + 2])
    if limit > 0 then
        redis.call('ZREMRANGEBYSCORE', KEYS[i], '-inf', now - window)
        if tonumber(redis.call('ZCARD', KEYS[i])) >= limit then
            return 0
        end
    end
end
for i = 1, 3 do
    local limit = tonumber(ARGV[2 + (i - 1) * 2 + 1])
    local window = tonumber(ARGV[2 + (i - 1) * 2 + 2])
    if limit > 0 then
        redis.call('ZADD', KEYS[i], now, token)
        redis.call('EXPIRE', KEYS[i], window * 2)
    end
end
return 1
LUA;
        $result = Redis::eval($script, 3, $buckets[0]['key'], $buckets[1]['key'], $buckets[2]['key'], (string) microtime(true), bin2hex(random_bytes(16)), (string) $buckets[0]['limit'], (string) $buckets[0]['window'], (string) $buckets[1]['limit'], (string) $buckets[1]['window'], (string) $buckets[2]['limit'], (string) $buckets[2]['window']);

        return (int) $result === 1;
    }
}
