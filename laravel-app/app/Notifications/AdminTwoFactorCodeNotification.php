<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminTwoFactorCodeNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $code)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Your MAAT TECHNOLOGIE BD admin verification code')
            ->greeting('Admin verification required')
            ->line('Use the following code to complete your admin sign-in:')
            ->line($this->code)
            ->line('This code expires in 10 minutes.')
            ->line('If this sign-in was not requested by you, reset your password and review account access immediately.');
    }
}
