<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\SenderIdRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SenderIdService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SenderIdRequestController extends Controller
{
    public function index(): JsonResponse
    {
        $requests = SenderIdRequest::query()
            ->with([
                'customer.user',
                'senderId',
                'identityDocument' => fn ($query) => $query->select([
                    'id',
                    'sender_id_request_id',
                    'original_name',
                    'size',
                ]),
            ])
            ->latest('id')
            ->get()
            ->map->toAdminArray()
            ->values();

        return response()->json(['requests' => $requests]);
    }

    public function show(SenderIdRequest $senderIdRequest): JsonResponse
    {
        return response()->json([
            'request' => $senderIdRequest->toAdminArray(),
        ]);
    }

    public function identityDocument(SenderIdRequest $senderIdRequest): Response
    {
        return $senderIdRequest->identityDocumentResponse();
    }

    public function review(
        Request $request,
        SenderIdRequest $senderIdRequest,
        SenderIdService $senderIds,
        AuditLogger $audit,
    ): JsonResponse {
        $updated = $senderIds->markUnderReview($senderIdRequest, $this->admin($request));
        $this->audit($request, $audit, AuditAction::SenderIdReviewed, $updated);

        return response()->json([
            'request' => $updated->toAdminArray(),
            'csrf_token' => csrf_token(),
        ]);
    }

    public function submitToProvider(
        Request $request,
        SenderIdRequest $senderIdRequest,
        SenderIdService $senderIds,
        AuditLogger $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'submitted_to_provider_at' => ['nullable', 'date'],
            'provider_reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $updated = $senderIds->submitToProvider(
            $senderIdRequest,
            $this->admin($request),
            $validated,
        );
        $this->audit($request, $audit, AuditAction::SenderIdSubmitted, $updated);

        return response()->json([
            'request' => $updated->toAdminArray(),
            'csrf_token' => csrf_token(),
        ]);
    }

    public function approve(
        Request $request,
        SenderIdRequest $senderIdRequest,
        SenderIdService $senderIds,
        AuditLogger $audit,
    ): JsonResponse {
        $updated = $senderIds->approve($senderIdRequest, $this->admin($request));
        $this->audit($request, $audit, AuditAction::SenderIdApproved, $updated);

        return response()->json([
            'request' => $updated->toAdminArray(),
            'csrf_token' => csrf_token(),
        ]);
    }

    public function reject(
        Request $request,
        SenderIdRequest $senderIdRequest,
        SenderIdService $senderIds,
        AuditLogger $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:255'],
        ]);

        $updated = $senderIds->reject(
            $senderIdRequest,
            $this->admin($request),
            $validated['rejection_reason'],
        );
        $this->audit($request, $audit, AuditAction::SenderIdRejected, $updated, [
            'reason' => $validated['rejection_reason'],
        ]);

        return response()->json([
            'request' => $updated->toAdminArray(),
            'csrf_token' => csrf_token(),
        ]);
    }

    public function requestResubmission(
        Request $request,
        SenderIdRequest $senderIdRequest,
        SenderIdService $senderIds,
        AuditLogger $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:255'],
        ]);

        $updated = $senderIds->requestResubmission(
            $senderIdRequest,
            $this->admin($request),
            $validated['rejection_reason'],
        );
        $this->audit($request, $audit, AuditAction::SenderIdResubmissionRequested, $updated, [
            'reason' => $validated['rejection_reason'],
        ]);

        return response()->json([
            'request' => $updated->toAdminArray(),
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
        SenderIdRequest $senderIdRequest,
        array $payload = [],
    ): void {
        $audit->record(
            actor: $this->admin($request),
            action: $action,
            subject: $senderIdRequest,
            customer: $senderIdRequest->customer,
            payload: array_merge(['name' => $senderIdRequest->name], $payload),
        );
    }
}
