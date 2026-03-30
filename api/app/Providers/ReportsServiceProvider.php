<?php

namespace App\Providers;

use App\Services\Reports\Reports;
use Illuminate\Support\ServiceProvider;

class ReportsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Register the 6 working reports
        Reports::register('hostel_performance', function ($params) {
            return \App\Services\Reports\HostelPerformanceReport::generate($params);
        });
        
        Reports::register('attendance_compliance', function ($params) {
            return \App\Services\Reports\AttendanceComplianceReport::generate($params);
        });
        
        Reports::register('tickets_sla_aging', function ($params) {
            return \App\Services\Reports\TicketsSlaAgingReport::generate($params);
        });
        
        Reports::register('outpasses_late_returns', function ($params) {
            return \App\Services\Reports\OutpassesLateReturnsReport::generate($params);
        });
        
        Reports::register('visitors_log', function ($params) {
            return \App\Services\Reports\VisitorsLogReport::generate($params);
        });
        
        Reports::register('device_health', function ($params) {
            return \App\Services\Reports\DeviceHealthReport::generate($params);
        });
        
        // Register the 4 stubbed reports
        Reports::register('student_attendance_summary', function ($params) {
            return \App\Services\Reports\StudentAttendanceSummaryReport::generate($params);
        });
        
        Reports::register('room_utilization', function ($params) {
            return \App\Services\Reports\RoomUtilizationReport::generate($params);
        });
        
        Reports::register('staff_performance', function ($params) {
            return \App\Services\Reports\StaffPerformanceReport::generate($params);
        });
        
        Reports::register('financial_summary', function ($params) {
            return \App\Services\Reports\FinancialSummaryReport::generate($params);
        });

        // New reports
        Reports::register('staff_activity', function ($params) {
            return \App\Services\Reports\StaffActivityReport::generate($params);
        });

        Reports::register('tenant_health', function ($params) {
            return \App\Services\Reports\TenantHealthReport::generate($params);
        });

        Reports::register('occupancy_trend', function ($params) {
            return \App\Services\Reports\OccupancyTrendReport::generate($params);
        });
    }
}
