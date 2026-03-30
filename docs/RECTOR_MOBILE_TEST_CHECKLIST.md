# Rector Mobile App – Testing Checklist

Use this when testing SMS, push notifications, and all **Rector** features on the mobile app.

---

## 1. Login & Auth (Rector)

| # | What to test | How | Pass? |
|---|--------------|-----|-------|
| 1.1 | Login with Rector phone | Enter Rector phone (e.g. test number from backend) → request OTP | ☐ |
| 1.2 | OTP SMS received | Check Rector phone for OTP SMS (DLT OTP template) | ☐ |
| 1.3 | OTP verification | Enter OTP → land on **Rector Dashboard** (not Warden / CM) | ☐ |
| 1.4 | Tenant/college shown | Dashboard shows correct college name/logo | ☐ |
| 1.5 | Logout | Profile / Logout → back to login | ☐ |

**Backend:** Mobile login uses `/v1/mobile/auth/send-otp`, `/v1/mobile/auth/verify-otp` with Rector’s phone.

---

## 2. Push Notifications (Device Registration)

| # | What to test | How | Pass? |
|---|--------------|-----|-------|
| 2.1 | Push permission | On first launch, allow notifications when prompted | ☐ |
| 2.2 | Device registered | After login, Rector device appears in backend devices list/logs | ☐ |
| 2.3 | Push in foreground | Send a test Rector push (from backend or script) → visible in app | ☐ |
| 2.4 | Push in background | App in background → send push → tapping it opens correct Rector screen | ☐ |

---

## 3. SMS – Rector Approval Flows

When Rector **approves / rejects** Outpass / Leave / Sick Leave, the `SendApprovalNotification` job sends SMS.

| # | Scenario | Expected SMS | Recipient | Pass? |
|---|----------|--------------|-----------|-------|
| 3.1 | Approve Outpass | “Update: Out-Pass Approved for {studentName} valid until {date/time}.Team OMAP Services” | Student | ok |
| 3.2 | Reject Outpass | “OMAPMS: Update: Out-Pass Denied for {studentName}. Reason: {note}.” | Student | ☐ |
| 3.3 | Approve Leave | “OMAPMS: Leave request L-{id} has been approved by Rector. Note: {note}” | Student | ☐ |
| 3.4 | Reject Leave | “OMAPMS: Leave request L-{id} has been rejected by Rector. Note: {note}” | Student | ☐ |
| 3.5 | Reject Sick Leave | “OMAPMS: Sick Leave request SL-{id} has been rejected by Rector. Note: {note}” | Student | ☐ |
| 3.6 | CM info SMS (all above) | “Rector {rectorName} has approved/rejected {type} request #{id} for {studentName}.” | Campus Manager | ☐ |

**How to test:**
- From **student app**, raise Outpass, Leave, and Sick Leave.
- On **Rector mobile** or Rector web panel, approve/reject.
- Verify SMS content on student & Campus Manager phones.

---

## 4. Push – Rector Approval Notifications

Same Rector decisions also send push notifications via `PushNotifier`.

| # | What to test | When it triggers | Recipient | Pass? |
|---|--------------|------------------|-----------|-------|
| 4.1 | Outpass approved push | Rector approves Outpass | Student, Campus Manager | ok |
| 4.2 | Outpass rejected push | Rector rejects Outpass | Student, Campus Manager | ok|
| 4.3 | Leave / Sick Leave approved push | Rector approves Leave / Sick Leave | Student, Campus Manager |ok |
| 4.4 | Leave / Sick Leave rejected push | Rector rejects Leave / Sick Leave | Student, Campus Manager | ok |

Check that:
- Notification text matches the SMS message intent.
- Tapping the push opens the relevant screen (Outpass/Leave detail) in student app; CM sees relevant request in Requests or notifications.

---

## 5. Rector Dashboard (Mobile)

Screen: `RectorDashboard` (staff app).

| # | What to test | How | Pass? |
|---|--------------|-----|-------|
| 5.1 | Rector header | After login, dashboard title shows “Rector” and Rector’s name | ok|
| 5.2 | Tiles visible | Tiles for **Outpass**, **Leave**, **Guest Entries**, **Insights**, **Profile**, **Comm Box** (if present) | ok |
| 5.3 | Outpass tile | Tap Outpass tile → opens Rector Outpass List |ok|
| 5.4 | Leave tile | Tap Leave tile → opens Rector Leave List | ok |
| 5.5 | Guest Entries tile | Tap Guest Entries tile → opens Rector Guest Entry List | ok |
| 5.6 | Insights tile | Tap Insights tile → opens Rector Insights screen | ok |
| 5.7 | Profile tile | Tap Profile tile → opens Rector Profile screen | ok |

**Backend:** `/mobile/rector/dashboard` for stats & header data.

---

## 6. Outpass Approvals (Rector)

| # | What to test | How | Pass? |
|---|--------------|-----|-------|
| 6.1 | Outpass list loads | RectorDashboard → Outpass tile → list of pending/approved Outpasses | ☐ |
| 6.2 | Outpass detail | Tap an Outpass → detail screen shows student, reason, dates, status | ☐ |
| 6.3 | Approve Outpass | On detail, tap **Approve** → status changes to Approved | ☐ |
| 6.4 | Reject Outpass | On detail, tap **Reject** (with note) → status changes to Rejected | ☐ |
| 6.5 | Student / CM notifications | For 6.3/6.4, verify student & CM receive SMS + push as per sections 3–4 | ☐ |

---

## 7. Leave & Sick Leave Approvals (Rector)

| # | What to test | How | Pass? |
|---|--------------|-----|-------|
| 7.1 | Leave list loads | RectorDashboard → Leave tile → list of leave requests | ☐ |
| 7.2 | Leave detail | Tap a Leave → detail shows dates, reason, student, status | ☐ |
| 7.3 | Approve Leave | Tap **Approve** → status becomes Approved | ☐ |
| 7.4 | Reject Leave | Tap **Reject** → status becomes Rejected with note | ☐ |
| 7.5 | Sick Leave detail | If sick leaves are in same list, open a Sick Leave and verify details | ☐ |
| 7.6 | Reject Sick Leave | Reject a Sick Leave → status updates | ☐ |
| 7.7 | Notifications for all | For each decision, confirm SMS + push to student & CM as in sections 3–4 | ☐ |

---

## 8. Security Incidents / Emergency Exits (Guard → Rector)

Rector sees incidents raised by guard/emergency flows.

| # | What to test | How | Pass? |
|---|--------------|-----|-------|
| 8.1 | Incident creation (Guard) | From Guard app, submit a **Security Incident** or **Emergency Exit** | ☐ |
| 8.2 | Incidents visible to Rector | Open Rector incidents view (Insights / Incidents tab) – new incident appears | ☐ |
| 8.3 | Incident detail | Open incident → details of student, reason, time, status are correct | ☐ |
| 8.4 | Notifications | On incident creation, check if Rector and CM get push / in-app alerts as per product spec | ☐ |

**Backend:** Rector incidents come from `/rector/incidents` and related endpoints in `api/routes/api/rector.php`.

---

## 9. Rector Insights & Reports

| # | What to test | How | Pass? |
|---|--------------|-----|-------|
| 9.1 | Insights screen loads | RectorDashboard → Insights tile | ☐ |
| 9.2 | Metrics visible | Verify student counts, hostel health, incidents, approvals, etc. load without error | ☐ |
| 9.3 | Filters / date ranges | If filters exist (date, hostel, type), change them and ensure results update | ☐ |
| 9.4 | Performance | Scrolling and loading graphs/tables is smooth; no crashes | ☐ |

---

## 10. Rector Profile & Comm Box

| # | What to test | How | Pass? |
|---|--------------|-----|-------|
| 10.1 | Profile screen | From dashboard, tap Profile tile → RectorProfileScreen | ☐ |
| 10.2 | Name / role / tenant | Correct Rector name, role “Rector”, and college name | ☐ |
| 10.3 | Announcements / Notices | If available, open announcements/notices from profile | ☐ |
| 10.4 | Rector Comm Box screen | From dashboard or nav, open RectorCommBoxScreen | ☐ |
| 10.5 | Messages list | Notices/messages load correctly (or show empty state) | ☐ |
| 10.6 | Unread handling | Unread items get marked read when opened; unread count decrements if visible | ☐ |

**Backend:** Rector Comm Box reuses student/staff comm-box endpoints via `/mobile/messages` / notifications API.

---

## 11. Notifications & History (Rector)

| # | What to test | How | Pass? |
|---|--------------|-----|-------|
| 11.1 | Notifications list | Open Notifications screen from Rector app | ☐ |
| 11.2 | Approval events show | After approving/rejecting Outpass/Leave/Sick Leave, entries appear | ☐ |
| 11.3 | Incident / emergency alerts | Guard incidents/emergency exits appear as notifications if configured | ☐ |
| 11.4 | Mark as read | Mark notifications as read; unread count updates | ☐ |

**Backend:** `/mobile/notifications`, `/mobile/notifications/unread-count`, `/notifications/read-all`, etc.

---

## 12. API & Permissions (Rector)

| # | What to test | How | Pass? |
|---|--------------|-----|-------|
| 12.1 | Base URL / tenant | Rector app points to correct tenant API (e.g., `https://<code>.mapservices.in/api/v1`) | ☐ |
| 12.2 | 401 on invalid token | After logout or token expiry, Rector API calls return 401 and app sends user to login | ☐ |
| 12.3 | Rector-only endpoints | As Rector, `/mobile/rector/*` and `/rector/*` return **200** (no 403) | ☐ |
| 12.4 | No access to CM-only routes | Rector should not access `/mobile/campus-manager/*` endpoints reserved for Campus Manager | ☐ |

---

## 13. Quick Reference – What Rector Triggers

| Channel | When | Message type |
|--------|------|--------------|
| **SMS** | Rector approves/rejects Outpass | Student + Campus Manager get approval/rejection SMS |
| **SMS** | Rector approves/rejects Leave/Sick Leave | Student + Campus Manager get leave decision SMS |
| **Push** | Same approval events | Student + Campus Manager get push notifications with decision summary |
| **Push** | Security incident/emergency exit (from Guard) | Rector (and CM) get incident/emergency notifications (if enabled) |

---

Use this checklist end-to-end for each release or before UAT sign-off for **Rector** mobile (SMS, notifications, approvals, incidents, insights, and all core features).

