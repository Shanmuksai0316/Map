# Campus Manager Mobile App – Testing Checklist

Use this when testing SMS, push notifications, and all Campus Manager features on the mobile app.

---

## 1. Login & Auth

| # | What to test | How | Pass? |
|---|----------------|-----|-------|
| 1.1 | Login with phone number | Enter Campus Manager phone → request OTP | ☐ |
| 1.2 | OTP SMS received | Check phone for OTP SMS (DLT template) | ☐ |
| 1.3 | OTP verification | Enter OTP → land on Dashboard | ☐ |
| 1.4 | Tenant/college shown | Dashboard shows correct college name/logo | ☐ |
| 1.5 | Logout | Profile / Logout → back to login | ☐ |

---

## 2. Push Notifications (Device Registration)

| # | What to test | How | Pass? |
|---|----------------|-----|-------|
| 2.1 | Push permission | On first launch, allow notifications when prompted | ☐ |
| 2.2 | Device registered | After login, backend has device for user (check DB or logs) | ☐ |
| 2.3 | Push received in foreground | Send a test push → notification appears (or in-app handler) | ☐ |
| 2.4 | Push received in background | App in background → send push → tap opens app to right screen | ☐ |
| 2.5 | Tap opens correct screen | e.g. tap “Checklist overdue” → opens app (Notifications or relevant screen) | ☐ |

**Backend test script (optional):**  
`php send_demo_campus_manager_role_notifications.php <tenant_code>`  
Sends demo push for all Campus Manager templates to one CM.

---

## 3. SMS (Campus Manager as Assignee)

Campus Manager gets **SMS** when they are the **checklist assignee** (their own “My Checklist”):

| # | What to test | When it triggers | Pass? |
|---|----------------|-------------------|-------|
| 3.1 | Morning reminder SMS | Scheduled at 9:00 (config: `CHECKLIST_REMINDER_MORNING_HOUR`) – “New day, new checklist…” | ☐ |
| 3.2 | Afternoon reminder SMS | Scheduled at 15:00 – “Friendly reminder: please finish and submit…” | ☐ |
| 3.3 | Checklist sent back SMS | Manager sends back checklist from web → assignee gets “Checklist sent back…” | ☐ |
| 3.4 | Overdue SMS (assignee) | After midnight run (00:05) for yesterday’s unsubmitted checklist – “Checklist is overdue…” | ☐ |

**SMS templates (DLT):** `checklist_morning`, `checklist_afternoon`, `checklist_overdue`, `checklist_reminder`.  
**Manual test:**  
`php artisan sms:test-one-by-one <phone> --template=checklist_morning`  
(and same for afternoon/overdue if needed).

---

## 4. Push – Checklist (Campus Manager as Assignee)

When Campus Manager has an **assigned checklist** (My Checklist):

| # | What to test | When it triggers | Pass? |
|---|----------------|-------------------|-------|
| 4.1 | Checklist reminder push | Morning/afternoon reminder – “New checklist assigned” / “Please start your assigned checklist.” | ☐ |
| 4.2 | Checklist overdue push | After midnight for unsubmitted – “Checklist overdue” / “Submit your assigned checklist now.” | ☐ |
| 4.3 | Checklist sent back push | Manager sends back from web – “Checklist sent back” | ☐ |

Templates: `staff_all.checklist_assigned`, `staff_all.checklist_overdue`.

---

## 5. Push – Staff Checklist Overdue (Campus Manager as Manager)

When **any staff** (e.g. Guard) has an **overdue** checklist (after midnight), **all Campus Managers** in the tenant get a push:

| # | What to test | When it triggers | Pass? |
|---|----------------|-------------------|-------|
| 5.1 | Staff overdue push to CM | Night run at 00:05 finds a staff checklist overdue → each Campus Manager gets: “Staff Checklist Overdue” / “[Staff name] ([role]) has not submitted their checklist for [date].” | ☐ |

**How to test:**  
- Assign a checklist to a Guard (or other staff) for “yesterday” and leave it Pending.  
- Run: `php artisan checklists:remind --window=overdue` (or wait for 00:05).  
- Campus Manager app should get the push.

---

## 6. My Checklist (Campus Manager’s Own Checklist)

| # | What to test | How | Pass? |
|---|----------------|-----|-------|
| 6.1 | “My Daily Checklist” visible | Dashboard → Checklists → tab “My Daily Checklist” | ☐ |
| 6.2 | Today’s checklist loads | If a checklist is assigned to this CM for today, list shows tasks | ☐ |
| 6.3 | No checklist for today | If none assigned, see “No tasks assigned for today” (no error) | ☐ |
| 6.4 | Complete a task | Tap task → mark complete (with photo if required) | ☐ |
| 6.5 | Upload photo for task | Task “requires photo” → Take Photo → attach → complete | ☐ |
| 6.6 | Progress updates | Completed count and progress bar update after each completion | ☐ |
| 6.7 | Submit checklist | When all tasks done → “Submit Daily Checklist” → success message | ☐ |
| 6.8 | After submit | List refreshes; submitted checklist state correct | ☐ |
| 6.9 | Pull to refresh | Pull down to refresh “My Checklist” list | ☐ |

**Backend:** Uses `/mobile/campus-manager/checklists/current`, `.../submit`, `.../items/{index}/complete`, `.../items/{index}/photo`.

---

## 7. Staff Checklist Tab (View Staff Compliance)

| # | What to test | How | Pass? |
|---|----------------|-----|-------|
| 7.1 | “Staff Checklist” tab | Checklists screen → tab “Staff Checklist” | ☐ |
| 7.2 | Staff summary list | See list of staff with checklist completion (Today/Yesterday) | ☐ |
| 7.3 | Date filter | Switch Today / Yesterday – list updates | ☐ |
| 7.4 | Staff detail (if implemented) | Tap a staff row → see their checklist detail (if route exists) | ☐ |

**Backend:** `/mobile/campus-manager/checklists/staff-summary?date=today|yesterday`.

---

## 8. Requests Hub

| # | What to test | How | Pass? |
|---|----------------|-----|-------|
| 8.1 | Open Requests | Dashboard → Requests or Requests tab | ☐ |
| 8.2 | Tabs (Housekeeping, Maintenance, etc.) | Switch between request types – list loads | ☐ |
| 8.3 | List loads | Each tab shows requests (or empty) without error | ☐ |
| 8.4 | Request detail (if available) | Tap a request → detail screen | ☐ |

**Push (optional):** New request / status change / comment can send push (`campus_manager.request_created`, etc.) – verify if your backend sends these and that they appear on device.

---

## 9. Emergency

| # | What to test | How | Pass? |
|---|----------------|-----|-------|
| 9.1 | Emergency tab | Bottom tab “Emergency” | ☐ |
| 9.2 | Unacknowledged count badge | Red badge on tab when there are unacknowledged incidents | ☐ |
| 9.3 | Medical / Incidents list | Open Medical or Incidents – list loads | ☐ |
| 9.4 | Acknowledge | Acknowledge an item – count decreases, list updates | ☐ |
| 9.5 | Push for new emergency | (If backend sends) New medical/incident → CM gets push | ☐ |

**Backend:** `/mobile/campus-manager/emergency/medical`, `.../incidents`, `.../incidents/{id}/acknowledge`, etc.

---

## 10. Comm Box & Notices

| # | What to test | How | Pass? |
|---|----------------|-----|-------|
| 10.1 | Comm Box tab / tile | Dashboard “Comm Box” or tab – opens Comm Box | ☐ |
| 10.2 | Unread count badge | Badge shows unread count when > 0 | ☐ |
| 10.3 | Notice list | Notices load (or empty) | ☐ |
| 10.4 | Post Notice (if available) | Create a notice from app – success | ☐ |
| 10.5 | Push: new notice | When a notice is published targeting staff/CM – “New notice: {title}” push received | ☐ |

Template: `campus_manager.notice_published` or `staff_all.notice_published`.

---

## 11. My Staff

| # | What to test | How | Pass? |
|---|----------------|-----|-------|
| 11.1 | My Staff screen | Dashboard “My Staff” or tab | ☐ |
| 11.2 | Staff list | List of staff (names, roles, hostels) loads | ☐ |
| 11.3 | No crash when empty | If no staff, show empty state | ☐ |

**Backend:** `/mobile/campus-manager/staff`.

---

## 12. Profile & App Settings

| # | What to test | How | Pass? |
|---|----------------|-----|-------|
| 12.1 | Profile screen | Open Profile from dashboard/menu | ☐ |
| 12.2 | Name / role / tenant | Correct name, role “Campus Manager”, college name | ☐ |
| 12.3 | Announcements | Open Announcements – list or empty | ☐ |
| 12.4 | Notifications list | Open Notifications – past pushes/in-app notifications | ☐ |

---

## 13. API & Connectivity

| # | What to test | How | Pass? |
|---|----------------|-----|-------|
| 13.1 | Base URL / tenant | App points to correct API (tenant subdomain or central + tenant header) | ☐ |
| 13.2 | 401 on invalid token | After logout or token expiry, API returns 401 and app goes to login | ☐ |
| 13.3 | No 403 on CM endpoints | As Campus Manager, no 403 on `/mobile/campus-manager/*` (checklists, staff, emergency, etc.) | ☐ |

---

## 14. Quick Reference – What Campus Manager Gets

| Channel | When | Message type |
|--------|------|----------------|
| **SMS** | CM is assignee: morning reminder (9 AM) | “New day, new checklist…” |
| **SMS** | CM is assignee: afternoon reminder (3 PM) | “Friendly reminder: please finish and submit…” |
| **SMS** | CM is assignee: checklist sent back | “Checklist sent back. Please review and resubmit.” |
| **SMS** | CM is assignee: overdue (after midnight) | “Checklist is overdue. Complete it immediately…” |
| **Push** | Same as above (assignee) | “New checklist assigned” / “Checklist overdue” |
| **Push** | Any staff overdue (midnight run) | “Staff Checklist Overdue” – “[Name] (Role) has not submitted their checklist for [date].” |
| **Push** | New request / status / comment (if enabled) | campus_manager.request_* |
| **Push** | New notice (if targeted to staff/CM) | “New notice: {title}” |

---

## 15. Backend Commands for Test Data / Triggers

```bash
# Demo push – all Campus Manager templates (run from api/)
php send_demo_campus_manager_role_notifications.php <tenant_code>

# Checklist overdue run (notify assignees + Campus Managers for overdue instances)
php artisan checklists:remind --window=overdue

# Morning/afternoon reminders (normally scheduled)
php artisan checklists:remind --window=morning
php artisan checklists:remind --window=afternoon

# Test one SMS template
php artisan sms:test-one-by-one <phone> --template=checklist_morning
```

---

Use this checklist end-to-end for each release or before UAT sign-off for Campus Manager mobile (SMS, notifications, and all features).
