<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
// Temporarily disabled queues - uncomment when queue system is configured
// use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable // implements ShouldQueue
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
        public string $resetToken,
        public string $resetUrl
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reset Your Password - ' . config('app.name'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Use frontend URL for the reset link
        $frontendUrl = config('app.frontend_url');
        $resetUrl = rtrim($frontendUrl, '/') . '/reset-password?token=' . $this->resetToken . '&email=' . urlencode($this->user->email);

        return new Content(
            view: 'emails.auth.password-reset',
            with: [
                'user' => $this->user,
                'resetToken' => $this->resetToken,
                'resetUrl' => $resetUrl,
                'greeting' => 'Hello ' . $this->user->name . ',',
                'recipientEmail' => $this->user->email,
                'actionUrl' => $resetUrl,
                'actionText' => 'Reset Password',
                'buttonColor' => '#2563eb',
                'logoUrl' => url('flipzy_logo.jpg'),
                'headerColor' => '#2563eb',
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
