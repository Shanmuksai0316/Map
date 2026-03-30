# MAP HMS - College Management Web Panel UAT Checklist

**Version:** 1.0  
**Prepared on:** February 16, 2026  
**Role under test:** College Management  
**Environment:** __________________________  
**Test URL:** `https://<tenant-subdomain>.mapservices.in/college-mgmt`  
**Tested by:** __________________________  
**Date:** __________________________

---

## 1. How to use this checklist

For each row:
1. Perform test steps in panel.
2. Compare expected result.
3. Mark status.

Status options:
- ✅ Pass
- ❌ Fail
- ⏸ Not Tested

---

## 2. Pre-UAT readiness

| # | Check | Expected | Status | Remarks |
|---|---|---|---|---|
| PRE-01 | College Management account available | OTP login works | ☐ | |
| PRE-02 | Tenant has students | Student list has records | ☐ | |
| PRE-03 | Tenant has out-pass data | Out-Pass list has records | ☐ | |
| PRE-04 | Tenant has tickets | Tickets list has records | ☐ | |
| PRE-05 | Tenant has attendance sessions | Attendance list has records | ☐ | |
| PRE-06 | Report data available | Reports download can be validated | ☐ | |

---

## 3. Test data sheet

| Data Item | Value to use in UAT |
|---|---|
| College Management Phone | __________________ |
| Student Name for search | __________________ |
| Out-Pass ID for test | __________________ |
| Ticket ID for test | __________________ |
| Attendance Session ID | __________________ |
| Report date range | __________________ |

---

## 4. UAT test cases

## 4.1 Authentication and access

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| AUTH-01 | Open login page | Open `/college-mgmt/login` | Login form loads | ☐ | |
| AUTH-02 | Send OTP valid phone | Enter registered phone and send OTP | OTP success message shown | ☐ | |
| AUTH-03 | Invalid phone validation | Enter invalid phone format | Validation error shown | ☐ | |
| AUTH-04 | Login with valid OTP | Enter OTP and submit | Redirect to dashboard | ☐ | |
| AUTH-05 | Login with wrong OTP | Enter wrong OTP | Login blocked with error | ☐ | |
| AUTH-06 | Resend OTP | Trigger resend | OTP resent | ☐ | |
| AUTH-07 | Unauthorized role access | Try non-college-mgmt role | Access denied | ☐ | |
| AUTH-08 | Logout | Click logout | Redirect to login page | ☐ | |

---

## 4.2 Dashboard

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| DASH-01 | Dashboard opens | Login and open dashboard | Page loads without errors | ☐ | |
| DASH-02 | Greeting heading | Check top heading | Greeting with user/role context shown | ☐ | |
| DASH-03 | KPI cards render | Scroll cards section | Cards visible (occupancy, aging, fee status etc.) | ☐ | |
| DASH-04 | Occupancy card values | Compare bed/occupancy values | Values are logical and non-negative | ☐ | |
| DASH-05 | Late returns card | Verify count display | Count appears and updates with data | ☐ | |
| DASH-06 | Ticket aging card | Verify days metric | Metric displayed | ☐ | |
| DASH-07 | Fee status card | Verify paid/pending split | Paid/pending values displayed | ☐ | |
| DASH-08 | Checklist compliance card | Verify percentage display | Compliance percentage visible | ☐ | |
| DASH-09 | Total students and hostels cards | Verify count cards | Both cards visible | ☐ | |

---

## 4.3 Students (read-only)

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| STU-01 | Open Students | Click Students menu | List loads | ☐ | |
| STU-02 | Search by name | Search existing name | Correct row appears | ☐ | |
| STU-03 | Search by student ID | Search by ID | Correct row appears | ☐ | |
| STU-04 | Hostel filter | Apply hostel filter | Correct subset shown | ☐ | |
| STU-05 | Year filter | Apply year filter | Correct subset shown | ☐ | |
| STU-06 | Open student detail | Click View | Profile opens with full sections | ☐ | |
| STU-07 | Read-only guardrail | Check for create/edit/delete actions | No create/edit/delete available | ☐ | |

---

## 4.4 Out-Passes (read-only)

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| OP-01 | Open Out-Passes | Operations > Out-Passes | List loads | ☐ | |
| OP-02 | Status filter | Filter by pending/approved/declined/etc. | Correct records shown | ☐ | |
| OP-03 | Hostel filter | Apply hostel filter | Correct hostel rows shown | ☐ | |
| OP-04 | Date range filter | Apply from/until | Correct range rows shown | ☐ | |
| OP-05 | Search request ID | Search by request ID | Matching row shown | ☐ | |
| OP-06 | Open out-pass detail | Click View | Detail page opens | ☐ | |
| OP-07 | Read-only behavior | Check actions | No approve/reject/edit/delete actions | ☐ | |

---

## 4.5 Tickets (read-only)

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| TKT-01 | Open Tickets | Operations > Tickets | List loads | ☐ | |
| TKT-02 | Status filter | Filter by open/in_progress/resolved/closed | Correct rows shown | ☐ | |
| TKT-03 | Priority filter | Filter by high/medium/low | Correct rows shown | ☐ | |
| TKT-04 | Category filter | Filter by housekeeping/maintenance/security/laundry/other | Correct rows shown | ☐ | |
| TKT-05 | Ticket search | Search by title | Matching rows shown | ☐ | |
| TKT-06 | Open ticket detail | Click View | Detail page opens with assignment and timestamps | ☐ | |
| TKT-07 | Read-only behavior | Check for create/edit/delete | No write actions available | ☐ | |

---

## 4.6 Attendance sessions (read-only)

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| ATT-01 | Open Attendance page | Operations > Attendance | Session list loads | ☐ | |
| ATT-02 | Hostel filter | Filter by hostel | Correct records shown | ☐ | |
| ATT-03 | Date range filter | Apply date range | Correct records shown | ☐ | |
| ATT-04 | Status filter | Filter scheduled/open/closed | Correct records shown | ☐ | |
| ATT-05 | Open session detail | Click View | Session detail opens | ☐ | |
| ATT-06 | Progress summary display | Check progress field | Present/Absent/Leave/Unmarked values visible | ☐ | |
| ATT-07 | Read-only behavior | Check for create/edit/delete actions | No create/edit/delete available | ☐ | |

---

## 4.7 Reports

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| REP-01 | Open report center | Operations > Reports | Report form loads | ☐ | |
| REP-02 | Mandatory report type | Try download without report type | Validation warning shown | ☐ | |
| REP-03 | Attendance summary report | Select type/date and download | File downloads | ☐ | |
| REP-04 | Attendance detail report | Select type/date and download | File downloads | ☐ | |
| REP-05 | Pass requests report | Select type/date and download | File downloads | ☐ | |
| REP-06 | Incident summary report | Select type/date and download | File downloads | ☐ | |
| REP-07 | Room occupancy report | Select type/date and download | File downloads | ☐ | |
| REP-08 | Checklist report | Select type/date and download | File downloads | ☐ | |
| REP-09 | No-data handling | Use date range with no records | "No data found" warning shown | ☐ | |

---

## 4.8 Tenant isolation and permissions

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| SEC-01 | Tenant-scoped student data | Login on one tenant and open Students | No cross-tenant records | ☐ | |
| SEC-02 | Tenant-scoped out-pass data | Open Out-Passes | No cross-tenant records | ☐ | |
| SEC-03 | Tenant-scoped ticket data | Open Tickets | No cross-tenant records | ☐ | |
| SEC-04 | Read-only panel guardrail | Attempt to modify operations data | Modification not allowed | ☐ | |

---

## 5. UAT sign-off summary

| Item | Value |
|---|---|
| Total test cases executed | __________ |
| Passed | __________ |
| Failed | __________ |
| Not tested | __________ |
| Critical issues found | Yes / No |
| Ready for production recommendation | Yes / No |

Final comments:

____________________________________________________________________

____________________________________________________________________

Sign-off:
- Name: __________________________
- Role: __________________________
- Date: __________________________

