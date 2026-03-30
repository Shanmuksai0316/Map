<?php

use App\Domain\Checklists\Models\ChecklistInstance;
use App\Domain\Checklists\Models\ChecklistTemplate;
use App\Domain\Gate\Models\GateDevice;
use App\Domain\OutPass\Models\OutPass;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketComment;
use App\Domain\Visitors\Models\GuestVisit;
use App\Models\AttendanceMark;
use App\Models\AttendanceSession;
use App\Models\Hostel;
use App\Models\Notice;
use App\Models\Room;
use App\Models\RoomAllocation;
use App\Models\RoomBed;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DemoTenantSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\DemoSeederTestCase;

uses(DemoSeederTestCase::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    // RefreshDatabase trait already handles migrations
    // Calling migrate:fresh causes VACUUM to run inside transaction
    $this->artisan('db:seed', ['--class' => RolesAndPermissionsSeeder::class]);
    $this->artisan('db:seed', ['--class' => DemoTenantSeeder::class]);
});

test('demo seeder creates required user count', function () {
    expect(User::count())->toBeGreaterThanOrEqual(10);
    
    // Check that all demo staff users exist
    $staffEmails = [
        'super@demo.map.ac.in',
        'campus@demo.map.ac.in',
        'rector@demo.map.ac.in',
        'warden.h1@demo.map.ac.in',
        'warden.h2@demo.map.ac.in',
        'hk@demo.map.ac.in',
        'rm@demo.map.ac.in',
        'guard@demo.map.ac.in',
        'laundry@demo.map.ac.in',
        'sports@demo.map.ac.in',
    ];
    
    foreach ($staffEmails as $email) {
        expect(User::where('email', $email)->exists())->toBeTrue();
    }
});

test('demo seeder creates required student count', function () {
    expect(Student::count())->toBeGreaterThanOrEqual(150);
    
    // Check gender distribution
    $maleCount = Student::where('gender', 'Male')->count();
    $femaleCount = Student::where('gender', 'Female')->count();
    
    expect($maleCount)->toBeGreaterThanOrEqual(70);
    expect($femaleCount)->toBeGreaterThanOrEqual(70);
});

test('demo seeder creates required hostel count', function () {
    expect(Hostel::count())->toBeGreaterThanOrEqual(2);
    
    $hostels = Hostel::all();
    expect($hostels->where('gender_mode', 'Male')->count())->toBeGreaterThanOrEqual(1);
    expect($hostels->where('gender_mode', 'Female')->count())->toBeGreaterThanOrEqual(1);
});

test('demo seeder creates required room and bed counts', function () {
    expect(Room::count())->toBeGreaterThanOrEqual(80);
    expect(RoomBed::count())->toBeGreaterThanOrEqual(240);
    
    // Check that rooms have proper structure (3 floors, 2 blocks, 7 rooms per floor)
    $rooms = Room::all();
    $uniqueBlocks = $rooms->pluck('block_code')->unique()->count();
    $uniqueFloors = $rooms->pluck('floor_code')->unique()->count();
    
    expect($uniqueBlocks)->toBeGreaterThanOrEqual(2);
    expect($uniqueFloors)->toBeGreaterThanOrEqual(3);
});

test('demo seeder respects allocation constraints', function () {
    $allocations = RoomAllocation::where('is_active', true)->get();
    
    // Check no duplicate active allocations per student
    $studentIds = $allocations->pluck('student_id');
    expect($studentIds->count())->toBe($studentIds->unique()->count());
    
    // Check no duplicate active allocations per bed
    $bedIds = $allocations->pluck('room_bed_id');
    expect($bedIds->count())->toBe($bedIds->unique()->count());
});

test('demo seeder creates attendance sessions', function () {
    $today = now()->format('Y-m-d');
    $sessions = AttendanceSession::where('session_date', $today)->get();
    
    expect($sessions->count())->toBeGreaterThanOrEqual(2); // At least one per hostel
    
    // Check at least one session has marks
    $sessionWithMarks = $sessions->filter(function ($session) {
        return AttendanceMark::where('session_id', $session->id)->exists();
    });
    
    expect($sessionWithMarks->count())->toBeGreaterThanOrEqual(1);
});

test('demo seeder creates gate devices with known UUIDs', function () {
    $devices = GateDevice::all();
    expect($devices->count())->toBeGreaterThanOrEqual(2);
    
    $deviceUuids = $devices->pluck('device_uuid');
    expect($deviceUuids)->toContain('DEMO-TABLET-01');
    expect($deviceUuids)->toContain('DEMO-TABLET-02');
});

test('demo seeder creates visitors for today', function () {
    $today = now()->format('Y-m-d');
    $visitors = GuestVisit::where('visit_date', $today)->get();
    
    expect($visitors->count())->toBeGreaterThanOrEqual(10);
    
    // Check status distribution
    $statuses = $visitors->pluck('status');
    expect($statuses->contains('approved'))->toBeTrue();
    expect($statuses->contains('pending'))->toBeTrue();
    expect($statuses->contains('denied'))->toBeTrue();
});

test('demo seeder creates tickets with comments', function () {
    $tickets = Ticket::all();
    expect($tickets->count())->toBeGreaterThanOrEqual(20);
    
    // Check status distribution
    $statuses = $tickets->pluck('status');
    expect($statuses->contains('open'))->toBeTrue();
    expect($statuses->contains('in_progress'))->toBeTrue();
    expect($statuses->contains('resolved'))->toBeTrue();
    expect($statuses->contains('closed'))->toBeTrue();
    
    // Check that 25-30% have comments
    $ticketsWithComments = $tickets->filter(function ($ticket) {
        return TicketComment::where('ticket_id', $ticket->id)->exists();
    });
    
    $commentPercentage = ($ticketsWithComments->count() / $tickets->count()) * 100;
    expect($commentPercentage)->toBeGreaterThanOrEqual(20);
    expect($commentPercentage)->toBeLessThanOrEqual(35);
});

test('demo seeder creates notices', function () {
    $notices = Notice::all();
    expect($notices->count())->toBeGreaterThanOrEqual(5);
    
    // Check at least one is published within window
    $today = now();
    $publishedNotices = $notices->filter(function ($notice) use ($today) {
        return $notice->status === 'published' 
            && $notice->published_at <= $today 
            && $notice->expires_at >= $today;
    });
    
    expect($publishedNotices->count())->toBeGreaterThanOrEqual(1);
    
    // Check at least one has attachment
    $noticesWithAttachment = $notices->filter(function ($notice) {
        return !empty($notice->attachment_url);
    });
    
    expect($noticesWithAttachment->count())->toBeGreaterThanOrEqual(1);
});

test('demo seeder creates checklist templates and instances', function () {
    $templates = ChecklistTemplate::all();
    expect($templates->count())->toBeGreaterThanOrEqual(2);
    
    $instances = ChecklistInstance::all();
    expect($instances->count())->toBeGreaterThanOrEqual(2);
    
    // Check at least one is submitted and one is approved
$submittedInstances = $instances->where('status', ChecklistInstance::STATUS_SUBMITTED);
$approvedInstances = $instances->where('review_status', ChecklistInstance::STATUS_APPROVED);
    
    expect($submittedInstances->count())->toBeGreaterThanOrEqual(1);
    expect($approvedInstances->count())->toBeGreaterThanOrEqual(1);
});

test('demo seeder creates out-passes with proper status distribution', function () {
    $today = now()->format('Y-m-d');
    $outpasses = OutPass::all();
    
    expect($outpasses->count())->toBeGreaterThanOrEqual(40);
    
    // Check status distribution
    $statuses = $outpasses->pluck('status');
    expect($statuses->contains('approved'))->toBeTrue();
    expect($statuses->contains('pending'))->toBeTrue();
    
    // Check some are for today
    $todayOutpasses = $outpasses->where('requested_for', $today);
    expect($todayOutpasses->count())->toBeGreaterThanOrEqual(10);
});

test('demo seeder is idempotent', function () {
    $initialUserCount = User::count();
    $initialStudentCount = Student::count();

    // Run seeders again
    Artisan::call('db:seed', ['--class' => RolesAndPermissionsSeeder::class]);
    Artisan::call('db:seed', ['--class' => DemoTenantSeeder::class]);
    
    // Should not create duplicates due to guard + firstOrCreate usage
    expect(User::count())->toBe($initialUserCount);
    expect(Student::count())->toBe($initialStudentCount);
});

test('demo seeder respects tenant isolation', function () {
    $tenant = \App\Models\Tenant::where('code', 'DEMO-COLLEGE')->first();
    expect($tenant)->not->toBeNull();
    
    // All created models should belong to the demo tenant
    expect(User::where('tenant_id', '!=', $tenant->id)->count())->toBe(0);
    expect(Student::where('tenant_id', '!=', $tenant->id)->count())->toBe(0);
    expect(Room::where('tenant_id', '!=', $tenant->id)->count())->toBe(0);
    expect(RoomBed::where('tenant_id', '!=', $tenant->id)->count())->toBe(0);
    expect(Ticket::where('tenant_id', '!=', $tenant->id)->count())->toBe(0);
});
