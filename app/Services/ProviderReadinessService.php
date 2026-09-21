<?php

namespace App\Services;

use App\Enums\GatewayDriver;
use App\Exceptions\ProviderNotReadyException;
use App\Models\Provider;
use Illuminate\Support\Facades\File;

class ProviderReadinessService
{
    public const PRODUCTION_URL = 'https://sms.soscom.co.tz';

    public function assertReadyToSend(): void
    {
        if ($this->driver() !== GatewayDriver::Mobishastra) {
            return;
        }

        $reason = $this->blockReason($this->productionProvider());
        if ($reason !== null) {
            throw new ProviderNotReadyException($reason);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $provider = $this->productionProvider();
        $environment = (string) app()->environment();
        $appUrl = rtrim((string) config('app.url'), '/');
        $https = str_starts_with($appUrl, 'https://');
        $driver = $this->driver();
        $blockReason = $this->blockReason($provider);

        $checks = [
            $this->check(
                'https',
                'HTTPS and APP_URL',
                $environment !== 'production' || ($https && $appUrl === self::PRODUCTION_URL),
                $environment === 'production'
                    ? 'Production must use '.self::PRODUCTION_URL
                    : 'Local/staging can stay on '.$appUrl,
            ),
            $this->check(
                'timezone',
                'Africa/Dar_es_Salaam timezone',
                (string) config('app.timezone') === 'Africa/Dar_es_Salaam',
                (string) config('app.timezone'),
            ),
            $this->check(
                'queue',
                'Queue worker (not sync in production)',
                $environment !== 'production' || (string) config('queue.default') !== 'sync',
                (string) config('queue.default'),
            ),
            $this->check(
                'mail',
                'Mailer for password resets and alerts',
                $environment !== 'production' || ! in_array((string) config('mail.default'), ['log', 'array'], true),
                (string) config('mail.default'),
            ),
            $this->check(
                'logs',
                'Daily log rotation',
                $environment !== 'production' || $this->usesDailyLogs(),
                (string) config('logging.default'),
            ),
            $this->check(
                'database',
                'Real database in production',
                $environment !== 'production' || (string) config('database.default') !== 'sqlite',
                (string) config('database.default'),
            ),
            $this->check(
                'gateway',
                'SMS gateway driver',
                $environment !== 'production' || $driver === GatewayDriver::Mobishastra,
                $driver->value.($environment !== 'production' ? ' (keep fake until go-live)' : ''),
            ),
            $this->check(
                'static_ip',
                'SOSCOM static IP recorded',
                filled($provider->static_ip),
                $provider->static_ip ?: 'Not recorded',
            ),
            $this->check(
                'whitelist',
                'Mobishastra has whitelisted the static IP',
                $provider->static_ip_whitelisted_at !== null,
                $provider->static_ip_whitelisted_at?->toIso8601String() ?? 'Not confirmed',
            ),
            $this->check(
                'base_url',
                'Tanzania provider base URL',
                filled($provider->base_url),
                $provider->base_url ?: 'Not set',
            ),
            $this->check(
                'profile',
                'Provider username',
                filled($provider->profile_id),
                $provider->maskedProfileId() ?: 'Not set',
            ),
            $this->check(
                'password',
                'Provider password stored',
                filled($provider->password),
                filled($provider->password) ? 'Set' : 'Missing',
            ),
            $this->check(
                'country_code',
                'CountryCode',
                filled($provider->countryCode()),
                $provider->countryCode(),
            ),
            $this->check(
                'account',
                'Tanzania account confirmed with Mobishastra',
                $provider->credentials_confirmed_at !== null,
                $provider->credentials_confirmed_at?->toIso8601String() ?? 'Not confirmed',
            ),
        ];

        $ready = collect($checks)->every(fn (array $check): bool => $check['ready']);

        return [
            'environment' => $environment,
            'appUrl' => $appUrl,
            'expectedAppUrl' => self::PRODUCTION_URL,
            'https' => $https,
            'timezone' => (string) config('app.timezone'),
            'queueConnection' => (string) config('queue.default'),
            'mailer' => (string) config('mail.default'),
            'logChannel' => (string) config('logging.default'),
            'database' => (string) config('database.default'),
            'driver' => $driver->value,
            'localUsesFake' => $environment !== 'production',
            'canSendViaMobishastra' => $driver === GatewayDriver::Mobishastra && $blockReason === null,
            'blockReason' => $blockReason,
            'goLiveReady' => $environment === 'production' && $ready && $blockReason === null,
            'checks' => $checks,
            'provider' => [
                'id' => $provider->id,
                'name' => $provider->name,
                'driver' => $provider->driver->value,
                'staticIp' => $provider->static_ip,
                'staticIpWhitelistedAt' => $provider->static_ip_whitelisted_at?->toIso8601String(),
                'baseUrl' => $provider->base_url,
                'profileId' => $provider->maskedProfileId(),
                'passwordSet' => filled($provider->password),
                'countryCode' => $provider->countryCode(),
                'credentialsConfirmedAt' => $provider->credentials_confirmed_at?->toIso8601String(),
                'lowBalanceThreshold' => $provider->low_balance_threshold,
                'lastBalance' => $provider->last_balance,
                'lastBalanceAt' => $provider->last_balance_at?->toIso8601String(),
                'lowBalanceAlertedAt' => $provider->low_balance_alerted_at?->toIso8601String(),
            ],
            'lastBackupAt' => $this->lastBackupAt(),
            'backupKeepDays' => (int) config('sms.production.backup_keep_days', 14),
        ];
    }

    public function productionProvider(): Provider
    {
        $provider = Provider::query()->firstOrCreate(
            ['driver' => GatewayDriver::Mobishastra],
            [
                'name' => 'Mobishastra',
                'profile_id' => config('sms.provider.profile_id') ?: null,
                'password' => config('sms.provider.password') ?: null,
                'base_url' => config('sms.provider.base_url') ?: null,
                'static_ip' => config('sms.provider.static_ip') ?: null,
                'country_code' => config('sms.provider.country_code') ?: 'ALL',
                'is_active' => true,
                'low_balance_threshold' => (int) config('sms.provider.low_balance_threshold', 10000),
            ],
        );

        $updates = [];
        if (filled(config('sms.provider.profile_id'))
            && (string) $provider->profile_id !== (string) config('sms.provider.profile_id')) {
            $updates['profile_id'] = config('sms.provider.profile_id');
        }
        if (! filled($provider->password) && filled(config('sms.provider.password'))) {
            $updates['password'] = config('sms.provider.password');
        }
        if (! filled($provider->base_url) && filled(config('sms.provider.base_url'))) {
            $updates['base_url'] = config('sms.provider.base_url');
        }
        if (! filled($provider->static_ip) && filled(config('sms.provider.static_ip'))) {
            $updates['static_ip'] = config('sms.provider.static_ip');
        }
        if ($updates !== []) {
            $provider->forceFill($updates)->save();
            Provider::forgetCurrent();
        }

        return $provider;
    }

    public function blockReason(?Provider $provider = null): ?string
    {
        $provider ??= $this->productionProvider();

        if (! filled($provider->static_ip)) {
            return 'Record the SOSCOM production static IP before sending through Mobishastra.';
        }

        if ($provider->static_ip_whitelisted_at === null) {
            return 'Mobishastra must whitelist the SOSCOM static IP before real sends are allowed.';
        }

        if (! filled($provider->base_url) || ! filled($provider->profile_id) || ! filled($provider->password)) {
            return 'Confirm the Tanzania base URL, username, and password before switching to Mobishastra.';
        }

        if ($provider->credentials_confirmed_at === null) {
            return 'Confirm the Tanzania Mobishastra account (base URL, username, CountryCode) before sending.';
        }

        return null;
    }

    public function lastBackupAt(): ?string
    {
        $dir = $this->backupDirectory();
        if (! File::isDirectory($dir)) {
            return null;
        }

        $latest = collect(File::files($dir))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->first();

        if ($latest === null) {
            return null;
        }

        return date('c', $latest->getMTime());
    }

    public function backupDirectory(): string
    {
        return storage_path('app/private/backups');
    }

    private function driver(): GatewayDriver
    {
        return GatewayDriver::from((string) config('sms.gateway', GatewayDriver::Fake->value));
    }

    private function usesDailyLogs(): bool
    {
        if ((string) config('logging.default') === 'daily') {
            return true;
        }

        $stack = config('logging.channels.stack.channels', []);

        return is_array($stack) && in_array('daily', $stack, true);
    }

    /**
     * @return array{id: string, label: string, ready: bool, detail: string}
     */
    private function check(string $id, string $label, bool $ready, string $detail): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'ready' => $ready,
            'detail' => $detail,
        ];
    }
}
