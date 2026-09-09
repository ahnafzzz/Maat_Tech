<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminTwoFactorCodeNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function code(): string
    {
        return $this->code;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your MAAT TECHNOLOGIE BD admin verification code')
            ->greeting('Admin verification required')
            ->line('Use the following code to complete your admin sign-in:')
            ->line($this->code)
            ->line('This code expires in '.config('admin.two_factor_expiration_minutes', 10).' minutes and can be used only by the browser that requested it.')
            ->line('If this sign-in was not requested by you, reset your password and review account access immediately.');
    }
}
