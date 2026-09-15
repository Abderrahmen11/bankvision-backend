<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Collection;

class NotificationService
{
    /**
     * Latest notifications for a user plus their unread count.
     */
    public function listNotifications(User $user, int $limit = 15): array
    {
        $notifications = Notification::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (Notification $n) => $this->serialize($n));

        return [
            'notifications' => $notifications,
            'unread_count'  => $this->unreadCount($user),
        ];
    }

    /**
     * Mark a single unread notification as read; returns rows affected.
     */
    public function markRead(User $user, int $id): int
    {
        return Notification::where('user_id', $user->id)
            ->where('id', $id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Mark every unread notification as read; returns rows affected.
     */
    public function markAllRead(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Count unread notifications for a user.
     */
    public function unreadCount(User $user): int
    {
        return Notification::where('user_id', $user->id)->whereNull('read_at')->count();
    }

    /**
     * Consistent field-specific payload for a notification.
     */
    private function serialize(Notification $notification): array
    {
        return [
            'id'         => $notification->id,
            'type'       => $notification->type,
            'title'      => $notification->title,
            'message'    => $notification->message,
            'link'       => $notification->link,
            'read_at'    => $notification->read_at?->format('Y-m-d H:i:s'),
            'created_at' => $notification->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
