<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminNotificationMail extends Mailable implements ShouldQueue
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
        public string $subject,
        public string $message,
        public ?string $actionUrl = null,
        public ?string $actionText = null
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.admin.notification',
            with: [
                'subject' => $this->subject,
                'message' => $this->message,
                'actionUrl' => $this->actionUrl,
                'actionText' => $this->actionText,
                'user' => $this->user,
                'greeting' => 'Hello ' . $this->user->name . ',',
                'recipientEmail' => $this->user->email,
                'logoUrl' => url('flipzy_logo.jpg'),
                'headerColor' => '#2563eb',
                'buttonColor' => $this->actionUrl ? '#2563eb' : null,
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
        return 'emails-admin';
    }
}

