<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        $name = $notifiable->first_name ?: $notifiable->name ?: 'Student';

        return (new MailMessage)
            ->subject('Reset Your Academic Hub Password')
            ->action('Reset Password', $url)
            ->view('emails.reset-password', [
                'name' => $name,
                'email' => $notifiable->getEmailForPasswordReset(),
                'url' => $url,
            ]);
    }
}

