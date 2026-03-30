# PRD_v1.1.md — Product Requirements (Final)

**Release:** MAP‑HMS v1.0  
**Date:** 26‑Sep‑2025 (IST)  
**Owner:** MAP Co‑Pilot  
**Related:** Document Index_v1.1.md, ERD_v1.1.mmd, DataDictionary_v1.1.md, API_Spec_v1.1.yaml, SecurityPlan_MASVS_v1.0.md, ImplementationPlan_v1.0.md, TestStrategy_v1.0.md, OpsAnalytics_v1.0.md

---

## 1. Vision & Goals
Digitize and streamline daily hostel operations for multi‑college environments with a **lean, policy‑first** product that works reliably **online/offline** at gates, keeps data **tenant‑isolated** (logical), and provides **actionable dashboards** for decision makers.

**Success metrics (pilot):**
- QR **scan success ≥ 99%**; **offline exit** sync accuracy 100%.  
- Rector **median decision time ≤ 2h** (7‑day).  
- Attendance **Unmarked ≤ 3%** in last session.  
- Ticket **median resolve ≤ 3 days** (7‑day).  
- App crash rate **≤ 0.5%** sessions; API p95 **≤ 500/800ms** (R/W).

**Pilot tenant for UAT/demo references:** *St. Xavier’s College* (code `MAP-STXAV`).

---

## 2. In/Out of Scope (v1)
**In:** Multi‑tenant onboarding, imports, room inventory & manual allocation, Out‑Pass/Leave approvals (Rector), gate scanning (Security add‑on), attendance (Warden), tickets & checklists, laundry & sports add‑ons, notices, minimal payments (S3), dashboards, exports, push/SMS/email, observability, retention & audit.

**Out:** Fee heads/schedules, refunds UI, settlements/Route/Linked Accounts, visitor ID capture, biometrics/face, per‑facility sports overrides, read‑receipts, maker‑checker for mark‑as‑paid, rotating QRs, certificate pinning (deferred), per‑hostel add‑on toggles.

---

## 3. Tenancy & Roles
**Tenancy model:** Single shared PostgreSQL database, single schema. All business tables include `tenant_id`, `created_at`, `updated_at`. Application-level global TenantScope + PostgreSQL RLS enforce isolation. Global uniqueness: `users.email`, `hostel.code`, `student_uid`. Topology: **1 Tenant = 1 Campus = Multiple Hostels** (no multi-campus).

**Modules:** Security/Gate, Sports, Laundry are always enabled for every tenant (no per‑tenant toggles in v1).

### 3.1 Tenant Lifecycle Management
- **Status:** `provisioning` (during onboarding) → `active` → `archived` (read-only; non-reactivable)
- Archive is manual by Super Admin; for records/compliance only.
- No Suspended state; no hard delete (archive-only).
- Tenant code/slug: must start with "MAP" (e.g., MAP-STXAV); globally unique.

### 3.2 Subscription Management
- **Offline sales model:** tenants are pre-paid customers
- Subscription tracking: plan (Basic/Premium/Enterprise), amount, start/end dates, payment mode
- No automated billing - manual tracking only
- Payment notes field for offline payment details (bank transfer, cheque, transaction ID)
- Default: 1-year subscription, Basic plan

### 3.3 Tenant Creation
- **ONLY via Onboarding Wizard** (Super Admin). No manual creation outside wizard.
- **Wizard steps:** 1) Tenant Information (College & Campus) → 2) Hostel Details & Configuration → 3) Assign Staff (8 roles) → 4) Assign College Representatives → 5) Activation (requires step‑up OTP)
- Staff must pre-exist in Staff Management; wizard selects only. Save Draft + Resume (no expiry).
- **Pre‑flight (blocks Activate):** All mandatory roles assigned (roles marked "Not applicable" explicitly allowed), Rector & College Mgmt contacts valid, ≥1 hostel with curfew, rooms/beds generated, Campus Manager tenant-scoped.
- **Idempotency:** Create/Activate require `Idempotency-Key`; 24h dedupe; replay returns identical success payload.
- **Rollback:** Super Admin can rollback to provisioning within 24h; step‑up OTP mandatory before rollback executes; rollback writes audit entry.
- **Post‑activation edits:** Structural changes locked; **adding new hostels is not allowed**. Campus fields remain editable.

### 3.4 Staff Assignment (MAP HMS Team)
- Roles assigned with scope:
  - Campus Manager → **tenant‑scope** (spans all hostels under the tenant)
  - All other roles (Rector, Warden, Guard, HK Supervisor, RM Supervisor, Laundry Manager, Sports Manager) → **hostel‑scope**
- v1 simplicity: exactly **one user per role per hostel** (Campus Manager is single tenant-wide).
- Staff must have active scope to access mobile/app surfaces.
- Full audit (who/when/why), notifications on assignment/reassignment.

**Roles & Access Surfaces**
- **Web (Filament):** Super Admin (full system access), Campus Manager (tenant admin), Rector (tenant representative with web + mobile access), College Mgmt (read‑only).
- **Mobile — Student App (RN):** Student-only experience (self-service out-pass, payments, notices, tickets). Bundle ID: `com.mapmars.hmsstudent`. Authentication restricted to Student role only; attempting to log in with a staff role displays an error and forces logout.
- **Mobile — Staff App (RN):** Campus Manager (web + mobile), Rector (web + mobile approvals), Warden, Guard (add‑on), HK Supervisor, R&M Supervisor, Laundry Manager (add‑on), Sports Manager (add‑on), Super Admin (diagnostics). Bundle ID: `com.mapmars.hmsstaff`. Authentication restricted to operational roles; attempting to log in with a Student role displays an error and forces logout. **Note:** Staff must have active hostel assignment to access mobile app (enforced by assignment validation).

**Device policy:** Two distinct mobile binaries enforce role separation at the application level. Student App and Staff App are distributed as separate downloads on app stores. Each app validates role on login and bootstrap, rejecting unsupported roles immediately. **One active device per user per app variant.** Audit logs include `device_name` field to distinguish student-app vs staff-app authentications.

---

## 4. Core Policies & Workflows

**Connectivity policy:** Mobile apps operate online-first. If device has no connectivity, surfaces display "Connection lost. Retry." **Offline queue implemented for Guard/Warden roles:** Gate operations (exit/entry) and attendance marks are queued locally when offline and automatically synced when connectivity is restored. Queue persists across app restarts with retry logic (max 3 attempts). Student app operates online-only (no offline queue).
### 4.1 Onboarding (Super Admin)
- Wizard: Tenant Info → Hostel Configuration → Assign Staff (8, N/A allowed) → Assign College Representatives → Activation.
- **Status:** `provisioning` during wizard; `active` on completion. Save Draft + Resume supported.
- **Ready checks:** Mandatory roles present; contacts set; ≥1 hostel with curfew; rooms/beds generated; Campus Manager tenant-scoped.
- **Security:** Step‑up OTP enforced every time Super Admin activates or rolls back a tenant; audit log captures OTP session ID.
- **Idempotent Create/Activate** with `Idempotency-Key` (24h).
- **Notifications on Activate:** SMS+Email to assigned staff (role/scope), Rector, and College Mgmt. Push begins post first login.

### 4.2 Imports (Campus Manager)
- **Mode:** Self‑serve via web; **Dry‑run → Commit**; ad‑hoc cadence; row fix SLA **48h**.  
- **Students.csv (S1/S2):** `student_uid*`, `name*`, `phone*`, `gender*`, `dob`, `roll_no`, `program`, `year_of_study`, `admission_year`, `guardian_phone`, `email`, `permanent_address`, `correspondence_address`.  
- **RoomAllotments.csv (S1/S2 split fields):** `student_uid*`, `hostel_code*`, `block_code*`, `floor_code*`, `room_no* (3‑digit)`, `bed_code* (A/B/C/D)`, `effective_from* (YYYY‑MM‑DD)`.  
- **Validation:** Reject unknown beds, duplicates; enforce gender‑mode; partial failures produce downloadable `errors.csv`.  
- **Activation:** Post‑import → **Activate N** students (Welcome SMS with OTP login).  
- **Retention:** CSVs & error files kept 7d.

### 4.3 Rooms & Allocation (Campus Manager)
- Inventory CRUD; beds states `Available/Occupied/Blocked`.  
- **Manual allocation** only (S2/S3); atomic transaction; one active bed per student.  
- Room Change = Ticket (student) → Approve (Campus Manager) → Reallocate.  
- **Room-change reminders:** Pending requests inherit a **24h SLA**; when breached the platform auto-dispatches **SMS + push** to Campus Managers (repeat every 2h until status changes) and logs the escalation for dashboard counters/audits.

**Dispute Handling (Allocation/Billing/Tickets):**
- Student raises dispute ticket (Student app) selecting dispute reason.
- Campus Manager must acknowledge and provide resolution within **24h**.
- If unresolved after 24h, ticket auto‑escalates to Rector with full audit context.

### 4.4 Out‑Pass / Leave (Student → Rector)
- Student submits (Normal/Leave/Sick; overnight flag).
- **Rector approves all**; Pending auto‑expires **T+24h**; student can **cancel while Pending**.
- **Emergency Exit:** Guard can allow with note; Rector may convert to Approved Leave within 24h; Campus Manager can close with note.

### 4.4.1 Rector Approval Panel (Consolidated View)

**Navigation:** The Rector sees a unified "Approvals" group with three sub-tabs:
- **Out-Pass** (existing, enhanced with 2h SLA)
- **Leave** (new, combining Leave + Sick Leave with 4h SLA)
- **History** (new, consolidated approval history)

**Flow:**
1. Rector opens any tab and sees pending requests in a table view
2. Each request shows: Student name, request type, date/time, reason, and **SLA countdown badge**
3. Rector clicks "Approve" or "Reject" actions
4. Modal appears with:
   - Text area for notes
   - Quick template dropdown (e.g., "Approved as requested", "Approved with conditions", "Emergency approved")
   - Step-up OTP verification prompt (per § 5, existing security)
5. Upon confirmation, both **Student** and **Campus Manager** receive notifications:
   - Push notification (app)
   - SMS (MSG91)
   - Email (SendGrid, for Campus Manager)

**SLA Tracking:**
- **Out-Pass:** 2-hour SLA from submission
- **Leave/Sick Leave:** 4-hour SLA from submission
- Visual indicators:
  - 🟢 Green: > 25% SLA remaining
  - 🟡 Yellow: ≤ 25% SLA remaining (warning)
  - 🔴 Red: SLA breached
- **Notifications:**
  - At 75% of SLA time: Rector receives "Expiring Soon" push notification
  - At 100% (breach): Rector receives push + SMS; Campus Manager also notified
  - Hourly reminders continue until resolved
- **Filters:** "SLA Breached" and "Due Soon" filters available on all tabs

**Approval History:**
- Flow: Rector opens "History" tab to view all past decisions
- Table shows: Type (Out-Pass/Leave/Sick Leave), Student, Decision (Approved/Rejected), Date, Notes
- Filters: date range, request type, decision status
- Export to CSV available

**Reports & Downloads:**
- Monthly downloadable reports (PDF or CSV format)
- Rector clicks "Download Report" action from Dashboard
- Modal prompts for: Month, Year, Format (PDF/CSV)
- Report includes: total approvals/rejections, SLA performance, breakdown by request type

### 4.5 Gate (Security Guard App)
- Security Guard uses the staff mobile app (no dedicated tablet) to scan student QR codes, search passes, or enter emergency notes.
- **Online-only:** app requires connectivity; if offline, guard is prompted to retry or use Emergency Exit workflow.
- Visitor window: **single daily** per hostel, default **16:00–19:00**; campus manager configures per hostel.

### 4.6 Attendance (Warden)
- **Window:** Auto‑open **curfew − 1h**; auto‑close **curfew + 2h**; Warden can edit until close.  
- Marks: **Present/Absent**; **Leave** auto‑derived (read‑only) from overlapping Approved passes; **Unmarked** retained.  
- On close with Unmarked → **Missed Attendance** incident auto‑created; **Warden closes** with note.  
- **Campus Manager** can **edit after close** (audited) for up to **7 days** from session date.

**Mobile App Enhancements:**

**Warden Mobile App:**
- **Dashboard:** MAP logo displays before "Warden App" title; kebab menu (⋮) replaces logout button with options: Profile, Announcements, Notifications, Logout; time‑based greeting (Good Morning/Afternoon/Evening); "Requests Raised Today" section with count badge and two‑column layout grouped by department (Gate pass, Housekeeping, Repair & Maintenance, Laundry, Sports, Sick Leave Token, Leaves, Guest Entry, Room Change); Quick Stats section removed.
- **Attendance Screen:** "Attendance" header with current day and date display (e.g., "Monday, January 15, 2025"); text input field appears when Absent (A) or Leave (L) is selected; text field required for Absent/Leave selections; submit button enabled only when all students are marked and notes provided for absent/leave students.
- **Checklist Screen:** Each task has checkbox; tasks may require photo upload (`require_photo` flag); tasks may require notes/comments (`require_comment` flag); "Submit Checklist" button validates all tasks are checked, required photos uploaded, and required notes provided; submission blocked until all requirements met.
- **Requests Screen:** Filters: All, Housekeeping, Repair & Maintenance, Leave, Out Pass, Guest Entry; tapping request opens detail screen; **Housekeeping/Repair detail shows:** Request type, Student name & room, Submitted date/time, Time elapsed, Description, Attachment (if available), Back button; **Leave/Out Pass detail shows:** Request type, Student name & room, Approved by (Rector name), From date‑to date (Leave) or From time‑to time (Out Pass), Status, Reason for request.
- **Students Screen:** "View" button navigates to detail screen (replaces Alert dialog); detail screen shows 6 dropdown tabs: Personal Information, Academic Details, Parent/Guardian Information, Local Guardian Details, Medical & Health Information, Emergency Contact Details; all fields display correct data or "N/A" if empty.

**HK Supervisor Mobile App:**
- **Dashboard:** MAP logo displays before "Housekeeping App" title; kebab menu (⋮) replaces logout button with options: Profile, Announcements, Notifications; time‑based greeting (Good Morning/Afternoon/Evening) with "Hi Repair and Management {name}"; bottom navigation bar with tabs: Dashboard, Requests, Checklists; "Today's Raised Tickets" section showing counts: Open Request, In Progress, Completed; "Recent Requests" section with preview cards showing: issue, student name & room number, status badge (Open/In Progress/Completed), time elapsed (relative format like "15 mins ago"), View button that navigates to Requests screen.
- **Requests Screen:** All request preview cards displayed with: issue, student name & room number, time, request status badge; "View Details" button opens detail modal showing: issue, status, description, images (if available), student details (name & room number); "Update Status" button opens status update popup; status progression: Open → In Progress → Complete; "Confirm Status Update" button updates status; "Cancel" button closes popup; "Close" button returns to Requests list.
- **Checklist Screen:** Housekeeping checklist tasks displayed: Clean common areas (lobby, corridors) — requires comment; Sanitize and maintain restrooms — requires photo; Dispose of waste and empty bins; Check cleaning supplies inventory — requires comment; Clean and sanitize dining area — requires comment and photo; Verify room cleaning completion — requires comment; Clean and organize laundry area; Complete daily housekeeping report — requires comment. Each task shows completion status and requirement indicators (comment/photo icons); task detail modal validates requirements before allowing completion.

**Security Guard Mobile App:**
- **Dashboard:** MAP logo displays before "Guard App" title; kebab menu (⋮) replaces logout button with options: Profile, History, Notification, Announcement; time‑based greeting (Good Morning/Afternoon/Evening) with "Hi Security Guard {name}"; bottom navigation bar with tabs: Dashboard, Scan QR, Gate Pass; verification breakdown by type showing current day counts: Outpass count, Leave count, Guest Entry count; recent activity feed showing last 3 activities with: student name, action type (Exit/Entry), timestamp (relative format).
- **Scan QR Screen:** Button to scan QR code (opens camera scanner); manual entry option for QR code or gate pass ID; displays scanned code result with student gate pass details.
- **Gate Pass Screen:** 3 column interface with tabs: Outpass, Leave, Guest Entry; badge counts on each tab showing current active or upcoming count; **Outpass tab:** List of outpass requests, each card shows: student name, room number, reason, status, exit and entry time, View button; **Leave tab:** List of leave requests, each card shows: student name, room number, leave type, status, exit date range, time, View button; **Guest Entry tab:** List of guest entry requests, each card shows: student name, room number, visitor name, status, time, View button; View button opens detail screen for each type.
- **Detail Screens:** **Outpass detail:** student name, room number, reason, status, exit and entry timing, close button; **Leave detail:** student name, room number, reason, status, from date to date, emergency contact, close button; **Guest detail:** student name, room number, reason, status, guest name, guest relationship, guest phone number, close button.
- **Profile Screen (Kebab Menu):** Display profile, Name, ID, role, phone, shift details; logout with confirmation.
- **History Screen (Kebab Menu):** Display all gate logs; date filters: Today, Yesterday, This Week, This Month, Custom; search by student name or roll number; display: Student info, direction (Exit/Entry), timestamp, guard name, pass ID, status; group by date.
- **Notifications Screen (Kebab Menu):** Display all notifications; types: Gate alerts, expired pass warnings, visitor requests.
- **Announcements Screen (Kebab Menu):** Display all announcements; show: Date, title, description.

**Laundry Manager Mobile App:**
- **Dashboard:** MAP logo displays before "Laundry App" title; kebab menu (⋮) replaces logout button with options: Profile, History, Notification, Announcement; time‑based greeting (Good Morning/Afternoon/Evening) with "Hi Laundry Manager {name}"; bottom navigation bar with tabs: Dashboard, Scan QR, Gate Pass; verification breakdown by type showing current day counts: Outpass count, Leave count, Guest Entry count; recent activity feed showing last 3 activities with: student name, action type (Exit/Entry), timestamp (relative format).
- **Scan QR Screen:** Same as Security Guard - button to scan QR code, manual entry option.
- **Gate Pass Screen:** Same structure as Security Guard - 3 tabs (Outpass, Leave, Guest Entry) with badge counts and detail screens.
- **Profile, History, Notifications, Announcements:** Same as Security Guard implementation.

### 4.7 Tickets & Checklists
- **Tickets:** Categories Housekeeping, Maintenance, Laundry, Security, General, RoomChange. Statuses: Open → In‑Progress → Resolved → Closed (Reject allowed). Warden **view‑only**; Supervisors manage category queues.  
- **Checklists:** Templates per role/shift; daily auto‑instances; reminders **T−60/T−15** plus **09:00/15:00 SMS + push nudges**; overdue (>grace window) escalates to assignee + manager via push/SMS until submission; manager Approve/Send-back.

**Operational SLAs (Pilot default):**

| Request Type | SLA (Hours) | Primary Owner | Escalation |
|--------------|-------------|---------------|------------|
| Out-Pass Approval | 2h | Rector | Campus Manager (notified on breach) |
| Leave/Sick Leave Approval | 4h | Rector | Campus Manager (notified on breach) |
| Maintenance  | 24h         | R&M Supervisor | Campus Manager |
| Housekeeping | 12h         | HK Supervisor  | Campus Manager |
| Laundry      | 48h         | Laundry Manager | Campus Manager |
| Sports Booking issues | 24h | Sports Manager | Campus Manager |
| Room Change / General Dispute | 24h | Campus Manager | Rector |

SLA timers start at ticket creation and pause only when marked “In Progress.” Missed SLAs trigger alert + dashboard counter.

### 4.8 Laundry (add‑on)
- Create job (counts sliders + weight); statuses: Requested → Processing → Ready → Completed.  
- **Manual Verify** on hand‑over (note required; no QR).

### 4.9 Sports (add‑on)
- Global hours **06:00–22:00**; **60‑min slots**; **1 active upcoming** booking per student; book up to **7 days** ahead; cancel ≤ 1h; **auto no‑show +15m**; blockouts by Sports Manager.

### 4.10 Notices
- Target by hostel + audience (Students/Staff/Both); schedule start/end; rich text + one attachment; Push + Email; no read receipts.

### 4.11 Payments (Simplified Tracking)
- **Payment status tracked per student** during admission/import for entry purposes only.
- Fields: `hostel_fee_paid` (boolean), `payment_mode` (cash/upi/card/bank/cheque), `payment_amount`, `payment_date`, `payment_reference`, `payment_notes`.
- Campus Manager can update payment status via Student management interface (Filament).
- Import via Students CSV with optional payment columns: `hostel_fee_paid`, `payment_mode`, `payment_amount`, `payment_date`, `payment_reference`.
- Student list displays fee payment status with filters (Paid/Unpaid).
- **No online payments, no Razorpay integration, no payment requests, no reminders.**
- Settlements and receipts handled offline by college administration.
- Manual edits to payment status trigger step‑up OTP for the acting Campus Manager and write an audit entry.

### 4.12 Communications
- Notification channels in scope: push (FCM/APNs), SMS (MSG91), email (SendGrid). No opt-out controls in v1; recipients follow audience rules.
- Templates remain ad hoc for MVP; Product team signs off changes outside the repository.
- Visitor notifications do not send automatically; Campus Manager or Guard triggers manual communication if needed.

---

## 5. Non‑Functional Requirements
- **Performance:** API p95 ≤ **500ms** GET / ≤ **800ms** POST/PATCH; mobile TTF content ≤ **1.5s**; RN cold start ≤ **3s**.  
- **Availability:** **99.5%** monthly.  
- **Security:** MASVS baseline; **step‑up OTP** for tenant activation/rollback, Rector approvals, PII exports, and manual payment reconciliation edits; one active device; static QR; S3 presigned uploads (AV scan, EXIF strip).  
- **Privacy:** Encrypt guardian contacts, medical notes, student addresses, and staff personal numbers; Tap‑to‑reveal PII/Medical (audited); exports restricted; retention enforced.  
- **Observability:** Health checks must pass for app HTTP 200, DB, Redis, queue worker, and S3; alerts fire for failed jobs and SLA breaches.  
- **Accessibility:** English (en‑IN), labels, dynamic font sizes, contrast‑safe palette.  
- **Localization/Time:** IST; English-only for v1.  
- **Platforms:** Android ≥ **8.0**; iOS ≥ **14**.

## 5.1 Authentication Model
- **Super Admin (web/admin):** Email + password; password reset via email.
- **All other roles (web + staff app):** Phone number + OTP only (no passwords).
- **OTP defaults:** 6 digits, 10‑min TTL, 3 attempts, resend cooldown 60s, max 5/day; stored hashed; delivery logged.

---

## 6. Dashboards & Analytics (Chart.js)
**Rector/College Mgmt default cards** (read‑only): Occupancy %, Beds available, Approvals median (7d), Pending today, Auto‑expired, Late returns (today/7d), Tickets backlog & aging, Checklists on‑time % (7d), Attendance coverage % (last session), Sports utilization % (7d).

**Campus Manager** sees above + Pending approvals list, SLA breach count, incidents list, CSV exports, and a **Reminder Center** widget summarizing room-change SLA breaches and checklist reminder queues (morning / afternoon / overdue) with next due timestamps.

CSV exports of occupancy/student data trigger step‑up OTP prior to download; audit log stores OTP session ID and export metadata.

Event catalog & formulas — see OpsAnalytics_v1.0.md.

---

## 7. Acceptance Criteria (module highlights)
**Onboarding**  
- Creating tenant via wizard (single DB; 1 tenant=1 campus=multiple hostels) succeeds and is audited.  
- Activate blocked until: roles present (N/A allowed), contacts set, ≥1 hostel with curfew, rooms/beds generated, CM tenant-scope.
- Save Draft + Resume works (no expiry). Create/Activate idempotent with 24h dedupe.
- Activation/rollback cannot proceed without successful step‑up OTP verification (audited).
- Notifications delivered to assigned staff, Rector, College Mgmt; resend available if delivery failed.
- Post-activation: structural edits locked; adding new hostels disabled.

**Imports**  
- Dry‑run catches 100% structural errors; Commit requires **0 critical**.  
- Duplicate `student_uid` rejected; invalid bed keys rejected; error CSV downloadable.  
- **Activate N** triggers Welcome SMS once per student (rate‑limited).

**Rooms & Allocation**  
- Enforce gender mode; one active allocation per student; reallocation is atomic; bed states respected.

**Out‑Pass/Leave**  
- Rector decisions propagate within 1s (client feedback); Pending auto‑expires at 24h; student can cancel while Pending; Emergency Exit convertible within 24h.

**Gate**  
- Online exits/entries recorded; offline queue persists till sync; **offline exit requires cached Approved ≤24h**; otherwise Emergency Exit path available.  
- Visitor allow/deny strictly by visiting hours.

**Attendance**  
- Session auto‑opens/closes on schedule; Leave rows locked/read‑only; Unmarked retained; **Missed Attendance** incident auto‑created; Warden can close with note; Campus Manager can edit marks post‑close.

**Tickets & Checklists**  
- Ticket lifecycle transitions valid per role; photos upload via presigned URLs; Supervisor SLA visible.  
- Daily checklists auto‑created; reminders sent; manager can Approve/Send‑back.

**Laundry**  
- Ready triggers push to student; handover requires Manual Verify note; Completed hides from active list.

**Sports**  
- Booking conflicts prevented; cancel ≤1h; no‑show at +15m; blockouts prevent creation.

**Notices**  
- Scheduled notices display only within window; Push+Email delivered; attachment accessible via presigned URL.

**Payments**  
- Campus Manager can mark student fee payment status; payment details recorded (mode, amount, date, reference); filterable by payment status in student list.

---

## 8. Dependencies
- **Infra:** AWS (EC2+ALB, RDS, Redis, S3).  
- **Integrations:** MSG91 (DLT header **MAPHST**), FCM/APNs, SendGrid.  
- **Frameworks:** Laravel 11, Filament v3, Livewire 3; React Native; Chart.js; Spatie packages.

---

## 9. Risks & Mitigations
- **Rector load:** Bulk approve/decline + filters; dashboards highlight backlog.  
- **Offline gate abuse:** Cached‑approval‑only exits; Emergency Exit audited; reconcile diffs on sync.  
- **CSV data quality:** Dry‑run; error CSV; 48h correction SLA; import retention 7d.  
- **PII exposure:** Tap‑to‑reveal + screenshot block; audit; restricted exports.

---

## 10. Change Control
- No runtime feature flags in v1; all modules (Security, Sports, Laundry, SMS) deploy globally.
- Overnight Out‑Pass policy remains configurable per hostel via settings, not feature flags.
- Any policy change requires PRD delta + version bump.

---

## 11. Open Decisions Register (current status)
- **OD‑02** Naming convention: **CONFIRMED.**  
- **OD‑03** Import cadence & correction SLA: **Ad‑hoc + 48h** (locked).  
- **OD‑11** Sports per‑facility overrides: **Deferred to v1.x** (global defaults now).  
- **OD‑12** Guest pre‑reg fields: **Name + Phone + Person‑to‑meet** (locked).  
- **OD‑13** Rector approval timeout/escalation: **Auto‑expire at 24h** (locked).  
- **New** Maker‑checker for Mark‑as‑Paid: **Not required in v1**; revisit in v1.x if misuse observed.
- **OD‑14** Step‑up OTP scope (activation/rollback/exports): **Locked for v1**.  
- **OD‑15** Offline queue strategy: **No background queue; show retry message**.  
- **OD‑16** Pilot/demo tenant: **St. Xavier’s College** (MAP-STXAV).

---

## 12. Glossary
- **Tenant:** A college account owning campuses/hostels.  
- **Campus Manager:** Admin for campus/hostels; imports, allocation, settings.  
- **Rector:** Approves all Out‑Pass/Leave; insights.  
- **Warden:** Attendance & roster; ticket monitoring.  
- **Add‑on:** Optional module enabled per tenant (Security/Sports/Laundry).  
- **Emergency Exit:** Gate exit without prior approval; audited; convertible by Rector within 24h.

---

## 13. Release Notes (v1.1 docs)
- Expanded import specs; clearer acceptance criteria; added dashboards KPIs; codified default visiting hours and curfew window math; clarified offline gate rules and emergency path; enumerated feature flags; formalized risks & mitigations.

