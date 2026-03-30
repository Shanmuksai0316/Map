# MAP-HMS Backend Modules Guide

Comprehensive guide to all Laravel backend modules, their routes, policies, jobs, and data tables.

## Table of Contents

- [Onboarding](#onboarding)
- [Imports](#imports)
- [Rooms](#rooms)
- [Out-Pass](#out-pass)
- [Gate](#gate)
- [Visitors](#visitors)
- [Attendance](#attendance)
- [Checklists](#checklists)
- [Tickets](#tickets)
- [Notices](#notices)
- [Laundry](#laundry)
- [Sports](#sports)
- [Dashboards](#dashboards)

---

## Onboarding

**Purpose**: Guide new tenants through initial setup process

### Main Routes
```php
POST /api/v1/onboarding/wizards              // Create onboarding wizard
GET  /api/v1/onboarding/wizards/{wizard}     // Get wizard details
POST /api/v1/onboarding/wizards/{wizard}/ready-check  // Check readiness
```

### Policies & Guards
- **Policy**: `OnboardingWizardPolicy`
- **Guards**: `view_any_onboarding_wizards`, `create_onboarding_wizards`

### Jobs
- **OnboardingWizardCompletionJob**: Triggers after wizard completion
- **TenantSetupJob**: Initial tenant configuration

### Data Tables
```sql
onboarding_wizards
├── id (primary)
├── tenant_id (foreign)
├── step (string)
├── data (json)
├── completed_at (timestamp)
├── created_at
└── updated_at
```

### Feature Flags
- No feature flag (always enabled)

### Side Effects
- Sends welcome email notifications
- Creates default admin user
- Sets up initial campus/hostel structure

---

## Imports

**Purpose**: Bulk import of students and room allotments from CSV/Excel files

### Main Routes
```php
POST /api/v1/imports/students/dry-run              // Validate student import
POST /api/v1/imports/students/{job}/commit         // Commit student import
POST /api/v1/imports/room-allotments/dry-run       // Validate room import
POST /api/v1/imports/room-allotments/{job}/commit  // Commit room import
```

### Policies & Guards
- **Policy**: `ImportJobPolicy`
- **Guards**: `import_students`, `import_room_allocations`

### Jobs
- **ProcessStudentImportJob**: Processes student CSV imports
- **ProcessRoomAllotmentImportJob**: Processes room allocation imports

### Data Tables
```sql
import_jobs
├── id (primary)
├── tenant_id (foreign)
├── type (enum: student, room_allotment)
├── status (enum: pending, processing, completed, failed)
├── file_path (string)
├── total_rows (integer)
├── processed_rows (integer)
├── error_rows (integer)
├── errors (json)
├── created_at
└── updated_at

import_errors
├── id (primary)
├── import_job_id (foreign)
├── row_number (integer)
├── field (string)
├── error (text)
├── created_at
└── updated_at
```

### Feature Flags
- No feature flag (always enabled)

### Side Effects
- Creates students and user accounts
- Sends welcome emails to imported students
- Generates room allocations

---

## Rooms

**Purpose**: Room and bed management, allocation tracking

### Main Routes
```php
GET    /api/v1/rooms                    // List rooms
POST   /api/v1/rooms                    // Create room
GET    /api/v1/rooms/{room}             // Show room details
PUT    /api/v1/rooms/{room}             // Update room
DELETE /api/v1/rooms/{room}             // Delete room

GET    /api/v1/room-allocations         // List allocations
POST   /api/v1/room-allocations         // Create allocation
PUT    /api/v1/room-allocations/{id}    // Update allocation
DELETE /api/v1/room-allocations/{id}    // Delete allocation
```

### Policies & Guards
- **Policy**: `RoomPolicy`, `RoomAllocationPolicy`
- **Guards**: `view_rooms`, `create_rooms`, `update_rooms`, `delete_rooms`

### Jobs
- **RoomAllocationChangeJob**: Notifies of room changes
- **RoomMaintenanceReminderJob**: Sends maintenance reminders

### Data Tables
```sql
rooms
├── id (primary)
├── tenant_id (foreign)
├── hostel_id (foreign)
├── room_block_id (foreign)
├── number (string)
├── capacity (integer)
├── type (enum: single, double, triple, dormitory)
├── status (enum: available, occupied, maintenance)
├── created_at
└── updated_at

room_blocks
├── id (primary)
├── tenant_id (foreign)
├── hostel_id (foreign)
├── name (string)
├── floor (integer)
├── created_at
└── updated_at

room_allocations
├── id (primary)
├── tenant_id (foreign)
├── student_id (foreign)
├── room_id (foreign)
├── bed_number (integer)
├── allocated_at (timestamp)
├── deallocated_at (timestamp)
├── status (enum: active, inactive)
├── created_at
└── updated_at

room_blocked_beds
├── id (primary)
├── room_id (foreign)
├── bed_number (integer)
├── reason (text)
├── blocked_until (timestamp)
├── created_at
└── updated_at
```

### Feature Flags
- No feature flag (always enabled)

### Side Effects
- Updates student hostel assignments
- Sends room allocation notifications

---

## Out-Pass

**Purpose**: Student exit/entry permission management

### Main Routes
```php
GET    /api/v1/outpasses                    // List outpasses
POST   /api/v1/outpasses                    // Create outpass
GET    /api/v1/outpasses/{outpass}          // Show outpass
PUT    /api/v1/outpasses/{outpass}          // Update outpass
POST   /api/v1/outpasses/{outpass}/cancel   // Cancel outpass

POST   /api/v1/outpasses/export             // Export outpasses
GET    /api/v1/outpasses/export/{export}    // Download export
```

### Policies & Guards
- **Policy**: `OutPassPolicy`, `OutPassExportPolicy`
- **Guards**: `view_outpasses`, `create_outpasses`, `approve_outpasses`

### Jobs
- **OutPassExpiryReminderJob**: Reminds of expiring outpasses
- **ExportOutPassesJob**: Generates CSV exports
- **OutPassApprovalNotificationJob**: Sends approval notifications

### Data Tables
```sql
out_passes
├── id (primary)
├── tenant_id (foreign)
├── student_id (foreign)
├── hostel_id (foreign)
├── type (enum: emergency, planned, medical)
├── reason (text)
├── requested_at (timestamp)
├── valid_until (timestamp)
├── approved_at (timestamp)
├── approved_by (foreign)
├── status (enum: pending, approved, rejected, expired, completed)
├── rejection_reason (text)
├── created_at
└── updated_at
```

### Feature Flags
- No feature flag (always enabled)

### Side Effects
- Sends approval/rejection notifications
- Updates student attendance records
- Triggers gate entry logging

---

## Gate

**Purpose**: Guard operations, device management, gate control

### Main Routes
```php
GET  /api/v1/gate/outpasses/today        // Today's approved outpasses
POST /api/v1/gate/out                    // Record student going OUT
POST /api/v1/gate/in                     // Record student coming IN

POST /api/v1/gate/devices/register       // Register gate device
POST /api/v1/gate/devices/heartbeat      // Device heartbeat

GET  /api/v1/gate/visitors/today         // Today's visitors
POST /api/v1/gate/visitors/{id}/allow    // Allow visitor
POST /api/v1/gate/visitors/{id}/deny     // Deny visitor

GET  /api/v1/gate-entries                // List gate entries
POST /api/v1/gate-entries                // Create gate entry
POST /api/v1/gate-entries/sync           // Sync offline entries
```

### Policies & Guards
- **Policy**: `GatePolicy`, `GateEntryPolicy`
- **Guards**: `gate_out`, `gate_in`, `view_gate_entries`

### Jobs
- **GateDeviceTimeoutJob**: Handles device timeouts
- **GateEntrySyncJob**: Syncs offline gate entries

### Data Tables
```sql
gate_devices
├── id (primary)
├── tenant_id (foreign)
├── hostel_id (foreign)
├── device_uuid (string, unique)
├── name (string)
├── status (enum: active, inactive, offline)
├── last_heartbeat (timestamp)
├── created_at
└── updated_at

gate_entries
├── id (primary)
├── tenant_id (foreign)
├── hostel_id (foreign)
├── student_id (foreign)
├── device_id (foreign)
├── type (enum: out, in)
├── entry_time (timestamp)
├── exit_time (timestamp, nullable)
├── otp_verified (boolean)
├── late_minutes (integer, nullable)
├── created_at
└── updated_at
```

### Feature Flags
- No feature flag (always enabled)

### Side Effects
- Logs all gate activities for audit
- Updates student attendance
- Sends late entry notifications

---

## Visitors

**Purpose**: Guest visit management and approval

### Main Routes
```php
POST /api/v1/visitors                     // Create visitor request
GET  /api/v1/visitors/mine/today         // My today's visits
DELETE /api/v1/visitors/{guestVisit}     // Cancel visit
```

### Policies & Guards
- **Policy**: `GuestVisitPolicy`
- **Guards**: `create_visitor_requests`, `view_visitor_requests`

### Jobs
- **VisitorApprovalNotificationJob**: Sends approval notifications
- **VisitorReminderJob**: Reminds of upcoming visits

### Data Tables
```sql
guest_visits
├── id (primary)
├── tenant_id (foreign)
├── student_id (foreign)
├── visitor_name (string)
├── visitor_phone (string)
├── visitor_id_number (string)
├── relationship (string)
├── purpose (text)
├── visit_date (date)
├── expected_arrival (time)
├── status (enum: pending, approved, rejected, completed, cancelled)
├── approved_at (timestamp)
├── approved_by (foreign)
├── created_at
└── updated_at
```

### Feature Flags
- No feature flag (always enabled)

### Side Effects
- Sends approval/rejection notifications
- Updates gate visitor lists
- Logs visitor activities

---

## Attendance

**Purpose**: Daily attendance tracking and session management

### Main Routes
```php
GET  /api/v1/attendance/sessions                    // List attendance sessions
POST /api/v1/attendance/sessions                    // Create session
POST /api/v1/attendance/sessions/{session}/students/{student}/mark  // Mark attendance

GET  /api/v1/attendance/session/today               // Today's session
GET  /api/v1/attendance/sessions/{session}/rooms    // Session rooms
GET  /api/v1/attendance/sessions/{session}/rooms/{room}  // Room roster
POST /api/v1/attendance/sessions/{session}/rooms/{room}/mark  // Mark room attendance
POST /api/v1/attendance/sessions/{session}/rooms/{room}/submit  // Submit room
POST /api/v1/attendance/sessions/{session}/rooms/{room}/marks/batch  // Batch mark
```

### Policies & Guards
- **Policy**: `AttendancePolicy`, `AttendanceSessionPolicy`
- **Guards**: `view_attendance`, `mark_attendance`, `manage_attendance_sessions`

### Jobs
- **AttendanceCloseJob**: Closes daily attendance
- **AttendanceEnsureTodayJob**: Ensures daily session exists
- **AttendanceReminderJob**: Sends attendance reminders

### Data Tables
```sql
attendance_sessions
├── id (primary)
├── tenant_id (foreign)
├── hostel_id (foreign)
├── date (date)
├── status (enum: open, closed)
├── created_by (foreign)
├── closed_at (timestamp)
├── closed_by (foreign)
├── created_at
└── updated_at

attendance_logs
├── id (primary)
├── tenant_id (foreign)
├── student_id (foreign)
├── session_id (foreign)
├── room_id (foreign)
├── status (enum: present, absent, late)
├── marked_at (timestamp)
├── marked_by (foreign)
├── created_at
└── updated_at
```

### Feature Flags
- No feature flag (always enabled)

### Side Effects
- Updates student attendance records
- Sends absence notifications to parents
- Generates attendance reports

---

## Checklists

**Purpose**: Daily/weekly maintenance and compliance checklists

### Main Routes
```php
GET  /api/v1/checklists/today              // Today's checklists
POST /api/v1/checklists/{instance}/items/{code}  // Mark checklist item
POST /api/v1/checklists/{instance}/submit  // Submit checklist
POST /api/v1/checklists/{instance}/approve // Approve checklist
POST /api/v1/checklists/{instance}/send-back  // Send back for revision
```

### Policies & Guards
- **Policy**: `ChecklistPolicy`
- **Guards**: `view_checklists`, `complete_checklists`, `approve_checklists`

### Jobs
- **ChecklistAutoCreateDailyJob**: Creates daily checklist instances
- **ChecklistEscalationJob**: Escalates overdue checklists
- **ChecklistReminderJob**: Sends completion reminders

### Data Tables
```sql
checklist_templates
├── id (primary)
├── tenant_id (foreign)
├── name (string)
├── description (text)
├── frequency (enum: daily, weekly, monthly)
├── items (json)
├── is_active (boolean)
├── created_at
└── updated_at

checklist_instances
├── id (primary)
├── tenant_id (foreign)
├── template_id (foreign)
├── hostel_id (foreign)
├── assigned_to (foreign)
├── due_date (date)
├── status (enum: pending, in_progress, submitted, approved, rejected)
├── completed_items (json)
├── submitted_at (timestamp)
├── approved_at (timestamp)
├── approved_by (foreign)
├── created_at
└── updated_at
```

### Feature Flags
- **Feature Flag**: `checklists_module` (default: enabled)

### Side Effects
- Sends completion reminders
- Escalates overdue items
- Generates compliance reports

---

## Tickets

**Purpose**: Support ticket system for student issues

### Main Routes
```php
GET  /api/v1/tickets                        // List tickets
POST /api/v1/tickets                        // Create ticket
GET  /api/v1/tickets/{ticket}               // Show ticket
POST /api/v1/tickets/{ticket}/assign        // Assign ticket
POST /api/v1/tickets/{ticket}/status        // Update status

GET  /api/v1/tickets/{ticket}/comments      // List comments
POST /api/v1/tickets/{ticket}/comments      // Add comment
```

### Policies & Guards
- **Policy**: `TicketPolicy`
- **Guards**: `view_tickets`, `create_tickets`, `assign_tickets`

### Jobs
- **TicketAssignmentNotificationJob**: Notifies of ticket assignments
- **TicketEscalationJob**: Escalates overdue tickets
- **TicketStatusUpdateJob**: Notifies of status changes

### Data Tables
```sql
tickets
├── id (primary)
├── tenant_id (foreign)
├── student_id (foreign)
├── hostel_id (foreign)
├── category (enum: maintenance, electrical, plumbing, other)
├── priority (enum: low, medium, high, urgent)
├── subject (string)
├── description (text)
├── status (enum: open, in_progress, resolved, closed)
├── assigned_to (foreign, nullable)
├── assigned_at (timestamp, nullable)
├── resolved_at (timestamp, nullable)
├── created_at
└── updated_at

ticket_comments
├── id (primary)
├── ticket_id (foreign)
├── user_id (foreign)
├── comment (text)
├── is_internal (boolean)
├── created_at
└── updated_at
```

### Feature Flags
- No feature flag (always enabled)

### Side Effects
- Sends assignment notifications
- Escalates overdue tickets
- Logs all ticket activities

---

## Notices

**Purpose**: Announcements and communications

### Main Routes
```php
GET    /api/v1/notices                     // List notices
POST   /api/v1/notices                     // Create notice
PUT    /api/v1/notices/{notice}            // Update notice
POST   /api/v1/notices/{notice}/publish    // Publish notice
DELETE /api/v1/notices/{notice}            // Delete notice
```

### Policies & Guards
- **Policy**: `NoticePolicy`
- **Guards**: `view_notices`, `create_notices`, `publish_notices`

### Jobs
- **NoticePublishJob**: Publishes notices
- **NoticeNotificationJob**: Sends notice notifications

### Data Tables
```sql
notices
├── id (primary)
├── tenant_id (foreign)
├── hostel_id (foreign, nullable)
├── title (string)
├── content (text)
├── priority (enum: low, medium, high, urgent)
├── target_audience (enum: all, students, staff, specific_hostel)
├── status (enum: draft, published, archived)
├── published_at (timestamp, nullable)
├── published_by (foreign, nullable)
├── expires_at (timestamp, nullable)
├── created_at
└── updated_at
```

### Feature Flags
- No feature flag (always enabled)

### Side Effects
- Sends push notifications
- Updates mobile app feeds
- Logs notice views

---

## Laundry

**Purpose**: Laundry cycle and request management

### Main Routes
```php
GET  /api/v1/laundry/requests              // List laundry requests
POST /api/v1/laundry/requests              // Create request
POST /api/v1/laundry/requests/{request}/status  // Update status

GET  /api/v1/laundry/cycles                // List cycles
POST /api/v1/laundry/cycles                // Create cycle
```

### Policies & Guards
- **Policy**: `LaundryRequestPolicy`, `LaundryCyclePolicy`
- **Guards**: `view_laundry_requests`, `create_laundry_requests`

### Jobs
- **LaundryCycleCompletionJob**: Processes completed cycles
- **LaundryReminderJob**: Sends pickup reminders

### Data Tables
```sql
laundry_requests
├── id (primary)
├── tenant_id (foreign)
├── student_id (foreign)
├── cycle_id (foreign)
├── items (json)
├── status (enum: pending, collected, washing, ready, delivered)
├── requested_at (timestamp)
├── collected_at (timestamp, nullable)
├── ready_at (timestamp, nullable)
├── delivered_at (timestamp, nullable)
├── created_at
└── updated_at

laundry_cycles
├── id (primary)
├── tenant_id (foreign)
├── hostel_id (foreign)
├── machine_label (string)
├── status (enum: scheduled, running, completed)
├── start_time (timestamp)
├── end_time (timestamp, nullable)
├── metadata (json)
├── created_at
└── updated_at
```

### Feature Flags
- **Feature Flag**: `laundry_module` (default: disabled)

### Side Effects
- Sends pickup/delivery notifications
- Updates cycle status
- Generates laundry reports

---

## Sports

**Purpose**: Sports events management

### Main Routes
```php
GET  /api/v1/sports/events                 // List events
POST /api/v1/sports/events                 // Create event
PUT  /api/v1/sports/events/{event}         // Update event
POST /api/v1/sports/events/{event}/enroll  // Enroll in event
POST /api/v1/sports/events/{event}/enrollments/{enrollment}  // Update enrollment
```

### Policies & Guards
- **Policy**: `SportsEventPolicy`
- **Guards**: `view_sports_events`, `enroll_sports_events`

### Jobs
- **SportsEventReminderJob**: Sends event reminders

### Data Tables
```sql
sports_events
├── id (primary)
├── tenant_id (foreign)
├── name (string)
├── description (text)
├── event_date (date)
├── start_time (time)
├── end_time (time)
├── location (string)
├── max_participants (integer)
├── status (enum: scheduled, ongoing, completed, cancelled)
├── created_at
└── updated_at

sports_enrollments
├── id (primary)
├── event_id (foreign)
├── student_id (foreign)
├── status (enum: enrolled, attended, absent)
├── enrolled_at (timestamp)
├── created_at
└── updated_at
```

### Feature Flags
- **Feature Flag**: `sports_module` (default: disabled)

### Side Effects
- Sends event notifications
- Generates sports reports

---

## Dashboards

**Purpose**: Analytics, reporting, and data visualization

### Main Routes
- No direct API routes (Filament widgets)

### Policies & Guards
- **Policy**: Dashboard access via Filament policies
- **Guards**: Role-based dashboard access

### Jobs
- **DashboardDataRefreshJob**: Refreshes cached dashboard data
- **ReportGenerationJob**: Generates scheduled reports

### Data Tables
- Uses aggregated data from other modules
- Cached in Redis for performance

### Feature Flags
- No feature flag (always enabled)

### Side Effects
- Refreshes cached metrics
- Generates automated reports
- Sends dashboard alerts

---

## Common Patterns

### Multi-Tenancy
All modules enforce tenant isolation through:
- Global scopes on models
- Policy checks
- Middleware validation

### Audit Logging
All critical operations are logged via `AuditLogger` service.

### Notifications
Modules use the notification system for:
- SMS via MSG91
- Email via SendGrid
- Push notifications via FCM

### Feature Flags
Controlled via `config/features.php` and environment variables.

---

*Backend modules guide version: v1.0*
*Owner: MAP Co-Pilot*
