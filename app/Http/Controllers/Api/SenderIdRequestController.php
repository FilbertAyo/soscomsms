<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCustomer;
use App\Http\Controllers\Controller;
use App\Models\SenderIdRequest;
use App\Services\SenderIdService;
use App\Support\SenderIdName;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SenderIdRequestController extends Controller
{
    use ResolvesCustomer;

    public function index(Request $request): JsonResponse
    {
        $customer = $this->currentCustomer($request);

        $requests = $customer->senderIdRequests()
            ->with('senderId')
            ->latest('id')
            ->get()
            ->map->toApiArray()
            ->values();

        return response()->json(['requests' => $requests]);
    }

    public function show(Request $request, SenderIdRequest $senderIdRequest): JsonResponse
    {
        $customer = $this->currentCustomer($request);
        abort_unless($senderIdRequest->customer_id === $customer->id, 404);

        return response()->json([
            'request' => $senderIdRequest->toApiArray(),
        ]);
    }

    public function store(Request $request, SenderIdService $senderIds): JsonResponse
    {
        $customer = $this->currentCustomer($request);

        if ($request->filled('name')) {
            $request->merge([
                'name' => SenderIdName::normalize($request->string('name')->toString()),
            ]);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:13',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! SenderIdName::isValid((string) $value)) {
                        $fail(SenderIdName::invalidMessage());
                    }
                },
            ],
            'purpose' => ['required', 'string', 'max:120'],
            'organization' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $created = $senderIds->request($customer, $validated);

        return response()->json([
            'request' => $created->toApiArray(),
            'csrf_token' => csrf_token(),
        ], 201);
    }
}
