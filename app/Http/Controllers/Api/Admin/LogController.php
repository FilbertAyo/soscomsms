<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ProviderLog;
use Illuminate\Http\JsonResponse;

class LogController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'auditLogs' => AuditLog::query()
                ->with(['actor', 'customer.user'])
                ->latest('id')
                ->limit(200)
                ->get()
                ->map->toAdminArray()
                ->values(),
            'providerLogs' => ProviderLog::query()
                ->with(['provider', 'campaign'])
                ->latest('id')
                ->limit(200)
                ->get()
                ->map->toAdminArray()
                ->values(),
        ]);
    }
}
