<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCustomer;
use App\Http\Controllers\Controller;
use App\Models\SenderId;
use App\Services\SenderIdService;
use App\Support\SenderIdName;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SenderIdController extends Controller
{
    use ResolvesCustomer;

    public function index(Request $request, SenderIdService $senderIds): JsonResponse
    {
        $customer = $this->currentCustomer($request);

        return response()->json([
            'senderIds' => array_map(
                fn (SenderId $senderId): array => $senderId->toApiArray(),
                $senderIds->usableFor($customer),
            ),
        ]);
    }

    public function assert(Request $request, SenderIdService $senderIds): JsonResponse
    {
        $customer = $this->currentCustomer($request);

        if ($request->filled('sender_id')) {
            $request->merge([
                'sender_id' => SenderIdName::normalize($request->string('sender_id')->toString()),
            ]);
        }

        $validated = $request->validate([
            'sender_id' => [
                'required',
                'string',
                'max:13',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! SenderIdName::isValid((string) $value)) {
                        $fail(SenderIdName::invalidMessage());
                    }
                },
            ],
        ]);

        $senderId = $senderIds->assertUsable($customer, $validated['sender_id']);

        return response()->json([
            'senderId' => $senderId->toApiArray(),
        ]);
    }
}
