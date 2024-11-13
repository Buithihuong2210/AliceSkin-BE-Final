<?php
namespace App\Mail;

use Illuminate\Mail\Mailable;

class ResetPasswordEmail extends Mailable
{
    public $resetLink;

    /**
     * Tạo một instance mới của email.
     *
     * @param string $resetLink
     */
    public function __construct($resetLink)
    {
        $this->resetLink = $resetLink;
    }

    /**
     * Xây dựng email.
     *
     * @return \Illuminate\Contracts\Mail\Renderable
     */
    public function build()
    {
        return $this->subject('Reset Password Notification')
            ->view('emails.passwordReset') // View mà bạn sẽ tạo dưới đây
            ->with([
                'resetLink' => $this->resetLink, // Truyền reset link vào view
            ]);
    }
}
