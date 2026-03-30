# MAP HMS - Rector Web Panel User Manual

**Version:** 1.0  
**Prepared on:** February 16, 2026  
**Audience:** Rector users (non-technical)

---

## 1. Why this manual exists

This guide explains how to use the **Rector web panel** clearly and confidently.

You will learn:
- How to login with OTP
- How to review and decide student approvals
- How to use dashboard decision widgets
- How to view student information
- How to download reports

---

## 2. Before you start

You need:
- A Rector account
- Access to URL: `https://<your-tenant-subdomain>.mapservices.in/rector`
- Access to your registered mobile number for OTP

Recommended browser:
- Latest Chrome or Edge

---

## 3. Quick start (first 10 minutes)

1. Login using phone and OTP.
2. Open **Out-Pass Approvals** and clear oldest pending requests.
3. Open **Leave Approvals** and complete pending decisions.
4. Check **Approval History** for decision audit.
5. Open **Dashboard** and review pending, SLA, and recent decisions.
6. Download a report from **Reports**.

---

## 4. Login and navigation

## 4.1 OTP login

What you see:
- Phone number field
- OTP field (after OTP is sent)
- OTP resend option

How to login:
1. Enter registered phone number.
2. Send OTP.
3. Enter 6-digit OTP.
4. Click login/verify.

## 4.2 Main sidebar

Typical menu items:
- Dashboard
- Approvals group:
- Out-Pass Approvals
- Leave Approvals
- Approval History
- Students
- Reports

User menu (top-right):
- My Profile
- Logout

---

## 5. Dashboard

Menu path:
- **Dashboard**

## 5.1 Dashboard cards and widgets

You will see:
- Greeting card
- Active Hostels (under your oversight)
- Resident Students
- Pending Requests
- Approval Trend chart (Approved vs Declined)
- Hostel Occupancy chart
- Urgent Pending Approvals table
- SLA Performance chart
- Recent Decisions table

## 5.2 Monthly report action on dashboard

Top action:
- **Download Monthly Report**

You can choose:
- Month
- Year
- Format (PDF/CSV)

After generation, a download action appears in notification.

---

## 6. Out-Pass Approvals

Menu path:
- **Approvals > Out-Pass Approvals**

## 6.1 What you can do

You can:
- View pending out-pass requests
- Filter by status, hostel, and date range
- Open request details
- Approve with note
- Decline with reason
- Bulk approve selected pending requests

Important columns:
- Request ID
- Student Name
- Room Number
- Purpose
- Going Out Date
- SLA Status

## 6.2 Decision rules

- Only pending requests are actionable.
- If request is too old (expired rule), it cannot be approved.
- Every approval/rejection is recorded in history.

---

## 7. Leave Approvals

Menu path:
- **Approvals > Leave Approvals**

This list includes:
- Regular leave requests
- Sick leave requests

## 7.1 What you can do

You can:
- Filter by status, request type, and date range
- View full leave details
- Approve with note
- Reject with reason
- Use quick note templates
- Bulk approve pending items

Important columns:
- Request ID
- Student Name
- Room Number
- Purpose
- Date range
- Request type

---

## 8. Approval History

Menu path:
- **Approvals > Approval History**

Use this page to audit past decisions.

You can:
- Filter by decision (Approved/Declined)
- Filter by date range
- See decision timestamp, actor, and note

---

## 9. Students (read-only)

Menu path:
- **Students**

You can:
- Search students
- Filter by hostel and year
- Open complete student profile

You cannot:
- Create student
- Edit student
- Delete student

Profile sections include:
- Basic information
- Academic details
- Hostel allocation
- Parent and guardian details
- Medical details

---

## 10. Reports

Menu path:
- **Reports**

You can download reports for selected date range and format.

Available report types:
- Approval Summary (Pass/Leave)
- Attendance Summary
- Attendance Detail
- Checklist Compliance
- Incident Summary
- Pass Requests Detail

Formats:
- CSV
- XLSX option is shown in UI, export stream is CSV style output in current implementation

If no data is found:
- You will get a "No data found" warning.

---

## 11. My Profile

Menu path:
- Top-right user menu > **My Profile**

You can view:
- Name
- Role
- Phone
- Email
- College/Tenant info
- Tenant code

---

## 12. Recommended daily routine

Morning:
1. Open Dashboard.
2. Check urgent pending approvals table.
3. Clear oldest Out-Pass and Leave requests first.

Afternoon:
1. Recheck SLA widget for near-breach requests.
2. Review recent decisions for quality check.

End of day:
1. Open Approval History and verify all major decisions have proper notes.
2. Download daily/weekly report from Reports page.

---

## 13. Common issues and quick fixes

Issue: OTP not received  
Try: resend OTP, verify number format, check with admin.

Issue: No requests visible  
Try: clear filters, verify hostel assignment, verify tenant URL.

Issue: Cannot approve/reject  
Try: check request is still pending and not already decided/expired.

Issue: Empty report download  
Try: widen date range and retry.

---

## 14. Quick glossary

- Pending: request waiting for your decision
- SLA: expected maximum decision time
- Declined/Rejected: request denied
- Approval History: audit trail of decisions

