<?php

namespace App\Http\Controllers\Api\Admin;

use App\Contracts\SmsGatewayInterface;
use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Services\ProviderReadinessService;
use Illuminate\Http\JsonResponse;

class ProviderBalanceController extends Controller
{
    public function show(SmsGatewayInterface $gateway, ProviderReadinessService $readiness): JsonResponse
    {
        $provider = Provider::current();
        $production = $readiness->productionProvider();
        $balance = $gateway->checkBalance();

        return response()->json([
            'driver' => $provider->driver->value,
            'name' => $provider->name,
            'smsBalance' => $balance->smsBalance,
            'parsed' => $balance->parsed,
            'parseError' => $balance->error,
            'rawResponse' => mb_substr($balance->raw, 0, 500),
            'lowBalanceThreshold' => $provider->low_balance_threshold,
            'lowBalance' => $balance->parsed && $balance->smsBalance < $provider->low_balance_threshold,
            'staticIp' => $production->static_ip,
            'staticIpWhitelistedAt' => $production->static_ip_whitelisted_at?->toIso8601String(),
            'baseUrl' => $production->base_url,
            'profileId' => $production->maskedProfileId(),
            'countryCode' => $production->countryCode(),
            'credentialsConfirmedAt' => $production->credentials_confirmed_at?->toIso8601String(),
            'checkedAt' => $provider->fresh()?->last_balance_at?->toIso8601String(),
        ]);
    }
}
