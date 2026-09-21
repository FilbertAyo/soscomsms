<?php

namespace App\Notifications;

use App\Mail\SoscomMail;
use App\Models\SenderIdRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SenderIdRejectedNotification extends Notification implements ShouldQueue
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
        $reason = trim((string) $this->request->rejection_reason);

        $mail = SoscomMail::message()
            ->subject('Sender ID '.$this->request->name.' was not approved')
            ->greeting(SoscomMail::greetingFor($user))
            ->line('Your sender ID request for '.$this->request->name.' was not approved.');

        if ($reason !== '') {
            $mail->line('Reason: '.$reason);
        }

        return $mail
            ->line('You can request a different name from your dashboard.')
            ->action('Request sender ID', url('/sender-ids/request'));
    }
}
