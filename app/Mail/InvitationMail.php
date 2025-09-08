<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invitation $invitation) {}

    public function build()
    {
        $acceptUrl = env('FRONTEND_URL', 'http://localhost:3000')."/invite/accept?token={$this->invitation->token}";
        return $this->subject('Приглашение в систему')
            ->view('mail.invitation')
            ->with([
                'clientName' => $this->invitation->client->name ?? 'Наш сервис',
                'acceptUrl'  => $acceptUrl,
            ]);
    }
}
