<?php

namespace App\Mail;

use App\Models\EmailCampaign;
use App\Models\WaitingListEntry;
use Illuminate\Bus\Queueable;
// Temporarily disabled queues - uncomment when queue system is configured
// use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WaitingListCampaignMail extends Mailable // implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = 60;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public WaitingListEntry $entry,
        public EmailCampaign $campaign
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->campaign->subject,
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
                'subject' => $this->campaign->subject,
                'emailContent' => $this->campaign->content,
                'actionUrl' => null,
                'actionText' => null,
                'user' => $this->entry,
                'greeting' => 'Hello ' . $this->entry->name . ',',
                'recipientEmail' => $this->entry->email,
                'logoUrl' => url('flipzy_logo.jpg'),
                'headerColor' => '#2563eb',
                'buttonColor' => null,
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
