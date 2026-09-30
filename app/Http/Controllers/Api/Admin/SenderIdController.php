<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\AuditAction;
use App\Enums\SenderIdStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SenderId;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SenderIdService;
use App\Support\SenderIdName;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SenderIdController extends Controller
{
    public function index(): JsonResponse
    {
        $senderIds = SenderId::query()
            ->with(['grantedCustomers.user'])
            ->orderBy('name')
            ->get()
            ->map->toAdminCatalogArray()
            ->values();

        return response()->json(['senderIds' => $senderIds]);
    }

    public function store(
        Request $request,
        SenderIdService $senderIds,
        AuditLogger $audit,
    ): JsonResponse {
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
                    if (! SenderIdName::isValid((string) $value, allowSpaces: true)) {
                        $fail(SenderIdName::invalidMessage(allowSpaces: true));
                    }
                },
            ],
            'generic' => ['sometimes', 'boolean'],
            'available_to_all' => ['sometimes', 'boolean'],
        ]);

        $created = $senderIds->createCatalog($validated);
        $this->audit($request, $audit, AuditAction::SenderIdCreated, $created);

        return response()->json([
            'senderId' => $created->toAdminCatalogArray(),
            'csrf_token' => csrf_token(),
        ], 201);
    }

    public function update(
        Request $request,
        SenderId $senderId,
        SenderIdService $senderIds,
        AuditLogger $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'generic' => ['sometimes', 'boolean'],
            'available_to_all' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', Rule::enum(SenderIdStatus::class)],
        ]);

        $updated = $senderIds->updateCatalog($senderId, $validated);
        $this->audit($request, $audit, AuditAction::SenderIdUpdated, $updated, $validated);

        return response()->json([
            'senderId' => $updated->toAdminCatalogArray(),
            'csrf_token' => csrf_token(),
        ]);
    }

    public function grant(
        Request $request,
        SenderId $senderId,
        SenderIdService $senderIds,
        AuditLogger $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
        ]);

        $customer = Customer::query()->findOrFail($validated['customer_id']);
        $updated = $senderIds->grant($senderId, $customer);
        $this->audit($request, $audit, AuditAction::SenderIdGranted, $updated, [
            'customer_id' => $customer->id,
        ], $customer);

        return response()->json([
            'senderId' => $updated->toAdminCatalogArray(),
            'csrf_token' => csrf_token(),
        ]);
    }

    public function revoke(
        Request $request,
        SenderId $senderId,
        Customer $customer,
        SenderIdService $senderIds,
        AuditLogger $audit,
    ): JsonResponse {
        $updated = $senderIds->revoke($senderId, $customer);
        $this->audit($request, $audit, AuditAction::SenderIdRevoked, $updated, [
            'customer_id' => $customer->id,
        ], $customer);

        return response()->json([
            'senderId' => $updated->toAdminCatalogArray(),
            'csrf_token' => csrf_token(),
        ]);
    }

    public function destroy(
        Request $request,
        SenderId $senderId,
        SenderIdService $senderIds,
        AuditLogger $audit,
    ): JsonResponse {
        $name = $senderId->name;
        $senderIds->deleteCatalog($senderId);
        $this->audit($request, $audit, AuditAction::SenderIdUpdated, $senderId, [
            'deleted' => true,
            'name' => $name,
        ]);

        return response()->json([
            'csrf_token' => csrf_token(),
        ]);
    }

    private function admin(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function audit(
        Request $request,
        AuditLogger $audit,
        AuditAction $action,
        SenderId $senderId,
        array $payload = [],
        ?Customer $customer = null,
    ): void {
        $audit->record(
            actor: $this->admin($request),
            action: $action,
            subject: $senderId,
            customer: $customer,
            payload: array_merge(['name' => $senderId->name], $payload),
        );
    }
}
