<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\AdminNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AdminNotificationService
{
    /**
     * Send notification to all users
     *
     * @param string $subject
     * @param string $message
     * @param string|null $actionUrl
     * @param string|null $actionText
     * @return array
     */
    public function sendToAll(
        string $subject,
        string $message,
        ?string $actionUrl = null,
        ?string $actionText = null
    ): array {
        $users = User::where('email_verified_at', '!=', null)->get();
        $total = $users->count();
        $sent = 0;
        $failed = 0;

        foreach ($users as $user) {
            try {
                $user->notify(new AdminNotification($subject, $message, $actionUrl, $actionText));
                $sent++;
            } catch (\Exception $e) {
                $failed++;
                Log::error('Failed to send admin notification to user', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'success' => true,
            'total_users' => $total,
            'sent' => $sent,
            'failed' => $failed,
        ];
    }

    /**
     * Send notification to selected users
     *
     * @param array $userIds Array of user UUIDs
     * @param string $subject
     * @param string $message
     * @param string|null $actionUrl
     * @param string|null $actionText
     * @return array
     */
    public function sendToSelected(
        array $userIds,
        string $subject,
        string $message,
        ?string $actionUrl = null,
        ?string $actionText = null
    ): array {
        $users = User::whereIn('id', $userIds)->get();
        $total = $users->count();
        $sent = 0;
        $failed = 0;
        $notFound = count($userIds) - $total;

        foreach ($users as $user) {
            try {
                $user->notify(new AdminNotification($subject, $message, $actionUrl, $actionText));
                $sent++;
            } catch (\Exception $e) {
                $failed++;
                Log::error('Failed to send admin notification to user', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'success' => true,
            'total_requested' => count($userIds),
            'total_found' => $total,
            'not_found' => $notFound,
            'sent' => $sent,
            'failed' => $failed,
        ];
    }

    /**
     * Send notification to a single user
     *
     * @param string $userId User UUID
     * @param string $subject
     * @param string $message
     * @param string|null $actionUrl
     * @param string|null $actionText
     * @return array
     */
    public function sendToSingle(
        string $userId,
        string $subject,
        string $message,
        ?string $actionUrl = null,
        ?string $actionText = null
    ): array {
        $user = User::find($userId);

        if (!$user) {
            return [
                'success' => false,
                'error' => 'User not found',
            ];
        }

        try {
            $user->notify(new AdminNotification($subject, $message, $actionUrl, $actionText));

            return [
                'success' => true,
                'user_id' => $user->id,
                'user_email' => $user->email,
                'sent' => true,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to send admin notification to user', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to send notification: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Send notification to users filtered by criteria
     *
     * @param array $filters Array of filter criteria (role, search, etc.)
     * @param string $subject
     * @param string $message
     * @param string|null $actionUrl
     * @param string|null $actionText
     * @return array
     */
    public function sendToFiltered(
        array $filters,
        string $subject,
        string $message,
        ?string $actionUrl = null,
        ?string $actionText = null
    ): array {
        $query = User::query();

        // Filter by role
        if (isset($filters['role']) && !empty($filters['role'])) {
            $role = $filters['role'];
            if (in_array($role, ['investor', 'wholesaler', 'admin'])) {
                $query->whereHas('roles', function ($q) use ($role) {
                    $q->where('name', $role);
                });
            }
        }

        // Filter by search (name or email)
        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Only send to verified users
        $query->whereNotNull('email_verified_at');

        $users = $query->get();
        $total = $users->count();
        $sent = 0;
        $failed = 0;

        foreach ($users as $user) {
            try {
                $user->notify(new AdminNotification($subject, $message, $actionUrl, $actionText));
                $sent++;
            } catch (\Exception $e) {
                $failed++;
                Log::error('Failed to send admin notification to user', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'success' => true,
            'total_users' => $total,
            'sent' => $sent,
            'failed' => $failed,
            'filters_applied' => $filters,
        ];
    }
}

