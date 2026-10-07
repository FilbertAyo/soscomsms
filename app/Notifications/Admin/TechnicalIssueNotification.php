<?php

namespace App\Notifications\Admin;

use App\Mail\SoscomMail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TechnicalIssueNotification extends Notification
{
    public function __construct(
        public string $title,
        public string $details,
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
            ->subject('[Soscom SMS] '.$this->title)
            ->greeting('Hi,')
            ->line($this->title.'.')
            ->line($this->details)
            ->line('Similar alerts are paused for an hour so your inbox does not fill up.')
            ->action('Open production checks', url('/admin/production'));
    }
}
