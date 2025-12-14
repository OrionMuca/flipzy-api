<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class EmailCampaign extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'subject',
        'content',
        'status',
        'recipients_count',
        'sent_count',
        'scheduled_at',
        'sent_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    /**
     * Get notifications for this campaign
     * Notifications are stored in the notifications table with campaign_id in data
     */
    public function notifications()
    {
        return DB::table('notifications')
            ->where('notifiable_type', 'App\Models\WaitingListEntry')
            ->where('type', 'App\Notifications\WaitingListCampaignNotification')
            ->whereJsonContains('data->campaign_id', $this->id)
            ->get();
    }

    /**
     * Calculate statistics from notifications
     */
    public function getStats(): array
    {
        $notifications = $this->notifications();
        
        return [
            'total_recipients' => $this->recipients_count,
            'sent' => $this->sent_count,
            'opened' => $notifications->whereNotNull('read_at')->count(),
            'open_rate' => $this->sent_count > 0 
                ? round(($notifications->whereNotNull('read_at')->count() / $this->sent_count) * 100, 2)
                : 0,
        ];
    }
}
