<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Cache;

class WhatsAppDispatchMetrics
{
    public function record(string $metric, int $value = 1): void
    {
        Cache::increment('whatsapp:metrics:'.$metric, $value);
        Cache::put('whatsapp:metrics:'.$metric.':updated_at', now()->toIso8601String(), now()->addDays(7));
    }
}
