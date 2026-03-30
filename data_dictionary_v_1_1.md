# DataDictionary_v1.1.md

**Release:** MAP‑HMS v1.0  
**Date:** 26‑Sep‑2025 (IST)  
**Conventions:** PostgreSQL 15+, UTF8 encoding, JSONB columns for structured data; all app tables (except system/webhook) include `tenant_id`, `created_at`, `updated_at`. Timezone IST. IDs are `BIGINT` snowflakes or auto‑inc, Tenant IDs are UUID (VARCHAR). Attachments in S3 private with presigned access. Audit via `AUDIT_LOG` + per‑module notes below.

**Reference tenant for seeds/UAT:** `MAP-STXAV` (St. Xavier’s College).

---

## 0) Keys, Indexing & Partition Guidelines
- **Tenant scoping:** Add composite indexes beginning with `(tenant_id, ...)` for all read paths.  
- **Uniqueness:**
  - `USER.phone` globally unique.  
  - `STUDENT.map_student_id` globally unique (prefix `STD-`).  
  - `STUDENT.student_uid` unique per tenant.  
  - One active `ROOM_ALLOCATION` per student → partial unique `(student_id) WHERE effective_to IS NULL` (emulated with trigger).  
  - `BOOKING` unique `(facility_id, start_at, end_at)` while `status IN (Active,Completed)`.  
  - `WEBHOOK_LOG.event_id` unique (idempotency).  
- **High‑volume tables** (`GATE_EVENT`, `PRODUCT_EVENT`, `AUDIT_LOG`): monthly partition by `occurred_at`/`happened_at`/`created_at` or use time‑based table sharding.  
- **Foreign keys:** Enforced where stable; for ultra‑hot paths (gate scans) consider FK off + app‑level integrity + nightly checks.  
- **Soft delete vs archive:** Students/Staff **not deleted** (use `archived` + `archived_at`). For content entities, optional `deleted_at` (soft delete) where UX requires undo.

---

## 1) Tenancy & Org
### TENANT
- **Fields:** `id, code, name, status(enum), archived_at, subscription_plan, subscription_amount, subscription_starts_at, subscription_ends_at, payment_mode, payment_notes, addon_security, addon_sports, addon_laundry, settings(json), data(json), created_at, updated_at, deleted_at`  
- **Status:** `provisioning` → `active` → `archived` (no suspended state).  
- **Notes:** Offline subscription tracking stored on tenant record (`subscription_*`, `payment_*`). `addon_*` columns retained for backward compatibility but set to TRUE for all tenants in v1. `settings` holds future theming knobs. `deleted_at` used for soft delete with 30-day retention.

### CAMPUS
- **Fields:** `id, tenant_id, code, name, address(json), created_at, updated_at`  
- **Indexes:** `(tenant_id, code)` unique.

### HOSTEL
- **Fields:** `id, tenant_id, campus_id, code, name, gender_mode, curfew_time, overnight_enabled, visiting_start, visiting_end, settings(json), created_at, updated_at`  
- **Indexes:** `(tenant_id, campus_id, code)` unique.

### ROOM
- **Fields:** `id, hostel_id, block_code, floor_code, room_no, created_at, updated_at`  
- **Indexes:** Unique `(hostel_id, block_code, floor_code, room_no)`.

### BED
- **Fields:** `id, room_id, state, bed_code, created_at, updated_at`  
- **Indexes:** Unique `(room_id, bed_code)`; `(state)`.

---

## 2) Identity & Access
### USER
- **Fields:** `id, tenant_id, phone, name, email, kind, archived, archived_at, created_at, updated_at`  
- **Indexes:** `phone` unique; `(tenant_id, kind)`.  
- **Privacy:** Staff phone numbers stored encrypted at rest; decrypted on access with audit trail.

### ROLE
- **Fields:** `id, name`  
- **Seed:** SuperAdmin, CampusManager, Rector, Warden, HKSupervisor, RMSupervisor, Guard, LaundryManager, SportsManager, CollegeMgmt, Student.

### USER_ROLE
- **Fields:** `user_id, role_id`  
- **Constraint:** One phone = one role enforced in provisioning (service‑level).  

### USER_SCOPE
- **Fields:** `id, user_id, campus_id?, hostel_id?, facility_id?`  
- **Notes:** Scopes restrict data access in policies; for student, scope is implicit via `student_id`.

---

## 3) Students & Allocation
### STUDENT
- **Fields:** `id, user_id, map_student_id, student_uid, roll_no, program, year_of_study, admission_year, guardian(json), medical_notes(json), correspondence_address(json), created_at, updated_at`  
- **Privacy:** Medical notes, guardian contact numbers, and addresses are encrypted at rest; access is audited and excluded from exports.
- **Indexes:** `(tenant_id, student_uid)` unique; `(tenant_id, roll_no)` optional unique by college policy.

### ROOM_ALLOCATION
- **Fields:** `id, student_id, bed_id, effective_from(date), effective_to(date?), created_at, updated_at`  
- **Rules:** One active at a time; use DB trigger to prevent overlaps; transactionally move on reallocation.

### ROOM_ALLOCATION_AUDIT
- **Fields:** `id, allocation_id, action, actor_user_id, meta(json), created_at`  
- **Use:** Evidence for room changes and checkouts.

### ROOM_CHANGE
- **Fields:** `id, tenant_id, student_id, hostel_id?, unique_id, title, description, preferred_room_number?, preferred_floor?, sharing_preference?, date_required?, status(enum: pending/approved/rejected), rejection_reason?, approved_by?, approved_at?, submitted_at, sla_due_at?, last_reminded_at?, last_escalated_at?, idempotency_key?, created_at, updated_at`
- **Notes:** `sla_due_at` drives escalation timers; reminder timestamps prevent duplicate SMS/push sends.

---

## 4) Passes, Gate & Attendance
### OUTPASS
- **Fields:** `id, tenant_id, student_id, hostel_id, reason, overnight, status, requested_at, requested_for(date), decided_at?, valid_until, note, sla_due_at?, sla_breached_at?, sla_warning_sent_at?, created_at, updated_at`
- **Indexes:** `(tenant_id, hostel_id, status, requested_at)`; `(tenant_id, student_id, requested_at)`.
- **Notes:** SLA tracking added for 2-hour approval requirement. `sla_due_at` auto-set to `requested_at + 2 hours`. Breach notifications sent to Rector and Campus Manager.

### LEAVES
- **Fields:** `tenant_id, student_id, hostel_id, unique_id, title, description, reason_for_leave, from_date, to_date, emergency_contact, status, rejection_reason?, approved_by?, approved_at?, submitted_at, sla_due_at?, sla_breached_at?, sla_warning_sent_at?, created_at, updated_at`
- **Indexes:** `(tenant_id, hostel_id, status)`; `(tenant_id, student_id, submitted_at)`; `(unique_id)`.
- **Notes:** SLA tracking added for 4-hour approval requirement. `sla_due_at` auto-set to `submitted_at + 4 hours`. Breach notifications sent to Rector and Campus Manager.

### SICK_LEAVES
- **Fields:** `tenant_id, student_id, hostel_id, unique_id, title, description, illness, illness_details, need_medical_attention, contact_parents, status, rejection_reason?, approved_by?, approved_at?, submitted_at, sla_due_at?, sla_breached_at?, sla_warning_sent_at?, created_at, updated_at`
- **Indexes:** `(tenant_id, hostel_id, status)`; `(tenant_id, student_id, submitted_at)`; `(unique_id)`.
- **Notes:** SLA tracking added for 4-hour approval requirement. `sla_due_at` auto-set to `submitted_at + 4 hours`. Breach notifications sent to Rector and Campus Manager.

### GATE_EVENT
- **Fields:** `id, tenant_id, campus_id, hostel_id, student_id, direction, method, verified(bool), verified_at, guard_user_id?, occurred_at, note?, late_minutes?, meta(json), created_at, updated_at`  
- **Indexes:** `(tenant_id, hostel_id, occurred_at)`; `(tenant_id, student_id, occurred_at)`; `(occurred_at)` partition key.  
- **Notes:** Online-only flow; `meta` stores device_id, app_version; `late_minutes` derived from curfew rules.

### ATTENDANCE_SESSION
- **Fields:** `id, tenant_id, campus_id?, hostel_id, name, kind, session_date, session_time, scheduled_at, status, metadata(jsonb), created_at, updated_at`  
- **Indexes:** `(tenant_id, hostel_id, session_date)` unique; `(tenant_id, status, scheduled_at)` for dashboards.
- **Notes:** Session edits allowed until close; Campus Manager may edit marks up to 7 days post session date (enforced at API).

### STUDENT_ATTENDANCE (a.k.a. ATTENDANCE_LOG)
- **Fields:** `id, tenant_id, session_id, student_id, hostel_id, status, note?, marked_at, marked_by, metadata(jsonb), created_at, updated_at`  
- **Indexes:** `(tenant_id, session_id, student_id)` unique; `(tenant_id, hostel_id, marked_at)`.

### INCIDENT
- **Fields:** `id, tenant_id, hostel_id, type, student_id?, note, status, opened_by, opened_at, closed_by?, closed_at?, created_at, updated_at`  
- **Indexes:** `(tenant_id, hostel_id, type, opened_at)`; `(status)`.

---

## 5) Tickets & Checklists
### TICKET
- **Fields:** `id, tenant_id, campus_id?, hostel_id?, category, status, reporter_user_id, assignee_user_id?, title, description, priority?, sla_due_at?, created_at, updated_at`  
- **Indexes:** `(tenant_id, hostel_id, category, status, updated_at)`.

### TICKET_COMMENT
- **Fields:** `id, tenant_id, ticket_id, user_id, body, attachments(jsonb)?, is_internal(bool), created_at, updated_at, deleted_at`  
- **Notes:** Attachments in S3; AV scanned; EXIF stripped; soft delete retains history.

### CHECKLIST_TEMPLATE
- **Fields:** `id, tenant_id, role, shift, title, tasks(json), created_at, updated_at`  
- **Notes:** `tasks` is array of `{id,label,evidence:{comment,photo}}`.

### CHECKLIST_INSTANCE
- **Fields:** `id, tenant_id, template_id, date, shift, role, assignee_user_id, status, review_status?, total_tasks, completed_tasks, submitted_at?, manager_user_id?, manager_note?, reviewed_at?, morning_reminded_at?, afternoon_reminded_at?, overdue_notified_at?, created_at, updated_at`  
- **Indexes:** `(tenant_id, assignee_user_id, date, shift)` unique for auto‑instances; `(tenant_id, status)`.

### CHECKLIST_ITEM
- **Fields:** `id, tenant_id, instance_id, code, label, state, comment?, photo_urls(jsonb)?, completed_at?, created_at, updated_at`  
- **Notes:** Photos S3; AV scan.

---

## 6) Laundry & Sports
### LAUNDRY_REQUEST
- **Fields:** `id, tenant_id, student_id, hostel_id, laundry_cycle_id?, service_type, bag_count, weight_kg?, status, requested_at, ready_at?, completed_at?, special_instructions?, metadata(jsonb)?, created_at, updated_at`  
- **Indexes:** `(tenant_id, hostel_id, status, requested_at)`; `(tenant_id, student_id, requested_at)`.

### LAUNDRY_CYCLE
- **Fields:** `id, tenant_id, hostel_id, machine_label, name?, scheduled_at, status, started_at?, completed_at?, estimated_completion_at?, actual_completion_at?, operator_id?, metadata(jsonb)?, created_at, updated_at`  
- **Indexes:** `(tenant_id, hostel_id, scheduled_at)`; `(tenant_id, status)`.

### SPORTS_ACTIVITY
- **Fields:** `id, tenant_id, name, description?, is_active, created_at, updated_at`

### SPORTS_EQUIPMENT
- **Fields:** `id, tenant_id, sport_activity_id?, name, code, quantity, available, condition, created_at, updated_at`

### SPORTS_EVENT
- **Fields:** `id, tenant_id, campus_id?, hostel_id?, sport, name, description?, scheduled_at, end_time?, venue?, status, capacity?, registration_deadline?, requirements(jsonb)?, metadata(jsonb)?, created_at, updated_at`  
- **Indexes:** `(tenant_id, sport, scheduled_at)`; `(tenant_id, status)`.

### SPORTS_ENROLLMENT
- **Fields:** `id, tenant_id, sports_event_id, student_id, status, enrolled_at, attended_at?, waitlist_position?, notes?, metadata(jsonb)?, created_at, updated_at`  
- **Indexes:** `unique(tenant_id, student_id, sports_event_id)`; `(tenant_id, status)`.

### SPORTS_EQUIPMENT_LOAN
- **Fields:** `id, tenant_id, student_id, equipment_id, borrowed_at, due_at, returned_at?, status, notes?, created_at, updated_at`  
- **Indexes:** `(tenant_id, student_id, status)`; `(tenant_id, equipment_id, borrowed_at)`.

---

## 7) Notices & Visitors
### NOTICE
- **Fields:** `id, tenant_id, campus_id?, hostel_id?, title, body, status, audience, channels(jsonb)?, publish_at?, published_at?, expires_at?, attachment_url?, created_by_user_id?, created_at, updated_at, deleted_at`  
- **Indexes:** `(tenant_id, hostel_id, status, publish_at)`; `(tenant_id, expires_at)`.

### VISITOR_PRE_REG
- **Fields:** `id, tenant_id, hostel_id, student_id, name, phone, whom_to_meet, visit_date?, status, created_by_user_id?, allowed_by_user_id?, denied_by_user_id?, allowed_at?, denied_at?, entry_time?, exit_time?, created_at, updated_at`

### VISITOR_LOG
- **Fields:** `id, tenant_id, hostel_id, prereg_id?, direction, occurred_at, guard_user_id?, note?, created_at, updated_at`

---

## 8) Payments (Manual Only)
### PAYMENTS
- **Fields:** `id, student_id, reference, amount_paise, currency, status(enum: pending/confirmed/failed/refunded), mode(enum: cash/upi/card/bank/cheque), metadata(json), created_at, updated_at`
- **Indexes:** `(student_id, status)`; `reference` unique.
- **Notes:** Manual payment tracking only - no online payments or Razorpay integration. Campus Manager records updates via step‑up OTP; `metadata` stores receipt reference or notes.

### WEBHOOK_LOG
- **Fields:** `id, source, event_type, event_id, valid_signature, payload(json), received_at, created_at, updated_at`  
- **Indexes:** `event_id` unique.

---

## 9) Audit, Events & Exports
### AUDIT_LOG
- **Fields:** `id, tenant_id, user_id?, action, meta(json), created_at, updated_at`  
- **Examples:** `pii_reveal`, `approval`, `mark_as_paid`, `export_create`, `login`.

### PRODUCT_EVENT
- **Fields:** `id, tenant_id, campus_id?, hostel_id?, user_id?, role?, name, entity_type, entity_id, properties(json), happened_at, created_at, updated_at`  
- **Indexes:** `(tenant_id, hostel_id, name, happened_at)`, `(tenant_id, name, happened_at)`.

### EXPORT_JOB
- **Fields:** `id, tenant_id, type, filters(json), status, file_url?, created_at, updated_at`  
- **Indexes:** `(tenant_id, type, created_at)`.

---

## 10) Imports
### IMPORT_JOB
- **Fields:** `id, tenant_id, kind, filename, status, total_rows, error_rows, error_report_url?, created_at, updated_at`  
- **Indexes:** `(tenant_id, kind, created_at)`.

### IMPORT_ERROR
- **Fields:** `id, import_job_id, row_number, code, message, row_snapshot(json), created_at, updated_at`  
- **Indexes:** `(import_job_id, row_number)`.

### Tenant Impersonation Logs (Central)
- **Table:** `tenant_impersonation_logs`  
- **Purpose:** Audit Super Admin impersonation sessions (MASVS control).  
- **Fields:** `id, super_admin_id, tenant_id, impersonated_user_id, started_at, ended_at, ip_address, reason, created_at, updated_at`  
- **Indexes:** `(super_admin_id)`, `(tenant_id)`, `(started_at)`.  
- **Notes:** Only Super Admin can impersonate; reason required; UI banner shown; nested impersonation blocked; sessions expire when user stops.

---

## 11) Retention & Purge Policy
- **Audit logs:** 7 years.  
- **Out‑Pass, Tickets, Incidents, Gate events:** 3 years online; beyond 3y archive to S3 cold (parquet/CSV).  
- **Attachments (photos, receipts):** 1 year hot → lifecycle to cold.  
- **Exports:** 7 days auto‑purge.  
- **Imports:** CSVs & error files 7 days.  
- **Users:** Archived, not deleted; **Right‑to‑Erasure** available (hard delete with legal hold bypass only by Super Admin).

---

## 12) Data Quality Rules (sample)
- **Gender‑mode enforcement:** Join `ROOM → HOSTEL.gender_mode`; reject mismatches on allocation.  
- **Curfew window math:** Auto compute Attendance open/close; allow override by Campus Manager pre‑close.  
- **Leave derivation:** If `OUTPASS.status=Approved` and `overnight=true` or time overlaps session window → mark Leave.  
- **Payments:** `status=confirmed` only after Campus Manager completes step‑up OTP; `reference` required; audit log records `mark_as_paid` metadata.  
- **Sports one‑active booking:** enforce at API via transactional check; DB unique with filtered index where available.

---

## 13) Sample Enumerations
- `gender_mode`: Male, Female, Coed  
- `bed.state`: Available, Occupied, Blocked  
- `outpass.reason`: Normal, Leave, Sick  
- `outpass.status`: Pending, Approved, Declined, Expired, Cancelled  
- `gate_event.direction`: IN, OUT  
- `gate_event.method`: QR, LIST, OTP, MANUAL, EMERGENCY  
- `attendance_session.status`: pending, in_progress, completed, open, closed  
- `student_attendance.status`: present, absent, excused, late, leave  
- `incident.type`: LateReturn, MissedAttendance, EmergencyExit, Security  
- `ticket.category`: Housekeeping, Maintenance, Laundry, Security, General, RoomChange  
- `ticket.status`: Open, InProgress, Resolved, Closed, Rejected  
- `checklist_instance.status`: Pending, Submitted  
- `checklist_instance.review_status`: Approved, SentBack  
- `laundry_request.status`: pending, picked_up, in_process, ready, delivered, cancelled, scheduled, washing, drying, completed, lost, damaged  
- `laundry_cycle.status`: scheduled, in_progress, washing, drying, ready, completed, cancelled  
- `sports_event.status`: scheduled, ongoing, completed, cancelled  
- `sports_enrollment.status`: registered, waitlisted, attended, no_show, cancelled  
- `notice.audience`: Students, Staff, Both  
- `notice.status`: draft, scheduled, published, archived  
- `export_job.status`: Queued, Running, Ready, Failed  
- `import_job.kind`: students, room_allotments  
- `import_job.status`: DryRunOK, DryRunErrors, Committed, Failed

