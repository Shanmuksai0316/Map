# MAP HMS - Campus Manager Web Panel UAT Checklist

**Version:** 1.0  
**Prepared on:** February 16, 2026  
**Role under test:** Campus Manager  
**Environment:** __________________________  
**Test URL:** `https://<tenant-subdomain>.mapservices.in/campus-manager`  
**Tested by:** __________________________  
**Date:** __________________________

---

## 1. How to use this checklist

For each row:
1. Perform the exact action in web panel.
2. Compare with expected result.
3. Mark status.

Status options:
- ✅ Pass
- ❌ Fail
- ⏸ Not Tested

---

## 2. Pre-UAT readiness

| # | Check | Expected | Status | Remarks |
|---|---|---|---|---|
| PRE-01 | Campus Manager login exists | OTP login works | ☐ | |
| PRE-02 | Tenant has hostels and students | Student and room pages show data | ☐ | |
| PRE-03 | At least 1 unassigned student exists | Unassigned Students page has records | ☐ | |
| PRE-04 | At least 1 request in each major bucket | Requests pages show data | ☐ | |
| PRE-05 | At least 1 incident/medical sample exists | Emergency pages can be tested | ☐ | |
| PRE-06 | Browser cache cleared | Latest UI changes visible | ☐ | |
| PRE-07 | Checklist module status known | Checklist menu visibility verified | ☐ | |
| PRE-08 | Report download permissions available | CSV/XLSX download possible | ☐ | |

---

## 3. Test data sheet

| Data Item | Value to use in UAT |
|---|---|
| Tenant Name | __________________ |
| Campus Manager Phone | __________________ |
| Hostel A | __________________ |
| Hostel B | __________________ |
| Student (Assigned) | __________________ |
| Student (Unassigned) | __________________ |
| Housekeeping Ticket ID | __________________ |
| Maintenance Ticket ID | __________________ |
| Outpass Request ID | __________________ |
| Leave Request ID | __________________ |
| Guest Entry ID | __________________ |
| Room Change ID | __________________ |
| Medical Incident ID | __________________ |
| General Incident ID | __________________ |

---

## 4. UAT test cases

## 4.1 Authentication and access

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| AUTH-01 | Open login page | Open `/campus-manager/login` | Login form loads | ☐ | |
| AUTH-02 | Send OTP with valid phone | Enter registered number and click Send OTP | Success message shown | ☐ | |
| AUTH-03 | Invalid phone format | Enter invalid phone and send OTP | Validation error shown | ☐ | |
| AUTH-04 | Login with valid OTP | Enter valid OTP and verify | Redirect to dashboard | ☐ | |
| AUTH-05 | Wrong OTP | Enter wrong OTP | Error shown, login blocked | ☐ | |
| AUTH-06 | Resend OTP | Click resend | New OTP sent message appears | ☐ | |
| AUTH-07 | Change phone flow | Click change phone | Phone field resets | ☐ | |
| AUTH-08 | Unauthorized role access | Try non-campus-manager user | Access denied | ☐ | |
| AUTH-09 | Logout | Logout from user menu | Redirect to login | ☐ | |

---

## 4.2 Dashboard

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| DASH-01 | Dashboard loads | Login and open Dashboard | Page loads without errors | ☐ | |
| DASH-02 | Greeting text | Check header greeting | Time-based greeting shown | ☐ | |
| DASH-03 | Hostel switcher list | Open hostel switcher | All tenant hostels listed | ☐ | |
| DASH-04 | Hostel switcher filter effect | Select one hostel | Dashboard data refreshes for selected hostel | ☐ | |
| DASH-05 | Reset hostel switcher | Select All Hostels | Tenant-wide data returns | ☐ | |
| DASH-06 | Time range filter | Change range | Charts refresh to new period | ☐ | |
| DASH-07 | KPI cards visible | Check cards | Active Hostels and Resident Students visible | ☐ | |
| DASH-08 | Occupancy chart render | Open chart section | Doughnut chart visible | ☐ | |
| DASH-09 | Request breakdown chart | Check request chart | Housekeeping and Maintenance bars visible | ☐ | |
| DASH-10 | Attendance trend chart | Check line chart | Attendance trend draws correctly | ☐ | |
| DASH-11 | Checkout timeline chart | Check bar chart | Upcoming checkout counts visible | ☐ | |
| DASH-12 | Pass request trend chart | Check trend chart | Approved/Declined/Pending lines visible | ☐ | |
| DASH-13 | Checklist compliance widget | View widget | Counts and compliance percentage visible | ☐ | |
| DASH-14 | Room change queue widget | View widget | Pending/urgent summary visible | ☐ | |
| DASH-15 | Activity feed list | View activity feed | Latest entries visible | ☐ | |
| DASH-16 | Add activity note | Submit note in widget | Note saved and displayed | ☐ | |

---

## 4.3 Student management

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| STU-01 | Open All Students | Go to Student Management > All Students | List loads | ☐ | |
| STU-02 | Search by student name | Search existing name | Matching record appears | ☐ | |
| STU-03 | Filter by hostel | Apply hostel filter | Only selected hostel students shown | ☐ | |
| STU-04 | Filter by year | Apply year filter | Correct year records shown | ☐ | |
| STU-05 | Filter by allocation status | Assigned/Unassigned filter | Correct subset shown | ☐ | |
| STU-06 | Create student required validation | Try save empty form | Validation errors shown | ☐ | |
| STU-07 | Create valid student | Fill mandatory fields and save | Student created | ☐ | |
| STU-08 | View student detail | Click View | Detail page opens | ☐ | |
| STU-09 | Edit student detail | Click Edit and save | Update persists | ☐ | |
| STU-10 | Activate student action | Use Activate on inactive student | Status changes to active | ☐ | |
| STU-11 | Deactivate student action | Use Deactivate on active student | Status changes to inactive | ☐ | |
| STU-12 | Archive student | Use Archive action with reason/date | Student archived | ☐ | |
| STU-13 | Restore student | Use Restore action | Student restored | ☐ | |
| STU-14 | Allocate room modal opens | Use Allocate Room action | Room/bed form opens | ☐ | |
| STU-15 | Allocate room success | Select available bed and save | Allocation created, success toast | ☐ | |
| STU-16 | Allocate room unavailable bed | Select already occupied bed | Error shown, allocation blocked | ☐ | |
| STU-17 | Assigned Students page | Open Assigned Students | Only assigned records listed | ☐ | |
| STU-18 | Unassigned Students page | Open Unassigned Students | Only unassigned records listed | ☐ | |
| STU-19 | Unassigned bulk activate | Select rows and run Activate Selected | Selected students activated | ☐ | |

---

## 4.4 Bulk upload students

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| IMP-01 | Open Bulk Upload Students | Open menu item | Import jobs list opens | ☐ | |
| IMP-02 | Start import screen access | Open upload screen | Upload form visible | ☐ | |
| IMP-03 | Download template | Click template download | Template file downloads | ☐ | |
| IMP-04 | Upload invalid file type | Upload unsupported file | Validation error shown | ☐ | |
| IMP-05 | Dry run valid file | Upload valid file and submit | Dry run job created | ☐ | |
| IMP-06 | View dry-run summary | Open import job | Counts and errors visible | ☐ | |
| IMP-07 | Commit enabled only for DryRunOK | Check commit button visibility | Visible only when dry run is OK | ☐ | |
| IMP-08 | Commit successful import | Click Commit on valid job | Job moves to queued/completed flow | ☐ | |

---

## 4.5 Room and allocation

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| ROOM-01 | Open Room Overview | Go to Room & Allocation > Room Overview | Room list loads | ☐ | |
| ROOM-02 | Room search | Search by room number | Matching room appears | ☐ | |
| ROOM-03 | Filter by hostel | Apply hostel filter | Correct rows shown | ☐ | |
| ROOM-04 | Filter by floor | Apply floor filter | Correct rows shown | ☐ | |
| ROOM-05 | Filter by type | Apply type filter | Correct rows shown | ☐ | |
| ROOM-06 | Occupancy column logic | Check occupancy values | Values displayed as occupied/total | ☐ | |
| ROOM-07 | Room view action | Click View | Room details open | ☐ | |
| ROOM-08 | Room edit action | Edit room and save | Changes persist for editable fields | ☐ | |
| ROOM-09 | Assigned Rooms page | Open Assigned Rooms | Only occupied rooms shown | ☐ | |
| ROOM-10 | Unassigned Rooms page | Open Unassigned Rooms | Only unoccupied rooms shown | ☐ | |
| ROOM-11 | Create room lock after activation | Try create room on active tenant | Creation blocked with lock message | ☐ | |

---

## 4.6 Checklist module

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| CHK-01 | Checklist menu visibility | Check sidebar | Checklist group shown when module enabled | ☐ | |
| CHK-02 | Open checklist configuration | Open Checklist Configuration | Role picker and tasks form shown | ☐ | |
| CHK-03 | Change role template | Switch role | Role-specific tasks load | ☐ | |
| CHK-04 | Add checklist task | Add item and save | Task persists after refresh | ☐ | |
| CHK-05 | Duplicate/invalid task code validation | Use invalid code format | Validation blocks save | ☐ | |
| CHK-06 | Save checklist active flag | Toggle active and save | State persists | ☐ | |
| CHK-07 | Open My Daily Checklist | Open page | Today's checklist appears | ☐ | |
| CHK-08 | Toggle checklist item | Mark item done/undo | Progress updates | ☐ | |
| CHK-09 | Submit incomplete checklist | Try submit before all done | Submission blocked with warning | ☐ | |
| CHK-10 | Submit completed checklist | Complete all and submit | Submitted successfully | ☐ | |
| CHK-11 | Open Staff Checklists | Open list page | Staff checklist table loads | ☐ | |
| CHK-12 | Staff checklist date filter | Apply date range | Correct results shown | ☐ | |
| CHK-13 | Staff checklist role filter | Apply role filter | Correct results shown | ☐ | |
| CHK-14 | Approve staff checklist | Open submitted checklist and approve | Review status becomes Approved | ☐ | |
| CHK-15 | Send back staff checklist | Send back with note | Review status becomes SentBack | ☐ | |

---

## 4.7 Requests

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| REQ-01 | Open Delayed Requests | Go to Requests > Delayed Requests | List loads | ☐ | |
| REQ-02 | Delayed badge | Check menu badge | Badge shown when overdue items exist | ☐ | |
| REQ-03 | Open Housekeeping | Requests > Housekeeping | List loads | ☐ | |
| REQ-04 | Housekeeping status filter | Apply status filter | Correct rows shown | ☐ | |
| REQ-05 | Housekeeping view modal | Click View | Request details modal opens | ☐ | |
| REQ-06 | Open Repair & Maintenance | Requests > Repair & Maintenance | List loads | ☐ | |
| REQ-07 | Maintenance status filter | Apply status filter | Correct rows shown | ☐ | |
| REQ-08 | Maintenance view modal | Click View | Modal shows complete details | ☐ | |
| REQ-09 | Open Outpass requests | Requests > Outpass | List loads | ☐ | |
| REQ-10 | Outpass status mapping | Check multiple statuses | Labels map correctly (Pending/Approved/Rejected/etc.) | ☐ | |
| REQ-11 | Outpass view modal | Click View | Modal opens with student and date/time details | ☐ | |
| REQ-12 | Open Leave requests | Requests > Leave | List loads | ☐ | |
| REQ-13 | Leave status filter | Filter by status | Correct rows shown | ☐ | |
| REQ-14 | Leave view modal | Click View | Modal shows leave dates and reason | ☐ | |
| REQ-15 | Open Guest Entry | Requests > Guest Entry | List loads | ☐ | |
| REQ-16 | Guest entry status filter | Apply status filter | Correct rows shown | ☐ | |
| REQ-17 | Guest entry view modal | Click View | Guest detail modal opens | ☐ | |
| REQ-18 | Open Room Change Requests | Requests > Room Change Requests | List loads | ☐ | |
| REQ-19 | Room change approve action | Approve pending request with bed | Request moves to approved | ☐ | |
| REQ-20 | Room change reject action | Reject pending request with reason | Request moves to rejected | ☐ | |
| REQ-21 | Sports requests screen (if enabled) | Open Requests > Sports | Sports booking list loads | ☐ | |

---

## 4.8 Communications (Comm Box)

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| COM-01 | Open Comm Box list | Go to Communications > Comm Box | List loads | ☐ | |
| COM-02 | Create notice required validation | Try submit blank form | Validation error shown | ☐ | |
| COM-03 | Create draft notice | Fill form, keep Draft, save | Draft appears in list | ☐ | |
| COM-04 | Schedule notice | Set publish_at and save | Status shows Scheduled | ☐ | |
| COM-05 | Publish now from list | Use Publish Now action | Status changes to Published | ☐ | |
| COM-06 | Audience targeting | Create students/staff/both notices | Audience badge correct in table | ☐ | |
| COM-07 | Channel flags | Select push/email channels | Channel icons reflect selection | ☐ | |
| COM-08 | Hostel filter | Apply hostel filter in list | Correct notices shown | ☐ | |
| COM-09 | View notice details | Click View | Details page shows content and schedule | ☐ | |
| COM-10 | Edit notice | Edit and save | Update persists | ☐ | |

---

## 4.9 Emergency

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| EMG-01 | Open Medical page | Emergency > Medical | List loads | ☐ | |
| EMG-02 | Medical view modal | Click View on record | Modal opens with details | ☐ | |
| EMG-03 | Medical acknowledge | Click Acknowledge | Record acknowledged and success toast shown | ☐ | |
| EMG-04 | Open Incidents page | Emergency > Incidents | List loads | ☐ | |
| EMG-05 | Incident view modal | Click View | Modal opens with incident type and note | ☐ | |
| EMG-06 | Incident acknowledge | Click Acknowledge | Record acknowledged and toast shown | ☐ | |
| EMG-07 | Auto-refresh behavior | Wait on page for polling interval | New/emergency updates appear automatically | ☐ | |

---

## 4.10 Operations

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| OPS-01 | Open My Staff | Operations > My Staff | Staff list loads | ☐ | |
| OPS-02 | My Staff role scope | Check role names | Only expected operational roles shown | ☐ | |
| OPS-03 | Open Hostels | Operations > Hostels | Hostel list loads | ☐ | |
| OPS-04 | Edit hostel timings | Edit curfew/visiting values and save | Values persist | ☐ | |
| OPS-05 | Overnight toggle | Toggle overnight and save | Change persists (if addon allowed) | ☐ | |
| OPS-06 | Create hostel lock after activation | Try create hostel | Blocked with lock message | ☐ | |
| OPS-07 | Open Reports | Operations > Reports | Report form loads | ☐ | |
| OPS-08 | Report type mandatory | Click download without type | Validation/toast shown | ☐ | |
| OPS-09 | Download housekeeping report | Select type/date/format and download | File downloads | ☐ | |
| OPS-10 | Download attendance detail report | Select attendance_detail and download | File downloads with detail rows | ☐ | |
| OPS-11 | No-data report behavior | Use empty date range | Warning shown for no data | ☐ | |

---

## 4.11 Optional module checks

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| MOD-01 | Laundry module visibility | Check menu when laundry addon enabled | Laundry module visible as per config | ☐ | |
| MOD-02 | Sports module visibility | Check menu when sports addon enabled | Sports modules visible as per config | ☐ | |
| MOD-03 | Gate module visibility | Check menu when gate module enabled | Gate entries module visible by role | ☐ | |
| MOD-04 | Hidden pages not shown in sidebar | Check sidebar for deprecated pages | Hidden resources are not listed | ☐ | |

---

## 4.12 Data security and tenant isolation

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| SEC-01 | Tenant-scoped data in students | Login under tenant subdomain and view students | Only tenant students visible | ☐ | |
| SEC-02 | Tenant-scoped data in requests | Open all request pages | No cross-tenant records | ☐ | |
| SEC-03 | Tenant-scoped notices | Open Comm Box | Only tenant notices visible | ☐ | |
| SEC-04 | Role-restricted actions | Login as non-campus role (if possible) | Unauthorized actions blocked | ☐ | |

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

