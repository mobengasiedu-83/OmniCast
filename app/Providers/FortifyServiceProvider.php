<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\EnsureTurnstileIsValid;
use App\Actions\Fortify\ResetUserPassword;
use App\Enums\LoginEvent;
use App\Models\LoginAudit;
use App\Models\User;
use App\Services\PhoneNumber;
use App\Services\PlatformConfiguration;
use App\Services\TurnstileVerifier;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Actions\AttemptToAuthenticate;
use Laravel\Fortify\Actions\CanonicalizeUsername;
use Laravel\Fortify\Actions\EnsureLoginIsNotThrottled;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
        $this->configureAuthentication();
        $this->configureLoginPipeline();
    }

    /**
     * Put the Turnstile check in front of the credential check.
     *
     * Fortify's own default pipeline is mirrored here with the security step
     * added, so a failed challenge never reaches the password comparison.
     */
    private function configureLoginPipeline(): void
    {
        Fortify::authenticateThrough(fn (Request $request): array => array_filter([
            config('fortify.limiters.login') ? null : EnsureLoginIsNotThrottled::class,
            config('fortify.lowercase_usernames') ? CanonicalizeUsername::class : null,
            Features::enabled(Features::twoFactorAuthentication()) ? RedirectIfTwoFactorAuthenticatable::class : null,
            EnsureTurnstileIsValid::class,
            AttemptToAuthenticate::class,
            PrepareAuthenticatedSession::class,
        ]));
    }

    /**
     * Reject suspended accounts before Fortify signs them in.
     */
    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request): ?User {
            $identifier = $request->input(Fortify::username());
            $password = $request->input('password');

            if (! is_string($identifier) || ! is_string($password)) {
                return null;
            }

            $normalizedIdentifier = trim($identifier);
            $user = str_contains($normalizedIdentifier, '@')
                ? User::query()->where('email', Str::lower($normalizedIdentifier))->first()
                : User::query()->where('phone', app(PhoneNumber::class)->normalize($normalizedIdentifier))->first();

            if (! $user instanceof User || ! Hash::check($password, $user->password)) {
                return null;
            }

            if (! $user->isActive()) {
                LoginAudit::record(LoginEvent::LoginBlocked, $request, $user, context: [
                    'reason' => $user->suspension_reason,
                ]);

                $request->attributes->set('login_audited', true);

                return null;
            }

            return $user;
        });
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(function (Request $request) {
            $turnstile = app(TurnstileVerifier::class);

            return Inertia::render('auth/login', [
                'canResetPassword' => Features::enabled(Features::resetPasswords()),
                'status' => $request->session()->get('status'),
                'turnstile' => [
                    'enabled' => $turnstile->enabled(),
                    'site_key' => $turnstile->siteKey(),
                ],
            ]);
        });

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(function (Request $request) {
            if ($request->user()?->phone_verified_at !== null) {
                return to_route('dashboard');
            }

            return Inertia::render('auth/verify-email', [
                'status' => $request->session()->get('status'),
            ]);
        });

        Fortify::registerView(fn () => Inertia::render('auth/register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());
            $perMinute = app(PlatformConfiguration::class)->resolve('rate_limits', 'login_attempts_per_minute', 5);

            return Limit::perMinute(max(1, (int) $perMinute))->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }
}
