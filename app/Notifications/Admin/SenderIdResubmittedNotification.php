<?php

namespace App\Notifications\Admin;

use App\Mail\SoscomMail;
use App\Models\SenderIdRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SenderIdResubmittedNotification extends Notification implements ShouldQueue
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
            ->subject('Updated document for Sender ID '.$this->request->name)
            ->greeting('Hi,')
            ->line($requester.' uploaded a new identity document for Sender ID '.$this->request->name.'.')
            ->action('Review Sender ID requests', url('/admin/sender-ids'));
    }
}
