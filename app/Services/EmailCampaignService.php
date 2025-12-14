<?php

namespace App\Services;

use App\Models\EmailCampaign;
use App\Models\WaitingListEntry;
use App\Notifications\WaitingListCampaignNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmailCampaignService
{
    /**
     * Create a new email campaign
     */
    public function create(array $data): EmailCampaign
    {
        return EmailCampaign::create([
            'name' => $data['name'],
            'subject' => $data['subject'],
            'content' => $data['content'],
            'status' => 'draft',
            'scheduled_at' => $data['scheduled_at'] ?? null,
        ]);
    }

    /**
     * Update an email campaign
     */
    public function update(EmailCampaign $campaign, array $data): EmailCampaign
    {
        $campaign->update([
            'name' => $data['name'] ?? $campaign->name,
            'subject' => $data['subject'] ?? $campaign->subject,
            'content' => $data['content'] ?? $campaign->content,
            'scheduled_at' => $data['scheduled_at'] ?? $campaign->scheduled_at,
        ]);

        return $campaign->fresh();
    }

    /**
     * Send campaign to waiting list entries
     */
    public function send(EmailCampaign $campaign, ?array $filters = null): array
    {
        if ($campaign->status === 'sent') {
            return [
                'success' => false,
                'error' => 'Campaign has already been sent',
            ];
        }

        // Get recipients based on filters
        $query = WaitingListEntry::query();
        
        if ($filters) {
            // Filter by status
            if (isset($filters['status']) && $filters['status'] !== 'all') {
                $query->where('status', $filters['status']);
            }

            // Filter by user type
            if (isset($filters['user_type']) && $filters['user_type'] !== 'all') {
                if ($filters['user_type'] === 'investor') {
                    $query->whereJsonContains('selected_roles', 'investor')
                          ->where(function ($q) {
                              $q->whereJsonDoesntContain('selected_roles', 'wholesaler')
                                ->orWhereNull('selected_roles');
                          });
                } elseif ($filters['user_type'] === 'wholesaler') {
                    $query->whereJsonContains('selected_roles', 'wholesaler')
                          ->where(function ($q) {
                              $q->whereJsonDoesntContain('selected_roles', 'investor')
                                ->orWhereNull('selected_roles');
                          });
                } elseif ($filters['user_type'] === 'both') {
                    $query->whereJsonContains('selected_roles', 'wholesaler')
                          ->whereJsonContains('selected_roles', 'investor');
                }
            }
        }

        $recipients = $query->get();
        $totalRecipients = $recipients->count();
        $sent = 0;
        $failed = 0;

        // Send notifications
        foreach ($recipients as $entry) {
            try {
                $entry->notify(new WaitingListCampaignNotification($campaign));
                $sent++;
            } catch (\Exception $e) {
                $failed++;
                Log::error('Failed to send campaign notification', [
                    'campaign_id' => $campaign->id,
                    'entry_id' => $entry->id,
                    'email' => $entry->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Update campaign
        $campaign->update([
            'status' => 'sent',
            'recipients_count' => $totalRecipients,
            'sent_count' => $sent,
            'sent_at' => now(),
        ]);

        return [
            'success' => true,
            'total_recipients' => $totalRecipients,
            'sent' => $sent,
            'failed' => $failed,
        ];
    }

    /**
     * Get campaign statistics
     */
    public function getStats(): array
    {
        $totalCampaigns = EmailCampaign::count();
        $totalSent = EmailCampaign::where('status', 'sent')->sum('sent_count');
        $totalRecipients = WaitingListEntry::count();
        
        // Count email templates (we can extend this later)
        $emailTemplates = 2; // welcome, update - can be made dynamic later
        
        // Calculate average open rate from notifications
        $notifications = DB::table('notifications')
            ->where('type', 'App\Notifications\WaitingListCampaignNotification')
            ->get();
        
        $totalNotifications = $notifications->count();
        $openedNotifications = $notifications->whereNotNull('read_at')->count();
        $averageOpenRate = $totalNotifications > 0 
            ? round(($openedNotifications / $totalNotifications) * 100, 2)
            : 0;

        return [
            'total_emails_sent' => $totalSent,
            'total_recipients' => $totalRecipients,
            'email_templates' => $emailTemplates,
            'average_open_rate' => $averageOpenRate,
        ];
    }
}
