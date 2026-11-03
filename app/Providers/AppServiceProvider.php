<?php

namespace App\Providers;

use App\Contracts\SmsGateway;
use App\Contracts\VoiceGateway;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Services\LogSmsGateway;
use App\Services\LogVoiceGateway;
use App\Services\PlatformConfiguration;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role as RoleModel;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsGateway::class, LogSmsGateway::class);
        $this->app->bind(VoiceGateway::class, LogVoiceGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureIdentity();
        $this->configureCommunicationLogging();
    }

    /**
     * Register the identity policies and rate limiters.
     */
    protected function configureIdentity(): void
    {
        Gate::policy(RoleModel::class, RolePolicy::class);

        Gate::before(static function (User $user, string $ability, array $arguments): ?bool {
            if ($ability === 'update' && ($arguments[0] ?? null) instanceof RoleModel) {
                return null;
            }

            return $user->isPlatformUser() && $user->isSuperAdmin() ? true : null;
        });

        RateLimiter::for('invitations', function (Request $request): Limit {
            $user = $request->user();

            return Limit::perMinute(10)->by(
                $user !== null ? 'user:'.$user->id : 'ip:'.$request->ip(),
            );
        });

        RateLimiter::for('client-registrations', function (Request $request): Limit {
            $perMinute = app(PlatformConfiguration::class)->resolve('rate_limits', 'client_registrations_per_minute', 3);

            return Limit::perMinute(max(1, (int) $perMinute))->by('ip:'.$request->ip());
        });

        RateLimiter::for('api', function (Request $request): Limit {
            $user = $request->user();

            return Limit::perMinute(60)->by(
                $user !== null ? 'user:'.$user->id : 'ip:'.$request->ip(),
            );
        });
    }

    /** Log successful outgoing email transport events without message contents or recipient addresses. */
    protected function configureCommunicationLogging(): void
    {
        Event::listen(MessageSent::class, static function (MessageSent $event): void {
            Log::channel('email')->info('Email accepted by the configured transport.', [
                'recipient_count' => count($event->message->getTo() ?? [])
                    + count($event->message->getCc() ?? [])
                    + count($event->message->getBcc() ?? []),
            ]);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
