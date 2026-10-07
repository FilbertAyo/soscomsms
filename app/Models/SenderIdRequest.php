<?php

namespace App\Models;

use App\Enums\SenderIdRequestStatus;
use Database\Factories\SenderIdRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\Response;

class SenderIdRequest extends Model
{
    /** @use HasFactory<SenderIdRequestFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'name',
        'purpose',
        'organization',
        'website_url',
        'description',
        'sample_content',
        'identity_document_type',
        'status',
        'submitted_to_provider_at',
        'provider_reference',
        'notes',
        'rejection_reason',
        'reviewed_by',
        'resolved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SenderIdRequestStatus::class,
            'submitted_to_provider_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return HasOne<SenderId, $this>
     */
    public function senderId(): HasOne
    {
        return $this->hasOne(SenderId::class);
    }

    /**
     * @return HasOne<SenderIdRequestDocument, $this>
     */
    public function identityDocument(): HasOne
    {
        return $this->hasOne(SenderIdRequestDocument::class);
    }

    public function timelineIndex(): int
    {
        return match ($this->status) {
            SenderIdRequestStatus::Submitted => 0,
            SenderIdRequestStatus::UnderReview => 1,
            SenderIdRequestStatus::SubmittedToProvider => 2,
            SenderIdRequestStatus::Approved => 4,
            SenderIdRequestStatus::Rejected => $this->submitted_to_provider_at !== null
                ? 2
                : ($this->reviewed_by !== null ? 1 : 0),
        };
    }

    /**
     * @return array{id: int, name: string, purpose: string, organization: string, websiteUrl: string|null, description: string|null, sampleContent: string|null, identityDocumentType: string|null, identityDocumentName: string|null, hasIdentityDocument: bool, status: string, currentStep: string, timelineIndex: int, submittedToProviderAt: string|null, rejectionReason: string|null, createdAt: string|null, senderIdId: int|null}
     */
    public function toApiArray(): array
    {
        $this->loadMissing('senderId');
        $document = $this->identityDocumentMeta();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'purpose' => $this->purpose,
            'organization' => $this->organization,
            'websiteUrl' => $this->website_url,
            'description' => $this->description,
            'sampleContent' => $this->sample_content,
            'identityDocumentType' => $this->identity_document_type,
            'identityDocumentName' => $document?->original_name,
            'hasIdentityDocument' => $document !== null,
            'status' => $this->status->value,
            'currentStep' => $this->status->currentStep(),
            'timelineIndex' => $this->timelineIndex(),
            'submittedToProviderAt' => $this->submitted_to_provider_at?->toIso8601String(),
            'rejectionReason' => $this->rejection_reason,
            'createdAt' => $this->created_at?->toIso8601String(),
            'senderIdId' => $this->senderId?->id,
        ];
    }

    public function identityDocumentResponse(): Response
    {
        $document = $this->identityDocument()->first();
        abort_if($document === null, 404);

        $bytes = $document->bytes();
        abort_if($bytes === '', 404);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$document->inlineFilename().'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function identityDocumentMeta(): ?SenderIdRequestDocument
    {
        if ($this->relationLoaded('identityDocument')) {
            return $this->identityDocument;
        }

        return $this->identityDocument()
            ->first(['id', 'sender_id_request_id', 'original_name', 'size']);
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        $this->loadMissing(['customer.user', 'senderId']);

        return [
            ...$this->toApiArray(),
            'customerId' => $this->customer_id,
            'customerName' => $this->customer?->user?->name,
            'customerBusiness' => $this->customer?->business_name,
            'customerEmail' => $this->customer?->user?->email,
            'providerReference' => $this->provider_reference,
            'notes' => $this->notes,
            'resolvedAt' => $this->resolved_at?->toIso8601String(),
        ];
    }
}
