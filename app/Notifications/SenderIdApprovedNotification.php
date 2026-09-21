<?php

namespace App\Notifications;

use App\Mail\SoscomMail;
use App\Models\SenderIdRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SenderIdApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public SenderIdRequest $request,
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
            ->subject('Sender ID '.$this->request->name.' is ready')
            ->greeting(SoscomMail::greetingFor($user))
            ->line('Your sender ID '.$this->request->name.' is approved. You can send SMS with this name now.')
            ->action('Send SMS', url('/send-sms'));
    }
}
