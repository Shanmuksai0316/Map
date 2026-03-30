# MAP HMS Mobile User Manual - Guard

## Document Summary
- App: Staff App
- Role: Guard
- Dashboard Title: Security Guard
- Last Updated: February 18, 2026

## 1. Purpose In Simple Words
This guide explains Guard app usage in plain language. It covers each screen, each major button, expected behavior, and common mistakes so non-technical users can work confidently and test properly.

## 2. Role Scope
- Primary responsibility: Gate movement verification, guard checklist execution, and movement history audit.
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
| QR Code | Open scanner/manual verification flow for gate checks. | During normal daily flow or whenever related alert appears. |
| Checklist | Complete duty checklist with required comments/photos. | During normal daily flow or whenever related alert appears. |
| Leave | Review leave movement requests. | During normal daily flow or whenever related alert appears. |
| Outpass | Review outpass movement requests. | During normal daily flow or whenever related alert appears. |
| Guest Entry | Review guest entry approvals. | During normal daily flow or whenever related alert appears. |
| Comm Box | Read security notices and updates. | During normal daily flow or whenever related alert appears. |
| Profile | Access profile, history, and logout. | During normal daily flow or whenever related alert appears. |

## 6. Navigation Map
| From | Action | Opens |
| --- | --- | --- |
| Dashboard | Tap `QR Code` | `QR Code` module screen |
| Dashboard | Tap `Checklist` | `Checklist` module screen |
| Dashboard | Tap `Leave` | `Leave` module screen |
| Dashboard | Tap `Outpass` | `Outpass` module screen |
| Dashboard | Tap `Guest Entry` | `Guest Entry` module screen |
| Dashboard | Tap `Comm Box` | `Comm Box` module screen |
| Dashboard | Tap `Profile` | `Profile` module screen |
| Any screen with back arrow | Tap back icon | Previous screen |
| Dashboard header | Tap bell icon | Notifications |

## 7. Detailed Module Walkthroughs
### Module 1: Gate Verification (Outpass/Leave/Guest)
**Purpose:** Verify approved movement and mark completion safely.

**UI Elements You Should See:**
- Lists show request cards with status badges.
- Detail pages expose verification actions like mark entry/exit when allowed.
- Actions are conditional on approved state and previous verification.

**Step-by-Step:**
1. Open relevant tile (Outpass/Leave/Guest Entry).
2. Select request and verify identity/details.
3. Run verification action (QR/backup/manual path).
4. Confirm request moves to completed/verified state.

**Expected Success Result:** Movement event is recorded and appears in history.

**Common Error/Edge Cases:**
- If action unavailable, verify request status is approved.

### Module 2: Guard Checklist
**Purpose:** Ensure guard shift tasks are completed and submitted.

**UI Elements You Should See:**
- Task cards show completed state and time stamps.
- Comment/photo controls appear where required.
- Submit button enabled only when all required tasks are done.

**Step-by-Step:**
1. Open Checklist tile.
2. Complete each task with required inputs.
3. Submit checklist at end of round.

**Expected Success Result:** Checklist submitted successfully with completion proof.

**Common Error/Edge Cases:**
- Incomplete task set blocks submit with instruction text.

### Module 3: History Audit
**Purpose:** View completed leave/outpass/guest records for handover and audit.

**UI Elements You Should See:**
- History tabs by request type with count badges.
- Completed status chips visible on cards.

**Step-by-Step:**
1. Open Profile > History.
2. Switch tabs and validate completed entries.

**Expected Success Result:** Verified movements are visible for shift handover.

**Common Error/Edge Cases:**
- No completed records shows clean empty state.

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
- Example: Verify approved outpass at gate and mark exit to close movement loop.
- Example: During handover, open History and confirm all guest entries are completed.

## 13. UAT Sign-Off Instructions
- Use the matching role-wise UAT Excel file.
- For each test case, mark exactly one column: `Approved` or `Partial` or `Not Done`.
- Write clear comments with screen name and observed behavior.
- Capture screenshot/video evidence for every Partial or Not Done case.
