<?php

namespace App\Notifications;

use App\Models\AdminInvitationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly AdminInvitationRequest $invitation,
        public readonly string $selector,
        public readonly string $token,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Accept your MAAT administrator invitation')
            ->greeting('Hello '.$this->invitation->name.',')
            ->line('A lead administrator approved an operator account for '.$this->invitation->email.'.')
            ->line('Administrator ID: '.$this->invitation->proposed_admin_id)
            ->action('Set administrator password', route('admin.invitations.accept.show', ['selector' => $this->selector]).'#token='.$this->token)
            ->line('This single-use link expires at '.$this->invitation->token_expires_at?->toIso8601String().'.')
            ->line('If you did not expect this invitation, do not use the link.');
    }
}
