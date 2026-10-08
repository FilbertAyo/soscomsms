<?php

namespace App\Notifications;

use App\Mail\SoscomMail;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentConfirmedNotification extends Notification implements ShouldQueue
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
        $balance = (int) ($this->payment->customer?->sms_balance ?? 0);

        return SoscomMail::message()
            ->subject($this->payment->quantity.' SMS added to your account')
            ->greeting(SoscomMail::greetingFor($user))
            ->line('Your mobile money payment of '.SoscomMail::tzs($this->payment->amount_tzs).' is confirmed.')
            ->line($this->payment->quantity.' SMS are now in your account. Your balance is '.number_format($balance).' SMS.')
            ->action('Send SMS', url('/send-sms'));
    }
}
