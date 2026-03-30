# MAP HMS - Student App Manual

**Version:** 1.0  
**Prepared on:** February 18, 2026  
**Audience:** Students and Resident Assistants

---

## 1. Preconditions

- App: Student-facing React Native bundle (tenant-specific subdomain).  
- Login: OTP (bypass `123456` on test numbers).  
- Required: Active student profile with phone number.

---

## 2. Home screen overview

- Tiles typically include: Attendance, Outpass, Leave, Requests, Comm Box, Profile, History.  
- Notifications appear as red badges on Comm Box or header bell.
- Tap a tile to go to its workflow.

---

## 3. Attendance

- **Status:** shows Today’s attendance summary or buttons to mark presence.  
- **Room search:** enter room number to focus on that entry.  
- Submitting a room’s attendance updates server and shows success toast.

---

## 4. Requests (Outpass / Leave / Guest)

- Each request type uses preview cards showing ID, dates, faculty/warden approval status.  
- Cards show current status (Pending, Approved, Rejected, Cancelled).  
- Tap a card to view details (reason, dates, approver comments).  
- Actions such as Cancel appear if allowed.

---

## 5. Comm Box

- Displays notices pushed by administration.  
- No additional filters; scroll through chronological feed.

---

## 6. Profile and History

- Profile shows name, hostel, room, phone, student ID.  
- History tab lists previous requests (Leave, Outpass, Guest) sorted by date.
- Each entry shows status badge, dates, and outcome comments.

---

## 7. Notifications and errors

- Toasts confirm actions (e.g., "Outpass request sent").  
- Errors show inline or toast (e.g., OTP failure, validation).  
- If a screen appears empty, check filters/date pickers and pull to refresh.

---

## 8. Best practices

- Submit Outpass/Leave before the expected departure date.  
- Attach comments/attachments when prompted (if required).  
- Use History tab to double-check prior decisions.

