<?php

namespace App\Enums;

enum IdentityDocumentType: string
{
    case NationalId = 'national_id';
    case Passport = 'passport';
    case VotersId = 'voters_id';
    case DrivingLicence = 'driving_licence';
    case BrelaCertificate = 'brela_certificate';
    case TinCertificate = 'tin_certificate';
    case BusinessLicence = 'business_licence';

    public function label(): string
    {
        return match ($this) {
            self::NationalId => 'National ID (NIDA)',
            self::Passport => 'Passport',
            self::VotersId => "Voter's ID",
            self::DrivingLicence => 'Driving licence',
            self::BrelaCertificate => 'BRELA certificate',
            self::TinCertificate => 'TIN certificate',
            self::BusinessLicence => 'Business licence',
        };
    }

    public static function labelFor(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return self::tryFrom($value)?->label() ?? $value;
    }
}
