<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class ClientEmailVerification extends Mailable
{
    public $client;

    public function __construct($client)
    {
        $this->client = $client;
    }

    public function build()
    {
        $url = config('app.frontend_url') . '/verify-email?token=' . $this->client->verification_token;

        return $this->subject('Verifica tu correo electrónico')
                    ->markdown('emails.clients.verify')
                    ->with([
                        'name' => $this->client->name,
                        'url' => $url,
                    ]);
    }
}
