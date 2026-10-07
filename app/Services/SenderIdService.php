<?php

namespace App\Services;

use App\Enums\IdentityDocumentType;
use App\Enums\SenderIdRequestStatus;
use App\Enums\SenderIdSource;
use App\Enums\SenderIdStatus;
use App\Enums\SenderIdUseCase;
use App\Models\Customer;
use App\Models\SenderId;
use App\Models\SenderIdRequest;
use App\Models\User;
use App\Notifications\Admin\SenderIdRequestedNotification;
use App\Notifications\Admin\SenderIdResubmittedNotification;
use App\Notifications\SenderIdApprovedNotification;
use App\Notifications\SenderIdRejectedNotification;
use App\Notifications\SenderIdResubmissionRequestedNotification;
use App\Support\AdminAlerts;
use App\Support\SenderIdName;
use Illuminate\Http\UploadedFile;
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
     * @param  array{name: string, purpose: string, organization: string, website_url?: string|null, description?: string|null, sample_content: string, identity_document_type: string}  $payload
     */
    public function request(Customer $customer, array $payload, UploadedFile $document): SenderIdRequest
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

        $bytes = $this->pdfBytes($document);

        $created = DB::transaction(function () use ($customer, $name, $payload, $document, $bytes): SenderIdRequest {
            $created = $customer->senderIdRequests()->create([
                'name' => $name,
                'purpose' => SenderIdUseCase::from($payload['purpose'])->value,
                'organization' => $payload['organization'],
                'website_url' => filled($payload['website_url'] ?? null) ? $payload['website_url'] : null,
                'description' => $payload['description'] ?? null,
                'sample_content' => $payload['sample_content'],
                'identity_document_type' => IdentityDocumentType::from($payload['identity_document_type'])->value,
                'status' => SenderIdRequestStatus::Submitted,
            ]);

            $created->identityDocument()->create([
                'original_name' => $this->documentName($document),
                'size' => (int) $document->getSize(),
                'contents' => $bytes,
            ]);

            return $created;
        });

        AdminAlerts::notify(new SenderIdRequestedNotification($created));

        return $created;
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
            SenderIdRequestStatus::NeedsResubmission,
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
            SenderIdRequestStatus::NeedsResubmission,
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
                    'available_to_all' => false,
                ]);

                return $saved->load(['senderId', 'customer.user']);
            }),
            SenderIdRequestStatus::Approved => $request->load('senderId'),
            SenderIdRequestStatus::Submitted,
            SenderIdRequestStatus::UnderReview,
            SenderIdRequestStatus::NeedsResubmission,
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
            SenderIdRequestStatus::SubmittedToProvider,
            SenderIdRequestStatus::NeedsResubmission => tap(
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

    public function requestResubmission(SenderIdRequest $request, User $admin, string $reason): SenderIdRequest
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'rejection_reason' => 'Give a reason so the customer knows what to upload.',
            ]);
        }

        return match ($request->status) {
            SenderIdRequestStatus::Submitted,
            SenderIdRequestStatus::UnderReview,
            SenderIdRequestStatus::SubmittedToProvider,
            SenderIdRequestStatus::NeedsResubmission => tap(
                $this->saveStatus(
                    $request,
                    SenderIdRequestStatus::NeedsResubmission,
                    $admin,
                    [
                        'rejection_reason' => $reason,
                        'resolved_at' => null,
                    ],
                ),
                function (SenderIdRequest $updated): void {
                    $updated->loadMissing('customer.user');
                    $updated->customer?->notifyOwner(new SenderIdResubmissionRequestedNotification($updated));
                },
            ),
            SenderIdRequestStatus::Approved, SenderIdRequestStatus::Rejected => throw ValidationException::withMessages([
                'status' => 'This request is already closed.',
            ]),
        };
    }

    public function resubmitDocument(
        SenderIdRequest $request,
        string $identityType,
        UploadedFile $document,
    ): SenderIdRequest {
        if ($request->status !== SenderIdRequestStatus::NeedsResubmission) {
            throw ValidationException::withMessages([
                'status' => 'This request is not waiting for a new document.',
            ]);
        }

        $bytes = $this->pdfBytes($document);

        $updated = DB::transaction(function () use ($request, $identityType, $document, $bytes): SenderIdRequest {
            $request->fill([
                'identity_document_type' => IdentityDocumentType::from($identityType)->value,
                'status' => SenderIdRequestStatus::Submitted,
                'rejection_reason' => null,
                'resolved_at' => null,
            ]);
            $request->save();

            $request->identityDocument()->delete();
            $request->identityDocument()->create([
                'original_name' => $this->documentName($document),
                'size' => (int) $document->getSize(),
                'contents' => $bytes,
            ]);

            return $request->fresh() ?? $request;
        });

        AdminAlerts::notify(new SenderIdResubmittedNotification($updated));

        return $updated;
    }

    /**
     * @param  array{name: string, generic?: bool, available_to_all?: bool}  $payload
     */
    public function createCatalog(array $payload): SenderId
    {
        $name = SenderIdName::normalize($payload['name']);

        if (! SenderIdName::isValid($name, allowSpaces: true)) {
            throw ValidationException::withMessages([
                'name' => SenderIdName::invalidMessage(allowSpaces: true),
            ]);
        }

        if (SenderIdName::isBlocked($name)) {
            throw ValidationException::withMessages([
                'name' => 'This Sender ID is reserved and cannot be added.',
            ]);
        }

        $taken = SenderId::query()
            ->whereRaw('LOWER(name) = ?', [SenderIdName::key($name)])
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'name' => 'This Sender ID is already in use.',
            ]);
        }

        $generic = (bool) ($payload['generic'] ?? true);

        return SenderId::query()->create([
            'customer_id' => null,
            'sender_id_request_id' => null,
            'name' => $name,
            'status' => SenderIdStatus::Active,
            'source' => $generic ? SenderIdSource::Generic : SenderIdSource::Admin,
            'available_to_all' => (bool) ($payload['available_to_all'] ?? false),
        ]);
    }

    /**
     * @param  array{available_to_all?: bool, status?: string, generic?: bool}  $payload
     */
    public function updateCatalog(SenderId $senderId, array $payload): SenderId
    {
        if (array_key_exists('available_to_all', $payload)) {
            $senderId->available_to_all = (bool) $payload['available_to_all'];
        }

        if (array_key_exists('generic', $payload)) {
            if ($senderId->source === SenderIdSource::Request) {
                throw ValidationException::withMessages([
                    'generic' => 'A customer request Sender ID cannot change type.',
                ]);
            }

            $senderId->source = $payload['generic']
                ? SenderIdSource::Generic
                : SenderIdSource::Admin;
        }

        if (isset($payload['status'])) {
            $senderId->status = SenderIdStatus::from($payload['status']);
        }

        $senderId->save();

        return $senderId->fresh(['grantedCustomers.user']) ?? $senderId;
    }

    public function grant(SenderId $senderId, Customer $customer): SenderId
    {
        $senderId->grantedCustomers()->syncWithoutDetaching([$customer->id]);

        return $senderId->fresh(['grantedCustomers.user']) ?? $senderId;
    }

    public function revoke(SenderId $senderId, Customer $customer): SenderId
    {
        $senderId->grantedCustomers()->detach($customer->id);

        return $senderId->fresh(['grantedCustomers.user']) ?? $senderId;
    }

    public function deleteCatalog(SenderId $senderId): void
    {
        if ($senderId->campaigns()->exists()) {
            throw ValidationException::withMessages([
                'sender_id' => 'This Sender ID has been used on campaigns. Deactivate it instead.',
            ]);
        }

        $senderId->delete();
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
                SenderIdRequestStatus::NeedsResubmission,
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

    private function pdfBytes(UploadedFile $document): string
    {
        $path = $document->getRealPath();
        $bytes = is_string($path) ? file_get_contents($path) : false;

        if (! is_string($bytes) || ! str_starts_with($bytes, '%PDF')) {
            throw ValidationException::withMessages([
                'identity_document' => 'Upload a PDF of the selected identity document.',
            ]);
        }

        return $bytes;
    }

    private function documentName(UploadedFile $document): string
    {
        $name = basename(str_replace('\\', '/', $document->getClientOriginalName()));
        $name = trim($name);

        if ($name === '' || $name === '.' || $name === '..') {
            return 'identity-document.pdf';
        }

        if (mb_strlen($name) > 180) {
            $name = mb_substr($name, 0, 180);
        }

        return $name;
    }
}
