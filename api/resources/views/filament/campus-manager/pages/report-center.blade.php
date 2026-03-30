<x-filament-panels::page>
    <div class="campus-report-center-page space-y-[26px]">
    <x-filament::section heading="Generate Report" description="Select report type, date range, and format. Maximum 30-day range per download.">
        <x-filament-panels::form wire:submit="download" class="campus-report-center-form">
            {{ $this->form }}

            <div class="mt-4 flex justify-end lg:mt-0">
                <x-filament::button type="submit" icon="heroicon-o-arrow-down-tray" class="btn-gradient-primary">
                    Download Report
                </x-filament::button>
            </div>
        </x-filament-panels::form>
    </x-filament::section>

    <x-filament::section heading="Available Reports" description="Campus Manager can export operational reports. Student data export is restricted to Super Admin." class="mt-[26px]">
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            @php
                $reports = [
                    ['icon' => 'heroicon-o-sparkles', 'title' => 'Housekeeping', 'desc' => 'HK tickets by status and SLA'],
                    ['icon' => 'heroicon-o-wrench-screwdriver', 'title' => 'Maintenance', 'desc' => 'Maintenance tickets and resolution'],
                    ['icon' => 'heroicon-o-paper-airplane', 'title' => 'Pass Requests', 'desc' => 'Outpass/leave requests by status'],
                    ['icon' => 'heroicon-o-clipboard-document-check', 'title' => 'Attendance', 'desc' => 'Daily attendance rates per hostel'],
                    ['icon' => 'heroicon-o-check-circle', 'title' => 'Checklist', 'desc' => 'Staff checklist completion rates'],
                    ['icon' => 'heroicon-o-home', 'title' => 'Room Occupancy', 'desc' => 'Room-wise occupancy and vacancies'],
                    ['icon' => 'heroicon-o-user-group', 'title' => 'Guest Visits', 'desc' => 'Guest entry/exit log per hostel'],
                    ['icon' => 'heroicon-o-exclamation-triangle', 'title' => 'Incidents', 'desc' => 'Incident reports by type'],
                ];
            @endphp

            @foreach ($reports as $report)
                <div class="rounded-xl border border-gray-200 bg-white p-6 transition hover:border-primary-400 hover:shadow-sm">
                    <div class="mb-2 flex items-center gap-3">
                        <x-filament::icon :icon="$report['icon']" class="report-card-icon h-[30px] w-[30px] shrink-0 text-[#2F4F2F]" />
                        <h4 class="text-lg font-semibold text-[#2F4F2F]">{{ $report['title'] }}</h4>
                    </div>
                    <p class="text-sm text-[#2F4F2F]/80">{{ $report['desc'] }}</p>
                </div>
            @endforeach
        </div>
    </x-filament::section>
    </div>
</x-filament-panels::page>
