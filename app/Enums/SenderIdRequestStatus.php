<?php

namespace App\Enums;

enum SenderIdRequestStatus: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case SubmittedToProvider = 'submitted_to_provider';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function isOpen(): bool
    {
        return match ($this) {
            self::Submitted, self::UnderReview, self::SubmittedToProvider => true,
            self::Approved, self::Rejected => false,
        };
    }

    public function currentStep(): string
    {
        return match ($this) {
            self::Submitted => 'submitted',
            self::UnderReview => 'under_review',
            self::SubmittedToProvider => 'submitted_to_provider',
            self::Approved => 'active',
            self::Rejected => 'rejected',
        };
    }
}
