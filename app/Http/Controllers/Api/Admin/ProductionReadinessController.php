<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ProviderReadinessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;

class ProductionReadinessController extends Controller
{
    public function show(ProviderReadinessService $readiness): JsonResponse
    {
        return response()->json($readiness->snapshot());
    }

    public function update(Request $request, AuditLogger $audit, ProviderReadinessService $readiness): JsonResponse
    {
        $validated = $request->validate([
            'static_ip' => ['nullable', 'ip'],
            'base_url' => ['nullable', 'url', 'max:255'],
            'country_code' => ['nullable', 'string', 'max:16'],
            'low_balance_threshold' => ['nullable', 'integer', 'min:0', 'max:10000000'],
        ]);

        $provider = $readiness->productionProvider();
        $provider->fill([
            'static_ip' => $validated['static_ip'] ?? $provider->static_ip,
            'base_url' => $validated['base_url'] ?? $provider->base_url,
            'country_code' => isset($validated['country_code'])
                ? strtoupper((string) $validated['country_code'])
                : $provider->country_code,
            'low_balance_threshold' => $validated['low_balance_threshold'] ?? $provider->low_balance_threshold,
        ]);
        $provider->save();
        Provider::forgetCurrent();

        $audit->record(
            actor: $this->admin($request),
            action: AuditAction::ProviderSettingsUpdated,
            subject: $provider,
            payload: [
                'static_ip' => $provider->static_ip,
                'base_url' => $provider->base_url,
                'country_code' => $provider->countryCode(),
            ],
        );

        return response()->json([
            ...$readiness->snapshot(),
            'csrf_token' => csrf_token(),
        ]);
    }

    public function confirmWhitelist(Request $request, AuditLogger $audit, ProviderReadinessService $readiness): JsonResponse
    {
        $provider = $readiness->productionProvider();
        if (! filled($provider->static_ip)) {
            throw ValidationException::withMessages([
                'static_ip' => 'Record the SOSCOM static IP before confirming the Mobishastra whitelist.',
            ]);
        }

        $provider->forceFill(['static_ip_whitelisted_at' => now()])->save();
        Provider::forgetCurrent();

        $audit->record(
            actor: $this->admin($request),
            action: AuditAction::ProviderWhitelistConfirmed,
            subject: $provider,
            payload: ['static_ip' => $provider->static_ip],
        );

        return response()->json([
            ...$readiness->snapshot(),
            'csrf_token' => csrf_token(),
        ]);
    }

    public function confirmAccount(Request $request, AuditLogger $audit, ProviderReadinessService $readiness): JsonResponse
    {
        $provider = $readiness->productionProvider();
        if (! filled($provider->base_url) || ! filled($provider->profile_id) || ! filled($provider->password)) {
            throw ValidationException::withMessages([
                'account' => 'Set the Tanzania base URL, username, and password before confirming the account.',
            ]);
        }

        $provider->forceFill(['credentials_confirmed_at' => now()])->save();
        Provider::forgetCurrent();

        $audit->record(
            actor: $this->admin($request),
            action: AuditAction::ProviderAccountConfirmed,
            subject: $provider,
            payload: [
                'base_url' => $provider->base_url,
                'country_code' => $provider->countryCode(),
            ],
        );

        return response()->json([
            ...$readiness->snapshot(),
            'csrf_token' => csrf_token(),
        ]);
    }

    public function backup(ProviderReadinessService $readiness): JsonResponse
    {
        $code = (int) Artisan::call('sms:backup-database');
        $output = trim(Artisan::output());

        if ($code !== 0) {
            throw ValidationException::withMessages([
                'backup' => $output !== '' ? $output : 'The database backup failed.',
            ]);
        }

        return response()->json([
            ...$readiness->snapshot(),
            'message' => $output !== '' ? $output : 'Backup finished.',
            'csrf_token' => csrf_token(),
        ]);
    }

    private function admin(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
