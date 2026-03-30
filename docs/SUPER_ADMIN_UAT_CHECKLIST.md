# MAP HMS - Super Admin UAT Checklist

**Version:** 1.0  
**Prepared on:** February 16, 2026  
**Role under test:** Super Admin  
**Environment:** __________________________  
**Test URL:** `https://admin.mapservices.in/admin` (or UAT URL)  
**Tested by:** __________________________  
**Date:** __________________________

---

## 1. How to use this checklist

For each row:
1. Perform the action in the app.
2. Compare with expected result.
3. Mark status.

Status options:
- ✅ Pass
- ❌ Fail
- ⏸ Not Tested

Use plain remarks like:
- “Button not visible”
- “Wrong error message”
- “Data not saved”

---

## 2. Pre-UAT readiness (must complete before testing)

| # | Check | Expected | Status | Remarks |
|---|---|---|---|---|
| P-01 | Super Admin account available | Login credentials are working | ☐ | |
| P-02 | At least one demo tenant exists | Tenant visible in All Tenants | ☐ | |
| P-03 | Unassigned staff pool exists | Unassigned Staff list has records | ☐ | |
| P-04 | Amenities master data exists | Amenities page opens and shows data | ☐ | |
| P-05 | Browser and internet stable | No random timeout/login drops | ☐ | |
| P-06 | Test users and phones prepared | Rector/CM/staff phone numbers available | ☐ | |

---

## 3. Test data sheet (fill before execution)

| Data Item | Value to use in UAT |
|---|---|
| Tenant Name | __________________ |
| Tenant Code (`MAP-...`) | __________________ |
| Subdomain | __________________ |
| Campus Name | __________________ |
| Hostel 1 Name/Code | __________________ |
| Hostel 2 Name/Code | __________________ |
| Rector Name/Phone | __________________ |
| College Mgmt Name/Phone | __________________ |
| Campus Manager Name/Phone | __________________ |
| Warden Name/Phone | __________________ |
| Guard Name/Phone | __________________ |
| HK Supervisor Name/Phone | __________________ |
| RM Supervisor Name/Phone | __________________ |
| Laundry Manager Name/Phone | __________________ |
| Sports Manager Name/Phone | __________________ |

---

## 4. UAT test cases

## 4.1 Authentication and access

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| AUTH-01 | Login success | Open login page, enter valid credentials, sign in | Dashboard opens successfully | ☐ | |
| AUTH-02 | Invalid password | Enter wrong password | Login blocked with clear error | ☐ | |
| AUTH-03 | Password reset link | Use forgot password flow | Reset flow starts without error | ☐ | |
| AUTH-04 | Super Admin only access | Try non-super-admin user on admin panel | Access denied | ☐ | |
| AUTH-05 | Logout | Click logout from profile menu | User returned to login page | ☐ | |
| AUTH-06 | Session persistence | Refresh browser after login | User stays logged in | ☐ | |
| AUTH-07 | Session timeout behavior | Stay idle and retry action later | User is either still valid or redirected safely | ☐ | |
| AUTH-08 | Unauthorized URL access | Open a protected URL without login | Redirect to login page | ☐ | |

---

## 4.2 Dashboard

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| DASH-01 | Dashboard opens | Click Dashboard in menu | Dashboard loads fully | ☐ | |
| DASH-02 | Greeting card | Check greeting block | Greeting, name, date visible | ☐ | |
| DASH-03 | Quick action link | Click `+ New Tenant` | Opens tenant onboarding flow | ☐ | |
| DASH-04 | KPI cards visible | Check KPI cards row | All cards show values (no crash) | ☐ | |
| DASH-05 | KPI card values logical | Compare values with known data | Counts are reasonable | ☐ | |
| DASH-06 | Students by Tenant chart | Open chart area | Chart renders with tenant labels | ☐ | |
| DASH-07 | Tenant status distribution | Open chart area | Donut chart renders | ☐ | |
| DASH-08 | Global occupancy chart | Open chart area | Occupied/available bars visible | ☐ | |
| DASH-09 | Requests overview widget | Open stats block | Pending/In Progress/Completed/Overdue visible | ☐ | |
| DASH-10 | Footer charts | Scroll to bottom | Attendance trend and tickets-by-priority charts visible | ☐ | |

---

## 4.3 Tenant onboarding wizard (critical)

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| ONB-01 | Open wizard | Go to Tenant Management > New Tenant Onboarding | Wizard opens with progress bar | ☐ | |
| ONB-02 | Step 1 mandatory checks | Leave required fields blank and continue | Validation errors shown clearly | ☐ | |
| ONB-03 | Tenant code format check | Enter invalid code (not starting MAP-) | Code rejected with message | ☐ | |
| ONB-04 | Tenant code uniqueness | Reuse existing tenant code | Duplicate validation shown | ☐ | |
| ONB-05 | Logo upload valid | Upload small JPG/PNG | Upload accepted | ☐ | |
| ONB-06 | Logo upload invalid | Upload unsupported file | Error shown | ☐ | |
| ONB-07 | Rector phone format | Enter non-10-digit value | Validation blocks save/next | ☐ | |
| ONB-08 | College Mgmt phone format | Enter non-10-digit value | Validation blocks save/next | ☐ | |
| ONB-09 | Draft auto-creation after step 1 | Complete step 1 | Tenant draft created in provisioning | ☐ | |
| ONB-10 | Step 2 hostel minimum | Try without hostel | Validation: at least one hostel needed | ☐ | |
| ONB-11 | Hostel type options | Check hostel type dropdown | Boys/Girls/Co-Ed options shown | ☐ | |
| ONB-12 | Hostel code format | Enter invalid hostel code | Validation shown | ☐ | |
| ONB-13 | Curfew required | Leave curfew empty | Validation shown | ☐ | |
| ONB-14 | Multiple hostels | Add second hostel | Both hostels saved properly | ☐ | |
| ONB-15 | Step 3 campus manager required | Skip Campus Manager and continue | Validation shown | ☐ | |
| ONB-16 | Step 3 mandatory roles | Skip Warden/Guard/HK/RM | Validation shown | ☐ | |
| ONB-17 | Optional roles behavior | Leave laundry/sports blank | Wizard allows continue | ☐ | |
| ONB-18 | Staff picker from unassigned pool | Open role dropdown | Unassigned staff selectable | ☐ | |
| ONB-19 | Hostel-wise assignment mapping | Assign roles per hostel | Each hostel assignment retained correctly | ☐ | |
| ONB-20 | Step 4 room config required | Skip room config and attempt activation | Activation blocked by preflight | ☐ | |
| ONB-21 | Room config generation | Define floors, capacity, room count | Rooms and beds generated | ☐ | |
| ONB-22 | Step 5 amenities selection | Select amenities and save | Selection persists | ☐ | |
| ONB-23 | Save Draft action | Click Save Draft | Draft saved success message | ☐ | |
| ONB-24 | Save and Exit action | Click Save & Exit | Redirects to tenants list; progress saved | ☐ | |
| ONB-25 | Resume draft | Reopen onboarding tenant | Previous data present | ☐ | |
| ONB-26 | Confirmation checkbox required | Do not tick confirmation and activate | Activation blocked | ☐ | |
| ONB-27 | Pre-flight failures visible | Trigger missing data and activate | Clear preflight error shown | ☐ | |
| ONB-28 | Successful activation | Complete all steps and activate | Tenant status becomes Active | ☐ | |
| ONB-29 | Success notification details | After activation | Notification shows created users/subdomain info | ☐ | |
| ONB-30 | Redirect after activation | Activation success | Returns to All Tenants list | ☐ | |

---

## 4.4 All Tenants and tenant details

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| TEN-01 | Open All Tenants list | Tenant Management > All Tenants | List loads | ☐ | |
| TEN-02 | Search by code | Search using tenant code | Matching row shown | ☐ | |
| TEN-03 | Search by name | Search using tenant name | Matching row shown | ☐ | |
| TEN-04 | Filter active | Apply active filter | Only active tenants shown | ☐ | |
| TEN-05 | Filter archived | Apply archived filter | Archived records shown | ☐ | |
| TEN-06 | Export tenants | Click Export | CSV downloads | ☐ | |
| TEN-07 | Open tenant view | Click View action | Tenant detail screen opens | ☐ | |
| TEN-08 | Edit tenant fields | Click Edit and update editable fields | Save successful | ☐ | |
| TEN-09 | Active tenant locked fields | Try editing locked fields like code/name on active | Locked behavior enforced | ☐ | |
| TEN-10 | Branding image on view | Check tenant logo in detail view | Logo shows or fallback appears | ☐ | |
| TEN-11 | Contact/address visibility | View contact section | Data visible correctly | ☐ | |
| TEN-12 | Hostels relation tab | Open Hostels relation | Existing hostels listed | ☐ | |
| TEN-13 | Add hostel from relation | Click Add Hostel and submit | New hostel added with rooms/beds | ☐ | |
| TEN-14 | Hostel rooms shortcut | Click Rooms action | Opens hostel listing context | ☐ | |
| TEN-15 | Campuses relation tab | Open Campuses relation | Campuses listed | ☐ | |
| TEN-16 | Add campus | Create campus record | Campus created successfully | ☐ | |
| TEN-17 | Edit campus | Edit campus details | Save successful | ☐ | |
| TEN-18 | Delete campus | Delete campus | Record deleted | ☐ | |
| TEN-19 | Provisioning-only activate button | Open provisioning tenant | Activate button visible | ☐ | |
| TEN-20 | Activate button hidden for active tenant | Open active tenant | Activate button not shown | ☐ | |
| TEN-21 | Archived tenants page | Open Archived Tenants | Read-only archive list loads | ☐ | |
| TEN-22 | Archived tenant view | Open archived tenant record | Details visible, no edit/delete | ☐ | |

---

## 4.5 Staff management (critical)

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| STF-01 | Open All Staff | Staff Management > All Staff | List loads | ☐ | |
| STF-02 | Create new staff | Click Create New Staff and submit valid data | Staff created | ☐ | |
| STF-03 | Create without tenant | Leave tenant blank while creating | Staff appears as unassigned | ☐ | |
| STF-04 | Duplicate phone validation | Create second user with same phone | Validation error shown | ☐ | |
| STF-05 | Duplicate email validation | Create second user with same email | Validation error shown | ☐ | |
| STF-06 | View staff profile | Click View action | Profile details visible | ☐ | |
| STF-07 | Edit staff profile | Click Edit and save | Changes saved | ☐ | |
| STF-08 | Activate/deactivate toggle | Use toggle action twice | Status changes correctly each time | ☐ | |
| STF-09 | Assign action open | Click Assign/Reassign | Assignment modal opens | ☐ | |
| STF-10 | Assign valid flow | Select tenant, role, hostel and submit | Assignment success message shown | ☐ | |
| STF-11 | Cross-tenant reassignment | Reassign same person to new tenant | Old assignment revoked, new assignment active | ☐ | |
| STF-12 | Role change during assign | Change role while assigning | New role saved correctly | ☐ | |
| STF-13 | Assign without tenant | Try save without tenant | Validation blocks action | ☐ | |
| STF-14 | Assign without hostel | Try save without hostel | Validation blocks action | ☐ | |
| STF-15 | Assign duplicate role-hostel | Try assigning same role to same hostel with another person | Duplicate blocked with clear message | ☐ | |
| STF-16 | Revoke assignment success | Revoke a non-critical assignment | Assignment revoked | ☐ | |
| STF-17 | Revoke only-role protection | Revoke only person in role at hostel | Blocked with replacement-required message | ☐ | |
| STF-18 | Assignment history | Open Assignment History action | Historical records shown | ☐ | |
| STF-19 | Tenant filter | Filter by tenant | Only selected tenant staff shown | ☐ | |
| STF-20 | Role filter | Filter by role | Only selected role shown | ☐ | |
| STF-21 | Assignment status filter | Filter assigned/unassigned | Correct subset shown | ☐ | |
| STF-22 | Active status ternary filter | Active only and inactive only | Correct subset shown | ☐ | |
| STF-23 | Assigned hostel column | Check assigned hostel values | Correct hostel names shown | ☐ | |
| STF-24 | Since column | Check assigned since date | Date shown correctly | ☐ | |
| STF-25 | Assigned Staff page | Open Assigned Staff menu | Assigned-only list shown | ☐ | |
| STF-26 | Assigned Staff export | Click export on Assigned Staff | CSV downloads | ☐ | |
| STF-27 | Unassigned Staff page | Open Unassigned Staff menu | Unassigned-only list shown | ☐ | |
| STF-28 | Create from Unassigned page | Use create action there | Record appears in unassigned list | ☐ | |
| STF-29 | Archived Staff page | Open Archived Staff menu | Read-only archived list shown | ☐ | |
| STF-30 | Archived Staff view | Open archived record | Details visible | ☐ | |

---

## 4.6 Operations

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| OPS-01 | Open Amenities | Operations > Amenities | Page loads | ☐ | |
| OPS-02 | Create amenity | Add key + label and save | Record created | ☐ | |
| OPS-03 | Amenity key validation | Use invalid key format | Validation error shown | ☐ | |
| OPS-04 | Amenity unique key | Reuse existing key | Duplicate blocked | ☐ | |
| OPS-05 | Edit amenity | Update label and save | Updated successfully | ☐ | |
| OPS-06 | Delete amenity | Delete row | Record removed | ☐ | |
| OPS-07 | Open Hostels cross-tenant view | Operations > Hostels | List loads with tenant context | ☐ | |
| OPS-08 | Hostel filters | Filter by tenant and gender mode | Correct records shown | ☐ | |
| OPS-09 | Hostel occupancy value | Verify occupancy shows in percent | Logical value shown | ☐ | |
| OPS-10 | Hostel view details | Open View action | Hostel detail visible | ☐ | |
| OPS-11 | Open Students cross-tenant view | Operations > Students | Student list loads | ☐ | |
| OPS-12 | Student search | Search by name/ID | Matching records shown | ☐ | |
| OPS-13 | Student view details | Open student View action | Full student profile visible | ☐ | |
| OPS-14 | Students read-only protection | Check create/edit/delete | No write actions available | ☐ | |
| OPS-15 | Campuses view (if visible) | Open campuses menu | Cross-tenant campus list loads | ☐ | |
| OPS-16 | Campus filter | Filter campuses by tenant | Correct data shown | ☐ | |
| OPS-17 | Campus view action | Open campus record | Details visible | ☐ | |

---

## 4.7 Reports

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| REP-01 | Open Report Center | Reports > Report Center | Page loads | ☐ | |
| REP-02 | Mandatory report type | Click download without type | Validation/warning shown | ☐ | |
| REP-03 | Tenant Overview report | Select type + date range + download | File downloads | ☐ | |
| REP-04 | Occupancy report | Select occupancy report and download | File downloads | ☐ | |
| REP-05 | Student export report | Select student export and download | File downloads | ☐ | |
| REP-06 | Staff deployment report | Select staff deployment and download | File downloads | ☐ | |
| REP-07 | Attendance report | Select attendance and download | File downloads | ☐ | |
| REP-08 | Incident report | Select incidents and download | File downloads | ☐ | |
| REP-09 | Date range with no data | Use an empty data period | Warning “No data found” | ☐ | |
| REP-10 | Report Center error handling | Force invalid state/date | Friendly failure message shown | ☐ | |
| REP-11 | Reports page visibility | Open Reports menu item | Page opens (if enabled) | ☐ | |
| REP-12 | Reports feature flag behavior (backend-controlled) | Ask tech team to disable reports feature flag and retest access | Reports page visibility follows backend flag | ☐ | |

---

## 4.8 Settings

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| SET-01 | Open Settings page | Settings > Settings | Page loads with all sections | ☐ | |
| SET-02 | Toggle onboarding flag | Change Onboarding Wizard flag and save | Save success message shown | ☐ | |
| SET-03 | Toggle staff management flag | Change flag and save | Save success message shown | ☐ | |
| SET-04 | Toggle SMS events flag | Change flag and save | Save success message shown | ☐ | |
| SET-05 | Toggle MFA flag | Change flag and save | Save success message shown | ☐ | |
| SET-06 | Subscription defaults save | Change plan/duration and save | Values saved | ☐ | |
| SET-07 | Trial period field save | Change trial days and save | Value saved | ☐ | |
| SET-08 | Integration values save | Update MSG91/S3 values and save | Values saved successfully | ☐ | |
| SET-09 | Maintenance mode ON | Enable maintenance + message and save | Success message shown | ☐ | |
| SET-10 | Maintenance mode OFF | Disable maintenance and save | Success message shown | ☐ | |
| SET-11 | Advanced key-value save | Add custom key/value and save | Saved without error | ☐ | |
| SET-12 | Save confirmation modal | Click save | Confirmation modal appears | ☐ | |
| SET-13 | Clear Cache action | Click Clear Cache | Cache cleared success message | ☐ | |
| SET-14 | Save failure handling | Simulate invalid/inaccessible state | Friendly error message appears | ☐ | |

---

## 4.9 Advanced optional tests

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| ADV-01 | Impersonation start (if enabled) | Start tenant impersonation flow | Switches context with warning banner | ☐ | |
| ADV-02 | Impersonation banner | While impersonating | Yellow impersonation banner visible | ☐ | |
| ADV-03 | Stop impersonation | Click Stop Impersonation | Returns to Super Admin dashboard | ☐ | |
| ADV-04 | Hidden Requests page (direct URL) | Open `/admin/requests` resource route if shared by team | Page opens for Super Admin only | ☐ | |
| ADV-05 | Hidden Communications page (direct URL) | Open `/admin/communications` resource route if shared by team | Page opens for Super Admin only | ☐ | |

---

## 4.10 Non-functional sanity checks

| ID | Test scenario | Steps | Expected result | Status | Remarks |
|---|---|---|---|---|---|
| NFR-01 | Page load speed | Open key pages (Dashboard, Tenants, Staff) | Each loads in acceptable time | ☐ | |
| NFR-02 | No crash on empty data | Test with empty list pages | Graceful empty state shown | ☐ | |
| NFR-03 | No duplicate submission on double click | Double click save/activate rapidly | No duplicate records created | ☐ | |
| NFR-04 | Browser refresh resilience | Refresh while on list/detail pages | Page remains stable | ☐ | |
| NFR-05 | Friendly validation text | Trigger common form errors | Messages are understandable for non-tech users | ☐ | |
| NFR-06 | CSV opens correctly | Open downloaded CSV in Excel/Sheets | Data readable and columns aligned | ☐ | |

---

## 5. UAT defect log template

Use this for every failed test.

| Field | Value |
|---|---|
| Test ID | __________________ |
| Module | __________________ |
| Summary | __________________ |
| Steps to reproduce | __________________ |
| Actual result | __________________ |
| Expected result | __________________ |
| Severity (Critical/High/Medium/Low) | __________________ |
| Screenshot/video path | __________________ |
| Reported by | __________________ |
| Reported date | __________________ |

---

## 6. UAT sign-off

## 6.1 Summary

| Metric | Value |
|---|---|
| Total test cases executed | __________ |
| Passed | __________ |
| Failed | __________ |
| Not tested | __________ |
| Critical open defects | __________ |

## 6.2 Decision

- [ ] UAT Approved for production rollout
- [ ] UAT Conditionally Approved (minor fixes pending)
- [ ] UAT Rejected (major fixes required)

## 6.3 Approval

| Role | Name | Signature | Date |
|---|---|---|---|
| Business Owner | __________________ | __________________ | __________________ |
| Operations Lead | __________________ | __________________ | __________________ |
| Product/Implementation Team | __________________ | __________________ | __________________ |

---

## 7. Recommended execution order for client users

Run in this sequence for easiest UAT:
1. Authentication
2. Tenant onboarding
3. Tenant list/detail checks
4. Staff management
5. Operations views
6. Reports
7. Settings
8. Advanced optional checks

This order reduces confusion and makes issue diagnosis faster.
