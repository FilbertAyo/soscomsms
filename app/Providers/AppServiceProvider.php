<?php

namespace App\Providers;

use App\Contracts\SmsGatewayInterface;
use App\Enums\GatewayDriver;
use App\Mail\SoscomMail;
use App\Models\ApiKey;
use App\Models\User;
use App\Services\Gateway\FakeGateway;
use App\Services\Gateway\MobishastraGateway;
use App\Support\AdminAlerts;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SmsGatewayInterface::class, function ($app) {
            $driver = GatewayDriver::from((string) config('sms.gateway', GatewayDriver::Fake->value));

            return match ($driver) {
                GatewayDriver::Fake => $app->make(FakeGateway::class),
                GatewayDriver::Mobishastra => $app->make(MobishastraGateway::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(
                strtolower((string) $request->input('email')).'|'.$request->ip()
            );
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(10)->by((string) $request->ip());
        });

        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(5)->by((string) $request->ip());
        });

        RateLimiter::for('otp', function (Request $request) {
            return Limit::perMinute(8)->by((string) $request->ip());
        });

        $guard = Auth::guard('web');
        if (method_exists($guard, 'setRememberDuration')) {
            $guard->setRememberDuration((int) config('auth.remember', 43200));
        }

        RateLimiter::for('api-v1', function (Request $request) {
            $key = $request->attributes->get('api_key');
            $id = $key instanceof ApiKey ? 'api-key:'.$key->id : (string) $request->ip();

            return Limit::perMinute((int) config('sms.api.rate_limit', 60))->by($id);
        });

        RateLimiter::for('dashboard-send', function (Request $request) {
            return Limit::perMinute((int) config('sms.rate_limits.dashboard_send', 30))
                ->by((string) ($request->user()?->id ?: $request->ip()));
        });

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Queue::failing(function (JobFailed $event): void {
            $job = $event->job->resolveName();

            AdminAlerts::technical(
                'job-failed:'.$job,
                'Background job failed',
                $job.' failed on the "'.$event->job->getQueue().'" queue: '.Str::limit($event->exception->getMessage(), 500),
            );
        });

        ResetPassword::createUrlUsing(function (CanResetPassword $notifiable, string $token): string {
            return url('/reset-password').'?'.http_build_query([
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
        });

        ResetPassword::toMailUsing(function (object $notifiable, string $token): MailMessage {
            $url = url('/reset-password').'?'.http_build_query([
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
            $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');
            $user = $notifiable instanceof User ? $notifiable : null;

            return SoscomMail::message()
                ->subject('Reset your Soscom SMS password')
                ->greeting(SoscomMail::greetingFor($user))
                ->line('We received a request to reset your password.')
                ->action('Reset password', $url)
                ->line("This link expires in {$minutes} minutes. If you did not ask for this, you can ignore this email.");
        });
    }
}
