<?php

namespace App\Services;

use App\Enums\SenderIdRequestStatus;
use App\Enums\SenderIdSource;
use App\Enums\SenderIdStatus;
use App\Models\Customer;
use App\Models\SenderId;
use App\Models\SenderIdRequest;
use App\Models\User;
use App\Notifications\SenderIdApprovedNotification;
use App\Notifications\SenderIdRejectedNotification;
use App\Support\SenderIdName;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SenderIdService
{
    /**
     * @return list<SenderId>
     */
    public function usableFor(Customer $customer): array
    {
        return SenderId::query()
            ->with('request')
            ->usableBy($customer)
            ->orderBy('name')
            ->get()
            ->all();
    }

    public function assertUsable(Customer $customer, string $name): SenderId
    {
        $normalized = SenderIdName::normalize($name);

        $senderId = SenderId::query()
            ->usableBy($customer)
            ->whereRaw('LOWER(name) = ?', [SenderIdName::key($normalized)])
            ->first();

        if ($senderId === null) {
            throw ValidationException::withMessages([
                'sender_id' => 'Choose an active Sender ID that belongs to this account.',
            ]);
        }

        return $senderId;
    }

    /**
     * @param  array{name: string, purpose: string, organization: string, description?: string|null}  $payload
     */
    public function request(Customer $customer, array $payload): SenderIdRequest
    {
        $name = SenderIdName::normalize($payload['name']);

        if (! SenderIdName::isValid($name)) {
            throw ValidationException::withMessages([
                'name' => SenderIdName::invalidMessage(),
            ]);
        }

        if (SenderIdName::isBlocked($name)) {
            throw ValidationException::withMessages([
                'name' => 'This Sender ID is reserved and cannot be requested.',
            ]);
        }

        $this->assertNameAvailable($name, $customer);

        return $customer->senderIdRequests()->create([
            'name' => $name,
            'purpose' => $payload['purpose'],
            'organization' => $payload['organization'],
            'description' => $payload['description'] ?? null,
            'status' => SenderIdRequestStatus::Submitted,
        ]);
    }

    public function markUnderReview(SenderIdRequest $request, User $admin): SenderIdRequest
    {
        return match ($request->status) {
            SenderIdRequestStatus::Submitted, SenderIdRequestStatus::UnderReview => $this->saveStatus(
                $request,
                SenderIdRequestStatus::UnderReview,
                $admin,
            ),
            SenderIdRequestStatus::SubmittedToProvider,
            SenderIdRequestStatus::Approved,
            SenderIdRequestStatus::Rejected => throw ValidationException::withMessages([
                'status' => 'This request can no longer be marked under review.',
            ]),
        };
    }

    /**
     * @param  array{submitted_to_provider_at?: string|null, provider_reference?: string|null, notes?: string|null}  $payload
     */
    public function submitToProvider(SenderIdRequest $request, User $admin, array $payload = []): SenderIdRequest
    {
        return match ($request->status) {
            SenderIdRequestStatus::Submitted, SenderIdRequestStatus::UnderReview => $this->saveStatus(
                $request,
                SenderIdRequestStatus::SubmittedToProvider,
                $admin,
                [
                    'submitted_to_provider_at' => $payload['submitted_to_provider_at'] ?? now(),
                    'provider_reference' => $payload['provider_reference'] ?? $request->provider_reference,
                    'notes' => $payload['notes'] ?? $request->notes,
                ],
            ),
            SenderIdRequestStatus::SubmittedToProvider,
            SenderIdRequestStatus::Approved,
            SenderIdRequestStatus::Rejected => throw ValidationException::withMessages([
                'status' => 'This request has already been submitted to the SMS provider, or is closed.',
            ]),
        };
    }

    public function approve(SenderIdRequest $request, User $admin): SenderIdRequest
    {
        $pendingProvider = $request->status === SenderIdRequestStatus::SubmittedToProvider;

        $updated = match ($request->status) {
            SenderIdRequestStatus::SubmittedToProvider => DB::transaction(function () use ($request, $admin): SenderIdRequest {
                $customer = $request->customer;
                if ($customer === null) {
                    throw ValidationException::withMessages([
                        'status' => 'This request has no customer.',
                    ]);
                }

                $this->assertNameAvailable($request->name, $customer, $request->id);

                $saved = $this->saveStatus($request, SenderIdRequestStatus::Approved, $admin, [
                    'resolved_at' => now(),
                    'rejection_reason' => null,
                ]);

                SenderId::query()->create([
                    'customer_id' => $saved->customer_id,
                    'sender_id_request_id' => $saved->id,
                    'name' => $saved->name,
                    'status' => SenderIdStatus::Active,
                    'source' => SenderIdSource::Request,
                ]);

                return $saved->load(['senderId', 'customer.user']);
            }),
            SenderIdRequestStatus::Approved => $request->load('senderId'),
            SenderIdRequestStatus::Submitted,
            SenderIdRequestStatus::UnderReview,
            SenderIdRequestStatus::Rejected => throw ValidationException::withMessages([
                'status' => 'Submit this request to Mobishastra before recording an approval.',
            ]),
        };

        if ($pendingProvider) {
            $updated->loadMissing('customer.user');
            $updated->customer?->notifyOwner(new SenderIdApprovedNotification($updated));
        }

        return $updated;
    }

    public function reject(SenderIdRequest $request, User $admin, string $reason): SenderIdRequest
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'rejection_reason' => 'Give a reason so the customer knows why it was rejected.',
            ]);
        }

        return match ($request->status) {
            SenderIdRequestStatus::Submitted,
            SenderIdRequestStatus::UnderReview,
            SenderIdRequestStatus::SubmittedToProvider => tap(
                $this->saveStatus(
                    $request,
                    SenderIdRequestStatus::Rejected,
                    $admin,
                    [
                        'rejection_reason' => $reason,
                        'resolved_at' => now(),
                    ],
                ),
                function (SenderIdRequest $updated): void {
                    $updated->loadMissing('customer.user');
                    $updated->customer?->notifyOwner(new SenderIdRejectedNotification($updated));
                },
            ),
            SenderIdRequestStatus::Approved, SenderIdRequestStatus::Rejected => throw ValidationException::withMessages([
                'status' => 'This request is already closed.',
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function saveStatus(
        SenderIdRequest $request,
        SenderIdRequestStatus $status,
        User $admin,
        array $extra = [],
    ): SenderIdRequest {
        $request->fill($extra);
        $request->status = $status;
        $request->reviewed_by = $admin->id;
        $request->save();

        return $request->fresh() ?? $request;
    }

    private function assertNameAvailable(string $name, Customer $customer, ?int $ignoreRequestId = null): void
    {
        $key = SenderIdName::key($name);

        $taken = SenderId::query()
            ->whereRaw('LOWER(name) = ?', [$key])
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'name' => 'This Sender ID is already in use.',
            ]);
        }

        $open = SenderIdRequest::query()
            ->whereIn('status', [
                SenderIdRequestStatus::Submitted,
                SenderIdRequestStatus::UnderReview,
                SenderIdRequestStatus::SubmittedToProvider,
            ])
            ->whereRaw('LOWER(name) = ?', [$key])
            ->when($ignoreRequestId !== null, fn ($query) => $query->where('id', '!=', $ignoreRequestId))
            ->first();

        if ($open !== null && $open->customer_id !== $customer->id) {
            throw ValidationException::withMessages([
                'name' => 'This Sender ID is already in use.',
            ]);
        }

        if ($open !== null && $open->customer_id === $customer->id && $open->id !== $ignoreRequestId) {
            throw ValidationException::withMessages([
                'name' => 'You already have an open request for this Sender ID.',
            ]);
        }
    }
}
