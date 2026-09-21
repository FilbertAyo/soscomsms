<?php

namespace App\Notifications;

use App\Mail\SoscomMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeCustomerNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $welcomeCredits,
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
            ->subject('Welcome to Soscom SMS')
            ->greeting(SoscomMail::greetingFor($user))
            ->line('Your account is ready. You have '.$this->welcomeCredits.' free SMS to start sending.')
            ->line('Complete your business profile, then send your first message.')
            ->action('Open dashboard', url('/dashboard'));
    }
}
