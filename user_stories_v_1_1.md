# UserStories_v1.1.md

**Release:** MAP‑HMS v1.0  
**Date:** 26‑Sep‑2025 (IST)  
**Traceability:** Stories map to PRD_v1.1.md; API_Spec_v1.1.yaml; SecurityPlan_MASVS_v1.0.md; TestStrategy_v1.0.md.  
**Notation:** AC (acceptance criteria), NFR (non‑functional gates), Perm (permissions/tenancy), Edge/Neg (edge or negative cases).  

---

## 0) Global
**US-G-01 — OTP Login & Step‑up**
- **AC:** OTP send capped; verify ≤ 5 min; 5 wrong → 15‑min lock; one active device; step‑up required for Rector approvals, tenant activation/rollback, PII exports, CSV exports, and manual payment edits.  
- **NFR:** p95 send/verify ≤ 800ms; delivery ≥95% in 10 min.  
- **Edge/Neg:** Expired OTP; clock skew; multiple resend; airplane mode.  

**US-G-02 — Tenant Scoping**
- **AC:** All list/detail endpoints return only tenant‑scoped rows; cross‑tenant attempts return `E_FORBIDDEN_SCOPE`.
- **Test:** synthetic different‑tenant fixtures.

**US-G-03 — Mobile App Binary Split**
- **Student App (Bundle: com.mapmars.hmsstudent):** Authenticates only `Student` role; delivers self-service workflows (out-pass, payments, notices, sports booking, tickets).
  - **AC:** Login/OTP with non-Student role → display `UnsupportedRoleScreen` with guidance to download Staff App → force logout.
  - **AC:** Bootstrap with cached non-Student token → clear auth, show `UnsupportedRoleScreen`.
  - **AC:** Audit logs record `device_name: student-app` for all auth events.
- **Staff App (Bundle: com.mapmars.hmsstaff):** Authenticates operational roles (Warden, Guard, HK/RM Supervisors, Laundry Manager, Sports Manager, Rector companion, SuperAdmin). Role-specific navigator displayed post-login; no student flows bundled.
  - **AC:** Login/OTP with Student role → display `UnsupportedRoleScreen` with guidance to download Student App → force logout.
  - **AC:** Bootstrap with cached Student token → clear auth, show `UnsupportedRoleScreen`.
  - **AC:** Audit logs record `device_name: staff-app` for all auth events.
- **NFR:** Both apps distributed as separate binaries on app stores; build scripts support `APP_VARIANT=student|staff` env var; Expo config generates distinct bundle IDs, app names, and icons per variant.

**US-G-04 — Demo/UAT Seed Tenant**
- **AC:** Repository ships with seed tenant `MAP-STXAV` (St. Xavier’s); running DemoTenantSeeder provisions baseline data for every module used in UAT.
- **Edge/Neg:** Seeder rerun remains idempotent; missing tenant → command fails with actionable error.

---

## 1) Super Admin
**US-SA-01 — Onboarding Wizard**
- **AC:** Create Tenant→Campus→Hostel; curfew/visiting defaults; staff provision; integrations; import templates; pre‑flight must be ✅ with step‑up OTP to Activate.  
- **Edge/Neg:** Duplicate codes; missing Rector or Campus Manager → Launch disabled; activation/rollback after 24h → blocked.  

---

## 2) Campus Manager (Web)
**US-CM-01 — Students Import (CSV)**
- **AC:** Dry‑run validates all rows; errors downloadable; commit only with 0 critical errors; Activate N → Welcome SMS once.  
- **Edge/Neg:** Duplicate `student_uid`; invalid phone; bad CSV; upload > size cap.

**US-CM-02 — Room Allotments Import**
- **AC:** Split fields per convention; rejects unknown bed or duplicate active; gender mode enforced; error CSV.  
- **Edge/Neg:** Effective date in past/future; overlapping allocations.

**US-CM-03 — Room Inventory & Beds**
- **AC:** CRUD with unique `(block,floor,room_no)`; bed `Available/Occupied/Blocked`; block hides from allocation.  
- **Edge/Neg:** Delete room with occupied beds → blocked.

**US-CM-04 — Allocation & Reallocation**
- **AC:** Assign bed atomically; one active bed per student; reallocation frees previous; audit entry.  
- **Edge/Neg:** Concurrent assigns → 409 Conflict.

**US-CM-05 — Room Change Approval**
- **AC:** Approve/Reject; approved triggers reallocation; unresolved >24h auto-escalates to Rector dashboard.  
- **Edge/Neg:** Bed becomes unavailable mid‑flow.

**US-CM-06 — Attendance Edit After Close**
- **AC:** Edit mark up to 7 days after session; reason required; audit.  
- **Edge/Neg:** Edit Leave row (locked) → forbidden.

**US-CM-07 — Notices**
- **AC:** Create/schedule/expire; Push+Email.  
- **Edge/Neg:** Attachment type invalid → 415.

**US-CM-08 — Mark‑as‑Paid**
- **AC:** Evidence reference + note required; step‑up OTP must be verified within 10 min; audit trail stores OTP session ID.  
- **Edge/Neg:** Attempt edit without recent OTP → `E_STEPUP_REQUIRED`; duplicate reference → 409.

**US-CM-09 — Exports**
- **AC:** Request CSV; step‑up OTP required before job enqueues; link valid 15 min; file purges 7d.  
- **Edge/Neg:** Filters invalid → 400.

**US-CM-10 — Dispute Resolution**
- **AC:** Student can flag allocation/billing dispute; Campus Manager must respond within 24h; unresolved tickets auto-escalate to Rector with SLA badge.
- **Edge/Neg:** Attempt to close without resolution note → blocked; SLA breach triggers alert feed.

---

## 3) Rector (Web+Mobile)
**US-REC-01 — Approvals Inbox**
- **AC:** Filters; bulk actions; step‑up OTP; Pending auto‑expire 24h; convert Emergency Exit→Approved Leave within 24h.  
- **Edge/Neg:** Approve after expiry → 409; step‑up missing → `E_STEPUP_REQUIRED`.

**US-REC-02 — Insights**
- **AC:** Read-only dashboards; tap‑to‑reveal PII/Medical with audit trail.  
- **Edge/Neg:** Screenshot attempt blocked.

---

## 4) Staff App — Warden
**US-WAR-01 — Attendance Session & Marking**
- **AC:** Auto window (curfew−1h to curfew+2h); room cards; Present/Absent chips; Leave read‑only; Unmarked tracked; Submit Room when all marked; show “Connection lost. Retry.” when offline (no queue).  
- **Edge/Neg:** Attempt to mark while offline → blocked with retry; session closed → writes rejected.

**US-WAR-02 — Roster & PII**
- **AC:** View roster; tap‑to‑reveal phone/guardian; audit.  
- **Edge/Neg:** Unassigned student in hostel list.

---

## 5) Staff App — Guard
**US-GRD-01 — Verify & Exit/Entry**
- **AC:** Scan QR or search approved list; OTP fallback; Exit requires Approved; Entry always allowed; Emergency Exit with note when approvals missing; app is online-only and displays retry banner when network absent.  
- **Edge/Neg:** QR belongs to other tenant → reject; offline attempt (non-emergency) → block with instruction to Retry or log Emergency Exit.

**US-GRD-02 — Visitor Window**
- **AC:** Allow/deny strictly by visiting hours; log decision.  
- **Edge/Neg:** Attempt outside window → auto‑deny; missing pre‑reg → allow only if policy permits (v1: allow with log).

**US-GRD-03 — Security Incident**
- **AC:** Create simple incident with photos and note.  
- **Edge/Neg:** Photo upload fails → allow text‑only submit.

---

## 6) Staff App — Supervisors (HK/RM)
**US-SUP-01 — Daily Checklists**
- **AC:** Auto instances per role/shift; reminders T−60/T−15; submit; manager approve/send‑back; connectivity required (shows Retry banner when offline).  
- **Edge/Neg:** Submit after due → late flag; offline attempt → blocked.

**US-SUP-02 — Tickets Lifecycle**
- **AC:** Create/assign/self‑assign; Open→In‑Progress→Resolved→Closed; photos/comments; parts cost note (RM).  
- **Edge/Neg:** Close without Resolve → forbidden.

---

## 7) Staff App — Laundry Manager
**US-LAU-01 — Create/Process/Handover**
- **AC:** Fast student search; set counts & weight; Ready sends push; **Manual Verify** on handover with note; Completed removes from active list.  
- **Edge/Neg:** Negative counts/weight → blocked.

---

## 8) Staff App — Sports Manager
**US-SPM-01 — Blockouts**
- **AC:** Create blockouts; bookings during window are prevented.  
- **Edge/Neg:** Overlapping blockouts merged.

**US-SPM-02 — Monitor Student Slots**
- **AC:** View slot occupancy and waitlists; receive alerts for no-shows > 15m; cannot create bookings.
- **Edge/Neg:** Attempt to override booking → forbidden (web workflow only).

---

## 9) Student App — Student
**US-STU-01 — Out‑Pass/Leave**
- **AC:** Submit with reason & overnight; cancel while Pending; see decisions; static QR visible.  
- **Edge/Neg:** Duplicate Pending → merged UI; invalid time → 400.

**US-STU-02 — Tickets**
- **AC:** Create with up to 3 photos; track status; comment.  
- **Edge/Neg:** Upload fails → prompt user to retry after connectivity restored (no offline queue).

**US-STU-03 — Room Change**
- **AC:** Submit request; CM approves & reallocates.  
- **Edge/Neg:** No free bed → auto‑reject with message.

**US-STU-04 — Notices**
- **AC:** List & read; scheduled visibility only; attachment opens via presigned URL.  
- **Edge/Neg:** Expired → hidden.

**US-STU-05 — Payments (Manual Tracking)**
- **AC:** View hostel fee payment status (Paid/Unpaid) with reference note; contact Campus Manager if unpaid; no online payment capability shown.  
- **Edge/Neg:** Attempt to initiate payment → show info dialog “Payments are handled offline”.

**US-STU-06 — Sports Booking**
- **AC:** Hold one active upcoming slot; browse availability up to 7 days; cancel ≤1h; connection required for booking actions; receives push for confirmations/no-shows.
- **Edge/Neg:** Attempt second concurrent booking → 409; cancel after slot start → converts to no-show.

---

## 10) Rector (Web + Mobile Companion)

**US-RECT-001: Consolidated Approval Dashboard**
- **As a** Rector
- **I want to** see all pending approvals (Out-Pass, Leave, Sick Leave) in one place
- **So that** I can efficiently review and decide on student requests
- **AC:** Rector sees "Approvals" navigation group with 3 sub-tabs; each tab shows pending requests with SLA countdown; approve/reject actions available with quick templates; step-up OTP required before approval
- **Perm:** Rector role only

**US-RECT-002: SLA Notifications**
- **As a** Rector
- **I want to** receive alerts when approvals are nearing or have breached SLA
- **So that** I can prioritize urgent requests and maintain the 2h/4h SLA
- **AC:** At 75% of SLA time: Rector receives "Expiring Soon" push notification; at 100% (breach): Rector receives push + SMS; Campus Manager also notified; hourly reminders continue until resolved
- **NFR:** Notifications delivered within 1 minute of SLA trigger
- **Edge/Neg:** Offline Rector receives notifications when back online

**US-RECT-003: Approval History**
- **As a** Rector
- **I want to** view all my past approval decisions
- **So that** I can review my decision history and audit trails
- **AC:** "History" tab shows all past approvals/rejections; filters: date range, request type, decision status; export to CSV available
- **Perm:** Rector role only

**US-RECT-004: Monthly Reports**
- **As a** Rector
- **I want to** download monthly approval reports
- **So that** I can share performance metrics with management
- **AC:** "Download Report" action available on Dashboard; selectable: month, year, format (PDF/CSV); report includes: total approvals/rejections, SLA performance, breakdown by request type
- **NFR:** Reports generated within 30 seconds; PDF/CSV formats properly formatted
- **Perm:** Rector role only; requires step-up OTP for CSV exports

---

## 11) Non‑Functional User Stories (NFR)
**US-NFR-01 — Performance & Availability**
- **AC:** API p95 ≤ 500/800ms (R/W); uptime 99.5% monthly; dashboards render ≤ 1.5s.  

**US-NFR-02 — Security & Privacy**
- **AC:** Step‑up OTP for sensitive actions; PII reveal audit; screenshots blocked on PII/OTP screens; S3 uploads AV scanned; webhooks HMAC verified.

**US-NFR-03 — Observability**
- **AC:** Sentry error budgets enforced; product events recorded; exports logged; Horizon queue dashboards green; `/v1/central-healthz` + tenant `/v1/healthz` monitored with alerts on failure.

