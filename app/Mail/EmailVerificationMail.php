<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailVerificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 60;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public User $user,
        public string $verificationUrl
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify Your Email Address - ' . config('app.name'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // The verificationUrl is already constructed with frontend URL in AuthController
        // But we'll use it as-is since it's already correct
        $verificationUrl = $this->verificationUrl;

        return new Content(
            view: 'emails.auth.email-verification',
            with: [
                'user' => $this->user,
                'verificationUrl' => $verificationUrl,
                'greeting' => 'Hello ' . $this->user->name . ',',
                'recipientEmail' => $this->user->email,
                'actionUrl' => $verificationUrl,
                'actionText' => 'Verify Email Address',
                'buttonColor' => '#10b981',
            ],
        );
    }

    /**
     * Get the queue connection to use for this mailable.
     *
     * @return string
     */
    public function viaConnection(): string
    {
        return config('queue.default');
    }

    /**
     * Get the queue name to use for this mailable.
     *
     * @return string
     */
    public function viaQueue(): string
    {
        return 'emails-auth';
    }
}
