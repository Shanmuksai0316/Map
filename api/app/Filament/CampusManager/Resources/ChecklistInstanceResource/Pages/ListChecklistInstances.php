<?php

namespace App\Filament\CampusManager\Resources\ChecklistInstanceResource\Pages;

use App\Filament\CampusManager\Resources\ChecklistInstanceResource;
use App\Domain\Checklists\Models\ChecklistInstance;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListChecklistInstances extends ListRecords
{
    protected static string $resource = ChecklistInstanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export_csv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->form([
                    Forms\Components\DatePicker::make('from_date')
                        ->label('From Date')
                        ->default(now()->subDays(7))
                        ->required(),
                    Forms\Components\DatePicker::make('to_date')
                        ->label('To Date')
                        ->default(now())
                        ->required(),
                    Forms\Components\Select::make('role')
                        ->label('Filter by Role')
                        ->options([
                            '' => 'All Roles',
                            'CampusManager' => 'Campus Manager',
                            'Warden' => 'Warden',
                            'HKSupervisor' => 'HK Supervisor',
                            'RMSupervisor' => 'RM Supervisor',
                            'Guard' => 'Security Guard',
                            'LaundryManager' => 'Laundry Manager',
                            'SportsManager' => 'Sports Manager',
                        ]),
                ])
                ->action(function (array $data): StreamedResponse {
                    $user = auth()->user();
                    $tenantId = $user->tenant_id;

                    $fromDate = $data['from_date'];
                    $toDate = $data['to_date'];
                    $role = $data['role'] ?? null;

                    $query = DB::table('checklist_instances as ci')
                        ->join('users as u', 'ci.assignee_user_id', '=', 'u.id')
                        ->leftJoin('checklist_templates as ct', 'ci.template_id', '=', 'ct.id')
                        ->leftJoin('users as manager', 'ci.manager_user_id', '=', 'manager.id')
                        ->where('ci.tenant_id', $tenantId)
                        ->whereBetween('ci.date', [$fromDate, $toDate]);

                    if ($role) {
                        $query->where('ci.role', $role);
                    }

                    $results = $query->select(
                        'ci.date',
                        'u.name as staff_name',
                        'u.email as staff_email',
                        'ci.role',
                        'ct.title as checklist_title',
                        'ci.status',
                        'ci.review_status',
                        'ci.total_tasks',
                        'ci.completed_tasks',
                        'ci.submitted_at',
                        'ci.reviewed_at',
                        'manager.name as reviewed_by',
                        'ci.manager_note'
                    )
                        ->orderBy('ci.date', 'desc')
                        ->orderBy('u.name')
                        ->get();

                    $filename = sprintf('checklist_report_%s_to_%s.csv', $fromDate, $toDate);

                    return response()->streamDownload(function () use ($results) {
                        $handle = fopen('php://output', 'w');

                        fputcsv($handle, [
                            'Date',
                            'Staff Name',
                            'Staff Email',
                            'Role',
                            'Checklist Title',
                            'Status',
                            'Review Status',
                            'Total Tasks',
                            'Completed Tasks',
                            'Completion %',
                            'Submitted At',
                            'Reviewed At',
                            'Reviewed By',
                            'Manager Note',
                            'Is Overdue',
                        ]);

                        foreach ($results as $row) {
                            $completionPct = $row->total_tasks > 0
                                ? round(($row->completed_tasks / $row->total_tasks) * 100, 1)
                                : 0;

                            $roleDisplay = match ($row->role) {
                                'CampusManager' => 'Campus Manager',
                                'HKSupervisor' => 'HK Supervisor',
                                'RMSupervisor' => 'RM Supervisor',
                                'LaundryManager' => 'Laundry Manager',
                                'SportsManager' => 'Sports Manager',
                                default => $row->role,
                            };

                            fputcsv($handle, [
                                $row->date,
                                $row->staff_name,
                                $row->staff_email,
                                $roleDisplay,
                                $row->checklist_title,
                                $row->status,
                                $row->review_status ?? '-',
                                $row->total_tasks,
                                $row->completed_tasks,
                                $completionPct . '%',
                                $row->submitted_at ?? '-',
                                $row->reviewed_at ?? '-',
                                $row->reviewed_by ?? '-',
                                $row->manager_note ?? '-',
                                $row->status === 'Pending' ? 'Yes' : 'No',
                            ]);
                        }

                        fclose($handle);
                    }, $filename, [
                        'Content-Type' => 'text/csv',
                    ]);
                }),
        ];
    }
}
