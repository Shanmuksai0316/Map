# MAP HMS Mobile User Manual - Sports Manager

## Document Summary
- App: Staff App
- Role: Sports Manager
- Dashboard Title: Sports Manager
- Last Updated: February 18, 2026

## 1. Purpose In Simple Words
This guide explains Sports Manager app usage in plain language. It covers each screen, each major button, expected behavior, and common mistakes so non-technical users can work confidently and test properly.

## 2. Role Scope
- Primary responsibility: Sports requests, court setup, and sports operations visibility.
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
| Profile | Open profile and logout. | During normal daily flow or whenever related alert appears. |
| Raise Request | Create sports support request with date/slot selection. | During normal daily flow or whenever related alert appears. |
| Active Requests | Monitor open sports requests. | During normal daily flow or whenever related alert appears. |
| List of Courts | Manage courts (create, edit, delete, toggle status). | During normal daily flow or whenever related alert appears. |
| Comm Box | Read communications and updates. | During normal daily flow or whenever related alert appears. |

## 6. Navigation Map
| From | Action | Opens |
| --- | --- | --- |
| Dashboard | Tap `Profile` | `Profile` module screen |
| Dashboard | Tap `Raise Request` | `Raise Request` module screen |
| Dashboard | Tap `Active Requests` | `Active Requests` module screen |
| Dashboard | Tap `List of Courts` | `List of Courts` module screen |
| Dashboard | Tap `Comm Box` | `Comm Box` module screen |
| Any screen with back arrow | Tap back icon | Previous screen |
| Dashboard header | Tap bell icon | Notifications |

## 7. Detailed Module Walkthroughs
### Module 1: Sports Raise Request
**Purpose:** Create request against selected date tab and time slot.

**UI Elements You Should See:**
- Date toggle tabs: Today and Tomorrow.
- Slot selection chips/cards.
- Submit button with loading/disabled state during request creation.

**Step-by-Step:**
1. Open Raise Request tile.
2. Select date tab and slot.
3. Submit request and verify success.

**Expected Success Result:** New sports request appears in active queue.

**Common Error/Edge Cases:**
- No slot selected should block submission with user guidance.

### Module 2: Court Setup
**Purpose:** Maintain court inventory and availability.

**UI Elements You Should See:**
- Court list with action buttons (edit/delete/toggle status).
- Add/edit modal form with field validation.
- Status toggle updates active/inactive state visually.

**Step-by-Step:**
1. Open List of Courts.
2. Use Add to create new court record.
3. Edit existing court data as needed.
4. Toggle active status or delete when valid.

**Expected Success Result:** Court list reflects operations instantly and accurately.

**Common Error/Edge Cases:**
- Failed API update should show error alert and preserve previous data.

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
- Example: Disable one court for maintenance and notify users through Comm Box.
- Example: Raise request for extra sports support for tomorrow evening slot.

## 13. UAT Sign-Off Instructions
- Use the matching role-wise UAT Excel file.
- For each test case, mark exactly one column: `Approved` or `Partial` or `Not Done`.
- Write clear comments with screen name and observed behavior.
- Capture screenshot/video evidence for every Partial or Not Done case.
