<?php

namespace App\Exports;

use App\Models\Tenant;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TenantExport implements FromCollection, WithHeadings
{
    public function collection(): Collection
    {
        return Tenant::query()
            ->select(['code', 'name', 'status', 'created_at'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (Tenant $tenant) {
                return [
                    'code' => $tenant->code,
                    'name' => $tenant->name,
                    'status' => $tenant->status->value ?? (string) $tenant->status,
                    'created_at' => $tenant->created_at?->toDateTimeString(),
                ];
            });
    }

    public function headings(): array
    {
        return ['Code', 'Name', 'Status', 'Created At'];
    }
}

