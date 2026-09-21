<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\ResolvesApiCustomer;
use App\Http\Controllers\Controller;
use App\Models\SenderId;
use App\Services\SenderIdService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SenderIdController extends Controller
{
    use ResolvesApiCustomer;

    public function index(Request $request, SenderIdService $senderIds): JsonResponse
    {
        $customer = $this->apiCustomer($request);

        return response()->json([
            'sender_ids' => array_map(
                fn (SenderId $senderId): array => $senderId->toPublicArray(),
                $senderIds->usableFor($customer),
            ),
        ]);
    }
}
