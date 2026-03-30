# Product requirements (client Q&A summary)

Summary of client responses for implementation reference. Items marked **Done** are implemented; others are for future sprints.

---

## Dashboard and login

- **Login screen design update** – Client has a screen and design elements to use. Use provided reference when implementing.
- **Duplicate logos (Super Admin)** – **Done.** Keep only top-left MAP logo; middle logo removed.
- **Dashboard** – After login user lands on current dashboard; details remain same (no change).
- **Date and super admin information** – Ignore.

---

## MFA

- **MFA removal** – **Done.** MFA tab removed from Super Admin left side menu (page still exists for direct URL; not in nav).

---

## Multi-hostel / campus / rector

- **All points (existing data, campus vs hostel, hostel switcher, rector assignment)** – Ignore for now; client will provide a proper flow later.

---

## Staff (roles, archive, reassignment)

- **6 roles per hostel** – Warden, Housekeeping, Repair & Maintenance, Security Guard, Laundry Manager, Sports Manager. Rector + Campus Manager are tenant-level only.
- **Archive** – Archived staff move to archive list. Reassignment from archive not available.
- **Replacement** – Handled by MAP team: find replacement first, then archive and replace account (no “vacant” workflow in app).
- **SMS for staff assignments** – Ignore for now.

---

## Students

- **Bulk upload / gender–hostel** – Ignore for now.
- **Campus Manager export** – **Done.** Campus Manager can see student details but has no export option (Reports/Data Export hidden for Campus Manager).

---

## Rooms

- **Room change in requests section** – **Done.** Room change appears in Requests section with list/detail/approve-reject; moved to “Requests” nav group.
- **Room Overview filters** – **Done.** Filters are Hostel, Floor, and Room (Type). Block is not included (not implemented in hostel configuration).

---

## Checkout and renewal

- **Checkout due** – List students who are due in 2–3 months; logic based on academic year-end.
- **List** – Single list (e.g. “Checkout due in next 3 months”), not separate sections by month.
- **Renewal** – Multiple renewals allowed (extend again after one year).

---

## Bulk and unassigned pool

- **Activated** – Means assigning a room; no separate “Approve/Activate” step.
- **Add student and Bulk upload** – On the same page (one screen with both actions).

---

## Checklist and media

- **Staff checklist submissions** – Accessible for hostels under the user only.
- **Photos/media** – Display as links; click opens in new tab. Stored as uploaded files; no storage URL pattern yet.

---

## Reports and export

- **Report types / staging** – Ignore for now.
- **Export format** – PDF only when reports are implemented.

---

## Testing and rollout

- **Parallel staging / tenant flow** – Ignore for now.
