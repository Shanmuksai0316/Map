# MAP HMS - Rector Web Panel UAT Checklist

**Version:** 1.0  
**Prepared on:** February 16, 2026  
**Role under test:** Rector  
**Environment:** __________________________  
**Test URL:** `https://<tenant-subdomain>.mapservices.in/rector`  
**Tested by:** __________________________  
**Date:** __________________________

---

## 1. How to use this checklist

For each row:
1. Perform test steps.
2. Compare expected behavior.
3. Mark status.

Status options:
- ✅ Pass
- ❌ Fail
- ⏸ Not Tested

---

## 2. Pre-UAT readiness

| # | Check | Expected | Status | Remarks |
|---|---|---|---|---|
| PRE-01 | Rector account available | OTP login works | ☐ | |
| PRE-02 | Pending out-pass records available | Out-Pass list has pending records | ☐ | |
| PRE-03 | Pending leave records available | Leave list has pending records | ☐ | |
| PRE-04 | Approved/declined history data available | Approval History can be validated | ☐ | |
| PRE-05 | Student records exist | Students list is not empty | ☐ | |
| PRE-06 | Report data exists for date range | Reports can be downloaded | ☐ | |

---

## 3. Test data sheet

| Data Item | Value to use in UAT |
|---|---|
| Rector Phone | __________________ |
| Out-Pass Pending ID | __________________ |
| Out-Pass Expired ID (optional) | __________________ |
| Leave Pending ID | __________________ |
| Sick Leave Pending ID | __________________ |
| Student Name for search | __________________ |
| Date range for report | __________________ |

---

## 4. UAT test cases

## 4.1 Authentication and access

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| AUTH-01 | Open rector login | Open `/rector/login` | Login screen loads | ☐ | |
| AUTH-02 | Send OTP valid phone | Enter valid phone and send OTP | OTP sent message shown | ☐ | |
| AUTH-03 | Invalid phone format | Enter invalid phone | Validation error shown | ☐ | |
| AUTH-04 | Login with valid OTP | Enter OTP and submit | Redirect to Rector dashboard | ☐ | |
| AUTH-05 | Wrong OTP | Enter wrong OTP | Login blocked with error | ☐ | |
| AUTH-06 | Resend OTP | Resend OTP action | OTP resent message shown | ☐ | |
| AUTH-07 | Unauthorized role check | Try non-rector user on rector URL | Access denied | ☐ | |
| AUTH-08 | Logout | Click logout | Redirect to login page | ☐ | |

---

## 4.2 Dashboard

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| DASH-01 | Dashboard opens | Login and open dashboard | Page loads fully | ☐ | |
| DASH-02 | Greeting widget | Check greeting area | Greeting + rector name visible | ☐ | |
| DASH-03 | Stats cards visible | Check cards | Active Hostels, Resident Students, Pending Requests visible | ☐ | |
| DASH-04 | Approval trend chart | View chart | Approved/Declined trend visible | ☐ | |
| DASH-05 | Occupancy chart | View chart | Hostel occupancy chart visible | ☐ | |
| DASH-06 | Urgent pending table | View pending approvals widget | Pending items appear with SLA state | ☐ | |
| DASH-07 | SLA performance chart | View doughnut chart | Within SLA vs Breached shown | ☐ | |
| DASH-08 | Recent decisions widget | View table | Recent rector decisions shown | ☐ | |
| DASH-09 | Download monthly report action | Click dashboard action | Month/year/format form opens | ☐ | |
| DASH-10 | Monthly report notification | Generate report | Success notification with download link | ☐ | |

---

## 4.3 Out-Pass approvals

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| OP-01 | Open Out-Pass Approvals | Open menu item | List loads | ☐ | |
| OP-02 | Default pending filter | Open page initially | Pending requests shown by default | ☐ | |
| OP-03 | Hostel filter | Apply hostel filter | Correct hostel records shown | ☐ | |
| OP-04 | Date range filter | Apply from/until filter | Correct date range records shown | ☐ | |
| OP-05 | Search by request ID | Search request id | Matching row shown | ☐ | |
| OP-06 | View out-pass details | Click View | Record details open | ☐ | |
| OP-07 | Approve out-pass | Click Approve, add note, confirm | Status updates to Approved, success toast | ☐ | |
| OP-08 | Decline out-pass | Click Decline, add reason, confirm | Status updates to Declined, success toast | ☐ | |
| OP-09 | Action hidden for non-pending | Open already decided record | Approve/Decline actions not visible | ☐ | |
| OP-10 | Bulk approve selected | Select pending rows, bulk approve | Selected pending requests approved | ☐ | |
| OP-11 | Expired request cannot approve | Try approve expired/old request | Warning shown, approval blocked | ☐ | |

---

## 4.4 Leave approvals

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| LV-01 | Open Leave Approvals | Open menu item | List loads | ☐ | |
| LV-02 | Leave type filter | Filter by Leave/Sick Leave | Correct type records shown | ☐ | |
| LV-03 | Status filter | Filter Pending/Approved/Rejected | Correct rows shown | ☐ | |
| LV-04 | Date range filter | Apply submitted date range | Correct rows shown | ☐ | |
| LV-05 | View leave details | Click View | Request detail view opens | ☐ | |
| LV-06 | Approve leave with note | Use Approve action | Status changes to approved | ☐ | |
| LV-07 | Approve using template | Choose quick template and approve | Template fills note and save succeeds | ☐ | |
| LV-08 | Reject leave with reason | Use Reject action and confirm | Status changes to rejected | ☐ | |
| LV-09 | Bulk approve pending leave | Multi-select and bulk approve | Selected pending leaves approved | ☐ | |
| LV-10 | Approve/reject hidden after decision | Open decided request | Decision actions hidden | ☐ | |

---

## 4.5 Approval history

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| HIS-01 | Open Approval History | Open page | History table loads | ☐ | |
| HIS-02 | Decision filter | Filter approved/declined | Matching decisions shown | ☐ | |
| HIS-03 | Date range filter | Apply from/to date | Correct rows shown | ☐ | |
| HIS-04 | Search request ID | Search specific ID | Matching row found | ☐ | |
| HIS-05 | Timeline and note display | Inspect history row | Action label and note visible | ☐ | |

---

## 4.6 Students (read-only)

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| STD-01 | Open Students list | Click Students menu | List loads | ☐ | |
| STD-02 | Search student | Search by name or ID | Matching row appears | ☐ | |
| STD-03 | Filter by hostel | Apply hostel filter | Correct rows shown | ☐ | |
| STD-04 | Filter by year | Apply year filter | Correct rows shown | ☐ | |
| STD-05 | Open student profile | Click View | Full detail profile visible | ☐ | |
| STD-06 | Verify read-only behavior | Check for create/edit/delete buttons | No create/edit/delete actions visible | ☐ | |

---

## 4.7 Reports

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| REP-01 | Open Reports page | Click Reports | Report form loads | ☐ | |
| REP-02 | Report type mandatory | Leave report type empty and download | Validation warning shown | ☐ | |
| REP-03 | Approval summary export | Select type/date and download | File downloads | ☐ | |
| REP-04 | Attendance summary export | Select type/date and download | File downloads | ☐ | |
| REP-05 | Attendance detail export | Select type/date and download | File downloads with detail rows | ☐ | |
| REP-06 | Checklist compliance export | Select type/date and download | File downloads | ☐ | |
| REP-07 | Incident summary export | Select type/date and download | File downloads | ☐ | |
| REP-08 | Pass requests detail export | Select type/date and download | File downloads | ☐ | |
| REP-09 | No data warning | Use date range with no data | "No data found" warning shown | ☐ | |

---

## 4.8 Profile and user menu

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| PRF-01 | Open My Profile from user menu | Click user menu > My Profile | Profile page opens | ☐ | |
| PRF-02 | Profile data visibility | Check fields | Name, role, phone, tenant details visible | ☐ | |

---

## 4.9 Security and tenant isolation

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| SEC-01 | Tenant data isolation | Login on tenant subdomain and view approvals | Only tenant requests shown | ☐ | |
| SEC-02 | Role access protection | Try opening rector page with wrong role | Access denied | ☐ | |
| SEC-03 | Decision audit trail | Approve/reject then check history | Decision logged with timestamp | ☐ | |

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

