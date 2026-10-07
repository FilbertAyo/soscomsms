<?php

namespace App\Notifications;

use App\Mail\SoscomMail;
use App\Models\SenderIdRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SenderIdResubmissionRequestedNotification extends Notification implements ShouldQueue
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
            ->subject('Sender ID '.$this->request->name.' needs a new document')
            ->greeting(SoscomMail::greetingFor($user))
            ->line('We could not use the identity document you uploaded for Sender ID '.$this->request->name.'. Please upload a clear PDF and submit again.');

        if ($reason !== '') {
            $mail->line('Reason: '.$reason);
        }

        return $mail->action(
            'Upload a new document',
            url('/sender-ids/'.$this->request->id),
        );
    }
}
