<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class StaffExport implements FromCollection, WithHeadings
{
    public function collection(): Collection
    {
        return User::query()
            ->where('kind', '!=', 'student')
            ->where('archived', false)
            ->with(['tenantRelation', 'roles', 'staffHostels'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (User $user) {
                return [
                    'employee_id' => $user->employee_id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'tenant' => $user->tenantRelation?->name,
                    'roles' => $user->roles?->pluck('name')->join(', '),
                    'hostels' => $user->staffHostels?->pluck('name')->join(', '),
                    'created_at' => $user->created_at?->toDateTimeString(),
                ];
            });
    }

    public function headings(): array
    {
        return ['Employee ID', 'Name', 'Phone', 'Tenant', 'Roles', 'Hostels', 'Created At'];
    }
}

