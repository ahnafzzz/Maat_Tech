<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminSecurityChangedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $change) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('MAAT administrator security setting changed')
            ->greeting('Administrator security notice')
            ->line($this->change)
            ->line('Other administrator sessions were revoked as a precaution.')
            ->line('If you did not make this change, use the owner-controlled password recovery command and investigate access logs immediately.');
    }
}
