# MAP HMS Mobile User Manual - Student

## Document Summary
- App: Student App
- Role: Student
- Dashboard Title: Vidyarthi Dashboard
- Last Updated: February 18, 2026

## 1. Purpose In Simple Words
This guide explains Student app usage in plain language. It covers each screen, each major button, expected behavior, and common mistakes so non-technical users can work confidently and test properly.

## 2. Role Scope
- Primary responsibility: Student self-service for profile, requests, leave/outpass, communication, and feedback.
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
| My Profile | Open profile actions: details, room change, attendance shortcut, share, logout. | During normal daily flow or whenever related alert appears. |
| Requests | Open request categories: House Keeping, Repair & Maintenance, Guest Entry. | During normal daily flow or whenever related alert appears. |
| Emergency | Access emergency support screen. | During normal daily flow or whenever related alert appears. |
| Out-pass | Create and track outpass requests. | During normal daily flow or whenever related alert appears. |
| Leave | Create and track leave requests. | During normal daily flow or whenever related alert appears. |
| Sports | Book sports facility where enabled by tenant feature flags. | During normal daily flow or whenever related alert appears. |
| Laundry | Raise and track laundry requests. | During normal daily flow or whenever related alert appears. |
| Comm Box | Read notices and announcements. | During normal daily flow or whenever related alert appears. |
| Feedback | Submit feedback to management/product team. | During normal daily flow or whenever related alert appears. |

## 6. Navigation Map
| From | Action | Opens |
| --- | --- | --- |
| Dashboard | Tap `My Profile` | `My Profile` module screen |
| Dashboard | Tap `Requests` | `Requests` module screen |
| Dashboard | Tap `Emergency` | `Emergency` module screen |
| Dashboard | Tap `Out-pass` | `Out-pass` module screen |
| Dashboard | Tap `Leave` | `Leave` module screen |
| Dashboard | Tap `Sports` | `Sports` module screen |
| Dashboard | Tap `Laundry` | `Laundry` module screen |
| Dashboard | Tap `Comm Box` | `Comm Box` module screen |
| Dashboard | Tap `Feedback` | `Feedback` module screen |
| Any screen with back arrow | Tap back icon | Previous screen |
| Dashboard header | Tap bell icon | Notifications |

## 7. Detailed Module Walkthroughs
### Module 1: Dashboard + Header UX
**Purpose:** Understand home layout and navigation entry points quickly.

**UI Elements You Should See:**
- Top area: app branding + notifications bell with unread badge.
- Greeting card: student name, room detail, and tenant logo.
- Action tiles: 2-column grid with clear icon and title labels.
- Pull-to-refresh updates profile and notification counters.

**Step-by-Step:**
1. Login and verify greeting card details.
2. Tap bell to open notifications.
3. Use action tiles to navigate to modules.

**Expected Success Result:** All major modules are reachable directly from dashboard.

**Common Error/Edge Cases:**
- If tile does not open, user can retry after refresh.

### Module 2: Requests Hub + Tickets
**Purpose:** Raise and track service requests with clear categories.

**UI Elements You Should See:**
- Request category cards with icon, title, and helper text.
- Ticket list/detail screens with status visibility.

**Step-by-Step:**
1. Tap Requests tile.
2. Choose House Keeping or Repair & Maintenance.
3. Create ticket and track status from Tickets screen.

**Expected Success Result:** Ticket gets created and appears in request history/list.

**Common Error/Edge Cases:**
- Incomplete forms block submission with clear validation prompt.

### Module 3: Leave / Outpass / Guest Entry
**Purpose:** Submit movement-related requests and follow approval progress.

**UI Elements You Should See:**
- Preview/list screens show request cards and status labels.
- Form screens collect date, reason, destination/visitor details as applicable.

**Step-by-Step:**
1. Open Leave or Out-pass tile and submit request form.
2. Open Guest Entry through Requests Hub and submit details.
3. Track status from preview/detail screens.

**Expected Success Result:** Requests are submitted and status is visible for follow-up.

**Common Error/Edge Cases:**
- Missing required input should show validation and stop submit.

### Module 4: Comm Box + Profile + Feedback
**Purpose:** Read notices, manage account actions, and send feedback.

**UI Elements You Should See:**
- Comm Box list opens notice detail on tap.
- Profile includes identity card, room change request, attendance stats, share app, logout.
- Feedback module supports user opinion capture.

**Step-by-Step:**
1. Open Comm Box and read notice details.
2. Open Profile and verify personal details/attendance summary.
3. Submit feedback from Feedback tile.

**Expected Success Result:** Student can consume communication and close all self-service tasks.

**Common Error/Edge Cases:**
- No notices state shows user-friendly empty message.

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
- Example: Raise repair ticket for fan issue and track its status update in ticket detail.
- Example: Submit leave request for weekend travel and monitor approval before departure.
- Example: Open Comm Box daily to stay updated on hostel notices.

## 13. UAT Sign-Off Instructions
- Use the matching role-wise UAT Excel file.
- For each test case, mark exactly one column: `Approved` or `Partial` or `Not Done`.
- Write clear comments with screen name and observed behavior.
- Capture screenshot/video evidence for every Partial or Not Done case.
