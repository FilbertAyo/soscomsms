<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\ResolvesApiCustomer;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BalanceController extends Controller
{
    use ResolvesApiCustomer;

    public function show(Request $request): JsonResponse
    {
        $customer = $this->apiCustomer($request);

        return response()->json([
            'sms_balance' => $customer->sms_balance,
        ]);
    }
}
