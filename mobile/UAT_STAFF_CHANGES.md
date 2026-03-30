# UAT Checklist – Staff App (Role-Wise)

Test **one role at a time**. Shared checks (splash + dashboard common) can be done once with any staff login.

**Suggested order:** Shared → Campus Manager → Warden → Guard → RM Supervisor → HK Supervisor → Laundry Manager → Sports Manager / Rector (if needed).

---

## Shared (all staff roles)

*Verify once with any staff account.*

### Splash screen
- [ ] Background is **olive green**
- [ ] **KARTHA** text at top uses **Ethnocentric** font, **white**
- [ ] Tagline **"Smart control for Smarter hostels."** in **yellow**
- [ ] Central illustration (staff-splash-image) visible (~340×280px)
- [ ] **Get Started** button: yellow border, transparent background, yellow text; navigates correctly

### Every staff home screen
- [ ] **Intro / greeting card** height is **168px**
- [ ] **Footer** shows:
  - "Proudly made in India 🇮🇳"
  - "Version 1.0.0"
  - "OMAP Services Management Pvt Ltd"

---

## Role 1: Campus Manager

*Login as Campus Manager and complete all below.*

### Home
- [ ] **No stat boxes** on dashboard (no Active Hostels, Students, Open Requests, etc.)

### Checklist
- [ ] Screen header height matches home screen header
- [ ] **Staff Checklist** tab shows only **completed** checklists (100% completion) by staff

### Requests Hub
- [ ] Screen header height matches home screen
- [ ] **Outpass** tab: only **approved** outpass requests
- [ ] **Leave** tab: only **approved** leave requests
- [ ] **Guest Entry** tab: only **approved** guest entry requests
- [ ] Request cards show **type-specific** preview (housekeeping / maintenance / outpass / leave / guest-entry / sports / laundry)
- [ ] Cards show **Student ID** where relevant

### Notice Board
- [ ] **Urgent** filter option is **removed**
- [ ] Urgent badge still shows on individual items (if applicable)

### Emergency
- [ ] **Quick Actions** section **removed**
- [ ] **Emergency Protocol** section **removed**
- [ ] **Medical Emergencies** and **Security Incidents** cards **present**

### Profile
- [ ] **Profile** tile opens **dedicated Profile screen** (not popup)
- [ ] Profile shows: Name, Role, Employee ID, Phone Number, Hostel Name, Tenant Name, Logout

---

## Role 2: Warden

*Login as Warden and complete all below.*

### Home
- [ ] Intro card 168px and footer present (if not already checked in Shared)

### Outpass approvals
- [ ] Each card shows **"Outpass #&lt;id&gt;"** at the top

### Leave approvals
- [ ] Each card shows **"Leave #&lt;id&gt;"** at the top

### Requests (Warden requests list)
- [ ] Each card shows **"Request #&lt;id&gt;"** at the top

---

## Role 3: Guard

*Login as Guard and complete all below.*

- [ ] Intro card 168px and footer present
- [ ] Existing flows (outpass check, visitor management, gate entry/exit) work as before *(no changes in this chat)*

---

## Role 4: RM Supervisor (Repair & Maintenance)

*Login as RM Supervisor and complete all below.*

### Home
- [ ] Action tiles order: **Checklist → Requests → Notice Board → Profile**

### Requests
- [ ] Each request card shows **"Request #&lt;id&gt;"** above the title

### Profile
- [ ] Shows: Name, Role, Employee ID, Phone Number, Hostel Name, Tenant Name

### History
- [ ] **No calendar**
- [ ] Shows **completed** tickets (resolved/closed/completed), **newest first**
- [ ] Same card style as Requests screen

---

## Role 5: HK Supervisor (Housekeeping)

*Login as HK Supervisor and complete all below.*

### Home
- [ ] Action tiles order: **Checklist → Requests → Notice Board → Profile**

### Requests
- [ ] Each request card shows **"Request #&lt;id&gt;"** above the title

### Profile
- [ ] Shows: Name, Role, Employee ID, Phone Number, Hostel Name, Tenant Name

### History
- [ ] **No calendar**
- [ ] Completed tickets, newest first; same card style as Requests

---

## Role 6: Laundry Manager

*Login as Laundry Manager and complete all below.*

### Home
- [ ] **Profile** and **Notice Board** tiles are **swapped** (order matches spec)

### Laundry requests list
- [ ] Each card shows **"Request #&lt;id&gt;"** at top
- [ ] **Student ID** visible on each card
- [ ] **SLA pill** on each card: **Within SLA** (green) / **Near SLA** (orange) / **Breached** (red); hidden for completed/delivered

### Laundry request detail (open any active request)
- [ ] For status **pending / scheduled / collected / washing**: **Change status** button visible
- [ ] **Change status** opens modal with **one next step** (e.g. Pending→Scheduled, Scheduled→Collected, Collected→Washing, Washing→Drying); optional notes; **Update status** works
- [ ] For **pending / scheduled / collected / washing / drying**: **Mark Ready** button visible
- [ ] **Mark Ready** sets status to **Ready for Pickup** (student receives 4-digit code)
- [ ] When status is **Ready for Pickup**: **Pickup code** badge visible (4-digit)
- [ ] When **Ready for Pickup**: **Verify Pickup & Complete** button visible
- [ ] **Verify Pickup & Complete**: enter 4-digit code (modal on Android, prompt on iOS); correct code marks request **Completed**

---

## Role 7: Sports Manager / Rector

*If you test these roles:*

- [ ] Intro card 168px and footer on home
- [ ] No other changes in this chat; verify existing flows as needed

---

## Notes

- **Backend**: API running; for Laundry Manager ensure `/mobile/laundry/requests`, `PATCH .../status`, `POST .../verify-code` work.
- **Accounts**: Use test accounts per role (Campus Manager, Warden, Guard, RM Supervisor, HK Supervisor, Laundry Manager).
- **Font**: If Ethnocentric doesn’t show on Android, do a full native rebuild.

---

*UAT checklist – staff app changes, role-wise.*
