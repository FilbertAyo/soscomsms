<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCustomer;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Services\ApiKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiKeyController extends Controller
{
    use ResolvesCustomer;

    public function index(Request $request): JsonResponse
    {
        $customer = $this->currentCustomer($request);

        $keys = $customer->apiKeys()
            ->latest('id')
            ->get()
            ->map->toApiArray()
            ->values();

        return response()->json(['keys' => $keys]);
    }

    public function store(Request $request, ApiKeyService $keys): JsonResponse
    {
        $customer = $this->currentCustomer($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
        ]);

        $created = $keys->create($customer, $validated['name']);

        return response()->json([
            'key' => $created['plaintext'],
            'apiKey' => $created['key']->toApiArray(),
            'csrf_token' => csrf_token(),
        ], 201);
    }

    public function revoke(Request $request, ApiKey $apiKey, ApiKeyService $keys): JsonResponse
    {
        $customer = $this->currentCustomer($request);
        abort_unless($apiKey->customer_id === $customer->id, 404);

        $revoked = $keys->revoke($apiKey);

        return response()->json([
            'apiKey' => $revoked->toApiArray(),
            'csrf_token' => csrf_token(),
        ]);
    }
}
