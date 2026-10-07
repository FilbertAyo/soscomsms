<?php

namespace App\Notifications\Admin;

use App\Mail\SoscomMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewUserRegisteredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $user,
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
        return SoscomMail::message()
            ->subject('New Soscom SMS sign-up: '.$this->user->name)
            ->greeting('Hi,')
            ->line($this->user->name.' ('.$this->user->email.') just created an account.')
            ->line('Their business profile is not complete yet, so they cannot send SMS until they add it.')
            ->action('Open customers', url('/admin/customers'));
    }
}
