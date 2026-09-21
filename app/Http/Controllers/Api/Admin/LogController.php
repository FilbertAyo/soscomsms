<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ProviderLog;
use App\Support\PaginationMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = PaginationMeta::perPage($request, 15, 50);

        $audit = AuditLog::query()
            ->with(['actor', 'customer.user'])
            ->latest('id')
            ->paginate($perPage, ['*'], 'audit_page', max($request->integer('audit_page', 1), 1));

        $provider = ProviderLog::query()
            ->with(['provider', 'campaign'])
            ->latest('id')
            ->paginate($perPage, ['*'], 'provider_page', max($request->integer('provider_page', 1), 1));

        return response()->json([
            'auditLogs' => $audit->getCollection()->map->toAdminArray()->values(),
            'auditMeta' => PaginationMeta::from($audit),
            'providerLogs' => $provider->getCollection()->map->toAdminArray()->values(),
            'providerMeta' => PaginationMeta::from($provider),
        ]);
    }
}
