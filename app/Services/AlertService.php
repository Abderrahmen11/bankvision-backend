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
     * Resolve an open alert.
     */
    public function resolveAlert(Alert $alert): Alert
    {
        $alert->update([
            'status'      => 'resolved',
            'resolved_at' => now(),
        ]);

        return $alert->fresh(['assignedTo', 'alertable']);
    }

    /**
     * Assign an alert to a staff member.
     */
    public function assignAlert(Alert $alert, int $userId): Alert
    {
        $alert->update(['assigned_to' => $userId]);

        return $alert->fresh(['assignedTo', 'alertable']);
    }
}
