<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerificationOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $email;
    public string $code;
    public string $type;
    public ?string $token;

    /**
     * Create a new message instance.
     */
    public function __construct(string $email, string $code, string $type, ?string $token = null)
    {
        $this->email = $email;
        $this->code = $code;
        $this->type = $type;
        $this->token = $token;
    }

    public function envelope(): Envelope
    {
        $subject = $this->type === 'register' 
            ? 'رمز تأكيد حسابك الجديد' 
            : 'رمز تغيير كلمة المرور';

        $fromAddress = config('mail.from.address');

        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address($fromAddress, $fromAddress),
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.verification_otp',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
