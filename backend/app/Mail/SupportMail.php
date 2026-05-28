<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SupportMail extends Mailable
{
    use Queueable, SerializesModels;

    public $subject;

    public function __construct(
        public readonly string $body,
        public readonly ?string $senderEmail,
    ) {
        $this->subject = $senderEmail
            ? "Support request from $senderEmail"
            : 'Anonymous Support Request';
    }

    public function build(): self
    {
        return $this->view('mail.support')->subject($this->subject);
    }
}
