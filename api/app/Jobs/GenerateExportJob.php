<?php

namespace App\Jobs;

use App\Models\ExportJob;
use App\Models\Student;
use App\Models\Domain\OutPass\OutPass;
use App\Models\AttendanceSession;
use App\Domain\Billing\Models\Payment;
use App\Models\Ticket;
use App\Models\LaundryRequest;
use App\Models\Booking;
use App\Models\VisitorLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes
    public $tries = 3;

    public function __construct(
        public ExportJob $exportJob
    ) {}

    public function handle(): void
    {
        try {
            // Initialize tenant context (job runs in tenant context)
            $tenant = \App\Models\Tenant::find($this->exportJob->tenant_id);
            if (!$tenant) {
                throw new \Exception("Tenant {$this->exportJob->tenant_id} not found");
            }
            
            \Stancl\Tenancy\Facades\Tenancy::initialize($tenant);
            
            // Set tenant session variable for RLS policies
            \App\Http\Middleware\SetPostgresSessionTenant::setTenantSessionVariable($tenant->id);
            
            try {
                $this->exportJob->update(['status' => 'Running']);

                // Generate export based on type (now in tenant context)
                $data = $this->fetchData();
                $csv = $this->generateCsv($data);
                $totalRows = count($data);

                // Upload to S3
                $filename = "exports/{$this->exportJob->tenant_id}/{$this->exportJob->type}_{$this->exportJob->id}_" . now()->format('Y-m-d_His') . ".csv";
                Storage::disk('s3')->put($filename, $csv);

                // Update export job
                $this->exportJob->markComplete($filename, $filename, $totalRows);
                
                Log::info("Export job {$this->exportJob->id} completed successfully", [
                    'type' => $this->exportJob->type,
                    'total_rows' => $totalRows,
                ]);

                // TODO: Send notification to user (push/email)
            } finally {
                // Clear tenant session variable before ending tenancy
                \App\Http\Middleware\SetPostgresSessionTenant::clearTenantSessionVariable();
                \Stancl\Tenancy\Facades\Tenancy::end();
            }
        } catch (\Exception $e) {
            $this->exportJob->markFailed($e->getMessage());
            Log::error("Export job {$this->exportJob->id} failed", [
                'tenant_id' => $this->exportJob->tenant_id,
                'type' => $this->exportJob->type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Fetch data based on export type
     */
    private function fetchData(): array
    {
        $filters = $this->exportJob->filters ?? [];

        return match ($this->exportJob->type) {
            'students' => $this->fetchStudents($filters),
            'out_passes' => $this->fetchOutPasses($filters),
            'attendance' => $this->fetchAttendance($filters),
            'payments' => $this->fetchPayments($filters),
            'tickets' => $this->fetchTickets($filters),
            'laundry' => $this->fetchLaundry($filters),
            'sports' => $this->fetchSports($filters),
            'visitors' => $this->fetchVisitors($filters),
            default => throw new \Exception("Unknown export type: {$this->exportJob->type}"),
        };
    }

    private function fetchStudents(array $filters): array
    {
        // NOTE: tenant_id removed - we're in tenant context, no tenant_id column in tenant DB
        $query = Student::query()
            ->with(['user', 'hostel', 'room']);

        if (isset($filters['hostel_id'])) {
            $query->where('hostel_id', $filters['hostel_id']);
        }

        return $query->get()->map(fn ($student) => [
            'ID' => $student->map_student_id,
            'Name' => $student->user->name,
            'Email' => $student->user->email,
            'Phone' => $student->user->phone,
            'Hostel' => $student->hostel->name,
            'Room' => $student->room?->number ?? 'N/A',
            'Roll No' => $student->roll_no,
            'Year' => $student->year,
            'Program' => $student->program,
            'Status' => $student->status,
        ])->toArray();
    }

    private function fetchOutPasses(array $filters): array
    {
        // NOTE: tenant_id removed - we're in tenant context, no tenant_id column in tenant DB
        $query = OutPass::query()
            ->with(['student.user', 'hostel']);

        if (isset($filters['hostel_id'])) {
            $query->where('hostel_id', $filters['hostel_id']);
        }

        if (isset($filters['from_date'])) {
            $query->where('requested_at', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->where('requested_at', '<=', $filters['to_date']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get()->map(fn ($outPass) => [
            'ID' => $outPass->id,
            'Student' => $outPass->student->user->name,
            'Hostel' => $outPass->hostel->name,
            'Reason' => $outPass->reason,
            'Overnight' => $outPass->overnight ? 'Yes' : 'No',
            'Status' => $outPass->status,
            'Requested At' => $outPass->requested_at->toDateTimeString(),
            'Decided At' => $outPass->decided_at?->toDateTimeString() ?? 'N/A',
            'Valid Until' => $outPass->valid_until?->toDateTimeString() ?? 'N/A',
        ])->toArray();
    }

    private function fetchAttendance(array $filters): array
    {
        // NOTE: tenant_id removed - we're in tenant context, no tenant_id column in tenant DB
        $query = AttendanceSession::query()
            ->with(['hostel', 'attendanceLogs.student.user']);

        if (isset($filters['hostel_id'])) {
            $query->where('hostel_id', $filters['hostel_id']);
        }

        if (isset($filters['from_date'])) {
            $query->where('session_date', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->where('session_date', '<=', $filters['to_date']);
        }

        $data = [];
        foreach ($query->get() as $session) {
            foreach ($session->attendanceLogs as $log) {
                $data[] = [
                    'Session Date' => $session->session_date->toDateString(),
                    'Hostel' => $session->hostel->name,
                    'Student' => $log->student->user->name,
                    'Status' => $log->status,
                    'Marked At' => $log->marked_at?->toDateTimeString() ?? 'N/A',
                ];
            }
        }

        return $data;
    }

    private function fetchPayments(array $filters): array
    {
        // NOTE: tenant_id removed - we're in tenant context, no tenant_id column in tenant DB
        $query = Payment::query()
            ->with(['student.user']);

        if (isset($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get()->map(fn ($payment) => [
            'ID' => $payment->id,
            'Student' => $payment->student->user->name,
            'Type' => $payment->type,
            'Amount' => $payment->amount,
            'Status' => $payment->status,
            'Due At' => $payment->due_at?->toDateString() ?? 'N/A',
            'Paid At' => $payment->paid_at?->toDateTimeString() ?? 'N/A',
            'Created At' => $payment->created_at->toDateTimeString(),
        ])->toArray();
    }

    private function fetchTickets(array $filters): array
    {
        // NOTE: tenant_id removed - we're in tenant context, no tenant_id column in tenant DB
        $query = Ticket::query()
            ->with(['student.user', 'hostel']);

        if (isset($filters['hostel_id'])) {
            $query->where('hostel_id', $filters['hostel_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get()->map(fn ($ticket) => [
            'ID' => $ticket->id,
            'Student' => $ticket->student->user->name,
            'Hostel' => $ticket->hostel->name,
            'Category' => $ticket->category,
            'Subject' => $ticket->subject,
            'Priority' => $ticket->priority,
            'Status' => $ticket->status,
            'Created At' => $ticket->created_at->toDateTimeString(),
            'Resolved At' => $ticket->resolved_at?->toDateTimeString() ?? 'N/A',
        ])->toArray();
    }

    private function fetchLaundry(array $filters): array
    {
        // NOTE: tenant_id removed - we're in tenant context, no tenant_id column in tenant DB
        $query = LaundryRequest::query()
            ->with(['student.user', 'hostel']);

        if (isset($filters['hostel_id'])) {
            $query->where('hostel_id', $filters['hostel_id']);
        }

        return $query->get()->map(fn ($laundry) => [
            'ID' => $laundry->id,
            'Student' => $laundry->student->user->name,
            'Hostel' => $laundry->hostel->name,
            'Service Type' => $laundry->service_type,
            'Status' => $laundry->status,
            'Requested At' => $laundry->requested_at?->toDateTimeString() ?? 'N/A',
        ])->toArray();
    }

    private function fetchSports(array $filters): array
    {
        // NOTE: tenant_id removed - we're in tenant context, no tenant_id column in tenant DB
        $query = Booking::query()
            ->with(['student.user']);

        return $query->get()->map(fn ($booking) => [
            'ID' => $booking->id,
            'Student' => $booking->student->user->name,
            'Facility' => $booking->facility,
            'Start At' => $booking->start_at?->toDateTimeString() ?? 'N/A',
            'Duration' => $booking->duration_minutes . ' min',
            'Status' => $booking->status,
        ])->toArray();
    }

    private function fetchVisitors(array $filters): array
    {
        // NOTE: tenant_id removed - we're in tenant context, no tenant_id column in tenant DB
        $query = VisitorLog::query()
            ->with(['hostel', 'guard']);

        if (isset($filters['hostel_id'])) {
            $query->where('hostel_id', $filters['hostel_id']);
        }

        return $query->get()->map(fn ($visitor) => [
            'ID' => $visitor->id,
            'Guest Name' => $visitor->guest_name,
            'Guest Phone' => $visitor->guest_phone,
            'Hostel' => $visitor->hostel->name,
            'Decision' => $visitor->decision,
            'Guard' => $visitor->guard?->name ?? 'N/A',
            'Occurred At' => $visitor->occurred_at->toDateTimeString(),
        ])->toArray();
    }

    /**
     * Generate CSV from data
     */
    private function generateCsv(array $data): string
    {
        if (empty($data)) {
            return "No data found for export\n";
        }

        // Get headers from first row
        $headers = array_keys($data[0]);

        // Generate CSV
        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers);

        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}

