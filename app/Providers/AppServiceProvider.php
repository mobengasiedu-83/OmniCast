<?php

namespace App\Providers;

use App\Contracts\EmailGateway;
use App\Contracts\SmsGateway;
use App\Contracts\VoiceGateway;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Services\Email\EmailGatewayManager;
use App\Services\IntegrationScripts;
use App\Services\PlatformConfiguration;
use App\Services\Sms\SmsGatewayManager;
use App\Services\Voice\AmiTransport;
use App\Services\Voice\StreamAmiTransport;
use App\Services\Voice\VoiceGatewayManager;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
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
        $this->app->bind(
            SmsGateway::class,
            fn (): SmsGateway => $this->app->make(SmsGatewayManager::class)->driver(),
        );
        $this->app->bind(
            EmailGateway::class,
            fn (): EmailGateway => $this->app->make(EmailGatewayManager::class)->driver(),
        );
        $this->app->bind(AmiTransport::class, StreamAmiTransport::class);
        $this->app->bind(
            VoiceGateway::class,
            fn (): VoiceGateway => $this->app->make(VoiceGatewayManager::class)->driver(),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureIdentity();
        $this->configureIntegrations();
        $this->configureCommunicationLogging();
        $this->configureDevelopmentProcesses();
    }

    /**
     * Hand the enabled tracking snippets to the root Blade view.
     *
     * The platform team pastes the vendor tags under Admin -> Integrations. A
     * snippet is a complete vendor tag rather than user text, so the view
     * prints it unescaped; only platform administrators can write one.
     */
    protected function configureIntegrations(): void
    {
        View::composer('app', static function (ViewContract $view): void {
            $snippets = app(IntegrationScripts::class)->snippets();

            $view->with('integrationHeadScripts', $snippets['head']);
            $view->with('integrationBodyScripts', $snippets['body']);
        });
    }

    /**
     * Run the scheduler beside Horizon and Vite under `composer run dev`.
     *
     * The minute scheduler releases scheduled campaigns and sweeps stalled ones,
     * so without it a locally launched campaign only moves once Horizon is fed.
     */
    protected function configureDevelopmentProcesses(): void
    {
        if (! class_exists(DevCommands::class)) {
            return;
        }

        DevCommands::artisan('schedule:work');
    }

    /**
     * Register the identity policies and rate limiters.
     */
    protected function configureIdentity(): void
    {
        Gate::policy(RoleModel::class, RolePolicy::class);

        Gate::before(static function (User $user, string $ability, array $arguments): ?bool {
            $target = $arguments[0] ?? null;

            if ($ability === 'update' && $target instanceof RoleModel) {
                return null;
            }

            if ($ability === 'suspend' && $target instanceof User && $target->is($user)) {
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
            $token = $user?->currentAccessToken();
            $write = $token !== null && method_exists($token, 'can') && $token->can('write');

            return Limit::perMinute(max(1, (int) config(
                $write ? 'api.rate_limits.write' : 'api.rate_limits.read',
                $write ? 30 : 120,
            )))->by(
                $token !== null && isset($token->id)
                    ? 'token:'.$token->id
                    : ($user !== null ? 'user:'.$user->id : 'ip:'.$request->ip()),
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
