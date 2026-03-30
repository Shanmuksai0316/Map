# MAP HMS Mobile User Manual - Rector

## Document Summary
- App: Staff App
- Role: Rector
- Dashboard Title: Rector
- Last Updated: February 18, 2026

## 1. Purpose In Simple Words
This guide explains Rector app usage in plain language. It covers each screen, each major button, expected behavior, and common mistakes so non-technical users can work confidently and test properly.

## 2. Role Scope
- Primary responsibility: Approval authority for Outpass, Leave, and Guest Entry workflows.
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
| Outpass | View requests and approve/reject from detail screen. | During normal daily flow or whenever related alert appears. |
| Leave | Review leave requests with status filters and detailed context. | During normal daily flow or whenever related alert appears. |
| Guest Entry | Approve or reject visitor entry requests. | During normal daily flow or whenever related alert appears. |
| Profile | View profile and logout. | During normal daily flow or whenever related alert appears. |
| Comm Box | Read communication feed and updates. | During normal daily flow or whenever related alert appears. |

## 6. Navigation Map
| From | Action | Opens |
| --- | --- | --- |
| Dashboard | Tap `Outpass` | `Outpass` module screen |
| Dashboard | Tap `Leave` | `Leave` module screen |
| Dashboard | Tap `Guest Entry` | `Guest Entry` module screen |
| Dashboard | Tap `Profile` | `Profile` module screen |
| Dashboard | Tap `Comm Box` | `Comm Box` module screen |
| Any screen with back arrow | Tap back icon | Previous screen |
| Dashboard header | Tap bell icon | Notifications |

## 7. Detailed Module Walkthroughs
### Module 1: Outpass Approval
**Purpose:** Review outpass details and decide approval outcome.

**UI Elements You Should See:**
- List has status filter chips (All, Pending, Approved, Rejected).
- Each card shows student identity, date/time, and status badge.
- Detail page includes Approve and Reject actions.
- Reject action requires reason input.

**Step-by-Step:**
1. Tap Outpass tile.
2. Set filter to Pending for actionable queue.
3. Open card and review reason/time window.
4. Tap Approve to accept, or Reject and enter reason before submit.

**Expected Success Result:** Request status changes immediately and is reflected in list.

**Common Error/Edge Cases:**
- Reject without reason should be blocked with validation message.

### Module 2: Leave + Guest Entry Approvals
**Purpose:** Process leave and guest requests with consistent decision flow.

**UI Elements You Should See:**
- Dedicated list screens with filter pills and status badges.
- Detail screens mirror approval pattern with reason-required rejection.

**Step-by-Step:**
1. Open Leave tile, process pending records first.
2. Open Guest Entry tile and process request decisions similarly.
3. Use filters to audit approved/rejected history.

**Expected Success Result:** Approval decisions persist and are visible in filtered views.

**Common Error/Edge Cases:**
- Action failures show alert; retry after network check.

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
- Example: Approve urgent medical leave after verifying dates and student details.
- Example: Reject guest entry request with clear reason "visitor ID missing" for audit clarity.

## 13. UAT Sign-Off Instructions
- Use the matching role-wise UAT Excel file.
- For each test case, mark exactly one column: `Approved` or `Partial` or `Not Done`.
- Write clear comments with screen name and observed behavior.
- Capture screenshot/video evidence for every Partial or Not Done case.
