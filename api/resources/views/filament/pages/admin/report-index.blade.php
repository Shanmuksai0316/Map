@php
    $reports = [
        ['key' => 'tenant_details', 'title' => 'Tenant Details', 'description' => 'Complete tenant information across all campuses'],
        ['key' => 'student_details', 'title' => 'Student Details', 'description' => 'Student profiles and allocation information'],
        ['key' => 'staff_details', 'title' => 'Staff Details', 'description' => 'Staff assignments and contact information'],
        ['key' => 'feedback_details', 'title' => 'Feedback Details', 'description' => 'Student feedback and satisfaction metrics'],
        ['key' => 'request_details', 'title' => 'Request Details', 'description' => 'All student requests and their status'],
        ['key' => 'hostel_performance', 'title' => 'Hostel Performance', 'description' => 'Hostel utilization and performance metrics'],
        ['key' => 'attendance_compliance', 'title' => 'Attendance Compliance', 'description' => 'Student attendance patterns and compliance'],
        ['key' => 'tickets_sla_aging', 'title' => 'Tickets SLA Aging', 'description' => 'Ticket resolution time analysis'],
        ['key' => 'outpasses_late_returns', 'title' => 'Outpasses Late Returns', 'description' => 'Outpass approval and return tracking'],
        ['key' => 'visitors_log', 'title' => 'Visitors Log', 'description' => 'Visitor entries and security logs'],
        ['key' => 'device_health', 'title' => 'Device Health', 'description' => 'Device connectivity and health status'],
        ['key' => 'room_utilization', 'title' => 'Room Utilization', 'description' => 'Room occupancy and utilization rates'],
        ['key' => 'staff_performance', 'title' => 'Staff Performance', 'description' => 'Staff activity and performance metrics'],
        ['key' => 'financial_summary', 'title' => 'Financial Summary', 'description' => 'Financial transactions and summaries'],
        ['key' => 'campus_overview', 'title' => 'Campus Overview', 'description' => 'Complete campus information and metrics'],
    ];
@endphp

<x-filament-panels::page>
    <div class="report-index-page space-y-[26px]">
        <div>
            <h1 class="text-2xl font-bold text-[#2F4F2F]">Reports</h1>
            <p class="text-[#2F4F2F]/80 mt-1">Generate and download various reports</p>
        </div>

        <section aria-labelledby="reports-generate-heading" class="space-y-3 mt-[26px]">
            <h2 id="reports-generate-heading" class="text-lg font-semibold text-[#2F4F2F]">Generate Report</h2>
            <div
                class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between rounded-lg border border-gray-200 bg-white p-4 shadow-sm"
            >
                <div class="flex w-full flex-col gap-3 sm:flex-1 sm:flex-row sm:flex-wrap sm:items-end sm:gap-4 lg:max-w-4xl">
                    <div class="flex min-w-[140px] flex-1 flex-col gap-1">
                        <label for="date_from" class="text-sm font-medium text-[#2F4F2F]">From</label>
                        <input
                            type="date"
                            id="date_from"
                            class="rounded-md border border-gray-300 px-3 py-2 text-sm text-[#2F4F2F] shadow-sm focus:border-[#2F4F2F] focus:outline-none focus:ring-2 focus:ring-[#2F4F2F]/20"
                            value="{{ now()->subDays(30)->format('Y-m-d') }}"
                        >
                    </div>
                    <div class="flex min-w-[140px] flex-1 flex-col gap-1">
                        <label for="date_to" class="text-sm font-medium text-[#2F4F2F]">To</label>
                        <input
                            type="date"
                            id="date_to"
                            class="rounded-md border border-gray-300 px-3 py-2 text-sm text-[#2F4F2F] shadow-sm focus:border-[#2F4F2F] focus:outline-none focus:ring-2 focus:ring-[#2F4F2F]/20"
                            value="{{ now()->format('Y-m-d') }}"
                        >
                    </div>
                </div>
                <p class="text-xs text-[#2F4F2F]/70 sm:max-w-xs sm:text-right">
                    Dates apply to each export below. Choose a range, then use Export on a report card.
                </p>
            </div>
        </section>

        <section aria-labelledby="reports-available-heading" class="space-y-3 mt-[26px]">
            <h2 id="reports-available-heading" class="text-lg font-semibold text-[#2F4F2F]">Available Reports</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($reports as $report)
                    <div
                        class="group rounded-lg border border-gray-200 bg-white p-6 shadow transition hover:border-[#2F4F2F]/30 hover:shadow-md"
                    >
                        <div class="flex items-start gap-4">
                            <x-filament::icon
                                icon="heroicon-o-document-text"
                                class="report-card-icon h-[30px] w-[30px] shrink-0 text-[#2F4F2F] transition group-hover:text-[#244224]"
                            />
                            <div class="min-w-0 flex-1">
                                <h3 class="text-lg font-semibold text-[#2F4F2F]">{{ $report['title'] }}</h3>
                                <p class="mt-1 text-sm text-[#2F4F2F]/80">{{ $report['description'] }}</p>
                            </div>
                            <button
                                type="button"
                                class="btn-gradient-primary export-btn shrink-0 px-4 py-2 text-sm font-semibold"
                                data-report="{{ $report['key'] }}"
                            >
                                Export
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const exportButtons = document.querySelectorAll('.export-btn');

            exportButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const reportType = this.getAttribute('data-report');
                    const fromDate = document.getElementById('date_from').value;
                    const toDate = document.getElementById('date_to').value;

                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '/admin/reports/export';

                    const csrfToken = document.querySelector('meta[name="csrf-token"]');
                    if (csrfToken) {
                        const csrfInput = document.createElement('input');
                        csrfInput.type = 'hidden';
                        csrfInput.name = '_token';
                        csrfInput.value = csrfToken.getAttribute('content');
                        form.appendChild(csrfInput);
                    }

                    const inputs = [
                        { name: 'report_type', value: reportType },
                        { name: 'from_date', value: fromDate },
                        { name: 'to_date', value: toDate }
                    ];

                    inputs.forEach(input => {
                        const inputElement = document.createElement('input');
                        inputElement.type = 'hidden';
                        inputElement.name = input.name;
                        inputElement.value = input.value;
                        form.appendChild(inputElement);
                    });

                    document.body.appendChild(form);
                    form.submit();
                    document.body.removeChild(form);
                });
            });
        });
    </script>
</x-filament-panels::page>
