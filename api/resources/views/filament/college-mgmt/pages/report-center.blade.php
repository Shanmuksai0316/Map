<x-filament-panels::page>
    <div class="report-center-page space-y-[26px]">
    <x-filament::section heading="Generate Report" description="College Management: Select report type, date range, and format. Maximum 30-day range per download.">
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

    <x-filament::section heading="Available Reports">
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            @php
                $reports = [
                    ['key' => 'attendance', 'icon' => 'heroicon-o-clipboard-document-check', 'title' => 'Attendance Summary', 'desc' => 'Daily attendance rates across hostels'],
                    ['key' => 'attendance_detail', 'icon' => 'heroicon-o-user-minus', 'title' => 'Attendance Detail', 'desc' => 'Absent student names and details'],
                    ['key' => 'pass_requests', 'icon' => 'heroicon-o-paper-airplane', 'title' => 'Pass Requests', 'desc' => 'Outpass and leave requests by status'],
                    ['key' => 'incidents', 'icon' => 'heroicon-o-exclamation-triangle', 'title' => 'Incidents', 'desc' => 'Incident reports summary'],
                    ['key' => 'room_occupancy', 'icon' => 'heroicon-o-home', 'title' => 'Room Occupancy', 'desc' => 'Room-wise occupancy and vacancies'],
                    ['key' => 'checklist', 'icon' => 'heroicon-o-check-circle', 'title' => 'Checklist', 'desc' => 'Staff checklist compliance rates'],
                ];
            @endphp

            @foreach ($reports as $report)
                <button
                    type="button"
                    wire:click="$set('reportType', '{{ $report['key'] }}')"
                    class="w-full rounded-xl border border-gray-200 bg-white p-4 text-left transition hover:border-primary-400 hover:shadow-sm"
                >
                    <div class="flex items-center gap-3 mb-2">
                        <x-dynamic-component :component="$report['icon']" class="h-5 w-5 text-primary-500" />
                        <h4 class="text-sm font-semibold text-[#2F4F2F]">{{ $report['title'] }}</h4>
                    </div>
                    <p class="text-xs text-[#2F4F2F]/80">{{ $report['desc'] }}</p>
                </button>
            @endforeach
        </div>
    </x-filament::section>
    </div>
</x-filament-panels::page>
