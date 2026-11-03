<?php

namespace App\Providers;

use App\Contracts\SmsGateway;
use App\Contracts\VoiceGateway;
use App\Policies\RolePolicy;
use App\Services\LogSmsGateway;
use App\Services\LogVoiceGateway;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
    }

    /**
     * Register the identity policies and rate limiters.
     */
    protected function configureIdentity(): void
    {
        Gate::policy(RoleModel::class, RolePolicy::class);

        RateLimiter::for('invitations', function (Request $request): Limit {
            $user = $request->user();

            return Limit::perMinute(10)->by(
                $user !== null ? 'user:'.$user->id : 'ip:'.$request->ip(),
            );
        });

        RateLimiter::for('client-registrations', function (Request $request): Limit {
            return Limit::perMinute(3)->by('ip:'.$request->ip());
        });

        RateLimiter::for('api', function (Request $request): Limit {
            $user = $request->user();

            return Limit::perMinute(60)->by(
                $user !== null ? 'user:'.$user->id : 'ip:'.$request->ip(),
            );
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
