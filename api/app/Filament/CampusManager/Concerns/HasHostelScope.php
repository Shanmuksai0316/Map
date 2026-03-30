<?php

namespace App\Filament\CampusManager\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Trait for Campus Manager Filament resources/pages to scope queries
 * by the currently selected hostel from the session-based hostel switcher.
 *
 * Usage:
 *   use HasHostelScope;
 *   // In table query or any query builder:
 *   $query = $this->applyHostelScope($query, 'hostel_id');
 */
trait HasHostelScope
{
    /**
     * Get the currently active hostel ID from session.
     * Returns null when "All Hostels" is selected.
     */
    protected function getActiveHostelId(): ?int
    {
        return session('active_hostel_id');
    }

    /**
     * Apply hostel scope to a query builder.
     *
     * @param  Builder  $query   The Eloquent query builder
     * @param  string   $column  The column to filter on (default: 'hostel_id')
     * @return Builder
     */
    protected function applyHostelScope(Builder $query, string $column = 'hostel_id'): Builder
    {
        $hostelId = $this->getActiveHostelId();

        if ($hostelId) {
            return $query->where($column, $hostelId);
        }

        // "All Hostels" — no additional filter; tenant scope already applies
        return $query;
    }
}
