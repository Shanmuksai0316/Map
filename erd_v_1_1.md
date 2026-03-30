```mermaid
%% MAP-HMS v1.1 ERD (Mermaid)
%% Note: All application tables (except system tables) include tenant_id, created_at, updated_at.
%% Time columns are in IST; database PostgreSQL 15+; JSONB columns for JSON data; Tenant IDs are UUID (VARCHAR).

erDiagram
  TENANT ||--o{ CAMPUS : has
  CAMPUS ||--o{ HOSTEL : has
  HOSTEL ||--o{ ROOM : has
  ROOM ||--o{ BED : has

  TENANT {
    string id PK // UUID
    string code UNIQUE
    string name
    enum status // provisioning|active|suspended|archived|deleted
    datetime suspended_at
    text suspended_reason
    datetime archived_at
    string subscription_plan
    decimal subscription_amount
    date subscription_starts_at
    date subscription_ends_at
    string payment_mode // offline|online
    text payment_notes
    bool addon_security
    bool addon_sports
    bool addon_laundry
    json settings // feature flags, branding, etc.
    json data // stancl tenancy
    datetime created_at
    datetime updated_at
    datetime deleted_at // soft delete
  }

  CAMPUS {
    bigint id PK
    string code UNIQUE // per tenant DB
    string name
    json address
    datetime created_at
    datetime updated_at
  }

  HOSTEL {
    bigint id PK
    bigint campus_id FK
    string code UNIQUE // per tenant DB
    string name
    enum gender_mode // Male|Female|Coed
    time curfew_time
    bool overnight_enabled // default false
    time visiting_start // default 16:00
    time visiting_end   // default 19:00
    json settings
    datetime created_at
    datetime updated_at
  }

  ROOM {
    bigint id PK
    bigint hostel_id FK
    string block_code
    string floor_code
    string room_no // 3-digit
    datetime created_at
    datetime updated_at
  }

  BED {
    bigint id PK
    bigint room_id FK
    enum state // Available|Occupied|Blocked
    string bed_code // A|B|C|D
    datetime created_at
    datetime updated_at
  }

  TENANT ||--o{ TENANT_IMPERSONATION_LOG : has

  TENANT_IMPERSONATION_LOG {
    bigint id PK
    string tenant_id FK
    bigint super_admin_id FK // User ID
    bigint impersonated_user_id FK // Tenant admin user ID
    datetime started_at
    datetime ended_at
    string ip_address
    text reason
    datetime created_at
    datetime updated_at
  }

  USER ||--o{ USER_ROLE : has
  ROLE ||--o{ USER_ROLE : joins
  USER ||--o{ USER_SCOPE : has

  USER {
    bigint id PK
    bigint tenant_id FK
    string phone UNIQUE // globally unique
    string name
    string email
    enum kind // student|staff
    bool archived
    datetime archived_at
    datetime created_at
    datetime updated_at
  }

  ROLE {
    bigint id PK
    string name // SuperAdmin, CampusManager, Rector, Warden, HKSupervisor, RMSupervisor, Guard, LaundryManager, SportsManager, CollegeMgmt, Student
    enum app_surface // web|student_app|staff_app - Enforces binary-level access: Student role → student_app only; operational roles (Warden, Guard, Supervisors, Managers, Rector, SuperAdmin) → staff_app only; Campus Manager/CollegeMgmt → web only
  }

  USER_ROLE {
    bigint user_id FK
    bigint role_id FK
  }

  USER_SCOPE {
    bigint id PK
    bigint user_id FK
    bigint campus_id FK NULL
    bigint hostel_id FK NULL
    bigint facility_id FK NULL
  }

  STUDENT ||--|| USER : is
  STUDENT {
    bigint id PK
    bigint user_id FK UNIQUE
    string map_student_id UNIQUE // STD-xxxxx
    string student_uid UNIQUE // from import; tenant-scoped unique
    string roll_no
    string program
    string year_of_study
    string admission_year
    json guardian // names/phones/emails
    json medical_notes // PHI
    json correspondence_address
    bool hostel_fee_paid // Payment tracking (admission/entry)
    string payment_mode // cash|upi|card|bank|cheque
    decimal payment_amount
    date payment_date
    string payment_reference // Receipt/Transaction no
    text payment_notes
    datetime created_at
    datetime updated_at
  }

  ROOM_ALLOCATION ||--o{ ROOM_ALLOCATION_AUDIT : has
  STUDENT ||--o{ ROOM_ALLOCATION : has
  BED ||--o{ ROOM_ALLOCATION : used_by

  ROOM_ALLOCATION {
    bigint id PK
    bigint student_id FK
    bigint bed_id FK
    date effective_from
    date effective_to NULL
    datetime created_at
    datetime updated_at
  }

  ROOM_ALLOCATION_AUDIT {
    bigint id PK
    bigint allocation_id FK
    string action // assign|reassign|checkout
    bigint actor_user_id FK
    json meta // reasons, old/new
    datetime created_at
  }

  STUDENT ||--o{ ROOM_CHANGE : requests

  ROOM_CHANGE {
    bigint id PK
    bigint tenant_id FK
    bigint student_id FK
    bigint hostel_id FK NULL
    string unique_id UNIQUE
    string title
    text description
    string preferred_room_number NULL
    string preferred_floor NULL
    enum sharing_preference // single|double|triple|quad
    date date_required NULL
    enum status // pending|approved|rejected
    text rejection_reason NULL
    bigint approved_by FK NULL
    datetime approved_at NULL
    datetime submitted_at
    datetime sla_due_at NULL
    datetime last_reminded_at NULL
    datetime last_escalated_at NULL
    string idempotency_key NULL
    datetime created_at
    datetime updated_at
  }

  OUTPASS {
    bigint id PK
    bigint tenant_id FK
    bigint student_id FK
    bigint hostel_id FK
    enum reason // Normal|Leave|Sick
    bool overnight
    enum status // Pending|Approved|Declined|Expired|Cancelled
    datetime requested_at
    datetime decided_at NULL
    datetime valid_until
    text note
    datetime created_at
    datetime updated_at
  }

  GATE_EVENT {
    bigint id PK
    bigint tenant_id FK
    bigint student_id FK
    bigint hostel_id FK
    enum direction // OUT|IN
    enum method // QR|LIST|OTP|MANUAL|EMERGENCY
    datetime occurred_at
    bool offline_queued
    json meta // device_id, app_version
    datetime created_at
    datetime updated_at
  }

  ATTENDANCE_SESSION ||--o{ STUDENT_ATTENDANCE : has
  HOSTEL ||--o{ ATTENDANCE_SESSION : has

  ATTENDANCE_SESSION {
    bigint id PK
    bigint tenant_id FK
    bigint hostel_id FK
    date session_date
    time open_time
    time close_time
    enum status // Open|Closed
    datetime created_at
    datetime updated_at
  }

  STUDENT_ATTENDANCE {
    bigint id PK
    bigint tenant_id FK
    bigint session_id FK
    bigint student_id FK
    enum mark // Present|Absent|Leave|Unmarked
    text comment
    datetime marked_at
    bigint marked_by FK
    datetime created_at
    datetime updated_at
  }

  INCIDENT {
    bigint id PK
    bigint tenant_id FK
    bigint hostel_id FK
    enum type // LateReturn|MissedAttendance|EmergencyExit|Security
    bigint student_id FK NULL
    text note
    enum status // Open|Closed
    bigint opened_by FK
    datetime opened_at
    bigint closed_by FK NULL
    datetime closed_at NULL
    datetime created_at
    datetime updated_at
  }

  TICKET ||--o{ TICKET_COMMENT : has
  HOSTEL ||--o{ TICKET : has

  TICKET {
    bigint id PK
    bigint tenant_id FK
    bigint hostel_id FK
    enum category // Housekeeping|Maintenance|Laundry|Security|General|RoomChange
    enum status // Open|InProgress|Resolved|Closed|Rejected
    bigint created_by FK
    bigint assigned_to FK NULL
    string title
    text description
    datetime created_at
    datetime updated_at
  }

  TICKET_COMMENT {
    bigint id PK
    bigint ticket_id FK
    bigint user_id FK
    text comment
    string photo_url NULL
    datetime created_at
    datetime updated_at
  }

  CHECKLIST_TEMPLATE ||--o{ CHECKLIST_INSTANCE : spawns
  CHECKLIST_INSTANCE ||--o{ CHECKLIST_ITEM : has

  CHECKLIST_TEMPLATE {
    bigint id PK
    bigint tenant_id FK
    enum role
    string shift
    string title
    json tasks
    datetime created_at
    datetime updated_at
  }

  CHECKLIST_INSTANCE {
    bigint id PK
    bigint tenant_id FK
    bigint template_id FK
    date date
    string shift
    enum role
    bigint assignee_user_id FK
    enum status // Pending|Submitted|Approved|SentBack
    enum review_status NULL
    smallint total_tasks
    smallint completed_tasks
    datetime submitted_at NULL
    bigint manager_user_id FK NULL
    text manager_note NULL
    datetime reviewed_at NULL
    datetime morning_reminded_at NULL
    datetime afternoon_reminded_at NULL
    datetime overdue_notified_at NULL
    datetime created_at
    datetime updated_at
  }

  CHECKLIST_ITEM {
    bigint id PK
    bigint tenant_id FK
    bigint instance_id FK
    string code
    string label
    enum state // Pending|Done|Skipped
    text comment NULL
    json photo_urls NULL
    datetime completed_at NULL
    datetime created_at
    datetime updated_at
  }

  LAUNDRY_JOB {
    bigint id PK
    bigint tenant_id FK
    bigint student_id FK
    bigint hostel_id FK
    enum status // Requested|Processing|Ready|Completed|Cancelled
    int shirts
    int pants
    int jeans
    int others
    decimal total_weight_kg
    string bag_id
    text notes
    bigint verified_by FK NULL
    datetime ready_at NULL
    datetime completed_at NULL
    datetime created_at
    datetime updated_at
  }

  FACILITY ||--o{ BOOKING : has
  HOSTEL ||--o{ FACILITY : has

  FACILITY {
    bigint id PK
    bigint tenant_id FK
    bigint hostel_id FK
    string name
    time open_time // 06:00
    time close_time // 22:00
    datetime created_at
    datetime updated_at
  }

  BOOKING {
    bigint id PK
    bigint tenant_id FK
    bigint facility_id FK
    bigint student_id FK
    datetime start_at
    datetime end_at
    enum status // Active|Cancelled|NoShow|Completed
    datetime created_at
    datetime updated_at
  }

  NOTICE {
    bigint id PK
    bigint tenant_id FK
    bigint hostel_id FK
    enum audience // Students|Staff|Both
    string title
    text content
    string attachment_url NULL
    datetime start_at
    datetime end_at
    datetime created_at
    datetime updated_at
  }

  WEBHOOK_LOG {
    bigint id PK
    string source // msg91|sendgrid
    string event_type
    string event_id UNIQUE
    bool valid_signature
    json payload
    datetime received_at
    datetime created_at
    datetime updated_at
  }

  AUDIT_LOG {
    bigint id PK
    bigint tenant_id FK
    bigint user_id FK NULL
    string action // login, approval, pii_reveal, mark_as_paid, export, etc.
    json meta
    datetime created_at
    datetime updated_at
  }

  PRODUCT_EVENT {
    bigint id PK
    bigint tenant_id FK
    bigint campus_id FK NULL
    bigint hostel_id FK NULL
    bigint user_id FK NULL
    string role NULL
    string name // catalog in OpsAnalytics
    string entity_type
    bigint entity_id
    json properties
    datetime happened_at
    datetime created_at
    datetime updated_at
  }

  EXPORT_JOB {
    bigint id PK
    bigint tenant_id FK
    string type // dataset name
    json filters
    enum status // Queued|Running|Ready|Failed
    string file_url NULL
    datetime created_at
    datetime updated_at
  }

  VISITOR_PRE_REG ||--o{ VISITOR_LOG : has
  HOSTEL ||--o{ VISITOR_PRE_REG : has

  VISITOR_PRE_REG {
    bigint id PK
    bigint tenant_id FK
    bigint hostel_id FK
    bigint student_id FK
    string guest_name
    string guest_phone
    string person_to_meet // defaults to self
    datetime visiting_date // optional if same-day
    datetime created_at
    datetime updated_at
  }

  VISITOR_LOG {
    bigint id PK
    bigint tenant_id FK
    bigint hostel_id FK
    bigint prereg_id FK NULL
    string guest_name
    string guest_phone
    enum decision // Allowed|Denied
    string reason NULL
    datetime occurred_at
    datetime created_at
    datetime updated_at
  }

  IMPORT_JOB ||--o{ IMPORT_ERROR : has

  IMPORT_JOB {
    bigint id PK
    bigint tenant_id FK
    enum kind // students|room_allotments
    string filename
    enum status // DryRunOK|DryRunErrors|Committed|Failed
    int total_rows
    int error_rows
    string error_report_url NULL
    datetime created_at
    datetime updated_at
  }

  IMPORT_ERROR {
    bigint id PK
    bigint import_job_id FK
    int row_number
    string code
    string message
    json row_snapshot
    datetime created_at
    datetime updated_at
  }
```

