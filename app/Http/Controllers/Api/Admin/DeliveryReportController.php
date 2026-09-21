<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Support\PaginationMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reports = Delivery::query()
            ->with(['campaignRecipient.campaign.customer.user', 'campaignRecipient.message'])
            ->latest('id')
            ->paginate(PaginationMeta::perPage($request, 10, 200));

        return response()->json([
            'reports' => $reports->getCollection()->map->toAdminArray()->values(),
            'meta' => PaginationMeta::from($reports),
        ]);
    }
}
