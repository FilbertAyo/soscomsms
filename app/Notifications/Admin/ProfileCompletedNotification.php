<?php

namespace App\Notifications\Admin;

use App\Mail\SoscomMail;
use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProfileCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Customer $customer,
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
        $this->customer->loadMissing('user');
        $user = $this->customer->user;

        return SoscomMail::message()
            ->subject('Profile completed: '.$this->customer->business_name)
            ->greeting('Hi,')
            ->line(($user?->name ?? 'A customer').' ('.($user?->email ?? 'no email').') completed their business profile.')
            ->line('Business: '.$this->customer->business_name.' · Phone: '.$this->customer->phone)
            ->line('They can now send SMS.')
            ->action('Open customers', url('/admin/customers'));
    }
}
