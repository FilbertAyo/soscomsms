<?php

namespace App\Notifications;

use App\Mail\SoscomMail;
use App\Models\Provider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProviderLowBalanceNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Provider $provider,
        public int $smsBalance,
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
            ->subject('Soscom SMS provider balance is low')
            ->greeting('Hi,')
            ->line(
                'Upstream SMS balance is '.$this->smsBalance.' SMS, below the '.$this->provider->low_balance_threshold.' threshold.'
            )
            ->line('Top up the provider account before customer traffic is affected. Customers never see this alert.')
            ->action('Open provider balance', url('/admin/provider-balance'));
    }
}
