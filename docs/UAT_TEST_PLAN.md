# UAT Test Plan - MAP HMS Staff App
**Release:** MAP-HMS v1.0
**Date:** January 2026
**Status:** Ready for UAT
**Test Environment:** Staging (staging.mapservices.in)
**Demo Tenant:** MAP-STXAV (St. Xavier's College)

---

## Table of Contents
1. [UAT Overview](#uat-overview)
2. [Test Roles & Credentials](#test-roles--credentials)
3. [Global Test Cases](#global-test-cases)
4. [Role-Specific Test Suites](#role-specific-test-suites)
   - [Campus Manager](#campus-manager)
   - [Rector](#rector)
   - [Warden](#warden)
   - [Security Guard](#security-guard)
   - [HK Supervisor](#hk-supervisor)
   - [RM Supervisor](#rm-supervisor)
   - [Laundry Manager](#laundry-manager)
   - [Sports Manager](#sports-manager)
5. [Integration Test Scenarios](#integration-test-scenarios)
6. [Non-Functional Test Cases](#non-functional-test-cases)
7. [Bug Reporting Template](#bug-reporting-template)

---

## UAT Overview

### Objectives
- Validate all 8 staff roles function as per PRD v1.1
- Verify end-to-end workflows across Student App and Staff App
- Ensure tenant isolation and data security
- Confirm mobile app usability and offline capabilities
- Validate API performance and error handling

### Scope
- **In Scope:** All staff app features, workflows, and integrations for 8 roles
- **Out of Scope:** Super Admin onboarding wizard, web-only features, payment gateway integration

### Testing Approach
- **Manual Testing:** Primary approach for UAT
- **Exploratory Testing:** Encouraged for edge cases
- **Regression Testing:** Critical workflows after bug fixes
- **Performance Testing:** Basic response time validation

### Test Data
- Use MAP-STXAV demo tenant seeded data
- Test users with phone numbers ending in 43210-43216
- All test users use OTP: `123456`

### Success Criteria
- ✅ All critical test cases pass (P0/P1)
- ✅ No blocking bugs identified
- ✅ API response times meet NFR (p95 ≤ 500ms read, 800ms write)
- ✅ Offline queue sync accuracy 100% for Guard/Warden
- ✅ App crash rate ≤ 0.5% during UAT

---

## Test Roles & Credentials

### Demo Tenant: MAP-STXAV (St. Xavier's College)
**Environment:** staging.mapservices.in
**Hostels:** Boys Hostel A, Girls Hostel B

| Role | Phone Number | OTP | Hostel Assignment | Access |
|------|--------------|-----|-------------------|--------|
| **Student** | +91 98765 43210 | 123456 | Boys Hostel A, Room 101 | Student App Only |
| **Security Guard** | +91 98765 43211 | 123456 | Boys Hostel A | Staff App |
| **Warden** | +91 98765 43212 | 123456 | Boys Hostel A | Staff App |
| **HK Supervisor** | +91 98765 43213 | 123456 | Boys Hostel A | Staff App |
| **RM Supervisor** | +91 98765 43214 | 123456 | Boys Hostel A | Staff App |
| **Campus Manager** | +91 98765 43215 | 123456 | Tenant-wide (all hostels) | Web + Staff App |
| **Rector** | +91 98765 43216 | 123456 | Tenant-wide (all hostels) | Web + Staff App |
| **Laundry Manager** | +91 98765 43217 | 123456 | Boys Hostel A | Staff App |
| **Sports Manager** | +91 98765 43218 | 123456 | Tenant-wide | Staff App |

### Additional Test Users
- **Student (Female):** +91 98765 43220 (Girls Hostel B, Room 201)
- **Security Guard (Night Shift):** +91 98765 43221 (Boys Hostel A)

---

## Global Test Cases

### US-G-01: OTP Login & Step-up Authentication

#### Test Case: G-01-001 - Staff App Login (Happy Path)
**Priority:** P0 (Critical)
**User Story:** US-G-01
**Role:** All Staff Roles

**Preconditions:**
- Staff App installed on device
- User has valid phone number assigned to staff role

**Test Steps:**
1. Launch Staff App
2. Enter phone number: +91 98765 43211 (Guard)
3. Tap "Send OTP"
4. Wait for OTP SMS
5. Enter OTP: 123456
6. Tap "Verify OTP"

**Expected Results:**
- ✅ OTP SMS received within 10 seconds
- ✅ Login successful
- ✅ Guard Dashboard displayed
- ✅ User name and role shown in greeting
- ✅ College logo displayed
- ✅ Action tiles rendered (Profile, QR Code, Checklist, etc.)

**Acceptance Criteria:**
- OTP send/verify completes in ≤ 800ms (p95)
- Only one active device per user
- Audit log records `device_name: staff-app`

---

#### Test Case: G-01-002 - OTP Expiry (Negative Test)
**Priority:** P1 (High)
**User Story:** US-G-01

**Test Steps:**
1. Launch Staff App
2. Enter phone number and request OTP
3. Wait 6 minutes (OTP expires at 5 minutes)
4. Enter expired OTP
5. Tap "Verify OTP"

**Expected Results:**
- ❌ Login fails with error: "OTP has expired. Please request a new one."
- ✅ "Resend OTP" button enabled
- ✅ User can request new OTP

---

#### Test Case: G-01-003 - Invalid OTP Attempts (Security Test)
**Priority:** P1 (High)
**User Story:** US-G-01

**Test Steps:**
1. Login with phone number
2. Enter wrong OTP 5 times consecutively
3. Attempt 6th login

**Expected Results:**
- ❌ After 5 wrong attempts, account locked for 15 minutes
- ✅ Error message: "Too many failed attempts. Please try again after 15 minutes."
- ✅ Send OTP button disabled for 15 minutes
- ✅ Counter resets after 15 minutes

---

#### Test Case: G-01-004 - Step-Up OTP (Rector Approval)
**Priority:** P0 (Critical)
**User Story:** US-G-01, US-REC-01

**Test Steps:**
1. Login as Rector (+91 98765 43216)
2. Navigate to Approvals → Out-Pass tab
3. Select an outpass request
4. Tap "Approve"
5. Enter approval notes
6. Step-up OTP modal appears
7. Enter OTP: 123456
8. Tap "Confirm Approval"

**Expected Results:**
- ✅ Step-up OTP modal displayed before approval
- ✅ OTP verified within 10 minutes timeout
- ✅ Approval successful
- ✅ Student and Campus Manager notified (Push + SMS)
- ✅ Audit log records OTP session ID

---

### US-G-02: Tenant Scoping

#### Test Case: G-02-001 - Cross-Tenant Data Isolation
**Priority:** P0 (Critical)
**User Story:** US-G-02

**Preconditions:**
- Two tenants exist: MAP-STXAV and MAP-TEST
- Warden assigned to MAP-STXAV

**Test Steps:**
1. Login as Warden (MAP-STXAV tenant)
2. Navigate to Students screen
3. Attempt to view student list
4. Verify all students belong to MAP-STXAV only

**Expected Results:**
- ✅ Only MAP-STXAV students displayed
- ✅ No MAP-TEST students visible
- ✅ API returns tenant-scoped data only

**Test Method:**
- Inspect API response (Developer Tools / Charles Proxy)
- Verify `tenant_id` filter in queries
- Attempt direct API call with different tenant_id (should fail with E_FORBIDDEN_SCOPE)

---

### US-G-03: Mobile App Binary Split

#### Test Case: G-03-001 - Staff Login in Student App (Negative)
**Priority:** P0 (Critical)
**User Story:** US-G-03

**Test Steps:**
1. Install Student App (com.mapmars.hmsstudent)
2. Attempt login with staff phone: +91 98765 43211 (Guard)
3. Enter OTP: 123456
4. Tap "Verify OTP"

**Expected Results:**
- ❌ Login blocked
- ✅ `UnsupportedRoleScreen` displayed with message:
  - "This account is a staff account. Please download the Staff App to continue."
- ✅ Forced logout
- ✅ Audit log records `device_name: student-app` with failed role validation

---

#### Test Case: G-03-002 - Student Login in Staff App (Negative)
**Priority:** P0 (Critical)
**User Story:** US-G-03

**Test Steps:**
1. Install Staff App (com.mapmars.hmsstaff)
2. Attempt login with student phone: +91 98765 43210
3. Enter OTP: 123456
4. Tap "Verify OTP"

**Expected Results:**
- ❌ Login blocked
- ✅ `UnsupportedRoleScreen` displayed with message:
  - "This account is a student account. Please download the Student App to continue."
- ✅ Forced logout
- ✅ Audit log records `device_name: staff-app` with failed role validation

---

#### Test Case: G-03-003 - Bootstrap with Cached Wrong Role Token
**Priority:** P1 (High)
**User Story:** US-G-03

**Test Steps:**
1. Login to Student App successfully
2. Kill app (don't logout)
3. Reinstall as Staff App (simulate app variant change)
4. Launch Staff App

**Expected Results:**
- ✅ Cached student token detected
- ✅ Token cleared automatically
- ✅ `UnsupportedRoleScreen` displayed
- ✅ User redirected to login screen

---

---

## Role-Specific Test Suites

---

## Campus Manager

### US-CM-01: Students Import (CSV)

#### Test Case: CM-01-001 - Successful CSV Import (Happy Path)
**Priority:** P0 (Critical)
**User Story:** US-CM-01
**Platform:** Web Panel

**Preconditions:**
- Login to Campus Manager panel (staging.mapservices.in/campus-manager)
- Valid Students.csv file ready

**Test Steps:**
1. Navigate to Students → Import
2. Upload valid Students.csv:
   ```csv
   student_uid,name,phone,gender,dob,roll_no,program,year_of_study,admission_year,guardian_phone,email
   STU001,John Doe,9876543230,Male,2005-01-15,2024001,B.Tech,1,2024,9876543240,john@example.com
   ```
3. Click "Dry Run"
4. Review validation results
5. Click "Commit Import"
6. Select 1 student to activate
7. Click "Activate Students"

**Expected Results:**
- ✅ Dry run validates all rows successfully (0 errors)
- ✅ Commit imports student into database
- ✅ Activate sends Welcome SMS to student phone
- ✅ Student can now login to Student App
- ✅ Import history logged with timestamp

---

#### Test Case: CM-01-002 - CSV Import with Errors (Negative)
**Priority:** P1 (High)
**User Story:** US-CM-01

**Test Steps:**
1. Upload Students.csv with errors:
   ```csv
   student_uid,name,phone,gender,dob
   STU001,John Doe,invalid_phone,Male,2005-01-15
   STU002,Jane Doe,9876543232,Invalid,2006-02-20
   STU001,Duplicate UID,9876543233,Female,2005-03-10
   ```
2. Click "Dry Run"

**Expected Results:**
- ❌ Dry run fails with errors
- ✅ Errors downloadable as `errors.csv`
- ✅ Error messages clear:
  - Row 1: "Invalid phone number format"
  - Row 2: "Gender must be Male/Female"
  - Row 3: "Duplicate student_uid: STU001"
- ✅ Commit button disabled until errors resolved

---

### US-CM-02: Room Allotments Import

#### Test Case: CM-02-001 - Room Allotment CSV Import
**Priority:** P0 (Critical)
**User Story:** US-CM-02

**Test Steps:**
1. Navigate to Room Allotments → Import
2. Upload RoomAllotments.csv:
   ```csv
   student_uid,hostel_code,block_code,floor_code,room_no,bed_code,effective_from
   STU001,BHA,A,1,101,A,2026-01-15
   STU002,BHA,A,1,101,B,2026-01-15
   ```
3. Click "Dry Run"
4. Review validation
5. Click "Commit"

**Expected Results:**
- ✅ Validates hostel_code, block, floor, room_no exist
- ✅ Validates bed_code (A/B/C/D)
- ✅ Rejects unknown beds
- ✅ Rejects duplicate active allocations
- ✅ Gender mode enforced (male students to boys hostel only)
- ✅ Beds marked as "Occupied" after import

---

#### Test Case: CM-02-002 - Gender Mode Violation (Negative)
**Priority:** P1 (High)
**User Story:** US-CM-02

**Test Steps:**
1. Upload RoomAllotments.csv assigning male student to Girls Hostel:
   ```csv
   student_uid,hostel_code,block_code,floor_code,room_no,bed_code,effective_from
   STU001,GHB,A,1,201,A,2026-01-15
   ```
   (STU001 is male, GHB is Girls Hostel B)
2. Run Dry Run

**Expected Results:**
- ❌ Validation error: "Gender mismatch: Male student cannot be assigned to Girls hostel"
- ✅ Import blocked until corrected

---

### US-CM-05: Room Change Approval

#### Test Case: CM-05-001 - Approve Room Change Request
**Priority:** P0 (Critical)
**User Story:** US-CM-05

**Preconditions:**
- Student has submitted room change request (via Student App)

**Test Steps:**
1. Login to Campus Manager panel
2. Navigate to Requests → Room Change
3. View pending request from student STU001
4. Review reason: "Current roommate is noisy"
5. Click "Approve"
6. Select new bed: Room 102, Bed A
7. Enter approval note
8. Click "Confirm Approval"

**Expected Results:**
- ✅ Request status changes to "Approved"
- ✅ Student automatically reallocated to new bed
- ✅ Previous bed (101-A) freed and marked "Available"
- ✅ New bed (102-A) marked "Occupied"
- ✅ Student receives push notification: "Room change approved"
- ✅ Audit log created with timestamp and approver

---

#### Test Case: CM-05-002 - SLA Breach Auto-Escalation
**Priority:** P1 (High)
**User Story:** US-CM-05

**Test Steps:**
1. Student submits room change request
2. Wait 25 hours without approval
3. Check Rector dashboard

**Expected Results:**
- ✅ After 24h SLA breach, request auto-escalates to Rector
- ✅ Rector sees SLA badge on request
- ✅ Campus Manager receives SMS + Push reminder every 2h
- ✅ Escalation logged in audit trail

---

### US-CM-06: Attendance Edit After Close

#### Test Case: CM-06-001 - Edit Attendance Within 7 Days
**Priority:** P1 (High)
**User Story:** US-CM-06

**Preconditions:**
- Attendance session closed 2 days ago
- Student STU001 marked "Absent"

**Test Steps:**
1. Navigate to Attendance → History
2. Select session from 2 days ago
3. Find student STU001
4. Click "Edit Mark"
5. Change from "Absent" to "Present"
6. Enter reason: "Student was present, marking error"
7. Click "Save"

**Expected Results:**
- ✅ Mark changed to "Present"
- ✅ Reason required and saved
- ✅ Edit logged in audit trail with:
  - Who edited (Campus Manager name)
  - When edited (timestamp)
  - Old value (Absent) → New value (Present)
  - Reason for edit

---

#### Test Case: CM-06-002 - Cannot Edit Leave Mark (Locked)
**Priority:** P2 (Medium)
**User Story:** US-CM-06

**Test Steps:**
1. Navigate to closed attendance session
2. Find student with "Leave" mark (auto-derived from approved pass)
3. Attempt to click "Edit Mark"

**Expected Results:**
- ❌ Edit button disabled for "Leave" marks
- ✅ Tooltip: "Leave marks are auto-derived from approved passes and cannot be edited"

---

### US-CM-09: Exports with Step-Up OTP

#### Test Case: CM-09-001 - Export Students CSV
**Priority:** P0 (Critical)
**User Story:** US-CM-09

**Test Steps:**
1. Navigate to Students → Export
2. Select filters: Hostel = Boys Hostel A, Status = Active
3. Click "Export CSV"
4. Step-up OTP modal appears
5. Enter OTP: 123456
6. Click "Confirm Export"
7. Wait for job to complete
8. Click download link in notification

**Expected Results:**
- ✅ Step-up OTP required before export enqueues
- ✅ Export job queued in background
- ✅ Notification sent when ready (within 30 seconds)
- ✅ Download link valid for 15 minutes
- ✅ CSV file contains correct filtered data
- ✅ File purged after 7 days
- ✅ Audit trail logs export with OTP session ID

---

---

## Rector

### US-REC-01: Approvals Inbox

#### Test Case: REC-01-001 - Approve Out-Pass with Step-Up OTP
**Priority:** P0 (Critical)
**User Story:** US-REC-01

**Preconditions:**
- Student has submitted out-pass request
- Request status: Pending

**Test Steps:**
1. Login to Rector mobile app (+91 98765 43216)
2. Navigate to Dashboard
3. Tap "Outpass" tile (badge shows pending count)
4. View out-pass request:
   - Student: John Doe
   - Reason: Family function
   - Date: Tomorrow, 10:00 - 18:00
   - Status: Pending
5. Tap "Approve"
6. Select quick template: "Approved as requested"
7. Add note (optional)
8. Step-up OTP modal appears
9. Enter OTP: 123456
10. Tap "Confirm Approval"

**Expected Results:**
- ✅ Step-up OTP required before approval
- ✅ OTP verified within 10 min timeout
- ✅ Request status changes to "Approved"
- ✅ Student receives push notification + SMS
- ✅ Campus Manager receives email notification
- ✅ Rector sees updated badge count (-1)
- ✅ Approved pass visible in Gate Pass list

---

#### Test Case: REC-01-002 - Reject Leave Request
**Priority:** P0 (Critical)
**User Story:** US-REC-01

**Test Steps:**
1. Navigate to Approvals → Leave tab
2. View leave request:
   - Student: Jane Doe
   - Type: Leave
   - Dates: Jan 20-22, 2026
   - Reason: Attend cousin's wedding
3. Tap "Reject"
4. Enter rejection note: "Exams scheduled during this period"
5. Step-up OTP verification
6. Confirm rejection

**Expected Results:**
- ✅ Request status changes to "Rejected"
- ✅ Student receives push + SMS with rejection reason
- ✅ Campus Manager notified
- ✅ Request moved to History tab

---

#### Test Case: REC-01-003 - Bulk Approve Out-Passes
**Priority:** P1 (High)
**User Story:** US-REC-01

**Test Steps:**
1. Navigate to Approvals → Out-Pass tab
2. Select 5 pending requests using checkboxes
3. Tap "Bulk Approve"
4. Confirm bulk action
5. Step-up OTP verification
6. Tap "Approve All"

**Expected Results:**
- ✅ All 5 requests approved simultaneously
- ✅ Single step-up OTP for bulk action
- ✅ All students notified
- ✅ Bulk action logged in audit trail

---

#### Test Case: REC-01-004 - Pending Request Auto-Expiry
**Priority:** P1 (High)
**User Story:** US-REC-01

**Test Steps:**
1. Student submits out-pass request
2. Rector does not approve/reject
3. Wait 25 hours (24h SLA)
4. Check request status

**Expected Results:**
- ✅ After 24h, request auto-expires
- ✅ Status changes to "Expired"
- ✅ Student notified: "Your out-pass request has expired"
- ✅ Rector receives SLA breach notification

---

#### Test Case: REC-01-005 - Approve After Expiry (Negative)
**Priority:** P2 (Medium)
**User Story:** US-REC-01

**Test Steps:**
1. Wait for request to expire (24h)
2. Attempt to approve expired request
3. Tap "Approve"

**Expected Results:**
- ❌ Approval fails with error code 409
- ✅ Error message: "Cannot approve expired request"
- ✅ Request locked in "Expired" state

---

#### Test Case: REC-01-006 - Convert Emergency Exit to Approved Leave
**Priority:** P1 (High)
**User Story:** US-REC-01

**Preconditions:**
- Guard created Emergency Exit for student (no approved pass)

**Test Steps:**
1. Navigate to Approvals → Leave tab
2. Filter: Emergency Exits
3. View emergency exit record:
   - Student: John Doe
   - Guard note: "Medical emergency - took student to hospital"
   - Time: Yesterday 14:30
4. Tap "Convert to Approved Leave"
5. Enter leave dates: Yesterday - Today
6. Add rector note: "Emergency approved retroactively"
7. Step-up OTP verification
8. Confirm conversion

**Expected Results:**
- ✅ Emergency exit converted to Approved Leave
- ✅ Student sees approved leave in gate pass list
- ✅ Campus Manager notified
- ✅ Conversion must happen within 24h of emergency exit

---

### US-RECT-002: SLA Notifications

#### Test Case: RECT-02-001 - SLA Warning at 75%
**Priority:** P0 (Critical)
**User Story:** US-RECT-002

**Test Steps:**
1. Student submits out-pass (2h SLA)
2. Wait 1.5 hours (75% of 2h)
3. Check Rector notifications

**Expected Results:**
- ✅ At 1.5h mark, Rector receives push notification:
  - "Approval Expiring Soon: Out-pass from John Doe expires in 30 minutes"
- ✅ SLA badge on request turns yellow (warning)
- ✅ Notification delivered within 1 minute

---

#### Test Case: RECT-02-002 - SLA Breach at 100%
**Priority:** P0 (Critical)
**User Story:** US-RECT-002

**Test Steps:**
1. Student submits leave request (4h SLA)
2. Wait 4 hours without approval
3. Check notifications

**Expected Results:**
- ✅ At 4h mark, Rector receives:
  - Push notification: "SLA Breached: Leave request from Jane Doe"
  - SMS notification
- ✅ Campus Manager also receives notification
- ✅ SLA badge turns red
- ✅ Hourly reminders sent until resolved

---

### US-RECT-003: Approval History

#### Test Case: RECT-03-001 - View Approval History
**Priority:** P1 (High)
**User Story:** US-RECT-003

**Test Steps:**
1. Navigate to Approvals → History tab
2. View past approvals list

**Expected Results:**
- ✅ All past decisions displayed (approved + rejected)
- ✅ Each entry shows:
  - Type (Out-Pass / Leave / Sick Leave)
  - Student name
  - Decision (Approved / Rejected)
  - Date and time
  - Notes entered by Rector
- ✅ Most recent at top (descending order)

---

#### Test Case: RECT-03-002 - Filter Approval History
**Priority:** P2 (Medium)
**User Story:** US-RECT-003

**Test Steps:**
1. Navigate to History tab
2. Apply filters:
   - Date range: Last 7 days
   - Request type: Out-Pass
   - Decision: Approved
3. View filtered results

**Expected Results:**
- ✅ Only approved out-passes from last 7 days shown
- ✅ Filter count badge updated
- ✅ Export filtered results to CSV

---

### US-RECT-004: Monthly Reports

#### Test Case: RECT-04-001 - Download Monthly Report (PDF)
**Priority:** P1 (High)
**User Story:** US-RECT-004

**Test Steps:**
1. Navigate to Dashboard
2. Tap "Download Report" button
3. Select month: December 2025
4. Select format: PDF
5. Tap "Generate Report"

**Expected Results:**
- ✅ Report generated within 30 seconds
- ✅ PDF download link provided
- ✅ Report includes:
  - Total approvals (count)
  - Total rejections (count)
  - SLA performance (% on-time)
  - Breakdown by type (Out-Pass, Leave, Sick Leave)
  - Chart/graph of trends
- ✅ Professional PDF formatting

---

#### Test Case: RECT-04-002 - Download Monthly Report (CSV)
**Priority:** P1 (High)
**User Story:** US-RECT-004

**Test Steps:**
1. Tap "Download Report"
2. Select month: January 2026
3. Select format: CSV
4. Step-up OTP modal appears (required for CSV exports)
5. Enter OTP: 123456
6. Tap "Generate Report"

**Expected Results:**
- ✅ Step-up OTP required for CSV export
- ✅ CSV file generated with columns:
  - Date, Student, Request Type, Decision, SLA Status, Notes
- ✅ Import-friendly format for Excel/Sheets

---

### US-REC-02: Insights & Tap-to-Reveal PII

#### Test Case: REC-02-001 - View Student Insights
**Priority:** P1 (High)
**User Story:** US-REC-02

**Test Steps:**
1. Navigate to Dashboard
2. Tap "Insights" tile
3. View student insights screen (read-only)

**Expected Results:**
- ✅ Dashboard shows:
  - Total resident students
  - Attendance trends (last 7 days)
  - Top requesters (students with most requests)
  - Late return incidents
- ✅ All data read-only (no edit actions)
- ✅ Tap student name to view profile

---

#### Test Case: REC-02-002 - Tap-to-Reveal PII with Audit
**Priority:** P0 (Critical)
**User Story:** US-REC-02

**Test Steps:**
1. Navigate to Student Insights
2. Tap student "John Doe"
3. View student profile
4. Phone number displayed as: "98***43210" (masked)
5. Tap "Reveal Phone"
6. Verify step-up OTP if required
7. Phone revealed: "9876543210"

**Expected Results:**
- ✅ PII initially masked
- ✅ Tap-to-reveal action triggers step-up OTP
- ✅ Full phone number displayed after verification
- ✅ Audit trail logs:
  - Who revealed (Rector name)
  - What was revealed (Phone number)
  - When (timestamp)
  - Student ID

---

#### Test Case: REC-02-003 - Screenshot Blocked on PII Screen
**Priority:** P0 (Critical - Security)
**User Story:** US-REC-02

**Test Steps:**
1. Navigate to Student Insights with revealed PII
2. Attempt to take screenshot (device screenshot button)

**Expected Results:**
- ❌ Screenshot blocked
- ✅ Screen becomes black in screenshot OR
- ✅ Alert shown: "Screenshots are disabled on this screen for security"

---

---

## Warden

### US-WAR-01: Attendance Session & Marking

#### Test Case: WAR-01-001 - Mark Room Attendance (Happy Path)
**Priority:** P0 (Critical)
**User Story:** US-WAR-01

**Preconditions:**
- Curfew time: 22:00 (10 PM)
- Attendance window auto-opens at 21:00 (curfew - 1h)
- Current time: 21:30 (within window)

**Test Steps:**
1. Login as Warden (+91 98765 43212)
2. Navigate to Dashboard
3. Tap "Attendance" tile
4. View Attendance screen:
   - Header shows: "Monday, January 15, 2026"
   - List of rooms with student count badges
5. Tap "Room 101" (4 students)
6. View student list:
   - John Doe - (unmarked)
   - Jane Smith - (unmarked)
   - Bob Wilson - (On Leave - read-only, gray)
   - Alice Brown - (unmarked)
7. Mark students:
   - John Doe → Tap "P" (Present)
   - Jane Smith → Tap "A" (Absent), enter note: "Called parent, confirmed at home"
   - Alice Brown → Tap "P" (Present)
8. Tap "Submit Room"

**Expected Results:**
- ✅ Attendance window open between 21:00-00:00 (curfew - 1h to curfew + 2h)
- ✅ Date and day displayed correctly
- ✅ Room cards show student count
- ✅ "Leave" marks auto-derived from approved passes (read-only, cannot change)
- ✅ Text input appears when "Absent" selected
- ✅ Note required for Absent marks
- ✅ Submit button enabled only when all non-Leave students marked
- ✅ Room submitted successfully
- ✅ Room card shows checkmark badge

---

#### Test Case: WAR-01-002 - Attempt Marking Without Note (Validation)
**Priority:** P1 (High)
**User Story:** US-WAR-01

**Test Steps:**
1. Open Room 102
2. Mark student as "Absent"
3. Leave note field empty
4. Tap "Submit Room"

**Expected Results:**
- ❌ Validation error: "Note required for Absent marks"
- ✅ Submit blocked until note provided
- ✅ Field highlighted in red

---

#### Test Case: WAR-01-003 - Offline Attendance Marking (Online-Only)
**Priority:** P0 (Critical)
**User Story:** US-WAR-01

**Test Steps:**
1. Open Attendance screen
2. Disable device network (airplane mode)
3. Attempt to mark student as Present
4. Tap "Submit Room"

**Expected Results:**
- ❌ Marking blocked
- ✅ Banner displayed: "Connection lost. Retry."
- ✅ No offline queue (attendance is online-only per PRD)
- ✅ Retry button available
- ✅ After reconnecting, can retry submission

---

#### Test Case: WAR-01-004 - Auto-Close Session with Unmarked Students
**Priority:** P1 (High)
**User Story:** US-WAR-01

**Preconditions:**
- Attendance window closes at 00:00 (curfew + 2h)
- Room 103 has 2 unmarked students

**Test Steps:**
1. Allow session to auto-close at 00:00
2. Check Incidents list (next day)

**Expected Results:**
- ✅ Session auto-closes at curfew + 2h (00:00)
- ✅ "Missed Attendance" incident auto-created
- ✅ Incident details:
  - Room: 103
  - Unmarked students: 2 (names listed)
  - Created: Auto-system
- ✅ Warden must close incident with note

---

#### Test Case: WAR-01-005 - Edit After Session Close (Locked)
**Priority:** P2 (Medium)
**User Story:** US-WAR-01

**Test Steps:**
1. After session closes (00:00)
2. Navigate to Attendance → History
3. Select closed session
4. Attempt to edit marks

**Expected Results:**
- ❌ Edit blocked for Warden role
- ✅ Message: "Session closed. Only Campus Manager can edit attendance within 7 days."
- ✅ Marks are read-only

---

### US-WAR-02: Roster & Tap-to-Reveal PII

#### Test Case: WAR-02-001 - View Student Roster
**Priority:** P1 (High)
**User Story:** US-WAR-02

**Test Steps:**
1. Navigate to Dashboard
2. Tap "Students" tile
3. View student list

**Expected Results:**
- ✅ All students in warden's assigned hostel displayed
- ✅ Student info shown:
  - Name
  - Room number
  - Roll number
  - Photo (if available)
- ✅ Phone initially masked: "98***43210"
- ✅ Search/filter by name or room

---

#### Test Case: WAR-02-002 - Tap-to-Reveal Guardian Phone
**Priority:** P1 (High)
**User Story:** US-WAR-02

**Test Steps:**
1. View Students list
2. Tap student "John Doe"
3. Navigate to "Parent/Guardian Information" tab
4. Guardian phone shown masked: "98***43240"
5. Tap "Reveal Phone"
6. View full number: "9876543240"

**Expected Results:**
- ✅ PII initially masked
- ✅ Tap reveals full number
- ✅ Audit trail logs:
  - Who revealed (Warden name)
  - Student ID
  - Field revealed (Guardian phone)
  - Timestamp

---

---

## Security Guard

### US-GRD-01: Verify & Exit/Entry

#### Test Case: GRD-01-001 - QR Code Scan (Approved Out-Pass)
**Priority:** P0 (Critical)
**User Story:** US-GRD-01

**Preconditions:**
- Student has approved out-pass for today
- Out-pass time: 10:00 - 18:00
- Current time: 10:15

**Test Steps:**
1. Login as Security Guard (+91 98765 43211)
2. Navigate to Dashboard
3. Tap "Scan QR" tab (bottom navigation)
4. Tap "Scan QR Code" button
5. Camera opens
6. Student shows QR code from Student App
7. Scan QR code

**Expected Results:**
- ✅ QR code scanned successfully
- ✅ Student details displayed:
  - Name: John Doe
  - Room: 101
  - Out-pass status: Approved
  - Valid time: 10:00 - 18:00
- ✅ "Allow Exit" button enabled
- ✅ Tap "Allow Exit" → Exit logged
- ✅ Student status: "Checked Out"

---

#### Test Case: GRD-01-002 - Gate Entry (Student Return)
**Priority:** P0 (Critical)
**User Story:** US-GRD-01

**Preconditions:**
- Same student from GRD-01-001 (already checked out)
- Current time: 17:45 (before 18:00 return time)

**Test Steps:**
1. Student returns to hostel
2. Guard scans QR code again
3. View details

**Expected Results:**
- ✅ Student details shown with status: "Checked Out"
- ✅ Return time: Before 18:00 (on-time)
- ✅ "Allow Entry" button enabled (entry always allowed per PRD)
- ✅ Tap "Allow Entry" → Entry logged
- ✅ Out-pass status updated: "Completed"

---

#### Test Case: GRD-01-003 - Late Return Detection
**Priority:** P1 (High)
**User Story:** US-GRD-01

**Preconditions:**
- Student out-pass valid until 18:00
- Student returns at 19:30 (1.5h late)

**Test Steps:**
1. Scan student QR code at 19:30
2. View entry screen

**Expected Results:**
- ✅ Late return detected
- ⚠️ Warning badge: "Late by 1h 30m"
- ✅ Entry still allowed (entry always permitted)
- ✅ Late return incident auto-created
- ✅ Campus Manager notified
- ✅ Entry logged with late flag

---

#### Test Case: GRD-01-004 - Emergency Exit (No Approved Pass)
**Priority:** P0 (Critical)
**User Story:** US-GRD-01

**Preconditions:**
- Student has NO approved out-pass
- Emergency situation (medical)

**Test Steps:**
1. Navigate to Dashboard
2. Tap "Gate Pass" tab → "Outpass" subtab
3. Search for student: "John Doe"
4. No approved pass found
5. Tap "Emergency Exit" button
6. Enter emergency note: "Medical emergency - taking student to hospital"
7. Tap "Confirm Emergency Exit"

**Expected Results:**
- ✅ Emergency exit created without approved pass
- ✅ Guard note required (minimum 10 characters)
- ✅ Exit logged as "Emergency Exit"
- ✅ Campus Manager notified immediately
- ✅ Rector can convert to Approved Leave within 24h
- ✅ Student allowed to exit gate

---

#### Test Case: GRD-01-005 - Cross-Tenant QR Code Rejection
**Priority:** P0 (Critical - Security)
**User Story:** US-GRD-01

**Preconditions:**
- Guard assigned to MAP-STXAV tenant
- Student QR code from MAP-TEST tenant

**Test Steps:**
1. Scan QR code from different tenant student
2. View scan result

**Expected Results:**
- ❌ QR code rejected
- ✅ Error message: "Invalid QR code. Student not found in this hostel."
- ✅ No exit/entry allowed
- ✅ Security log created (suspicious scan attempt)

---

#### Test Case: GRD-01-006 - Offline Scan Attempt (Online-Only)
**Priority:** P1 (High)
**User Story:** US-GRD-01

**Test Steps:**
1. Navigate to Scan QR screen
2. Disable device network
3. Attempt to scan QR code

**Expected Results:**
- ❌ Scan blocked
- ✅ Banner: "Connection lost. Retry or use Emergency Exit."
- ✅ Retry button available
- ✅ Emergency Exit option still accessible (offline flow)

---

#### Test Case: GRD-01-007 - Manual Search (Fallback)
**Priority:** P1 (High)
**User Story:** US-GRD-01

**Preconditions:**
- Student QR code not working (battery dead)

**Test Steps:**
1. Navigate to Gate Pass tab → Outpass subtab
2. Use search field: "John Doe" or "Roll: 2024001"
3. View search results
4. Select student
5. Tap "Allow Exit"

**Expected Results:**
- ✅ Search finds student by name or roll number
- ✅ Approved pass displayed
- ✅ Exit/entry logged same as QR scan
- ✅ Fallback method works without QR

---

### US-GRD-02: Visitor Management

#### Test Case: GRD-02-001 - Allow Visitor Within Window
**Priority:** P0 (Critical)
**User Story:** US-GRD-02

**Preconditions:**
- Visitor window: 16:00 - 19:00 (single daily window)
- Current time: 17:30
- Student pre-registered visitor (optional in v1)

**Test Steps:**
1. Navigate to Gate Pass → Guest Entry tab
2. View guest entry request:
   - Student: John Doe, Room 101
   - Visitor: Mr. Robert Doe
   - Relationship: Father
   - Time: 17:30
3. Tap "View" button
4. Review visitor details
5. Tap "Allow Visitor"
6. Log entry

**Expected Results:**
- ✅ Visitor allowed during window (16:00-19:00)
- ✅ Visitor entry logged with:
  - Visitor name
  - Relationship
  - Time in
  - Student name and room
- ✅ Student notified: "Your visitor has arrived"

---

#### Test Case: GRD-02-002 - Deny Visitor Outside Window
**Priority:** P1 (High)
**User Story:** US-GRD-02

**Preconditions:**
- Visitor arrives at 14:00 (before 16:00 window)

**Test Steps:**
1. Visitor requests entry
2. Check current time vs visitor window

**Expected Results:**
- ❌ Auto-deny outside visitor window
- ✅ Guard cannot override (strictly by policy)
- ✅ Message: "Visiting hours are 16:00-19:00. Please return during this time."
- ✅ Denial logged

---

#### Test Case: GRD-02-003 - Visitor Without Pre-Registration
**Priority:** P2 (Medium)
**User Story:** US-GRD-02

**Preconditions:**
- v1 policy: Allow visitors even without pre-registration
- Visitor not pre-registered

**Test Steps:**
1. During visitor window (17:00)
2. Visitor arrives without pre-registration
3. Guard manually creates visitor log

**Expected Results:**
- ✅ Visitor allowed with manual log creation
- ✅ Guard enters:
  - Student name/room
  - Visitor name
  - Relationship
- ✅ Entry logged
- ✅ Student notified

---

### US-GRD-03: Security Incident Reporting

#### Test Case: GRD-03-001 - Create Security Incident with Photos
**Priority:** P1 (High)
**User Story:** US-GRD-03

**Test Steps:**
1. Navigate to Dashboard
2. Tap action tile "Security Incident" (if available) or via menu
3. Fill incident form:
   - Incident type: "Unauthorized entry attempt"
   - Description: "Unknown person tried to enter through side gate"
   - Severity: High
4. Tap "Add Photo"
5. Take photo with camera
6. Add second photo
7. Tap "Submit Incident"

**Expected Results:**
- ✅ Incident created successfully
- ✅ Photos uploaded (up to 3 photos)
- ✅ Description required (minimum 20 characters)
- ✅ Campus Manager notified immediately (high severity)
- ✅ Incident ID generated
- ✅ Timestamp recorded

---

#### Test Case: GRD-03-002 - Photo Upload Failure (Graceful Degradation)
**Priority:** P2 (Medium)
**User Story:** US-GRD-03

**Test Steps:**
1. Create security incident
2. Attempt to upload photo
3. Photo upload fails (network issue)
4. Tap "Submit Incident" (text-only)

**Expected Results:**
- ✅ Incident submission allowed without photos
- ✅ Text description sufficient
- ✅ Message: "Photo upload failed. Incident submitted with description only."
- ✅ Incident created in text-only mode

---

---

## HK Supervisor

### US-SUP-01: Daily Checklists

#### Test Case: SUP-01-001 - Complete Daily Checklist (Happy Path)
**Priority:** P0 (Critical)
**User Story:** US-SUP-01

**Preconditions:**
- Daily checklist auto-generated for HK Supervisor
- Tasks:
  1. Clean common areas (lobby, corridors) - requires comment
  2. Sanitize and maintain restrooms - requires photo
  3. Dispose of waste and empty bins
  4. Check cleaning supplies inventory - requires comment

**Test Steps:**
1. Login as HK Supervisor (+91 98765 43213)
2. Navigate to Dashboard
3. Tap "Checklists" tab (bottom navigation)
4. View today's checklist
5. Complete Task 1:
   - Tap checkbox
   - Text field appears (comment required)
   - Enter: "Lobby and corridors cleaned at 8 AM"
   - Tap "Save"
6. Complete Task 2:
   - Tap checkbox
   - Photo icon shows (photo required)
   - Tap "Add Photo" → take photo of clean restroom
   - Upload photo
7. Complete Task 3:
   - Tap checkbox (no additional requirement)
8. Complete Task 4:
   - Enter comment: "All supplies stocked, ordered extra bleach"
9. Tap "Submit Checklist"

**Expected Results:**
- ✅ Checklist tasks displayed with requirement indicators (comment/photo icons)
- ✅ Checkbox toggles task completion
- ✅ Required fields enforced (cannot submit without them)
- ✅ Photo upload successful
- ✅ Submit button enabled only when all tasks completed
- ✅ Checklist submitted successfully
- ✅ Manager notified for approval
- ✅ Checklist status: "Pending Approval"

---

#### Test Case: SUP-01-002 - Checklist Reminder Notifications
**Priority:** P1 (High)
**User Story:** US-SUP-01

**Preconditions:**
- Checklist due at 12:00 PM
- Supervisor has not started checklist

**Test Steps:**
1. Wait until 11:00 AM (T-60min)
2. Check notifications
3. Wait until 11:45 AM (T-15min)
4. Check notifications again

**Expected Results:**
- ✅ At 11:00 AM (T-60): Push notification "Checklist reminder: Due in 1 hour"
- ✅ At 11:45 AM (T-15): Push notification + SMS "Checklist reminder: Due in 15 minutes"
- ✅ Notifications include direct link to checklist

---

#### Test Case: SUP-01-003 - Late Submission Escalation
**Priority:** P1 (High)
**User Story:** US-SUP-01

**Preconditions:**
- Checklist due at 12:00 PM
- Grace window: 30 minutes
- Supervisor does not submit by 12:30 PM

**Test Steps:**
1. Do not submit checklist by deadline
2. Wait until 12:31 PM (past grace window)
3. Check manager notifications

**Expected Results:**
- ✅ After grace window, escalation triggered
- ✅ Supervisor receives push + SMS: "Overdue: Daily checklist not submitted"
- ✅ Manager (Campus Manager) receives notification:
  - "HK Supervisor checklist overdue by 31 minutes"
- ✅ Escalation continues every 2 hours until submission
- ✅ Late flag set on checklist

---

#### Test Case: SUP-01-004 - Offline Checklist Attempt (Online-Only)
**Priority:** P1 (High)
**User Story:** US-SUP-01

**Test Steps:**
1. Open Checklists screen
2. Disable network
3. Attempt to complete task 1
4. Tap checkbox

**Expected Results:**
- ❌ Action blocked
- ✅ Banner: "Connection lost. Retry."
- ✅ No offline queue (checklists online-only per PRD)
- ✅ Checklist data cached (viewable)
- ✅ Submit disabled until reconnect

---

#### Test Case: SUP-01-005 - Manager Approval/Send-Back
**Priority:** P1 (High)
**User Story:** US-SUP-01

**Preconditions:**
- HK Supervisor submitted checklist
- Status: Pending Approval

**Test Steps (Campus Manager):**
1. Login as Campus Manager
2. Navigate to Checklists → Staff Checklists
3. View HK Supervisor checklist
4. Review all tasks and photos
5. Option A: Approve
   - Tap "Approve"
   - Status: Completed
6. Option B: Send Back
   - Tap "Send Back"
   - Enter note: "Restroom photo unclear, please retake"
   - Status: Sent Back

**Expected Results:**
- ✅ Manager can approve or send back
- ✅ If approved: Checklist status "Completed"
- ✅ If sent back:
  - HK Supervisor notified with manager note
  - Status: "Revision Needed"
  - Supervisor can edit and resubmit

---

### US-SUP-02: Tickets Lifecycle

#### Test Case: SUP-02-001 - Create Housekeeping Ticket
**Priority:** P0 (Critical)
**User Story:** US-SUP-02

**Test Steps:**
1. Navigate to Dashboard
2. Tap "+" floating action button or "Requests" → "Create"
3. Fill ticket form:
   - Category: Housekeeping
   - Issue: "Common area needs deep cleaning"
   - Description: "Stains on corridor carpet, needs professional cleaning"
   - Priority: Medium
4. Add photo of stained carpet
5. Tap "Submit"

**Expected Results:**
- ✅ Ticket created successfully
- ✅ Auto-assigned to HK Supervisor (self)
- ✅ Status: Open
- ✅ Ticket ID generated (e.g., TKT-001)
- ✅ Photo uploaded
- ✅ Timestamp recorded
- ✅ Visible in Requests list

---

#### Test Case: SUP-02-002 - Update Ticket Status (Open → In-Progress → Resolved)
**Priority:** P0 (Critical)
**User Story:** US-SUP-02

**Preconditions:**
- Ticket TKT-001 exists with status: Open

**Test Steps:**
1. Navigate to Requests → View TKT-001
2. Tap "Update Status"
3. Select: In-Progress
4. Add comment: "Started deep cleaning process"
5. Tap "Confirm"
6. (After work done) Update status again:
7. Select: Resolved
8. Add comment: "Carpet professionally cleaned, stains removed"
9. Add photo of cleaned carpet
10. Tap "Confirm"

**Expected Results:**
- ✅ Status progression: Open → In-Progress → Resolved
- ✅ Comments saved with timestamp
- ✅ Before/after photos attached
- ✅ Student/requester notified at each status change
- ✅ Cannot skip statuses (must go Open → In-Progress → Resolved → Closed)

---

#### Test Case: SUP-02-003 - Close Without Resolve (Negative)
**Priority:** P1 (High)
**User Story:** US-SUP-02

**Test Steps:**
1. Open ticket with status: In-Progress
2. Attempt to update status to: Closed
3. Tap "Confirm"

**Expected Results:**
- ❌ Status update blocked
- ✅ Error: "Cannot close ticket without resolving first. Please mark as Resolved."
- ✅ Enforce workflow: In-Progress → Resolved → Closed

---

#### Test Case: SUP-02-004 - Reject Ticket (Invalid Request)
**Priority:** P2 (Medium)
**User Story:** US-SUP-02

**Preconditions:**
- Student submitted ticket: "Request maid service for personal room"
- Policy: HK does not provide personal room cleaning

**Test Steps:**
1. View ticket
2. Tap "Reject"
3. Enter rejection note: "HK does not provide individual room cleaning. Please maintain your own room."
4. Tap "Confirm Rejection"

**Expected Results:**
- ✅ Status: Rejected
- ✅ Student notified with rejection reason
- ✅ Ticket closed (terminal state)

---

---

## RM Supervisor

### US-SUP-02: Tickets Lifecycle (Room Maintenance)

#### Test Case: RM-02-001 - Create Maintenance Ticket with Parts Cost
**Priority:** P0 (Critical)
**User Story:** US-SUP-02

**Test Steps:**
1. Login as RM Supervisor (+91 98765 43214)
2. Navigate to Dashboard → Requests → Create
3. Fill form:
   - Category: Room Maintenance / Electrical
   - Issue: "Fan not working in Room 102"
   - Description: "Ceiling fan stopped working, possible motor failure"
   - Priority: High
   - **Parts Cost:** ₹850 (for new motor)
   - Parts Note: "Ceiling fan motor replacement"
4. Add photo of broken fan
5. Tap "Submit"

**Expected Results:**
- ✅ Ticket created with parts cost field (RM Supervisor only)
- ✅ Parts cost displayed: ₹850
- ✅ Student notified
- ✅ Campus Manager can view parts cost in ticket detail

---

#### Test Case: RM-02-002 - Update Ticket with Parts Cost
**Priority:** P1 (High)
**User Story:** US-SUP-02

**Preconditions:**
- Ticket created without parts cost initially
- After inspection, parts needed

**Test Steps:**
1. View ticket TKT-RM-005
2. Tap "Update Status" → In-Progress
3. Add comment: "Inspected fan, motor needs replacement"
4. Edit parts cost field: ₹850
5. Add parts note: "Ceiling fan motor"
6. Tap "Confirm"

**Expected Results:**
- ✅ Parts cost updated in ticket
- ✅ Parts cost visible to Campus Manager
- ✅ Student sees updated estimate (if enabled)

---

#### Test Case: RM-02-003 - Self-Assign Ticket
**Priority:** P2 (Medium)
**User Story:** US-SUP-02

**Preconditions:**
- Student submitted maintenance request
- Ticket unassigned

**Test Steps:**
1. Navigate to Requests
2. Filter: Unassigned
3. View ticket: "Door lock broken in Room 105"
4. Tap "Assign to Me"
5. Confirm assignment

**Expected Results:**
- ✅ Ticket assigned to RM Supervisor
- ✅ Assignee shown in ticket details
- ✅ Ticket appears in "My Tickets" filter
- ✅ Student notified: "Your request has been assigned to RM Supervisor"

---

---

## Laundry Manager

### US-LAU-01: Create/Process/Handover

#### Test Case: LAU-01-001 - Create Laundry Request on Behalf of Student
**Priority:** P0 (Critical)
**User Story:** US-LAU-01

**Preconditions:**
- Student hands over clothes physically
- Laundry Manager logs request

**Test Steps:**
1. Login as Laundry Manager (+91 98765 43217)
2. Navigate to Dashboard
3. Tap "Raise Request" action tile
4. Search for student: "John Doe" or Room 101
5. Select student from fast search results
6. Fill laundry details:
   - Item count: 12 pieces
   - Weight: 3.5 kg
   - Notes: "2 jeans, 5 shirts, 5 undergarments"
7. Tap "Submit Request"

**Expected Results:**
- ✅ Fast student search by name or room
- ✅ Request created on behalf of student
- ✅ Item count and weight captured
- ✅ Request status: Submitted
- ✅ Student receives push notification: "Your laundry request has been submitted"
- ✅ Request ID generated (e.g., LAU-001)

---

#### Test Case: LAU-01-002 - Mark Ready for Pickup (Send Push)
**Priority:** P0 (Critical)
**User Story:** US-LAU-01

**Preconditions:**
- Laundry request LAU-001 in status: Washing
- Laundry cycle completed

**Test Steps:**
1. Navigate to Active Requests
2. View request LAU-001
3. Tap "Update Status"
4. Select: Ready for Pickup
5. Tap "Confirm"

**Expected Results:**
- ✅ Status updated: Ready for Pickup
- ✅ Student receives push notification immediately:
  - "Your laundry is ready for pickup. Please collect from laundry room."
- ✅ Request moved to "Ready" list
- ✅ Pickup time window started (e.g., 24h to collect)

---

#### Test Case: LAU-01-003 - Manual Verify on Handover
**Priority:** P0 (Critical)
**User Story:** US-LAU-01

**Preconditions:**
- Request LAU-001 status: Ready for Pickup
- Student arrives to collect laundry

**Test Steps:**
1. Student shows QR code or mentions request ID
2. Laundry Manager navigates to request LAU-001
3. Tap "Manual Verify"
4. Count items: 12 pieces (matches original)
5. Enter verification note: "All items returned, no damage"
6. Tap "Complete Handover"

**Expected Results:**
- ✅ Manual verification with note required
- ✅ Item count confirmed
- ✅ Status updated: Completed
- ✅ Request removed from active list
- ✅ Handover timestamp recorded
- ✅ Student receives confirmation: "Laundry handover completed"

---

#### Test Case: LAU-01-004 - Mark Item as Lost/Damaged
**Priority:** P1 (High)
**User Story:** US-LAU-01 (Edge)

**Preconditions:**
- During washing, 1 item lost

**Test Steps:**
1. View request LAU-002
2. Tap "Report Issue"
3. Select issue type: Lost Item
4. Enter details: "1 white shirt lost during washing cycle"
5. Add compensation note (if applicable)
6. Tap "Submit Issue"

**Expected Results:**
- ✅ Issue reported with details
- ✅ Status updated: Issue Reported
- ✅ Student notified immediately
- ✅ Campus Manager notified for resolution
- ✅ Incident created for tracking
- ✅ Compensation process initiated (if policy requires)

---

#### Test Case: LAU-01-005 - Negative Count/Weight Validation
**Priority:** P1 (High)
**User Story:** US-LAU-01 (Edge)

**Test Steps:**
1. Create laundry request
2. Enter item count: -5
3. Enter weight: -2.0 kg
4. Tap "Submit"

**Expected Results:**
- ❌ Validation error: "Item count must be positive"
- ❌ Validation error: "Weight must be positive"
- ✅ Submit button disabled
- ✅ Fields highlighted in red

---

---

## Sports Manager

### US-SPM-01: Facility Blockouts

#### Test Case: SPM-01-001 - Create Facility Blockout
**Priority:** P0 (Critical)
**User Story:** US-SPM-01

**Preconditions:**
- Sports Manager manages: Basketball Court, Cricket Ground

**Test Steps:**
1. Login as Sports Manager (+91 98765 43218)
2. Navigate to Dashboard
3. Tap "Blockouts" action tile (or via menu)
4. Tap "Create Blockout"
5. Fill form:
   - Facility: Basketball Court
   - Start: Jan 20, 2026 - 10:00 AM
   - End: Jan 20, 2026 - 2:00 PM
   - Reason: "Maintenance - resurfacing court"
6. Tap "Create Blockout"

**Expected Results:**
- ✅ Blockout created successfully
- ✅ Basketball court unavailable for booking during window (10 AM - 2 PM on Jan 20)
- ✅ Students attempting to book during this time see: "Facility blocked for maintenance"
- ✅ Existing bookings during window auto-canceled with notification
- ✅ Sports Manager can view/edit/delete blockouts

---

#### Test Case: SPM-01-002 - Overlapping Blockouts Merged
**Priority:** P2 (Medium)
**User Story:** US-SPM-01 (Edge)

**Test Steps:**
1. Create blockout: Jan 20, 10:00 - 12:00
2. Create another blockout: Jan 20, 11:00 - 14:00 (overlaps)
3. View blockouts list

**Expected Results:**
- ✅ Overlapping blockouts merged into single window: Jan 20, 10:00 - 14:00
- ✅ Both reasons combined or latest reason kept
- ✅ Prevents duplicate/conflicting blockouts

---

### US-SPM-02: Monitor Student Slots

#### Test Case: SPM-02-001 - View Slot Occupancy
**Priority:** P1 (High)
**User Story:** US-SPM-02

**Test Steps:**
1. Navigate to Dashboard
2. Tap "Active Requests" tile
3. View current day bookings
4. Select "Basketball Court"
5. View slot occupancy:
   - 10:00 - 11:00: John Doe (Confirmed)
   - 11:00 - 12:00: Jane Smith (Confirmed)
   - 12:00 - 13:00: Empty slot
   - 13:00 - 14:00: Waitlist (3 students)

**Expected Results:**
- ✅ Real-time slot occupancy displayed
- ✅ Confirmed bookings shown with student names
- ✅ Empty slots visible
- ✅ Waitlist count displayed
- ✅ Sports Manager can view but NOT create/modify bookings (web-only)

---

#### Test Case: SPM-02-002 - No-Show Alert (>15 minutes)
**Priority:** P1 (High)
**User Story:** US-SPM-02

**Preconditions:**
- Student booked slot: 10:00 - 11:00
- Student does not arrive by 10:15

**Test Steps:**
1. Wait until 10:16 (16 minutes after slot start)
2. Check Sports Manager notifications

**Expected Results:**
- ✅ At 10:15 (15 min mark), no-show alert triggered
- ✅ Sports Manager receives push notification:
  - "No-show alert: John Doe has not checked in for 10:00 AM Basketball Court slot"
- ✅ Sports Manager can view no-show list
- ✅ Slot can be manually released to waitlist
- ✅ Student receives no-show penalty (if policy enabled)

---

#### Test Case: SPM-02-003 - Cannot Create Booking (Mobile)
**Priority:** P1 (High)
**User Story:** US-SPM-02 (Negative)

**Test Steps:**
1. Navigate to Active Requests
2. Attempt to find "Create Booking" action
3. Look for any booking creation UI

**Expected Results:**
- ❌ No "Create Booking" button in mobile app
- ✅ Sports Manager can only view/monitor bookings
- ✅ Booking creation is web-only workflow
- ✅ Message: "To create bookings, please use the web panel"

---

---

## Integration Test Scenarios

### INT-001: End-to-End Out-Pass Flow (Student → Rector → Guard)
**Priority:** P0 (Critical)

**Roles:** Student, Rector, Security Guard

**Test Steps:**
1. **Student App:**
   - Login as student (+91 98765 43210)
   - Navigate to Out-Pass → Create
   - Fill form:
     - Reason: "Family function"
     - Date: Tomorrow
     - Time: 10:00 AM - 6:00 PM
     - Overnight: No
   - Submit request
2. **Rector App:**
   - Login as Rector (+91 98765 43216)
   - Notification received: "New out-pass request from John Doe"
   - Navigate to Approvals → Out-Pass tab
   - View request (SLA countdown visible)
   - Tap "Approve"
   - Enter note: "Approved"
   - Step-up OTP verification
   - Confirm approval
3. **Student App (Verification):**
   - Student receives push notification: "Your out-pass has been approved"
   - Navigate to Gate Pass
   - View approved pass with QR code
4. **Guard App (Next Day):**
   - Login as Guard (+91 98765 43211)
   - Student arrives at gate at 10:15 AM
   - Scan student QR code
   - View pass details (Approved, valid 10 AM - 6 PM)
   - Tap "Allow Exit"
   - Exit logged
5. **Guard App (Student Returns):**
   - Student returns at 5:45 PM
   - Guard scans QR code again
   - View: "Checked Out at 10:15 AM"
   - Tap "Allow Entry"
   - Entry logged, pass status: Completed

**Expected Results:**
- ✅ Complete flow works across 3 apps
- ✅ Real-time notifications at each step
- ✅ QR code generation and scanning functional
- ✅ Status updates reflected instantly
- ✅ Audit trail complete

---

### INT-002: Room Change Request (Student → CM → Warden)
**Priority:** P1 (High)

**Roles:** Student, Campus Manager, Warden

**Test Steps:**
1. **Student App:**
   - Submit room change request
   - Current room: 101-A
   - Requested room: 102-B
   - Reason: "Current roommate disturbs sleep"
2. **Campus Manager Web:**
   - View request
   - Approve with note
   - System auto-reallocates bed
3. **Warden App:**
   - Notification: "Student John Doe moved from 101-A to 102-B"
   - View attendance screen
   - Verify student appears in Room 102 list (not 101)

**Expected Results:**
- ✅ Bed allocation updated in real-time
- ✅ Warden sees updated room assignment
- ✅ Student profile shows new room

---

### INT-003: Attendance Missed → Incident → Resolution (Warden → CM)
**Priority:** P1 (High)

**Roles:** Warden, Campus Manager

**Test Steps:**
1. **Warden App:**
   - Mark attendance for all rooms except Room 103
   - Session auto-closes at curfew + 2h
   - "Missed Attendance" incident auto-created
2. **Campus Manager Web:**
   - Notification: "Missed attendance incident for Room 103"
   - View incident details (2 unmarked students)
3. **Warden App (Next Day):**
   - Navigate to Incidents
   - View "Missed Attendance" incident
   - Tap "Close Incident"
   - Enter note: "Students were on approved leave, marking as Leave retroactively"
   - Confirm closure

**Expected Results:**
- ✅ Incident auto-creation works
- ✅ Notification flow correct
- ✅ Warden can close with note
- ✅ Audit trail complete

---

### INT-004: Ticket Escalation (Student → HK → CM)
**Priority:** P1 (High)

**Roles:** Student, HK Supervisor, Campus Manager

**Test Steps:**
1. **Student App:**
   - Create ticket: "Washroom sink clogged"
   - Category: Housekeeping
   - Add photo
2. **HK Supervisor App:**
   - Notification: "New housekeeping ticket from John Doe, Room 101"
   - View ticket
   - Update status: In-Progress
   - Add comment: "Will fix within 2 hours"
3. **Wait 25 hours (SLA breach):**
   - HK Supervisor does not resolve
4. **Campus Manager Web:**
   - Notification: "SLA breach: Ticket TKT-001 overdue"
   - View ticket with SLA badge
   - Follow up with HK Supervisor

**Expected Results:**
- ✅ Ticket creation and assignment work
- ✅ SLA tracking functional
- ✅ Escalation notifications sent
- ✅ CM can view and intervene

---

### INT-005: Laundry Cycle (Student → Laundry Manager → Student)
**Priority:** P1 (High)

**Roles:** Student, Laundry Manager

**Test Steps:**
1. **Student App:**
   - Create laundry request
   - Item count: 10
   - Notes: "Urgent - need by Friday"
2. **Laundry Manager App:**
   - View new request
   - Update status: Collected
   - Start washing cycle
   - Update status: Washing
3. **After washing complete:**
   - Mark status: Ready for Pickup
   - Student receives push: "Your laundry is ready"
4. **Student picks up laundry:**
   - Laundry Manager scans student QR or searches
   - Manual verify with note
   - Mark: Completed

**Expected Results:**
- ✅ Status progression smooth
- ✅ Push notifications at key stages
- ✅ Manual verification flow works
- ✅ Request completion recorded

---

---

## Non-Functional Test Cases

### NFR-001: API Response Time (Performance)
**Priority:** P0 (Critical)
**User Story:** US-NFR-01

**Test Method:** Manual timing or Performance testing tool

**Test Steps:**
1. Measure API response times for common operations:
   - **Read operations:**
     - GET /api/v1/guard/dashboard/stats
     - GET /api/v1/warden/students
     - GET /api/v1/rector/approvals
   - **Write operations:**
     - POST /api/v1/gate/scan (QR scan)
     - POST /api/v1/rector/approvals/bulk (bulk approve)
     - POST /api/v1/tickets (create ticket)
2. Run 10 requests for each endpoint
3. Calculate p95 response time

**Expected Results:**
- ✅ p95 read operations ≤ 500ms
- ✅ p95 write operations ≤ 800ms
- ✅ No timeouts or errors

---

### NFR-002: Dashboard Render Time
**Priority:** P1 (High)
**User Story:** US-NFR-01

**Test Steps:**
1. Launch Guard App
2. Login with phone + OTP
3. Measure time from login success to dashboard fully rendered (all tiles visible)

**Expected Results:**
- ✅ Dashboard renders in ≤ 1.5 seconds
- ✅ No blank screens or loading spinners beyond 1.5s
- ✅ Smooth animations

---

### NFR-003: Step-Up OTP Security
**Priority:** P0 (Critical - Security)
**User Story:** US-NFR-02

**Test Steps:**
1. Login as Rector
2. Attempt to approve out-pass without step-up OTP
3. Close step-up OTP modal
4. Wait 11 minutes
5. Attempt to complete approval

**Expected Results:**
- ❌ Approval blocked without step-up OTP
- ✅ Error: `E_STEPUP_REQUIRED`
- ❌ After 10 min timeout, OTP session expired
- ✅ New OTP required

---

### NFR-004: PII Reveal Audit Trail
**Priority:** P0 (Critical - Security)
**User Story:** US-NFR-02

**Test Steps:**
1. Login as Warden
2. Navigate to Students → John Doe
3. Tap-to-reveal Guardian Phone
4. Check audit logs (via web admin or API)

**Expected Results:**
- ✅ Audit log entry created with:
  - User: Warden name + ID
  - Action: PII_REVEALED
  - Field: guardian_phone
  - Student: John Doe ID
  - Timestamp: Accurate
  - Device: staff-app

---

### NFR-005: Screenshot Blocking (PII Screens)
**Priority:** P0 (Critical - Security)
**User Story:** US-NFR-02

**Test Steps:**
1. Login as Rector
2. Navigate to Student Insights
3. Tap-to-reveal Medical Information
4. Take screenshot (device screenshot button)

**Expected Results:**
- ❌ Screenshot blocked
- ✅ Screen appears black in screenshot OR
- ✅ System alert: "Screenshots disabled for security"
- ✅ No PII visible in captured image

---

### NFR-006: Offline Queue Accuracy (Guard/Warden)
**Priority:** P0 (Critical)
**User Story:** Global - Connectivity Policy

**Test Steps:**
1. Login as Guard
2. Disconnect network
3. Attempt to log emergency exit (offline flow)
4. Enter note: "Medical emergency"
5. Reconnect network
6. Verify sync

**Expected Results:**
- ✅ Emergency exit queued locally
- ✅ Automatic sync when online
- ✅ 100% sync accuracy (no data loss)
- ✅ Retry logic (max 3 attempts)
- ✅ Conflict resolution if needed

---

### NFR-007: Health Check Endpoints
**Priority:** P1 (High)
**User Story:** US-NFR-03

**Test Steps:**
1. Call central health check: GET /v1/central-healthz
2. Call tenant health check: GET /v1/healthz (with tenant context)

**Expected Results:**
- ✅ Response code: 200 OK
- ✅ Response body includes:
  - Database: connected
  - Redis: connected
  - Queue: running
  - Disk space: available
- ✅ Response time: ≤ 500ms

---

### NFR-008: App Crash Rate
**Priority:** P0 (Critical)
**User Story:** Success Metrics

**Test Method:** Monitor Sentry error tracking during UAT

**Test Steps:**
1. During entire UAT period, track app crashes
2. Calculate crash rate: (Crashes / Total Sessions) × 100

**Expected Results:**
- ✅ Crash rate ≤ 0.5% of sessions
- ✅ No critical crashes (app freeze, data loss)
- ✅ All crashes logged in Sentry with stack traces

---

---

## Bug Reporting Template

When reporting bugs during UAT, use this template:

### Bug Report Template

**Bug ID:** [Auto-generated or sequential]
**Reporter:** [Your name]
**Date:** [Date reported]
**Environment:** Staging / Production
**Device:** [e.g., iPhone 14 Pro, Android Pixel 7]
**OS Version:** [e.g., iOS 17.2, Android 14]
**App Version:** [e.g., Staff App v1.0.5]

**Priority:** [P0 - Critical / P1 - High / P2 - Medium / P3 - Low]

**Test Case:** [Reference test case ID if applicable]

**Summary:** [One-line description of bug]

**Steps to Reproduce:**
1.
2.
3.

**Expected Result:**
[What should happen]

**Actual Result:**
[What actually happened]

**Screenshots/Videos:**
[Attach if available]

**Error Messages:**
[Copy exact error text]

**Additional Context:**
[Network conditions, user role, tenant, etc.]

**Workaround (if any):**
[Temporary fix or alternative flow]

---

### Bug Priority Definitions

| Priority | Definition | Examples | Response SLA |
|----------|-----------|----------|--------------|
| **P0 - Critical** | Blocker preventing core functionality | Login fails, app crashes on launch, data loss, security breach | Fix within 24h |
| **P1 - High** | Major feature broken | QR scan not working, approval fails, offline sync broken | Fix within 3 days |
| **P2 - Medium** | Feature works but has issues | UI glitch, slow performance, incorrect validation message | Fix within 1 week |
| **P3 - Low** | Minor cosmetic or edge case | Typo, alignment issue, rare edge case | Fix in next release |

---

## UAT Sign-Off Checklist

Use this checklist to track overall UAT progress:

### Global Tests
- [ ] US-G-01: OTP Login & Step-up - All test cases pass
- [ ] US-G-02: Tenant Scoping - All test cases pass
- [ ] US-G-03: Mobile App Binary Split - All test cases pass

### Role-Specific Tests
- [ ] Campus Manager - All critical test cases pass (CM-01 to CM-10)
- [ ] Rector - All critical test cases pass (REC-01 to RECT-04)
- [ ] Warden - All critical test cases pass (WAR-01 to WAR-02)
- [ ] Security Guard - All critical test cases pass (GRD-01 to GRD-03)
- [ ] HK Supervisor - All critical test cases pass (SUP-01 to SUP-02)
- [ ] RM Supervisor - All critical test cases pass (RM-02)
- [ ] Laundry Manager - All critical test cases pass (LAU-01)
- [ ] Sports Manager - All critical test cases pass (SPM-01 to SPM-02)

### Integration Tests
- [ ] INT-001: Out-Pass Flow - Pass
- [ ] INT-002: Room Change Flow - Pass
- [ ] INT-003: Attendance Incident Flow - Pass
- [ ] INT-004: Ticket Escalation Flow - Pass
- [ ] INT-005: Laundry Cycle Flow - Pass

### Non-Functional Tests
- [ ] NFR-001: API Performance - Pass
- [ ] NFR-002: Dashboard Render Time - Pass
- [ ] NFR-003: Step-Up OTP Security - Pass
- [ ] NFR-004: PII Audit Trail - Pass
- [ ] NFR-005: Screenshot Blocking - Pass
- [ ] NFR-006: Offline Queue Accuracy - Pass
- [ ] NFR-007: Health Check Endpoints - Pass
- [ ] NFR-008: App Crash Rate - Pass (≤ 0.5%)

### Overall Sign-Off
- [ ] No P0 bugs remaining
- [ ] All P1 bugs resolved or have workarounds
- [ ] Performance metrics meet NFR requirements
- [ ] Security tests pass
- [ ] All stakeholders approve for production release

**UAT Manager Signature:** ___________________
**Date:** ___________________

---

**End of UAT Test Plan**

**Next Steps:**
1. Distribute this test plan to UAT team
2. Assign test cases to testers
3. Set up bug tracking system (Jira/Linear/GitHub Issues)
4. Schedule daily UAT standups
5. Track progress using sign-off checklist
6. Conduct UAT retrospective at completion

**Questions or Clarifications:**
Contact: [Project Manager / QA Lead]
