<?php

namespace App\Mail;

use App\Models\WaitingListEntry;
use Illuminate\Bus\Queueable;
// Temporarily disabled queues - uncomment when queue system is configured
// use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WaitingListWelcomeMail extends Mailable // implements ShouldQueue
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
        public WaitingListEntry $entry
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to ' . config('app.name') . ' - You\'re on the waiting list!',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Use frontend URL for the verification and status links
        $frontendUrl = config('app.frontend_url');
        $verificationLink = rtrim($frontendUrl, '/') . '/waiting-list/verify-email?email=' . urlencode($this->entry->email) . '&token=' . $this->entry->verification_token;
        $statusLink = rtrim($frontendUrl, '/') . '/waiting-list/status?email=' . urlencode($this->entry->email) . '&token=' . $this->entry->verification_token;

        return new Content(
            view: 'emails.waiting-list.welcome',
            with: [
                'entry' => $this->entry,
                'verificationLink' => $verificationLink,
                'statusLink' => $statusLink,
                'greeting' => 'Hi ' . $this->entry->name . ',',
                'recipientEmail' => $this->entry->email,
                'actionUrl' => $verificationLink,
                'actionText' => 'Verify Email',
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
        return 'emails-waiting-list';
    }
}
