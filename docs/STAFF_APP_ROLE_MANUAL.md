# MAP HMS - Staff App Role-wise Manual

**Version:** 1.0  
**Prepared on:** February 18, 2026  
**Audience:** Guards, Wardens, Campus Managers, Rectors, Laundry Managers, Sports Managers

---

## 1. Common access

- App: React Native staff app (Metro or release bundle).  
- Login: OTP (bypass `123456` for test numbers).  
- Base URL: depends on tenant + `mapservices.in`.  
- Tiles (home screen) show the modules the role can open; tile order is pulled from `mobile/src/shared/services/feature-flags.service.ts` and `UAT_STAFF_APP_CHANGES.md`.  
- Notifications appear via Comm Box tiles or in top-right bell (common for all roles).  
- Always clear cache / reinstall after backend deployment to avoid stale code.

---

## 2. Guard

### Home tiles
1. QR / Verify  
2. Checklist  
3. Leave  
4. Outpass  
5. Guest Entry  
6. Comm Box  
7. Profile

### Key flows
- **QR / Verify:** shows single 4-digit backup code, used for manual gate verification; no extra ID text.  
- **Leave/Outpass:** Activities show preview cards with status, student, dates, and `Mark completed` chips; completion updates card immediately.  
- **Guest Entry:** preview cards, detail view, completion button, list refresh.  
- **Profile:** shows name, role, ID, phone, hostel, tenant, history link, logout.  
- **History (from profile):** no calendar, just cards grouped by Leave/Outpass/Guest; only completed items display.

---

## 3. Warden

### Home tiles
1. Attendance  
2. Checklist  
3. Emergency  
4. Requests  
5. Comm Box  
6. Students  
7. Profile

### Key flows
- **Attendance:** default list is today, displayed as 2×2 grid of room cards. Each card shows present/leave/absent. You can search by room, open room detail, and submit updates.  
- **Emergency (Medical/Incidents):** warden sees only unacknowledged items; each detail modal has an `Acknowledge` action that removes the card. Polling refreshes list every 10s.  
- **Requests:** top header matches home screen height for visual continuity; cards follow consistent layout.

---

## 4. Campus Manager

### Home tiles
- Tiles similar to Warden but with Medical/Incidents read-only.  
- Emergency lists show every record (acknowledged/unacknowledged).  
- No `Acknowledge` button; only view details.  
- Checklist tile leads to daily checklist and staff checklists (per mobile configuration).  
- Requests tile exposes the same aggregated list as Warden but with full visibility from backend, plus `Mark Completed` actions for guard-style entries as needed.

---

## 5. Rector

### Home tiles
- Rector sees Guest Entry tile plus Leave and Outpass, matching the staff app order from `UAT_STAFF_APP_CHANGES.md`.
- **Guest Entry:** accessible, shows status tabs (Pending/Approved/Rejected) and detail view with approval actions.  
- **Outpass hub:** header height matches home, lists statuses including Rejected; filter for Rejected ensures none disappear when not pending.

---

## 6. Laundry Manager

- This role appears when laundry addon (feature flag) is enabled.  
- Home tile `Laundry` opens request list with statuses (Scheduled, Processing, Ready, Delivered).  
- Each record shows Student, Room, requested clothes/weight, status badge.  
- There are no creation actions; manager only monitors progress and updates statuses via backend (if available).  
- Bonus: `Comm Box` tile works like other roles.

---

## 7. Sports Manager

- Home tile `Raise Request` opens sports booking form.  
- Form fields: Facility dropdown, date tabs (Today/Tomorrow), available slots (start/end), student name/phone.  
- Submit creates a facility booking and the request shows under Sports tile (if available).  
- Sports Manager also sees `Requests` or `Comm Box` as per flags.

---

## 8. Notes for testers

- OTP bypass `123456` in UAT is defined in `mobile/docs/UAT_STAFF_APP_CHANGES.md`.  
- For roles that share test numbers (e.g., Warden and Rector), log out/back in to swap roles or verify server map roles.  
- Use `adb reverse tcp:8081 tcp:8081` if running Metro locally to load new JS bundles.

