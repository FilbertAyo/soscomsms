<?php

namespace App\Notifications;

use App\Mail\SoscomMail;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginOtpNotification extends Notification
{

    public function __construct(
        public string $code,
        public int $expiresMinutes,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $user = $notifiable instanceof User ? $notifiable : null;

        return SoscomMail::message()
            ->subject('Your Soscom SMS verification code')
            ->greeting(SoscomMail::greetingFor($user))
            ->line('Use this code to finish signing in:')
            ->line($this->code)
            ->line('This code expires in '.$this->expiresMinutes.' minutes.')
            ->line('If you did not try to sign in, you can ignore this email.');
    }
}
