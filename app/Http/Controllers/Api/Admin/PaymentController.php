<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Support\PaginationMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = Payment::query()
            ->with(['customer.user'])
            ->when($request->filled('status'), function ($query) use ($request): void {
                $query->where('status', $request->string('status')->toString());
            })
            ->latest('id')
            ->paginate(PaginationMeta::perPage($request));

        return response()->json([
            'payments' => $page->getCollection()->map->toApiArray()->values(),
            'meta' => PaginationMeta::from($page),
        ]);
    }
}
