<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class VerifyEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $token) {}

    public function build()
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000') . 
                    "/verify-email?" .
                    "token=" . $this->token;
        
        return $this->subject('Подтверждение email адреса')
            ->view('mail.verify-email')
            ->with([
                'user' => $this->user,
                'verificationUrl' => $frontendUrl
            ]);
    }
}