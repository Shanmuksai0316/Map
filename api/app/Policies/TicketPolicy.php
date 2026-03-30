<?php

namespace App\Policies;

use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TicketPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        // All authenticated users can view tickets (with scoping)
        return true;
    }

    public function view(User $user, Ticket $ticket): bool
    {
        // In DB-per-tenant mode, isolation is by connection. Only enforce equality
        // if the underlying table actually has a tenant_id column (e.g., central tables).
        $attrs = $ticket->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $ticket->tenant_id) {
                return false;
            }
        }

        // Students can view their own tickets
        if ($user->hasRole('Student')) {
            return $this->isReporter($user, $ticket);
        }

        // Staff can view tickets in their hostels
        if ($user->hasAnyRole([
            'Warden',
            'HK Supervisor',
            'RM Supervisor',
            'HKSupervisor',
            'RMSupervisor',
            'Guard',
            'Laundry Manager',
            'LaundryManager',
            'Sports Manager',
            'SportsManager',
        ])) {
            return $this->canAccessHostel($user, $ticket);
        }

        // Campus Manager, Rector, Super Admin can view all tickets in tenant
        if ($user->hasRole(['CampusManager', 'Rector', 'Super Admin'])) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        // Students and Staff can create tickets
        return $user->hasAnyRole([
            'Student',
            'Warden',
            'HK Supervisor',
            'RM Supervisor',
            'HKSupervisor',
            'RMSupervisor',
            'Guard',
            'Laundry Manager',
            'LaundryManager',
            'Sports Manager',
            'SportsManager',
            'Campus Manager',
            'CampusManager',
        ]);
    }

    public function update(User $user, Ticket $ticket): bool
    {
        // Delegate tenant scoping to view()
        if (! $this->view($user, $ticket)) {
            return false;
        }

        // Students can only update their own tickets (limited fields)
        if ($user->hasRole('Student')) {
            return $this->isReporter($user, $ticket) && $ticket->status === 'open';
        }

        // Supervisors can update tickets in their hostels
        if ($user->hasAnyRole(['HK Supervisor', 'RM Supervisor', 'HKSupervisor', 'RMSupervisor'])) {
            return $this->canAccessHostel($user, $ticket);
        }

        // Campus Manager can update all tickets
        if ($user->hasAnyRole(['Campus Manager', 'CampusManager'])) {
            return true;
        }

        return false;
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        // Only Campus Manager can delete tickets; tenant constraint handled by view()
        return $user->hasRole('CampusManager') && $this->view($user, $ticket);
    }

    public function comment(User $user, Ticket $ticket): bool
    {
        // Must be able to view the ticket
        if (! $this->view($user, $ticket)) {
            return false;
        }

        // Students can comment on their own tickets
        if ($user->hasRole('Student')) {
            return $this->isReporter($user, $ticket);
        }

        // Staff can comment on tickets they can view
        return true;
    }

    public function assign(User $user, Ticket $ticket): bool
    {
        if (! $this->view($user, $ticket)) {
            return false;
        }

        // Supervisors can assign tickets in their hostels
        if ($user->hasAnyRole(['HK Supervisor', 'RM Supervisor', 'HKSupervisor', 'RMSupervisor'])) {
            return $this->canAccessHostel($user, $ticket);
        }

        // Campus Manager can assign any ticket
        if ($user->hasAnyRole(['Campus Manager', 'CampusManager'])) {
            return true;
        }

        return false;
    }

    public function transition(User $user, Ticket $ticket): bool
    {
        if (! $this->view($user, $ticket)) {
            return false;
        }

        // Supervisors can transition tickets in their hostels
        if ($user->hasAnyRole(['HK Supervisor', 'RM Supervisor', 'HKSupervisor', 'RMSupervisor'])) {
            return $this->canAccessHostel($user, $ticket);
        }

        // Campus Manager can transition any ticket
        if ($user->hasAnyRole(['Campus Manager', 'CampusManager'])) {
            return true;
        }

        return false;
    }

    public function close(User $user, Ticket $ticket): bool
    {
        if (! $this->view($user, $ticket)) {
            return false;
        }

        // Only Campus Manager can close tickets
        return $user->hasAnyRole(['Campus Manager', 'CampusManager']);
    }

    public function reopen(User $user, Ticket $ticket): bool
    {
        // Enforce tenant equality only if model actually has tenant_id
        $attrs = $ticket->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $ticket->tenant_id) {
                return false;
            }
        }

        // Campus Manager can reopen any ticket
        if ($user->hasAnyRole(['Campus Manager', 'CampusManager'])) {
            return true;
        }

        // Supervisors can reopen tickets in their hostels
        if ($user->hasAnyRole(['HK Supervisor', 'RM Supervisor', 'HKSupervisor', 'RMSupervisor'])) {
            return $this->canAccessHostel($user, $ticket);
        }

        return false;
    }

    private function isReporter(User $user, Ticket $ticket): bool
    {
        // Check if user is the reporter (either as student or staff)
        if ($ticket->created_by_user_id === $user->id) {
            return true;
        }

        if ($ticket->reporter_user_id === $user->id) {
            return true;
        }

        if ($ticket->reporterStudent && $ticket->reporterStudent->user_id === $user->id) {
            return true;
        }

        return false;
    }

    private function canAccessHostel(User $user, Ticket $ticket): bool
    {
        // Get user's hostel scope
        $userHostelIds = $user->hostel_ids ?? [];

        // If no hostel scope, can access all hostels in tenant
        if (empty($userHostelIds)) {
            return true;
        }

        // Check if ticket's hostel is in user's scope
        return in_array($ticket->hostel_id, $userHostelIds);
    }
}
