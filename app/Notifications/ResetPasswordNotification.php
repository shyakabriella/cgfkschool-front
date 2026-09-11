<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $frontendUrl = rtrim(
            config('app.frontend_url'),
            '/'
        );

        $resetUrl = $frontendUrl
            . '/reset-password?token='
            . urlencode($this->token)
            . '&email='
            . urlencode($notifiable->getEmailForPasswordReset());

        return (new MailMessage)
            ->subject('Reset Your CGFK School Password')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line(
                'You are receiving this email because a password reset was requested for your CGFK School account.'
            )
            ->action('Reset Password', $resetUrl)
            ->line(
                'This password reset link will expire after the configured expiration period.'
            )
            ->line(
                'If you did not request a password reset, no action is required.'
            )
            ->salutation('CGFK School Management');
    }
}
