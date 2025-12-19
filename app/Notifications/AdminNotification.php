<?php

namespace App\Notifications;

use App\Mail\AdminNotificationMail;
use Illuminate\Bus\Queueable;
// Temporarily disabled queues - uncomment when queue system is configured
// use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AdminNotification extends Notification // implements ShouldQueue
{
    use Queueable;

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
     * Create a new notification instance.
     */
    public function __construct(
        public string $subject,
        public string $message,
        public ?string $actionUrl = null,
        public ?string $actionText = null
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
    public function toMail(object $notifiable): AdminNotificationMail
    {
        return new AdminNotificationMail(
            $notifiable,
            $this->subject,
            $this->message,
            $this->actionUrl,
            $this->actionText
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
            'subject' => $this->subject,
            'message' => $this->message,
            'action_url' => $this->actionUrl,
            'action_text' => $this->actionText,
            'type' => 'admin_notification',
            'sent_at' => now()->format('m-d-Y H:i:s'),
        ];
    }

    /**
     * Get the database representation of the notification.
     * This is used when storing notifications in the database.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }

    /**
     * Get the queue connection to use for this notification.
     *
     * @return string
     */
    public function viaConnection(): string
    {
        return config('queue.default');
    }

    /**
     * Get the queue name to use for this notification.
     *
     * @return string
     */
    public function viaQueue(): string
    {
        return 'emails-admin';
    }
}

