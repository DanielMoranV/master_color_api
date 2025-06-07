<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class ClientResetPassword extends Mailable
{
    public $token;
    public $email;
    public $name;

    public function __construct($token, $email, $name)
    {
        $this->token = $token;
        $this->email = $email;
        $this->name = $name;
    }

    public function build()
    {
        $url = config('app.frontend_url') . '/reset-password?token=' . $this->token . '&email=' . $this->email;

        return $this->subject('Recuperación de contraseña')
                    ->markdown('emails.clients.reset-password')
                    ->with([
                        'name' => $this->name,
                        'url' => $url,
                    ]);
    }
}
