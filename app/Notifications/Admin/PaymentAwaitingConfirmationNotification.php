<?php

namespace App\Notifications\Admin;

use App\Mail\SoscomMail;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentAwaitingConfirmationNotification extends Notification implements ShouldQueue
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
        $this->payment->loadMissing('customer.user');
        $customer = $this->payment->customer;
        $payer = $customer?->business_name ?: ($customer?->user?->name ?? 'A customer');

        return SoscomMail::message()
            ->subject('Payment to confirm: '.SoscomMail::tzs($this->payment->amount_tzs))
            ->greeting('Hi,')
            ->line($payer.' requested '.number_format($this->payment->quantity).' SMS for '.SoscomMail::tzs($this->payment->amount_tzs).'.')
            ->line('They will pay from: '.$this->payment->payer_reference)
            ->line('Check that the money arrived on Lipa namba '.$this->payment->lipa_namba.', then mark the payment as paid.')
            ->action('Open payments', url('/admin/payments'));
    }
}
