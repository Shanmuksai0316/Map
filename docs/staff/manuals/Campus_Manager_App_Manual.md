# MAP HMS Mobile User Manual - Campus Manager

## Document Summary
- App: Staff App
- Role: Campus Manager
- Dashboard Title: Campus Manager
- Last Updated: February 18, 2026

## 1. Purpose In Simple Words
This guide explains Campus Manager app usage in plain language. It covers each screen, each major button, expected behavior, and common mistakes so non-technical users can work confidently and test properly.

## 2. Role Scope
- Primary responsibility: Tenant-level operations monitoring across checklists, requests, emergency, and assigned staff.
- Access is controlled by role and tenant assignment.
- Some tiles may hide automatically when feature flags are disabled for a tenant.

## 3. Start Of Day (Login And Session)
1. Open the app and enter registered mobile number.
2. Tap `Send OTP` and enter received OTP.
3. If tenant selection appears, choose the correct tenant and continue.
4. Confirm dashboard title and your role-specific tiles are correct.
5. Pull down once to refresh live counts before starting work.

## 4. UI/UX Anatomy (What You See On Screen)
### Header
- Left side shows role title or back arrow, depending on screen.
- Right side usually has notifications bell.
- Bell count badge indicates unread alerts.

### Greeting Card
- Shows greeting text based on time of day.
- Shows your name and role context.
- Shows tenant branding/logo area (or fallback if logo unavailable).

### Action Tiles
- Large, icon-based buttons for the most common role actions.
- Badge counts highlight urgent modules (pending/emergency/unread).
- Tap feedback is immediate; if screen is heavy, loading state should appear.

### Status Colors And Labels
- `Pending/Open`: usually warning color (yellow/orange).
- `In Progress`: usually info color (blue).
- `Approved/Completed/Resolved`: usually success color (green).
- `Rejected/Error`: usually red.

## 5. Dashboard Quick Actions
| Tile | What It Does | When To Use |
| --- | --- | --- |
| Checklists | Manage My Daily Checklist and review Staff Checklist history. | During normal daily flow or whenever related alert appears. |
| Requests | Monitor cross-module request queues with tabs, status filters, and search. | During normal daily flow or whenever related alert appears. |
| Comm Box | Read communication messages and announcements relevant to operations. | During normal daily flow or whenever related alert appears. |
| Emergency | Open Medical and Security incident records with unacknowledged indicators. | During normal daily flow or whenever related alert appears. |
| My Staff | View assigned staff list with role, department mapping, and activity status. | During normal daily flow or whenever related alert appears. |
| Profile | View account information and logout safely. | During normal daily flow or whenever related alert appears. |

## 6. Navigation Map
| From | Action | Opens |
| --- | --- | --- |
| Dashboard | Tap `Checklists` | `Checklists` module screen |
| Dashboard | Tap `Requests` | `Requests` module screen |
| Dashboard | Tap `Comm Box` | `Comm Box` module screen |
| Dashboard | Tap `Emergency` | `Emergency` module screen |
| Dashboard | Tap `My Staff` | `My Staff` module screen |
| Dashboard | Tap `Profile` | `Profile` module screen |
| Any screen with back arrow | Tap back icon | Previous screen |
| Dashboard header | Tap bell icon | Notifications |

## 7. Detailed Module Walkthroughs
### Module 1: Checklists
**Purpose:** Complete your own checklist and review staff completion history.

**UI Elements You Should See:**
- Top tabs: My Daily Checklist, Staff Checklist.
- Task cards show title, completion state, and required evidence indicators.
- Submit button stays disabled until all mandatory tasks are complete.
- Staff list rows show name, role, and completion summary.

**Step-by-Step:**
1. Tap Checklists from dashboard.
2. Use My Daily Checklist tab for your own tasks.
3. Open each task card and complete required inputs (comment/photo where required).
4. Tap Submit when all mandatory tasks are complete.
5. Switch to Staff Checklist to view completed staff entries and open details.

**Expected Success Result:** My checklist submission is saved; staff checklist records are visible with completed history.

**Common Error/Edge Cases:**
- No checklist configured: empty state appears.
- API 404/403 fallback routes are attempted; if still failing, show error state.

### Module 2: Requests Hub
**Purpose:** Review requests from multiple categories without role-level decision actions.

**UI Elements You Should See:**
- Horizontal category tabs (housekeeping, repair, laundry, sports, leave, outpass, guest-entry).
- Status chips (All, Pending, In Progress, Resolved).
- Search box for student/request text lookup.
- Cards show request title, requester context, and status badge color.

**Step-by-Step:**
1. Open Requests tile.
2. Switch category tabs to inspect each queue.
3. Apply status chip and/or search text to narrow results.
4. Tap any request card to open details popup.

**Expected Success Result:** Filtered list matches selected category/filter/search and details are viewable.

**Common Error/Edge Cases:**
- No results for selected filter/search shows clear empty state message.

### Module 3: Emergency + My Staff
**Purpose:** Handle emergency visibility and staffing verification.

**UI Elements You Should See:**
- Emergency tile may show badge count from unacknowledged incidents.
- Emergency categories: Medical Emergencies and Incidents.
- My Staff table includes active indicator, role badge color, and department mapping.

**Step-by-Step:**
1. Tap Emergency tile and open both categories.
2. Review incident cards and status indicators.
3. Tap My Staff tile to validate assigned team members.
4. Use search in My Staff to locate role/person quickly.

**Expected Success Result:** Emergency records and staff assignment list are visible and searchable.

**Common Error/Edge Cases:**
- If My Staff API returns no records, empty state explains missing assignment context.

## 8. UI/UX Quality Checklist For UAT Testers
- Text is readable without overlap on small screens.
- Buttons and chips are tappable without accidental touches.
- No clipped icons or broken spacing in cards and lists.
- Empty state messages are understandable and actionable.
- Loading states (refresh/spinner) appear during data fetch.
- Errors are shown in plain language, not raw technical logs.
- Pull-to-refresh works without freezing the screen.
- Back navigation always returns to expected screen.

## 9. Data Rules Users Must Understand
- If a record is not assigned to your scope/role, it may not appear.
- Some screens are view-only for specific roles.
- Status updates are role-driven; only allowed transitions are shown.
- Comm Box and Notifications can show similar content but from different entry points.

## 10. Daily SOP (Simple Routine)
1. Login and confirm role/tenant context.
2. Check notifications and urgent badge counts.
3. Complete highest-priority operational actions first.
4. Review pending queues and close actionable items.
5. Confirm end-of-day modules are submitted/updated.
6. Logout from profile after shift end.

## 11. Plain-Language Troubleshooting
- If data looks old: pull to refresh.
- If screen is blank: go back once and reopen module.
- If action fails: check internet, retry once, then record issue in UAT comments.
- If status button is missing: current status may not allow that action.
- If logo is missing: tenant branding might be unavailable; verify with admin.

## 12. Practical Examples
- Example: At 9:00 AM, complete My Daily Checklist, then switch to Staff Checklist to verify Guard and Laundry completion.
- Example: In Requests Hub, filter Repair > Pending to identify unresolved maintenance blockers.
- Example: In My Staff, search "guard" to confirm staffing for Tulip Boys Hostel shift coverage.

## 13. UAT Sign-Off Instructions
- Use the matching role-wise UAT Excel file.
- For each test case, mark exactly one column: `Approved` or `Partial` or `Not Done`.
- Write clear comments with screen name and observed behavior.
- Capture screenshot/video evidence for every Partial or Not Done case.
