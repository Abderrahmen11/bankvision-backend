<?php

namespace App\Services;

use App\Models\Alert;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AlertService
{
    /**
     * Get paginated alerts with filters, assigned staff, and polymorphic subjects.
     */
    public function getPaginatedAlerts(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Alert::query()
            ->with(['assignedTo', 'alertable'])
            ->filter($filters)
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Find single alert with assignee and triggering entity.
     */
    public function getAlertDetails(string|int $id): Alert
    {
        return Alert::with(['assignedTo', 'alertable'])->findOrFail($id);
    }

    /**
     * Resolve an open or in-progress alert.
     */
    public function resolveAlert(Alert|string|int $alert): Alert
    {
        $alert = $alert instanceof Alert
            ? $alert
            : Alert::whereIn('status', ['open', 'in-progress'])->findOrFail($alert);

        $alert->update([
            'status'      => 'resolved',
            'resolved_at' => now(),
        ]);

        return $alert->fresh(['assignedTo', 'alertable']);
    }

    /**
     * Assign an alert to a staff member and mark it as in-progress.
     */
    public function assignAlert(Alert|string|int $alert, int $userId): Alert
    {
        $alert = $alert instanceof Alert ? $alert : Alert::findOrFail($alert);

        $alert->update([
            'assigned_to' => $userId,
            'status'      => $alert->status === 'open' ? 'in-progress' : $alert->status,
        ]);

        return $alert->fresh(['assignedTo', 'alertable']);
    }
}
