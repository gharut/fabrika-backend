<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public $resetUrl;
    public $email;

    public function __construct($resetUrl, $email)
    {
        $this->resetUrl = $resetUrl;
        $this->email = $email;
    }

    public function build()
    {
        return $this->subject('Восстановление пароля')
            ->view('mail.password-reset')
            ->with([
                'resetUrl' => $this->resetUrl,
                'email' => $this->email
            ]);
    }
}