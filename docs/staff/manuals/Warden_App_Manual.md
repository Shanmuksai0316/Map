# MAP HMS Mobile User Manual - Warden

## Document Summary
- App: Staff App
- Role: Warden
- Dashboard Title: Warden
- Last Updated: February 18, 2026

## 1. Purpose In Simple Words
This guide explains Warden app usage in plain language. It covers each screen, each major button, expected behavior, and common mistakes so non-technical users can work confidently and test properly.

## 2. Role Scope
- Primary responsibility: Hostel-level attendance, student operations, requests, and emergency oversight.
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
| Attendance | Open room-wise attendance and submit session marks. | During normal daily flow or whenever related alert appears. |
| Checklist | Run and submit daily warden checklist. | During normal daily flow or whenever related alert appears. |
| Emergency | Review medical and incident records; respond to alerts. | During normal daily flow or whenever related alert appears. |
| Requests | Review hostel request list and open detail pages. | During normal daily flow or whenever related alert appears. |
| Comm Box | Read communication notices. | During normal daily flow or whenever related alert appears. |
| Students | Search student list and open student details. | During normal daily flow or whenever related alert appears. |
| Profile | View profile and logout. | During normal daily flow or whenever related alert appears. |

## 6. Navigation Map
| From | Action | Opens |
| --- | --- | --- |
| Dashboard | Tap `Attendance` | `Attendance` module screen |
| Dashboard | Tap `Checklist` | `Checklist` module screen |
| Dashboard | Tap `Emergency` | `Emergency` module screen |
| Dashboard | Tap `Requests` | `Requests` module screen |
| Dashboard | Tap `Comm Box` | `Comm Box` module screen |
| Dashboard | Tap `Students` | `Students` module screen |
| Dashboard | Tap `Profile` | `Profile` module screen |
| Any screen with back arrow | Tap back icon | Previous screen |
| Dashboard header | Tap bell icon | Notifications |

## 7. Detailed Module Walkthroughs
### Module 1: Attendance Management
**Purpose:** Mark attendance per room and submit complete session records.

**UI Elements You Should See:**
- Room-level cards show counts and completion percentage.
- Date selector supports limited back-date window.
- Detail screen provides status controls (Present, Absent, Leave).
- Submit button disabled until required marks/reasons are complete.

**Step-by-Step:**
1. Tap Attendance tile and choose a room.
2. Mark each student as P/A/L.
3. Provide reasons where required.
4. Tap Submit Attendance and confirm success.

**Expected Success Result:** Attendance submission is accepted and reflected in session status.

**Common Error/Edge Cases:**
- Incomplete marks prevent submission with clear prompt.
- Offline mode queues actions where supported; sync when online.

### Module 2: Requests + Students
**Purpose:** Track hostel requests and student details.

**UI Elements You Should See:**
- Request filters by type/status; status badge colors indicate progress.
- Student list includes search and detail navigation.

**Step-by-Step:**
1. Open Requests tile, apply filters, and inspect detail pages.
2. Open Students tile and search by name/room/phone.
3. Open student detail for complete context.

**Expected Success Result:** Requests and student data are accessible with filter/search accuracy.

**Common Error/Edge Cases:**
- No results states are shown with guidance text.

### Module 3: Emergency + Checklist
**Purpose:** Manage emergency visibility and complete duty checklist.

**UI Elements You Should See:**
- Emergency tile can show badge and blinking alert card for urgency.
- Checklist screen shows task progress and submit state.

**Step-by-Step:**
1. Review emergency categories from dashboard alert/tile.
2. Complete checklist tasks and submit before shift close.

**Expected Success Result:** Critical events are reviewed and checklist is submitted.

**Common Error/Edge Cases:**
- If emergency data fetch fails, retry from pull-to-refresh.

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
- Example: Complete attendance for all Tulip Boys Hostel rooms before 9:30 AM.
- Example: Use Students search to locate a student during attendance mismatch investigation.

## 13. UAT Sign-Off Instructions
- Use the matching role-wise UAT Excel file.
- For each test case, mark exactly one column: `Approved` or `Partial` or `Not Done`.
- Write clear comments with screen name and observed behavior.
- Capture screenshot/video evidence for every Partial or Not Done case.
