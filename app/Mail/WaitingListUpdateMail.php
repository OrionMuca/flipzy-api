<?php

namespace App\Mail;

use App\Models\WaitingListEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WaitingListUpdateMail extends Mailable implements ShouldQueue
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
        public WaitingListEntry $entry,
        public string $updateContent
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Update on ' . config('app.name') . ' Launch Progress',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.waiting-list.update',
            with: [
                'entry' => $this->entry,
                'updateContent' => $this->updateContent,
                'greeting' => 'Hi ' . $this->entry->name . ',',
                'recipientEmail' => $this->entry->email,
                'buttonColor' => '#2563eb',
                'logoUrl' => url('flipzy_logo.jpg'),
                'headerColor' => '#2563eb',
            ],
        );
    }

    /**
     * Get the queue connection to use for this mailable.
     */
    public function viaConnection(): string
    {
        return config('queue.default');
    }

    /**
     * Get the queue name to use for this mailable.
     */
    public function viaQueue(): string
    {
        return 'emails-waiting-list';
    }
}
