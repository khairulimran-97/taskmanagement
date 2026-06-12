<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginOtpNotification extends Notification
{

    public function __construct(
        public string $code,
        public int $expiresInMinutes,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = config('app.name');

        return (new MailMessage)
            ->subject($this->code.' is your '.$appName.' sign-in code')
            ->view('emails.login-otp', [
                'appName' => $appName,
                'name' => $notifiable->name ?? 'there',
                'code' => $this->code,
                'expiresInMinutes' => $this->expiresInMinutes,
                'year' => now()->year,
            ]);
    }
}
