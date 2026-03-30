# MAP HMS Mobile User Manual - HK Supervisor

## Document Summary
- App: Staff App
- Role: HK Supervisor
- Dashboard Title: Housekeeping
- Last Updated: February 18, 2026

## 1. Purpose In Simple Words
This guide explains HK Supervisor app usage in plain language. It covers each screen, each major button, expected behavior, and common mistakes so non-technical users can work confidently and test properly.

## 2. Role Scope
- Primary responsibility: Housekeeping ticket progression and housekeeping checklist compliance.
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
| Checklist | Open checklist tasks and submit completion. | During normal daily flow or whenever related alert appears. |
| Requests | Open housekeeping requests and move status forward. | During normal daily flow or whenever related alert appears. |
| Comm Box | Read communication updates. | During normal daily flow or whenever related alert appears. |
| Profile | View profile, history, and logout. | During normal daily flow or whenever related alert appears. |

## 6. Navigation Map
| From | Action | Opens |
| --- | --- | --- |
| Dashboard | Tap `Checklist` | `Checklist` module screen |
| Dashboard | Tap `Requests` | `Requests` module screen |
| Dashboard | Tap `Comm Box` | `Comm Box` module screen |
| Dashboard | Tap `Profile` | `Profile` module screen |
| Any screen with back arrow | Tap back icon | Previous screen |
| Dashboard header | Tap bell icon | Notifications |

## 7. Detailed Module Walkthroughs
### Module 1: HK Requests
**Purpose:** Move housekeeping tickets through Open -> In Progress -> Complete flow.

**UI Elements You Should See:**
- Request cards show ticket summary and status color.
- Detail modal includes status timeline and single next-state action.

**Step-by-Step:**
1. Open Requests tile.
2. Tap request card to inspect details.
3. Use Update Status action based on current state.
4. Repeat until request reaches completed/resolved state.

**Expected Success Result:** Ticket status updates and appears correctly in history/completed lists.

**Common Error/Edge Cases:**
- Status update failure shows alert and retains previous state.

### Module 2: HK Checklist
**Purpose:** Complete all required housekeeping checklist items with evidence where needed.

**UI Elements You Should See:**
- Task list indicates comment-required items.
- Task modal supports notes and photo actions.
- Submit becomes active when all items are completed.

**Step-by-Step:**
1. Open Checklist tile.
2. Open task modal, add comment/photo if required, and mark complete.
3. Submit checklist once all tasks are done.

**Expected Success Result:** Checklist submitted and ready for review/history.

**Common Error/Edge Cases:**
- Comment-required task cannot complete without comment.

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
- Example: Move cleaning ticket from Open to In Progress when staff starts, then to Complete after QA check.
- Example: Add comment "Deep cleaning done floor 2" before completing checklist item.

## 13. UAT Sign-Off Instructions
- Use the matching role-wise UAT Excel file.
- For each test case, mark exactly one column: `Approved` or `Partial` or `Not Done`.
- Write clear comments with screen name and observed behavior.
- Capture screenshot/video evidence for every Partial or Not Done case.
