# MAP HMS - College Management Web Panel User Manual

**Version:** 1.0  
**Prepared on:** February 16, 2026  
**Audience:** College Management users (non-technical)

---

## 1. Why this manual exists

This guide explains how to use the **College Management web panel** in simple language.

This panel is mainly for:
- Monitoring operations
- Reviewing hostel performance
- Viewing student, out-pass, ticket, and attendance data
- Downloading management reports

Important:
- This panel is primarily **read-only** for operational records.

---

## 2. Before you start

You need:
- College Management login account
- Access to URL: `https://<your-tenant-subdomain>.mapservices.in/college-mgmt`
- Access to registered phone number for OTP

Recommended browser:
- Latest Chrome or Edge

---

## 3. Quick start (first 10 minutes)

1. Login with OTP.
2. Open Dashboard and review KPI cards.
3. Open Students and verify hostel/year mix.
4. Open Operations > Out-Passes and check status distribution.
5. Open Operations > Tickets and filter high-priority items.
6. Open Operations > Attendance and check latest session progress.
7. Open Operations > Reports and download summary report.

---

## 4. Login and navigation

## 4.1 OTP login

How to login:
1. Enter phone number.
2. Send OTP.
3. Enter 6-digit OTP.
4. Click verify/login.

## 4.2 Sidebar structure

Typical menu:
- Dashboard
- Students
- Operations group:
- Out-Passes
- Tickets
- Attendance
- Reports

---

## 5. Dashboard

Menu path:
- **Dashboard**

The dashboard provides management KPIs.

Common KPI cards include:
- Occupancy %
- Approval Median (hours)
- Late Returns
- Ticket Aging
- Fee Status
- Checklist Compliance
- Total Students
- Total Hostels
- Sports Utilisation (if sports addon is enabled)

How to use dashboard:
- Track trends and exception metrics.
- Use high-risk cards (late returns, aging, low fee status) for escalation review.

---

## 6. Students (read-only)

Menu path:
- **Students**

You can:
- Search by name/ID
- Filter by hostel and year
- Open full student profile

You cannot:
- Create student
- Edit student
- Delete student

Student profile includes:
- Basic information
- Academic details
- Hostel allocation
- Parent/guardian details
- Medical details

---

## 7. Operations - Out-Passes (read-only)

Menu path:
- **Operations > Out-Passes**

You can:
- View all out-pass records
- Filter by status, hostel, and date range
- Open request detail

Important columns:
- Request ID
- Student Name
- Room Number
- Hostel
- Purpose
- Overnight flag
- Status
- Dates (requested/decided)

You cannot approve/reject from this panel.

---

## 8. Operations - Tickets (read-only)

Menu path:
- **Operations > Tickets**

You can:
- View ticket list across categories
- Filter by status, priority, category
- Open ticket detail (assignment + timestamps)

Important columns:
- ID
- Title
- Status
- Priority
- Category
- Assignee
- Reporter
- Hostel

You cannot create/edit/delete tickets in this panel.

---

## 9. Operations - Attendance (read-only)

Menu path:
- **Operations > Attendance**

You can:
- View attendance sessions
- Filter by hostel, date range, status
- Open session detail

Session detail includes:
- Hostel
- Session type
- Scheduled time
- Session status
- Progress summary (Present/Absent/Leave/Unmarked)

---

## 10. Operations - Reports

Menu path:
- **Operations > Reports**

You can download reports by date range and format.

Available report types:
- Attendance Summary
- Attendance Detail
- Pass Requests (Outpass/Leave)
- Incident Summary
- Room Occupancy
- Checklist Compliance

Formats:
- CSV
- XLSX option is shown in UI, export stream is CSV style output in current implementation

If data is empty:
- You will get a "No data found" warning.

---

## 11. Daily management routine (recommended)

Morning:
1. Open Dashboard and check red/yellow KPIs.
2. Open Tickets and review high-priority and open items.

Afternoon:
1. Open Out-Passes and check pending and late-return patterns.
2. Open Attendance and verify session closure quality.

End of day:
1. Download reports for leadership review.
2. Share summary with operations team.

---

## 12. Common issues and quick fixes

Issue: OTP not received  
Try: resend OTP, verify phone format, contact admin.

Issue: No records in list  
Try: clear filters, check date range, confirm tenant subdomain.

Issue: Cannot edit records  
Reason: this panel is designed as read-only for these modules.

Issue: Report download empty  
Try: wider date range or alternate report type.

---

## 13. Quick glossary

- Occupancy: % of occupied beds out of total beds
- Late Returns: students not returned by expected time
- Ticket Aging: resolution time trend for issues
- Read-only: data can be viewed but not modified

