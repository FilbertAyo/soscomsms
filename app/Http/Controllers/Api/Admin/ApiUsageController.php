<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\ApiLog;
use App\Models\WebhookDelivery;
use Illuminate\Http\JsonResponse;

class ApiUsageController extends Controller
{
    public function index(): JsonResponse
    {
        $logs = ApiLog::query()
            ->with(['customer.user', 'apiKey'])
            ->latest('id')
            ->limit(200)
            ->get()
            ->map->toAdminArray()
            ->values();

        $keys = ApiKey::query()
            ->with('customer.user')
            ->latest('id')
            ->limit(200)
            ->get()
            ->map->toAdminArray()
            ->values();

        return response()->json([
            'logs' => $logs,
            'keys' => $keys,
            'webhookDeliveries' => WebhookDelivery::query()
                ->with(['customer.user', 'webhook'])
                ->latest('id')
                ->limit(100)
                ->get()
                ->map->toAdminArray()
                ->values(),
        ]);
    }
}
