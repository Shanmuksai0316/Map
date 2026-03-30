<?php

namespace App\Filament\CampusManager\Widgets;

use App\Models\Hostel;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

/**
 * Hostel Switcher Widget
 *
 * Renders a hostel selector dropdown in the Campus Manager panel.
 * Stores the selected hostel in the session so all resources
 * can filter by it without each having their own hostel filter.
 *
 * - Campus Manager sees "All Hostels" + list of tenant hostels.
 * - Other roles see only their assigned hostel(s).
 */
class HostelSwitcher extends Widget
{
    protected static string $view = 'filament.campus-manager.widgets.hostel-switcher';

    protected int | string | array $columnSpan = 'full';

    /**
     * Get available hostels for the current user.
     */
    public function getHostels(): array
    {
        $user = Auth::user();
        if (! $user) {
            return [];
        }

        $tenantId = $user->tenant_id;
        if (! $tenantId) {
            return [];
        }

        return Hostel::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Get currently selected hostel ID from session.
     */
    public function getSelectedHostelId(): ?int
    {
        return session('active_hostel_id');
    }

    /**
     * Get the name of the currently selected hostel, or "All Hostels".
     */
    public function getSelectedHostelName(): string
    {
        $hostelId = $this->getSelectedHostelId();
        if (! $hostelId) {
            return 'All Hostels';
        }

        return Hostel::find($hostelId)?->name ?? 'All Hostels';
    }

    /**
     * Switch to a different hostel.
     */
    public function switchHostel(?int $hostelId): void
    {
        if ($hostelId) {
            // Verify the hostel belongs to the user's tenant
            $user = Auth::user();
            $valid = Hostel::where('id', $hostelId)
                ->where('tenant_id', $user->tenant_id)
                ->exists();

            if ($valid) {
                session(['active_hostel_id' => $hostelId]);
            }
        } else {
            session()->forget('active_hostel_id');
        }

        // Redirect to refresh all data with new hostel context
        $this->redirect(request()->header('Referer', '/campus-manager'));
    }
}
