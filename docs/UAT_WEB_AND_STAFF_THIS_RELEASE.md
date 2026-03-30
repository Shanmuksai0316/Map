# UAT: Web Panel & Staff App – This Release

Use this checklist to verify **all changes** from this release (web panel + staff app).  
**Prerequisites:** API deployed to server; staff app built with latest code; device/emulator with `adb reverse tcp:8081 tcp:8081` if using Metro.

---

## Part A: Web Panel (Super Admin & Campus Manager)

### A.1 Super Admin – Duplicate logo removed
- [ ] Log in to **Super Admin** panel (e.g. `/admin`).
- [ ] **Top bar:** Only **one** MAP logo is visible (top-left). There is **no** second logo in the middle of the top bar.
- [ ] Brand name / Super Admin text may appear next to the logo; no duplicate logo image.

### A.2 Super Admin – MFA tab hidden
- [ ] In the **left sidebar**, there is **no** “MFA Setup” or “MFA” menu item under Settings / System.
- [ ] Other menu items (Dashboard, Tenants, Staff, Reports, System Configuration, etc.) are unchanged.

### A.3 Campus Manager – No export option
- [ ] Log in as **Campus Manager** (tenant subdomain or campus-manager path).
- [ ] In the left sidebar, there is **no** “Reports” or “Data Export” menu item (or it is not visible to Campus Manager).
- [ ] Open **Students** (or any student list). There is **no** Export / Download button.
- [ ] Campus Manager can still **view** student details (list, view record); only export is removed.

### A.4 Room Overview – Filters (Hostel, Floor, Room type; no Block)
- [ ] As **Campus Manager**, go to **Room & Allocation** → **Room Overview** (or equivalent).
- [ ] Table filters are: **Hostel**, **Floor**, **Type** (room type). There is **no** “Block” filter.
- [ ] Selecting **Hostel** filters the list; selecting **Floor** filters the list. Both work.
- [ ] Room number search/column works as before.

### A.5 Room Change in Requests section
- [ ] As **Campus Manager**, in the left sidebar open the **Requests** group.
- [ ] **Room Change Requests** appears under **Requests** (not under “Room & Allocation” or “Rooms”).
- [ ] Open **Room Change Requests**. List and detail (approve/reject) work as before.

---

## Part B: Staff App (React Native)

For **full role-by-role** steps see: **`mobile/docs/UAT_STAFF_APP_CHANGES.md`**.  
Below is a **short checklist** for the main changes in this release.

### B.1 Incidents screen – No filters
- [ ] Log in as **Campus Manager** or **Warden** (staff app).
- [ ] Open **Incidents** / **Incidents Requests** (from Emergency or dashboard).
- [ ] The screen shows a **single list** of incidents. There are **no** filter pills (All / Unacknowledged / Acknowledged).
- [ ] List loads; empty state shows “No security incidents reported” when applicable.

### B.2 Profile screen – Cleanup
- [ ] Log in as any **staff role** (e.g. Guard, Warden, Campus Manager).
- [ ] Open **Profile**.
- [ ] **Header:** No “Edit” button in the top bar (only back + “Profile” title).
- [ ] **No** “Staff Actions” section (Edit Profile, Change Password, Staff Directory, System Settings).
- [ ] **No** “Support & Help” section.
- [ ] **No** “App Information” section (version, build type, user role).
- [ ] Screen shows: profile header, **Personal Information**, **Logout**, and footer only.

### B.3 Rector – Guest entries (list, detail, approve/reject)
- [ ] Log in as **Rector** in the staff app.
- [ ] Dashboard shows **Guest Entry** tile; open it.
- [ ] **List:** Guest entry requests appear as cards with `unique_id` (or request ID).
- [ ] Tap a card → **Detail screen** opens (same pattern as Leave/Outpass).
- [ ] **Approve** and **Reject** (with reason) are available and work; list updates after action.
- [ ] No 404 or “Failed to fetch guest entries” error.

### B.4 Rector – Outpass “Rejected” filter
- [ ] As **Rector**, open **Outpass** requests.
- [ ] If there is a **filter** or **status tab** for “Rejected”, select it.
- [ ] Outpasses that were **rejected/declined** appear in the list (filter works; no empty list when rejected items exist).

### B.5 Guard / Warden – General
- [ ] **Guard:** Tiles (QR/Verify, Checklist, Leave, Outpass, Guest Entry, Comm Box, Profile), QR backup code 4-digit, leave/outpass/guest list and detail, no filter on Comm Box.
- [ ] **Warden:** Attendance (today, room search, 2×2 grid), Emergency, Requests, Medical/Incidents (acknowledge), dashboard tiles as per design.

Use **`mobile/docs/UAT_STAFF_APP_CHANGES.md`** for full steps per role (Guard, Warden, Campus Manager, Rector, Sports Manager).

---

## Quick reference

| Area            | What to verify |
|-----------------|----------------|
| Web – Super Admin | One logo (top-left), no MFA in sidebar |
| Web – Campus Manager | No Reports/Export, Room filters Hostel+Floor+Type, Room Change under Requests |
| Staff – Incidents | No filter pills; single list |
| Staff – Profile  | No Edit, Staff Actions, Support, App Info |
| Staff – Rector  | Guest entry list/detail/approve-reject; Outpass rejected filter works |

---

## After deployment

1. **Web:** Clear browser cache or use incognito if you don’t see layout/nav changes.
2. **Staff app:** Rebuild/refresh the app so it uses the latest JS bundle; ensure API base URL points to the deployed server.
3. **adb (Android):** `adb reverse tcp:8081 tcp:8081` if testing with Metro against local API.
