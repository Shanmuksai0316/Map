# MAP HMS - Campus Manager Web Panel User Manual

**Version:** 1.0  
**Prepared on:** February 16, 2026  
**Audience:** Campus Manager users (non-technical)

---

## 1. Why this manual exists

This guide helps you run the **Campus Manager web panel** confidently in day-to-day hostel operations.

You will learn:
- Where each screen is in the sidebar
- What each table, button, and status means
- How to manage students and room allocation
- How to monitor requests and emergencies
- How to use checklist, comm box, and reports correctly

---

## 2. Before you start

You need:
- A valid Campus Manager account
- Access to tenant URL: `https://<your-tenant-subdomain>.mapservices.in/campus-manager`
- Access to your registered phone number (for OTP)

Recommended browser:
- Latest Chrome or Edge

---

## 3. Quick start (first 10 minutes)

1. Open the Campus Manager URL.
2. Enter your phone number and click **Send OTP**.
3. Enter OTP and click **Verify & Login**.
4. On Dashboard, choose hostel from **Hostel Switcher** if needed.
5. Open **Student Management > Unassigned Students** and allocate pending students.
6. Open **Requests > Delayed Requests** and check urgent items.
7. Open **Emergency > Medical** and **Emergency > Incidents** and clear red unacknowledged items.
8. Open **Operations > Reports** and download today/weekly report.

---

## 4. Login and navigation

## 4.1 Login (OTP)

What you see:
- Phone Number field
- Send OTP button
- OTP field after sending OTP
- Verify & Login button
- Resend OTP and Change Phone Number links

How to login:
1. Enter registered phone number.
2. Click **Send OTP**.
3. Enter 6-digit OTP.
4. Click **Verify & Login**.

If OTP fails:
- Use **Resend OTP**.
- Confirm your phone number is correct.

## 4.2 Main layout after login

Sidebar groups:
- Dashboard
- Student Management
- Room & Allocation
- Checklist
- Requests
- Communications
- Emergency
- Operations

Common UI items everywhere:
- Search box in table
- Filter dropdowns
- Status badges (Pending, In Progress, Resolved, etc.)
- Row actions (View, Edit, Approve, Reject)
- Success/Error toasts at bottom/top

---

## 5. Dashboard

Menu path:
- **Dashboard**

## 5.1 Top controls

- **Hostel Switcher**: choose one hostel or all hostels.
- **Time range filter**: changes dashboard charts (for example 7 days, 14 days, etc.).

## 5.2 KPI cards

Main cards:
- Active Hostels
- Resident Students

## 5.3 Charts

You will see:
- Hostel Occupancy
- Request Status Breakdown
- Attendance Trend
- Upcoming Checkouts
- Pass Request Trend

## 5.4 Operational widgets

You will also see:
- Checklist compliance summary
- Room change queue summary
- Activity feed (with option to add internal notes)

Practical use:
- Start each day by checking delayed items, occupancy risk, and pending approvals.

---

## 6. Student Management

Menu items:
- All Students
- Assigned Students
- Unassigned Students
- Bulk Upload Students

## 6.1 All Students

Menu path:
- **Student Management > All Students**

You can:
- Create student
- View student details
- Edit student details
- Activate/Deactivate student
- Archive/Restore student
- Allocate room to unassigned student

Important columns:
- Name
- Student ID
- Year
- Programme
- Contact
- Hostel
- Room
- Status (Assigned/Unassigned)

Important filters:
- Hostel
- Year
- Allocation status

### Create student

1. Click **Create Student**.
2. Fill sections:
- Basic Information
- Academic Details
- Emergency Contacts
- Medical Information
3. Save.

### Allocate room

1. From student row click **Allocate Room**.
2. Select room.
3. Select available bed.
4. Set allocation start date.
5. Save.

## 6.2 Assigned Students

Menu path:
- **Student Management > Assigned Students**

Use this to:
- See only students with active room allocation
- Verify hostel and room mapping
- Open View/Edit for corrections

## 6.3 Unassigned Students

Menu path:
- **Student Management > Unassigned Students**

Use this to:
- Find students waiting for room allocation
- Use **Activate Selected** bulk action when onboarding is ready

## 6.4 Bulk Upload Students

Menu path:
- **Student Management > Bulk Upload Students**

Flow:
1. Open import page.
2. Download template (if needed).
3. Upload CSV/XLSX file.
4. Run **Dry Run**.
5. Review counts and errors.
6. Click **Commit** only when dry run is clean.

Important:
- Always validate dry-run errors before commit.
- Imported students still need proper room allocation if unassigned.

---

## 7. Room & Allocation

Menu items:
- Room Overview
- Assigned Rooms
- Unassigned Rooms

## 7.1 Room Overview

Menu path:
- **Room & Allocation > Room Overview**

You can:
- View room inventory
- Edit room details
- Manage bed list in room

Important filters:
- Hostel
- Floor
- Type

Important behavior:
- Structural creation is locked after tenant activation (new room creation blocked).

## 7.2 Assigned Rooms

Menu path:
- **Room & Allocation > Assigned Rooms**

Shows rooms with at least one occupied bed.

Use this to:
- Track occupancy distribution
- Check assigned date and occupied count

## 7.3 Unassigned Rooms

Menu path:
- **Room & Allocation > Unassigned Rooms**

Shows rooms with no occupied beds.

Use this to:
- Find ready rooms for upcoming allocations

---

## 8. Checklist

This section is visible when checklist module is enabled.

Menu items:
- Checklist Configuration
- My Daily Checklist
- Staff Checklists

## 8.1 Checklist Configuration

Menu path:
- **Checklist > Checklist Configuration**

You can:
- Switch role template (Warden, Guard, HK, RM, Laundry, Sports, etc.)
- Add/edit up to 10 tasks per role
- Mark role checklist active/inactive
- Save role-wise template

## 8.2 My Daily Checklist

Menu path:
- **Checklist > My Daily Checklist**

You can:
- Tick checklist items
- Submit after completing all required tasks

## 8.3 Staff Checklists

Menu path:
- **Checklist > Staff Checklists**

You can:
- See submitted checklists from staff
- Filter by date range, completion, role, status
- **Approve** checklist
- **Send Back** checklist with note

---

## 9. Requests

Menu items:
- Delayed Requests
- Housekeeping
- Repair & Maintenance
- Outpass
- Leave
- Guest Entry
- Room Change Requests
- Sports (if enabled)

## 9.1 Delayed Requests

Shows overdue housekeeping/maintenance items.  
Use this first for priority clearing.

## 9.2 Housekeeping

You can:
- View request details
- Track Pending/In Progress/Resolved
- Check SLA status badge (On time/Delayed)

## 9.3 Repair & Maintenance

You can:
- View request details
- Track status and SLA

## 9.4 Outpass

You can:
- View outpass request records and status history
- Filter by request status

## 9.5 Leave

You can:
- View leave requests
- Check date range and status

## 9.6 Guest Entry

You can:
- View guest request details, ID proof info, relation, status

## 9.7 Room Change Requests

You can:
- Review pending room change requests
- Approve with destination bed and effective date
- Reject with reason

## 9.8 Sports (optional)

If sports module is enabled, this page shows facility booking requests.

---

## 10. Communications (Comm Box)

Menu path:
- **Communications > Comm Box**

Use this to publish announcements.

You can:
- Create notice with title and content
- Target by campus and hostel
- Select audience (students/staff/both)
- Choose channels (push/email)
- Publish now or schedule
- Set expiry date

Common statuses:
- Draft
- Scheduled
- Published

---

## 11. Emergency

Menu items:
- Medical
- Incidents

## 11.1 Medical

Shows medical emergency requests.

You can:
- Open detail modal
- Click **Acknowledge** for unacknowledged cases

## 11.2 Incidents

Shows non-medical incidents.

You can:
- Open incident detail
- Click **Acknowledge**

Note:
- Unacknowledged incidents are highlighted for quick response.

---

## 12. Operations

Menu items:
- My Staff
- Hostels
- Reports

## 12.1 My Staff

Menu path:
- **Operations > My Staff**

Shows assigned operational roles such as:
- Warden
- Guard
- HK Supervisor
- RM Supervisor
- Laundry Manager
- Sports Manager

Current build note:
- This screen is configured to show staff mapped for Tulip Boys Hostel role scope.

## 12.2 Hostels

Menu path:
- **Operations > Hostels**

You can:
- View hostel details
- Edit timings and rules (curfew, visiting window, overnight switch)

Important:
- Core fields like hostel name/code/gender are onboarding-controlled and locked later.
- Creating new hostel is blocked after tenant activation.

## 12.3 Reports

Menu path:
- **Operations > Reports**

You can download CSV/XLSX for:
- Housekeeping requests
- Maintenance requests
- Pass requests
- Attendance summary
- Attendance detail
- Checklist compliance
- Room occupancy
- Guest visit log
- Incident summary

Inputs:
- Report type
- Date range
- Hostel filter
- Format

---

## 13. Optional and advanced screens

Depending on feature flags, some screens may appear or stay hidden:
- Laundry Requests module
- Sports Facilities and Sports Events management
- Gate Entry logs
- Attendance Sessions (resource exists but may be hidden from sidebar)

If your menu does not show a module, ask Super Admin to confirm feature toggle.

---

## 14. Recommended daily routine

Morning:
1. Dashboard check (delays, occupancy, attendance trend).
2. Emergency acknowledgements.
3. Delayed Requests follow-up.
4. Student allocation cleanup.

Afternoon:
1. Checklist review and approvals.
2. Room change decisions.
3. Publish comm box notice (if needed).

End of day:
1. Download daily reports.
2. Log key note in activity feed.
3. Verify no critical requests remain pending.

---

## 15. Common issues and simple fixes

Issue: OTP not received  
Try: resend OTP, verify number format, check with admin.

Issue: Empty table  
Try: clear filters, check selected hostel in Hostel Switcher, verify date range.

Issue: Cannot see module  
Try: ask Super Admin to enable feature flag for tenant.

Issue: Cannot create room/hostel  
Reason: tenant is active and structural lock is enforced.

Issue: Unable to approve/send back checklist  
Try: ensure checklist status is Submitted and user has role permission.

---

## 16. Quick glossary

- Tenant: your institution account in MAP HMS
- Hostel Switcher: top selector that limits data to one hostel
- SLA: expected response time target
- Comm Box: announcement center
- Dry Run: validation run before final import commit

