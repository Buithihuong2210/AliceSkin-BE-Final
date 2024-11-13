<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WelcomeEmail extends Mailable
{
    use SerializesModels;

    public $user;

    /**
     * Tạo một instance mới của email.
     *
     * @param \App\Models\User $user
     * @return void
     */
    public function __construct($user)
    {
        $this->user = $user;
    }

    /**
     * Xây dựng email.
     *
     * @return \Illuminate\Contracts\Mail\Renderable
     */
    public function build()
    {
        // Gửi email dưới dạng văn bản thuần túy (plain text)
        return $this->subject('Welcome to Alice Skin')
            ->text('emails.welcome_plain') // Tạo file text thay vì view HTML
            ->with('user', $this->user);
    }
}