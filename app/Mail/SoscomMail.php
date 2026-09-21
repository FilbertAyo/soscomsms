<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

final class SoscomMail
{
    public static function message(): MailMessage
    {
        return (new MailMessage)
            ->from(
                (string) config('mail.from.address'),
                (string) config('mail.from.name'),
            )
            ->salutation('Soscom SMS');
    }

    public static function greetingFor(?User $user): string
    {
        $name = trim((string) $user?->name);
        if ($name === '') {
            return 'Hi,';
        }

        $first = explode(' ', $name)[0];

        return 'Hi '.$first.',';
    }

    public static function tzs(int $amount): string
    {
        return 'TZS '.number_format($amount, 0, '.', ',');
    }
}
