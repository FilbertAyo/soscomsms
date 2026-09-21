<?php

namespace App\Notifications;

use App\Mail\SoscomMail;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Payment $payment,
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
        $reason = trim((string) $this->payment->rejection_reason);

        $mail = SoscomMail::message()
            ->subject('Payment not confirmed')
            ->greeting(SoscomMail::greetingFor($user))
            ->line('We could not confirm your payment for '.$this->payment->quantity.' SMS.');

        if ($reason !== '') {
            $mail->line('Reason: '.$reason);
        }

        return $mail
            ->line('No SMS were added. You can submit a new purchase from your wallet.')
            ->action('Buy SMS', url('/wallet/buy'));
    }
}
