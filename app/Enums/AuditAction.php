<?php

namespace App\Enums;

enum AuditAction: string
{
    case CreditsGranted = 'credits.granted';
    case CreditsReversed = 'credits.reversed';
    case CustomerSuspended = 'customer.suspended';
    case CustomerUnsuspended = 'customer.unsuspended';
    case PaymentConfirmed = 'payment.confirmed';
    case PaymentRejected = 'payment.rejected';
    case SenderIdReviewed = 'sender_id.reviewed';
    case SenderIdSubmitted = 'sender_id.submitted';
    case SenderIdApproved = 'sender_id.approved';
    case SenderIdRejected = 'sender_id.rejected';
    case PricingUpdated = 'pricing.updated';
    case ProviderSettingsUpdated = 'provider.settings_updated';
    case ProviderWhitelistConfirmed = 'provider.whitelist_confirmed';
    case ProviderAccountConfirmed = 'provider.account_confirmed';
}
