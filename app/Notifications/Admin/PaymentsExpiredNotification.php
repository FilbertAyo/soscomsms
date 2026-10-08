<?php

namespace App\Notifications\Admin;

use App\Mail\SoscomMail;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentsExpiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, Payment>  $payments
     */
    public function __construct(
        public Collection $payments,
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
        $count = $this->payments->count();
        $message = SoscomMail::message()
            ->subject($count === 1 ? '1 payment expired unconfirmed' : $count.' payments expired unconfirmed')
            ->greeting('Hi,')
            ->line('These phone prompts expired before the customer approved them:');

        foreach ($this->payments as $payment) {
            $payment->loadMissing('customer.user');
            $customer = $payment->customer;
            $payer = $customer?->business_name ?: ($customer?->user?->name ?? 'Unknown customer');

            $message->line('- '.$payer.': '.number_format($payment->quantity).' SMS, '.SoscomMail::tzs($payment->amount_tzs).' (from '.$payment->payer_reference.')');
        }

        return $message
            ->line('No SMS were added. The customer can start a new purchase from Buy SMS.')
            ->action('Open payments', url('/admin/payments'));
    }
}
