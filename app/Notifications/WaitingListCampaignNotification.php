<?php

namespace App\Notifications;

use App\Mail\WaitingListCampaignMail;
use App\Models\EmailCampaign;
use Illuminate\Bus\Queueable;
// Temporarily disabled queues - uncomment when queue system is configured
// use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class WaitingListCampaignNotification extends Notification // implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = 60;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public EmailCampaign $campaign
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Store in database for in-app notifications and history, and send email
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): WaitingListCampaignMail
    {
        return new WaitingListCampaignMail(
            $notifiable,
            $this->campaign
        );
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'campaign_id' => $this->campaign->id,
            'campaign_name' => $this->campaign->name,
            'subject' => $this->campaign->subject,
            'type' => 'waiting_list_campaign',
            'sent_at' => now()->format('m-d-Y H:i:s'),
        ];
    }
}
