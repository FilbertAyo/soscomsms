<?php

namespace App\Notifications;

use App\Mail\SoscomMail;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentRequestedNotification extends Notification implements ShouldQueue
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

        return SoscomMail::message()
            ->subject('Complete your SMS purchase')
            ->greeting(SoscomMail::greetingFor($user))
            ->line('We sent a mobile money prompt for '.SoscomMail::tzs($this->payment->amount_tzs).' to '.$this->payment->payer_reference.' for '.$this->payment->quantity.' SMS.')
            ->line('Approve it on your phone. Your balance does not change until the payment is confirmed.')
            ->action('View wallet', url('/wallet'));
    }
}
