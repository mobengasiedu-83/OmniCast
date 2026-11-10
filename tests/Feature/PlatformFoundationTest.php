<?php

use App\Enums\Role;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
});

test('platform users see platform-wide client and campaign statistics', function () {
    Client::factory()->create();
    Client::factory()->pending()->create();

    $user = User::factory()->create();
    $user->syncRoles([Role::SuperAdmin->value]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dashboard')
            ->where('stats.clients', 2)
            ->where('stats.pending_clients', 1)
            ->where('stats.campaigns', 0)
            ->where('stats.pending_campaigns', 0),
        );
});

test('web errors render an Inertia error page', function () {
    $this->get('/missing-page')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('error')
            ->where('status', 404),
        );
});

test('supported web error statuses render their matching Inertia page', function (int $status) {
    Route::get('/_test/error/'.$status, function () use ($status) {
        abort($status);
    })->middleware('web');

    $this->get('/_test/error/'.$status)
        ->assertStatus($status)
        ->assertInertia(fn (Assert $page) => $page
            ->component('error')
            ->where('status', $status),
        );
})->with([403, 419, 429, 500]);

test('API errors remain JSON responses', function () {
    $this->getJson('/api/v1/missing-resource')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});

test('JSON log channel serializes structured message context', function () {
    $path = tempnam(sys_get_temp_dir(), 'omnicast-log-');

    if ($path === false) {
        throw new RuntimeException('Unable to allocate a temporary log file.');
    }

    $record = null;

    try {
        config(['logging.channels.json.handler_with.stream' => $path]);

        Log::channel('json')->info('Foundation check', [
            'request_id' => 'request-123',
        ]);

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('Unable to read the temporary log file.');
        }

        $record = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
    } finally {
        unlink($path);
    }

    if (! is_array($record) || ! is_array($record['context'] ?? null)) {
        throw new RuntimeException('The JSON log record did not include context.');
    }

    expect($record['message'])->toBe('Foundation check');
    expect($record['level_name'])->toBe('INFO');
    expect($record['context']['request_id'])->toBe('request-123');
});

test('web responses include a request identifier and log the signed-in user', function () {
    $user = User::factory()->create();
    $user->syncRoles([Role::SuperAdmin->value]);
    $log = Log::spy();

    $response = $this->actingAs($user)->get(route('dashboard'));
    $response->assertOk();

    $log->shouldHaveReceived('shareContext')
        ->once()
        ->with(Mockery::on(function (array $context) use ($user): bool {
            $requestId = $context['request_id'] ?? null;

            return is_string($requestId)
                && Str::isUuid($requestId)
                && ($context['user_id'] ?? null) === (string) $user->id;
        }));

    $requestId = (string) $response->headers->get('X-Request-Id');
    $response->assertHeader('X-Request-Id', $requestId);
    expect(Str::isUuid($requestId))->toBeTrue();
});

test('authenticated API responses include a request identifier and log the token user', function () {
    $user = User::factory()->create();
    $token = $user->createToken('foundation-test', ['read']);
    $log = Log::spy();

    $response = $this->withToken($token->plainTextToken)->getJson(route('api.v1.me'));
    $response->assertOk();

    $log->shouldHaveReceived('shareContext')
        ->once()
        ->with(Mockery::on(function (array $context) use ($user): bool {
            $requestId = $context['request_id'] ?? null;

            return is_string($requestId)
                && Str::isUuid($requestId)
                && ($context['user_id'] ?? null) === (string) $user->id;
        }));

    $requestId = (string) $response->headers->get('X-Request-Id');
    $response->assertHeader('X-Request-Id', $requestId);
    expect(Str::isUuid($requestId))->toBeTrue();
});
