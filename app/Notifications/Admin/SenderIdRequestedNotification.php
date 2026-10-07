<?php

namespace App\Notifications\Admin;

use App\Mail\SoscomMail;
use App\Models\SenderIdRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SenderIdRequestedNotification extends Notification implements ShouldQueue
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
        $this->request->loadMissing('customer.user');
        $customer = $this->request->customer;
        $requester = $customer?->business_name ?: ($customer?->user?->name ?? 'A customer');

        return SoscomMail::message()
            ->subject('New Sender ID request: '.$this->request->name)
            ->greeting('Hi,')
            ->line($requester.' requested the Sender ID '.$this->request->name.'.')
            ->line('Organization: '.$this->request->organization)
            ->line('Purpose: '.$this->request->purpose)
            ->action('Review Sender ID requests', url('/admin/sender-ids'));
    }
}
