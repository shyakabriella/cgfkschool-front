<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $token
    ) {
    }

    /**
     * Determine how the notification will be delivered.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the password-reset email.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = rtrim(
            env('FRONTEND_URL', 'http://localhost:3000'),
            '/'
        );

        $resetUrl = $frontendUrl . '/reset-password?' . http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Reset Your CGFK Account Password')
            ->view('emails.reset-password', [
                'user' => $notifiable,
                'resetUrl' => $resetUrl,
                'frontendUrl' => $frontendUrl,
                'expirationMinutes' => config(
                    'auth.passwords.users.expire',
                    60
                ),
            ]);
    }

    /**
     * Convert the notification to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
