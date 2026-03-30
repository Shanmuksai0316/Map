# UAT Playwright Report (Super Admin)
Date: 2026-02-23
Environment: https://admin.mapservices.in/admin
Credentials: super@demo.map.ac.in

Legend: `PASS` = validated in UI, `FAIL` = reproducible defect, `BLOCKED` = cannot execute due missing prerequisite/flow, `N/A` = intentionally removed scope.

| Test ID | Status | Comments |
|---|---|---|
| ONB-05 | PASS | Valid image upload accepted in onboarding flow (previous run, revalidated in same environment). |
| ONB-19 | PASS | Hostel-wise assignment mapping retained: selected `hostel_id` + role-user IDs persisted in Livewire state and remained intact after step transitions. |
| ONB-20 | PASS | Activation path is blocked while room configuration is missing; validation summary shows `Configure rooms in Step 4`. |
| ONB-21 | PASS | Room config (`floor_number=1`, `room_capacity=2`, `room_count=3`) generated successfully: verified in DB as `rooms=3`, `beds=6` for activated tenant hostels. |
| ONB-22 | PASS | Amenities selection persisted (`wifi`, `gym`): verified on activated hostel relations in DB. |
| ONB-23 | PASS | `Save Draft` click shows success toast: `Draft saved - Your progress has been saved`. |
| ONB-24 | PASS | `Save & Exit` redirects to `/admin/tenants`; onboarding progress remains resumable. |
| ONB-26 | PASS | Activation without confirmation checkbox shows warning toast (`Confirm activation first`) and blocks activation. |
| ONB-27 | PASS | Preflight/validation failures are clearly visible in validation summary block. |
| ONB-28 | PASS | Successful activation validated for provisioning tenants; tenant status moved to `active`. |
| ONB-29 | PASS | Success notification includes details: created OTP users and campus-manager subdomain URL. |
| ONB-30 | PASS | After activation, flow redirects to `/admin/tenants` (All Tenants list). |
| TEN-06 | PASS | Export works and downloads CSV (`tenants.csv`) after replacing Excel facade dependency. |
| TEN-12 | PASS | Hostels relation tab opens and lists data. |
| TEN-14 | PASS | Rooms shortcut opens expected hostel-room context. |
| TEN-15 | PASS | Campuses relation tab opens. |
| TEN-16 | PASS | Campus create now works from tenant view (`New Campus`) with `Campus created` confirmation. |
| TEN-17 | PASS | Campus edit now works from tenant view (`Edit Campus`) with `Campus updated` confirmation. |
| TEN-19 | PASS | Provisioning tenant shows activation pathway/action. |
| TEN-20 | PASS | Active tenant hides provisioning-only activation action. |
| TEN-21 | PASS | Archived tenants list page loads read-only. |
| TEN-22 | PASS | Archived tenant detail is viewable in read-only mode. |
| STF-06 | PASS | Initially failed with 500; fixed and deployed. Staff `View` now opens profile page correctly. |
| STF-08 | PASS | Activate/deactivate action available and verified with Deactivate->Activate on same staff record. |
| STF-09 | PASS | Assign/Reassign action opens correctly from staff list actions. |
| STF-10 | PASS | Valid assign flow succeeds with tenant+role+hostel and success notification. |
| STF-11 | PASS | Cross-tenant reassignment verified (staff moved to new tenant and active assignment updated). |
| STF-12 | PASS | Role change during assign verified (staff role updated to selected role). |
| STF-14 | PASS | Assign submit without hostel is blocked with explicit hostel-required validation. |
| STF-15 | PASS | Duplicate role-hostel assignment blocked with clear error message. |
| STF-16 | PASS | Revoke succeeds for non-critical role assignment (Assignment revoked). |
| STF-17 | PASS | Revoke is blocked for critical only-role case with replacement-required message. |
| STF-18 | PASS | Assignment History action opens and displays historical records. |
| STF-21 | PASS | Assignment status segmentation works via dedicated pages: `Assigned Staff` vs `Unassigned Staff` show expected subsets. |
| STF-26 | PASS | Assigned staff export works and downloads CSV (`staff.csv`) after replacing Excel facade dependency. |
| STF-29 | PASS | Archived Staff page loads read-only. |
| STF-30 | PASS* | Archived staff detail view works when archived rows exist; dataset had limited archived examples during this pass. |
| OPS-10 | PASS | Hostel `View` action opens details page. |
| REP-02 | PASS | Download without report type shows validation warning. |
| REP-03 | PASS | Tenant Overview report downloads successfully. |
| REP-04 | PASS | Occupancy report downloads successfully. |
| REP-05 | PASS | Student export report downloads successfully. |
| REP-06 | PASS | Staff deployment report downloads successfully. |
| REP-07 | PASS | Attendance report downloads successfully. |
| REP-08 | PASS | Incident report downloads successfully. |
| REP-10 | PASS | Invalid date/state scenarios now produce friendly validation errors (added range checks). |
| REP-12 | BLOCKED | Requires backend feature flag toggle by tech team to validate visibility behavior. |
| SET-12 | N/A | Settings removed from admin panel per request; save-confirmation modal scenario no longer applicable. |

## Fixes Applied During This Pass
1. Staff profile 500 fix (`STF-06`)
- Root cause: null state in assignment history blade (`isEmpty()` called on null).
- File fixed: `/Users/nagrajyr/Downloads/mapmars/api/resources/views/filament/infolists/staff-assignment-history-entry.blade.php`
- Deployed to production and verified.

2. Tenant campus create/edit reliability (`TEN-16`, `TEN-17`)
- Policy + tenant view actions hardened and deployed.
- Verified with successful create and edit notifications.

3. Staff action coverage restored across staff pages (`STF-08`..`STF-18`)
- Added operational actions on Assigned/Unassigned staff lists: Activate/Deactivate, Assign/Reassign, Revoke Assignment, Assignment History.
- Files fixed:
  - `/Users/nagrajyr/Downloads/mapmars/api/app/Filament/Resources/Admin/AssignedStaffResource.php`
  - `/Users/nagrajyr/Downloads/mapmars/api/app/Filament/Resources/Admin/UnassignedStaffResource.php`
- Deployed and verified via Playwright.

4. Revocation policy aligned to critical-role protection (`STF-16`, `STF-17`)
- Revoke now enforces replacement-required protection only for critical roles (`Campus Manager`, `Warden`).
- Non-critical roles can be revoked successfully.
- File fixed:
  - `/Users/nagrajyr/Downloads/mapmars/api/app/Services/StaffAssignmentService.php`
- Deployed and verified via Playwright.

5. Campus Manager selection binding fixed (`ONB-19`, `ONB-26+`)
- Root cause: Livewire entangle path `data.staff.campus_manager_id` was missing in initial state, so UI selection looked selected but backend state remained `null`.
- Fix: initialized nested wizard defaults (`staff.campus_manager_id`, `staff.hostel_assignments`, `room_config`, `amenities`, `activation`).
- File fixed:
  - `/Users/nagrajyr/Downloads/mapmars/api/app/Filament/Pages/Admin/TenantOnboardingWizard.php`

6. Activate button reactivity fixed (`ONB-26`, `ONB-28`, `ONB-30`)
- Root cause: custom Blade footer used a non-reactive `:disabled` expression and remained disabled even after confirmation checkbox was checked.
- Fix: removed static disabled binding and kept server-side gate in `activate()`; button now uses loading-state disable only.
- File fixed:
  - `/Users/nagrajyr/Downloads/mapmars/api/resources/views/filament/pages/admin/tenant-onboarding-wizard.blade.php`

7. Room config hostel mapping fixed (`ONB-21`)
- Root cause: Step 4 could persist `hostel_id=0/index` leading to unresolved hostels and skipped room generation.
- Fix: Step 4 now prioritizes persisted tenant hostels (real numeric IDs), resolves temporary keys safely, and includes legacy fallback for `0` values during processing.
- File fixed:
  - `/Users/nagrajyr/Downloads/mapmars/api/app/Filament/Pages/Admin/TenantOnboardingWizard.php`

8. Post-activation notification crash fixed (`ONB-29`, `ONB-30`)
- Root cause: `json_decode($this->tenant->data, true)` threw `TypeError` when `tenant->data` was already an array-cast value.
- Fix: replaced direct decode with existing safe helper `getTenantDataArray()`.
- File fixed:
  - `/Users/nagrajyr/Downloads/mapmars/api/app/Filament/Pages/Admin/TenantOnboardingWizard.php`

9. Preflight service lifecycle hardened (Livewire hydration)
- Root cause: injected service properties can become `null` across Livewire requests, causing intermittent `Preflight service temporarily unavailable` fallback.
- Fix: added lazy container resolution helper `getPreflightService()` and switched runtime checks to use it.
- File fixed:
  - `/Users/nagrajyr/Downloads/mapmars/api/app/Filament/Pages/Admin/TenantOnboardingWizard.php`
