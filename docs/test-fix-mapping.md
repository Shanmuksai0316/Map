# QA Test Fix Mapping

This document maps each in-scope failing QA ID to backend/frontend touchpoints and automated coverage.

## RM Supervisor

- **RM-007 (Logo fallback)**
  - Frontend: `mobile/src/shared/components/CollegeLogo.tsx`
  - Frontend consumer: `mobile/src/staff/screens/rm-supervisor/RMSupervisorDashboard.tsx`
  - Coverage: existing dashboard render tests + runtime fallback behavior through `CollegeLogo`.
- **RM-023 (Open -> In Progress)**
  - Frontend: `mobile/src/staff/screens/rm-supervisor/RMSupervisorRequestsScreen.tsx`
  - Backend: `api/app/Http/Controllers/TicketController.php`, `api/app/Http/Requests/TicketStatusRequest.php`, `api/app/Policies/TicketPolicy.php`
  - Coverage: `api/tests/Feature/Tickets/TicketLifecycleTest.php`
- **RM-024 (In Progress -> Complete/Resolved)**
  - Frontend: `mobile/src/staff/screens/rm-supervisor/RMSupervisorRequestsScreen.tsx`
  - Backend: `api/app/Domain/Tickets/Models/Ticket.php`, `api/app/Policies/TicketPolicy.php`
  - Coverage: `api/tests/Feature/Tickets/TicketLifecycleTest.php`
- **RM-027 (Checklist submit/image view)**
  - Frontend: `mobile/src/staff/screens/shared/SupervisorChecklistDetailScreen.tsx`, `mobile/src/staff/components/ChecklistItemRow.tsx`
  - Backend: `api/app/Http/Controllers/ChecklistsController.php`
  - Seed support: `api/database/seeders/TestingBaselineSeeder.php`

## HK Supervisor

- **HK-023 (Open -> In Progress)** / **HK-024 (In Progress -> Complete/Resolved)**
  - Frontend: `mobile/src/staff/screens/hk-supervisor/HKSupervisorRequestsScreen.tsx`
  - Backend: `api/app/Policies/TicketPolicy.php`, `api/app/Http/Controllers/TicketController.php`
  - Coverage: `api/tests/Feature/Tickets/TicketLifecycleTest.php`
- **HK-026 / HK-027 / HK-028 (Checklist validation/task completion/submit)**
  - Frontend: `mobile/src/staff/screens/shared/SupervisorChecklistDetailScreen.tsx`, `mobile/src/staff/components/ChecklistItemRow.tsx`
  - Backend: `api/app/Http/Controllers/ChecklistsController.php`
  - Seed support: `api/database/seeders/TestingBaselineSeeder.php`

## Warden

- **WA-017 (Attendance header overlap)**
  - Frontend: `mobile/src/staff/screens/warden/WardenAttendanceScreen.tsx`
  - Coverage: manual UI verification + existing warden screen tests.
- **WA-026 (Attendance submit success)**
  - Frontend: `mobile/src/staff/screens/warden/WardenAttendanceScreen.tsx`
  - Backend: `api/app/Http/Controllers/Api/V1/Staff/WardenController.php`
- **WA-032 (Search by phone)**
  - Frontend: `mobile/src/staff/screens/warden/WardenStudentsScreen.tsx`
  - Utility: `mobile/src/shared/utils/phone-search.util.ts`
  - Coverage: `mobile/src/shared/utils/__tests__/phone-search.util.test.ts`
- **WA-036 (Operational flow E2E blockers)**
  - Backend seed support: `api/database/seeders/TestingBaselineSeeder.php` (attendance/checklist/emergency fixtures)
  - Frontend attendance/search fixes listed above.

## Guard

- **GU-022 / GU-023 / GU-024 / GU-025 (Checklist load, task completion, submit)**
  - Frontend: `mobile/src/shared/store/checklist.store.ts`, `mobile/src/staff/screens/guard/GuardChecklistScreen.tsx`
  - Backend: `api/app/Http/Controllers/Api/V1/Guard/ChecklistController.php`
  - Seed support: `api/database/seeders/TestingBaselineSeeder.php` (Guard checklist template/tasks)
- **GU-034 (Operational flow E2E blocker)**
  - Seed support: `api/database/seeders/TestingBaselineSeeder.php` (guard checklist + gate movement fixtures)

## Laundry Manager

- **LA-026 (Filter behavior/Mark Ready visibility)**
  - Frontend: `mobile/src/staff/screens/laundry-manager/LaundryRequestListScreen.tsx`, `mobile/src/staff/screens/laundry-manager/LaundryRequestDetailScreen.tsx`
  - Backend: `api/app/Enums/LaundryRequestStatus.php`, `api/app/Http/Controllers/Api/V1/Mobile/LaundryController.php`
- **LA-028 (Change status action)** / **LA-030 (Mark Ready action)**
  - Frontend: `mobile/src/staff/screens/laundry-manager/LaundryRequestDetailScreen.tsx`
  - Backend: `api/app/Http/Controllers/Api/V1/Mobile/LaundryController.php`
- **LA-031 (Verify pickup valid code)** / **LA-032 (Invalid pickup code)**
  - Backend: `api/app/Http/Controllers/Api/V1/Mobile/LaundryController.php` (`verifyCode`)
  - Coverage: `api/tests/Feature/Laundry/MobileLaundryRequestFlowTest.php`
- **LA-033 (Manual verify fallback)**
  - Backend: `api/app/Http/Controllers/Api/V1/Mobile/LaundryController.php` (`manualVerify`)
  - Routes: `api/routes/api.php`
  - Coverage: `api/tests/Feature/Laundry/MobileLaundryRequestFlowTest.php`
- **LA-035 (Laundry lifecycle E2E blocker)**
  - Backend transition fixes + seed data in `api/database/seeders/TestingBaselineSeeder.php`
