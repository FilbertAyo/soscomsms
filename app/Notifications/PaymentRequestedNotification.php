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
            ->line('Pay '.SoscomMail::tzs($this->payment->amount_tzs).' to Lipa namba '.$this->payment->lipa_namba.' ('.config('sms.billing.lipa_namba_name', 'SOSCOM TECHNOLOGIES COMPANY LIMITED').') for '.$this->payment->quantity.' SMS.')
            ->line('We will add the SMS after we confirm the payment. Your balance does not change until then.')
            ->action('View billing', url('/settings/billing'));
    }
}
