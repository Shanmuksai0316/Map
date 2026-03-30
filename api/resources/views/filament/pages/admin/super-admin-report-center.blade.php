@php
    $availableReports = [
        ['icon' => 'heroicon-o-building-office-2', 'title' => 'Tenant Overview', 'desc' => 'All tenants with metrics'],
        ['icon' => 'heroicon-o-home', 'title' => 'Occupancy', 'desc' => 'Occupancy rates across hostels'],
        ['icon' => 'heroicon-o-users', 'title' => 'Student Export', 'desc' => 'Full student data per hostel'],
        ['icon' => 'heroicon-o-identification', 'title' => 'Staff Deployment', 'desc' => 'Staff roles and assignments'],
        ['icon' => 'heroicon-o-clipboard-document-check', 'title' => 'Attendance', 'desc' => 'Attendance compliance'],
        ['icon' => 'heroicon-o-inbox-stack', 'title' => 'Request Summary', 'desc' => 'All request types'],
        ['icon' => 'heroicon-o-exclamation-triangle', 'title' => 'Incidents', 'desc' => 'Incident reports'],
        ['icon' => 'heroicon-o-arrow-right-start-on-rectangle', 'title' => 'Checkout/Renewal', 'desc' => 'Checkout & renewal tracking'],
        ['icon' => 'heroicon-o-banknotes', 'title' => 'Payments', 'desc' => 'Payment collections'],
        ['icon' => 'heroicon-o-shield-check', 'title' => 'Audit Trail', 'desc' => 'Audit log of all actions'],
    ];
@endphp

<x-filament-panels::page>
    <div class="report-center-page space-y-[26px]">
        <p class="text-sm text-[#2F4F2F]/85">
            Super Admin: full access to all report types including student data export. Date range limits apply per report type.
        </p>

        <section aria-labelledby="reports-generate-heading" class="space-y-3 mt-[26px] mb-[26px]">
            <h2 id="reports-generate-heading" class="text-lg font-semibold text-[#2F4F2F]">Generate Report</h2>
            <div
                class="report-center-generate-wrap rounded-xl border border-gray-200 bg-white p-4 shadow-sm"
            >
                <x-filament-panels::form wire:submit="download" class="report-center-form w-full min-w-0 lg:items-end lg:gap-4">
                    {{ $this->form }}
                    <div class="mt-4 flex flex-wrap items-center gap-3 lg:mt-0 lg:justify-end">
                        <x-filament::button type="submit" icon="heroicon-o-arrow-down-tray" class="btn-gradient-primary">
                            Download Report
                        </x-filament::button>
                    </div>
                </x-filament-panels::form>
            </div>
        </section>

        <section aria-labelledby="reports-available-heading" class="space-y-3 mt-[26px]">
            <h2 id="reports-available-heading" class="text-lg font-semibold text-[#2F4F2F]">Available Reports</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($availableReports as $report)
                    <div
                        class="group rounded-lg border border-gray-200 bg-white p-6 shadow transition hover:border-[#2F4F2F]/35 hover:shadow-md"
                    >
                        <div class="flex items-start gap-4">
                            <x-filament::icon
                                :icon="$report['icon']"
                                class="report-card-icon h-[30px] w-[30px] shrink-0 text-[#2F4F2F] transition group-hover:text-[#244224]"
                            />
                            <div class="min-w-0 flex-1">
                                <h3 class="text-base font-semibold text-[#2F4F2F]">{{ $report['title'] }}</h3>
                                <p class="mt-1 text-sm text-[#2F4F2F]/80">{{ $report['desc'] }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</x-filament-panels::page>
