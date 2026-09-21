<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\AdminMetrics;
use App\Support\PaginationMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrafficController extends Controller
{
    public function index(Request $request, AdminMetrics $metrics): JsonResponse
    {
        $snapshot = $metrics->snapshot();
        $page = Campaign::query()
            ->with(['customer.user', 'message'])
            ->where('status', '!=', CampaignStatus::Draft->value)
            ->latest('id')
            ->paginate(PaginationMeta::perPage($request));

        return response()->json([
            'smsTraffic' => $snapshot['smsTraffic'],
            'smsThisMonth' => $snapshot['smsThisMonth'],
            'failedDeliveries' => $snapshot['failedDeliveries'],
            'campaigns' => $page->getCollection()->map->toAdminArray()->values(),
            'meta' => PaginationMeta::from($page),
        ]);
    }
}
