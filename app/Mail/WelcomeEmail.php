<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WelcomeEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    /**
     * Tạo constructor để truyền dữ liệu.
     */
    public function __construct($user)
    {
        $this->user = $user;
    }

    /**
     * Xây dựng email.
     */
    public function build()
    {
        return $this->subject('Welcome to Alice Skin')
        ->view('emails.welcome'); // Tên view blade để render email
    }
}
