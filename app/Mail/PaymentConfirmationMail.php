<?php

namespace App\Mail;

use App\Models\WaitingListEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentConfirmationMail extends Mailable implements ShouldQueue
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
            subject: 'Payment Confirmed - Welcome to ' . config('app.name') . '!',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.waiting-list.payment-confirmation',
            with: [
                'entry' => $this->entry,
                'greeting' => 'Hi ' . $this->entry->name . ',',
                'recipientEmail' => $this->entry->email,
                'buttonColor' => '#059669',
                'logoUrl' => url('flipzy_logo.jpg'),
                'headerColor' => '#059669',
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
