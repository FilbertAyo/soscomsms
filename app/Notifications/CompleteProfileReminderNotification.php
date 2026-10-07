<?php

namespace App\Notifications;

use App\Mail\SoscomMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CompleteProfileReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const TOTAL = 3;

    public function __construct(
        public int $step,
        public int $smsBalance,
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
        $free = $this->smsBalance > 0
            ? 'You already have '.number_format($this->smsBalance).' free SMS in your account.'
            : 'Your account is ready to send as soon as your profile is complete.';

        $message = SoscomMail::message()->greeting(SoscomMail::greetingFor($user));

        $message = match ($this->step) {
            1 => $message
                ->subject('Your Soscom SMS account is almost ready')
                ->line('Thanks for joining Soscom SMS. One small step is left before you can send your first message.')
                ->line($free),
            2 => $message
                ->subject('Your free SMS are waiting')
                ->line($free)
                ->line('Finish your profile and you can start sending right away.'),
            default => $message
                ->subject('Last reminder: finish setting up Soscom SMS')
                ->line('We do not want you to miss out. '.$free)
                ->line('If anything is unclear, the Help page in your dashboard walks you through it.'),
        };

        return $message
            ->line('It takes about a minute: just your business name and phone number.')
            ->action('Complete my profile', url('/settings/business'))
            ->line('This is reminder '.$this->step.' of '.self::TOTAL.'. We will stop once your profile is complete.');
    }
}
