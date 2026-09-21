<?php

namespace App\Models;

use App\Enums\GatewayDriver;
use App\Support\ProviderCache;
use Database\Factories\ProviderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Provider extends Model
{
    /** @use HasFactory<ProviderFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'driver',
        'profile_id',
        'password',
        'base_url',
        'static_ip',
        'country_code',
        'static_ip_whitelisted_at',
        'credentials_confirmed_at',
        'is_active',
        'low_balance_threshold',
        'last_balance',
        'last_balance_at',
        'low_balance_alerted_at',
        'fake_balance',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'driver' => GatewayDriver::class,
            'password' => 'encrypted',
            'is_active' => 'boolean',
            'low_balance_threshold' => 'integer',
            'last_balance' => 'integer',
            'last_balance_at' => 'datetime',
            'static_ip_whitelisted_at' => 'datetime',
            'credentials_confirmed_at' => 'datetime',
            'low_balance_alerted_at' => 'datetime',
            'fake_balance' => 'integer',
        ];
    }

    /**
     * @return HasMany<Delivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    /**
     * @return HasMany<ProviderLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ProviderLog::class);
    }

    public static function current(): self
    {
        $driver = GatewayDriver::from((string) config('sms.gateway', GatewayDriver::Fake->value));
        $factory = function () use ($driver): self {
            return self::query()->firstOrCreate(
                ['driver' => $driver],
                [
                    'name' => match ($driver) {
                        GatewayDriver::Fake => 'Fake gateway',
                        GatewayDriver::Mobishastra => 'Mobishastra',
                    },
                    'profile_id' => config('sms.provider.profile_id') ?: null,
                    'password' => config('sms.provider.password') ?: null,
                    'base_url' => config('sms.provider.base_url') ?: null,
                    'static_ip' => config('sms.provider.static_ip') ?: null,
                    'country_code' => config('sms.provider.country_code') ?: 'ALL',
                    'is_active' => true,
                    'low_balance_threshold' => (int) config('sms.provider.low_balance_threshold', 10000),
                    'fake_balance' => (int) config('sms.fake.balance', 100000),
                ],
            );
        };

        if (app()->runningUnitTests()) {
            return $factory();
        }

        return Cache::remember(
            ProviderCache::currentKey($driver->value),
            ProviderCache::CURRENT_TTL_SECONDS,
            $factory,
        );
    }

    public static function forgetCurrent(): void
    {
        foreach (GatewayDriver::cases() as $driver) {
            Cache::forget(ProviderCache::currentKey($driver->value));
        }
    }

    public function maskedProfileId(): ?string
    {
        $id = (string) $this->profile_id;
        if ($id === '') {
            return null;
        }

        if (strlen($id) <= 4) {
            return str_repeat('*', strlen($id));
        }

        return str_repeat('*', strlen($id) - 4).substr($id, -4);
    }

    public function countryCode(): string
    {
        return (string) ($this->country_code ?: config('sms.provider.country_code', 'ALL'));
    }
}
