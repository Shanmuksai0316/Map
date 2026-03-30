<x-filament-panels::page>
    <div class="report-center-page space-y-[26px]">
    <x-filament::section heading="Generate Report" description="Select report type, date range, and format. Maximum 30-day range per download.">
        <div class="report-center-generate-wrap rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <x-filament-panels::form wire:submit="download" class="report-center-form w-full min-w-0 lg:items-end lg:gap-4">
            {{ $this->form }}

            <div class="mt-4 flex flex-wrap items-center gap-3 lg:mt-0 lg:justify-end">
                <x-filament::button type="submit" icon="heroicon-o-arrow-down-tray" class="btn-gradient-primary">
                    Download Report
                </x-filament::button>
            </div>
        </x-filament-panels::form>
        </div>
    </x-filament::section>

    <x-filament::section heading="Available Reports" class="rector-available-reports mt-[26px]" style="margin-top: 26px !important;">
        <div class="rector-available-reports-grid grid gap-4">
            @php
                $reports = [
                    ['icon' => 'heroicon-o-check-badge', 'title' => 'Approval Summary (Pass/Leave)', 'desc' => 'Approvals overview with status counts'],
                    ['icon' => 'heroicon-o-clipboard-document-check', 'title' => 'Attendance Summary', 'desc' => 'Daily attendance summary by hostel'],
                    ['icon' => 'heroicon-o-user-minus', 'title' => 'Attendance Detail (with absent names)', 'desc' => 'Absent student list with details'],
                    ['icon' => 'heroicon-o-check-circle', 'title' => 'Checklist Compliance', 'desc' => 'Checklist completion and compliance rates'],
                    ['icon' => 'heroicon-o-exclamation-triangle', 'title' => 'Incident Summary', 'desc' => 'Incidents summary by type and status'],
                    ['icon' => 'heroicon-o-paper-airplane', 'title' => 'Pass Requests Detail', 'desc' => 'Outpass/leave requests with full details'],
                ];
            @endphp

            @foreach ($reports as $report)
                <div class="rounded-xl border border-gray-200 bg-white p-6 transition hover:border-primary-400 hover:shadow-sm">
                    <div class="mb-2 flex items-center gap-3">
                        <x-filament::icon :icon="$report['icon']" class="report-card-icon shrink-0" />
                        <h4 class="text-lg font-semibold text-[#2F4F2F]">{{ $report['title'] }}</h4>
                    </div>
                    <p class="text-sm text-[#2F4F2F]/80">{{ $report['desc'] }}</p>
                </div>
            @endforeach
        </div>
    </x-filament::section>
    </div>
</x-filament-panels::page>
