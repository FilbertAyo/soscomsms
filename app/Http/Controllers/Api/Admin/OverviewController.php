<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Services\AdminMetrics;
use Illuminate\Http\JsonResponse;

class OverviewController extends Controller
{
    public function show(AdminMetrics $metrics): JsonResponse
    {
        return response()->json([
            ...$metrics->snapshot(),
            'recentCustomers' => Customer::query()
                ->with('user')
                ->latest('id')
                ->limit(5)
                ->get()
                ->map->toAdminArray()
                ->values(),
            'recentAuditLogs' => AuditLog::query()
                ->with(['actor', 'customer.user'])
                ->latest('id')
                ->limit(8)
                ->get()
                ->map->toAdminArray()
                ->values(),
        ]);
    }
}
