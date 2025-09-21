<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invitation $invitation, public bool $isNewUser) {}

    public function build()
    {
        $clientName = $this->invitation->client->name;
        $acceptUrl = env('FRONTEND_URL', 'http://localhost:3000') . 
                   "/invite?token={$this->invitation->token}" .
                   "&org_name=" . urlencode($clientName ?? 'Наш сервис') .
                   "&is_new=" . ($this->isNewUser ? 'true' : 'false');
        
        return $this->subject('Приглашение в систему')
            ->view('mail.invitation')
            ->with([
                'clientName' => $clientName ?? 'Наш сервис',
                'acceptUrl'  => $acceptUrl,
                'isNewUser'  => $this->isNewUser
            ]);
    }
}