# MAP HMS - Super Admin User Manual

**Version:** 1.0  
**Prepared on:** February 16, 2026  
**Audience:** Super Admin users (non-technical)

---

## 1. What this manual is for

This guide helps you run the **Super Admin panel** confidently, even if you are not from a technical background.

You will learn:
- Where each screen is in the menu
- What each button and table does
- How to onboard a new tenant (college/institution)
- How to assign staff correctly
- How to generate reports
- How to use settings safely

---

## 2. Before you start

You need:
- A valid Super Admin login
- Access to the URL: `https://admin.mapservices.in/admin`
- Tenant details ready (name, code, subdomain, key contacts)

Recommended browser:
- Latest Chrome or Edge

---

## 3. Quick start (10-minute flow)

If you are new, do this first:
1. Login to `https://admin.mapservices.in/admin`.
2. Open **Tenant Management > New Tenant Onboarding**.
3. Complete all 6 steps and click **Activate Tenant**.
4. Open **Staff Management > All Staff** and confirm staff assignments.
5. Open **Dashboard** and verify tenant/staff counts updated.

---

## 4. Login and basic navigation

## 4.1 Login screen

What you will see:
- Title: **Super Admin Login**
- Email/Password fields
- Login button
- Password reset option

What to do:
1. Enter your login credentials.
2. Click **Sign in**.
3. If password is forgotten, use **Forgot password**.

## 4.2 Main layout after login

### Left sidebar (main menu groups)
- Dashboard
- Tenant Management
- Staff Management
- Operations
- Reports
- Settings

### Top area
- Page title
- Your user/profile menu
- Logout option

### Common UI items used everywhere
- Search box in table
- Filter dropdowns
- Column sorting (click header)
- Row action buttons (`View`, `Edit`, etc.)
- Toast messages (green for success, red for errors)

---

## 5. Dashboard (home screen)

Menu path:
- **Dashboard**

### 5.1 Greeting section
You will see:
- Greeting like Good Morning/Good Afternoon
- Your name
- Current date
- Quick link: **+ New Tenant**

### 5.2 KPI cards
Main cards include:
- Total Tenants
- Total Hostels
- Total Students
- Staff Members
- Bed Utilization
- Staff Checklist completion
- Open Requests

How to read them:
- Green usually means healthy/good.
- Yellow means watch closely.
- Red means urgent attention needed.

### 5.3 Charts and trend blocks
You may see:
- Students by Tenant
- Tenant Status Distribution
- Global Occupancy
- Requests Overview (Pending, In Progress, Completed Today, Overdue)
- Attendance Closure Trend (7 days)
- Tickets by Priority (P1/P2/P3)

Use case example:
- If **Overdue Requests** is high, ask tenant teams to close old tickets first.

---

## 6. Tenant Management

Menu items:
- **New Tenant Onboarding**
- **All Tenants**
- **Archived Tenants**

## 6.1 New Tenant Onboarding (most important flow)

Menu path:
- **Tenant Management > New Tenant Onboarding**

This is a 6-step wizard.

### Step 1: Tenant Information
Fill:
- Tenant Name
- Tenant Code (must start with `MAP-`)
- Logo (optional)
- Campus Name
- Subdomain
- Rector details (name, phone, email)
- College Management details (name, phone, email)

Example:
- Tenant Name: `Tulip Institute`
- Tenant Code: `MAP-TULIP`
- Subdomain: `tulip`

### Step 2: Hostel Details
Add at least 1 hostel:
- Hostel Name
- Hostel Type (Boys/Girls/Co-Ed)
- Hostel Code
- Address
- Curfew time

### Step 3: Staff Assignment
Assign:
- 1 Campus Manager (tenant-level)
- Hostel-level roles for each hostel:
- Warden
- Guard
- HK Supervisor
- RM Supervisor
- Optional: Laundry Manager, Sports Manager

### Step 4: Hostel Configuration
Define room structure:
- Floor number
- Beds per room
- Number of rooms
- Room numbering mode

System auto-generates rooms and beds.

### Step 5: Amenities
Select available amenities from list (for example WiFi, Gym, Laundry, etc.).

### Step 6: Confirmation
- Review pre-flight checks
- Tick confirmation checkbox
- Click **Activate Tenant**

### Save options
- **Save Draft**: Save now and continue later
- **Save & Exit**: Save and go back to tenants list

Important behavior:
- Activation runs pre-flight validation.
- Tenant status moves to **Active** after successful activation.
- Success message includes created login users and subdomain details.

## 6.2 All Tenants

Menu path:
- **Tenant Management > All Tenants**

You can:
- View all tenants
- Search by code/name
- Filter by status
- Export tenant list
- Open tenant details
- Edit tenant details
- Start onboarding from header button

Main columns:
- Code
- Name
- Hostels count
- Students count
- Staff count
- Status
- Created date

## 6.3 Tenant details screen (`View`)

Inside one tenant record, you can:
- See tenant profile and branding
- See contact and address
- See related **Hostels** tab
- See related **Campuses** tab
- Use **Activate Tenant** button (if still in Provisioning)

## 6.4 Add hostels after activation

From tenant `View` page > **Hostels** relation:
- Click **Add Hostel**
- Fill hostel details
- Fill floor/room config
- Save

System will generate rooms and beds for the new hostel.

## 6.5 Archived Tenants

Menu path:
- **Tenant Management > Archived Tenants**

This is a read-only view of archived records.

---

## 7. Staff Management

Menu items:
- **All Staff** (primary working screen)
- **Assigned Staff**
- **Unassigned Staff**
- **Archived Staff**

## 7.1 All Staff (primary)

Menu path:
- **Staff Management > All Staff**

Use this screen for full lifecycle actions.

You can:
- Create new staff
- Edit profile
- Activate/deactivate staff login
- Assign or reassign tenant + hostel + role
- Revoke assignment
- View assignment history
- Filter by tenant, role, assignment status, active status

Main columns:
- Name
- Phone
- Tenant
- Role
- Assigned Hostel
- Assignment Since

## 7.2 Create new staff

Click:
- **Create New Staff**

Fill:
- Full Name
- Email
- Phone
- Optional Tenant
- Optional Notes

Use this tip:
- If tenant is left blank, staff appears under **Unassigned Staff** and can be assigned later.

## 7.3 Assign/Reassign staff

Row action:
- **Assign** or **Reassign**

Fill in modal:
- Tenant
- Role
- Hostel
- Notes

Expected result:
- Previous active assignment is auto-revoked
- New assignment is created
- Role is updated

Important rules:
- Only valid MAP staff roles can be assigned
- Duplicate same-role-for-same-hostel is blocked

## 7.4 Revoke assignment

Row action:
- **Revoke Assignment**

Fill:
- Revocation reason

Important rule:
- If this staff is the only person in that role at that hostel, revocation may be blocked until replacement is assigned.

## 7.5 Assignment history

Row action:
- **Assignment History**

Shows:
- Past assignments
- Revoked assignments
- Dates and notes

## 7.6 Assigned Staff

Menu path:
- **Staff Management > Assigned Staff**

Use this for quick review of currently assigned people.

Capabilities:
- Filter by tenant/role
- Export CSV
- Read-focused list

## 7.7 Unassigned Staff

Menu path:
- **Staff Management > Unassigned Staff**

Use this pool to:
- Add new unassigned staff
- Keep staff ready for future assignments

## 7.8 Archived Staff

Menu path:
- **Staff Management > Archived Staff**

Read-only historical view of archived staff.

## 7.9 Note about duplicate “All Staff” entry

In some builds, you may see another **All Staff** page (legacy style).

Recommendation:
- Use the **All Staff** page that supports assign/reassign/revoke actions as your main working screen.

---

## 8. Operations

Menu items:
- **Amenities**
- **Hostels**
- **Students**
- (In some builds) **Campuses**

## 8.1 Amenities

Menu path:
- **Operations > Amenities**

Use this to manage master amenity list.

You can:
- Create amenity key + label
- Edit amenity
- Delete amenity

Example:
- Key: `wifi`
- Label: `WiFi`

## 8.2 Hostels (cross-tenant view)

Menu path:
- **Operations > Hostels**

Read-only analytics view across tenants.

Shows:
- Tenant
- Hostel type
- Floors, rooms, beds
- Students
- Occupancy percentage

Useful for:
- Capacity planning
- Occupancy monitoring

## 8.3 Students (cross-tenant view)

Menu path:
- **Operations > Students**

Read-only student listing and details.

Shows:
- MAP ID
- ERP number
- Name
- Tenant
- Hostel
- Phone

## 8.4 Campuses (if visible)

In some builds, campuses appear as cross-tenant read-only listing.

## 8.5 Advanced pages (may be hidden from sidebar)

Some Super Admin pages are implemented but not always shown in left menu.

Examples:
- Requests view: `/admin/requests`
- Communications view: `/admin/communications`

Use these only if your implementation/support team has enabled them for your workflow.

---

## 9. Reports

Common menu items in Reports group:
- **Report Center**
- **Reports** (queue/history style page, feature-flag dependent)

## 9.1 Report Center (recommended)

Menu path:
- **Reports > Report Center**

Steps:
1. Select report type.
2. Select tenant (or All Tenants).
3. Select start/end date.
4. Select format.
5. Click **Download Report**.

Available report types include:
- Tenant Overview
- Occupancy Report
- Student Data Export
- Staff Deployment
- Attendance Compliance
- Request Summary
- Incident Report
- Checkout/Renewal
- Payments
- Audit Trail

If no data exists for selected filters:
- System shows **No data found** warning.

## 9.2 Reports page (if enabled)

Menu path:
- **Reports > Reports**

Use this page for report history/queue style workflows, depending on deployment configuration.

---

## 10. Settings

Menu path:
- **Settings > Settings**

This is global platform configuration. Changes affect all tenants.

Sections:
- Feature Flags
- Default Subscription Settings
- Integration Settings
- System Maintenance
- Advanced Settings

Actions:
- **Save Configuration**
- **Clear Cache**

## 10.1 Feature flags

Common toggles:
- Onboarding Wizard V2
- Super Admin Staff Management
- TOTP MFA
- SMS Notifications

## 10.2 Default subscription settings

Set default values for new tenants:
- Plan
- Duration (months)
- Trial days

## 10.3 Integration settings

Set credentials/config like:
- MSG91 auth key
- S3 bucket and region

## 10.4 Maintenance mode

Enable only when required.

Effect:
- Can block normal users while maintenance is active.

---

## 11. Impersonation mode (advanced support feature)

What it does:
- Lets Super Admin temporarily enter tenant user context for support/debugging.

How you know impersonation is active:
- A yellow top banner shows **IMPERSONATION MODE**
- Button available: **Stop Impersonation**

Safety:
- Start and stop actions are logged.

Note:
- This feature may be available through direct support flow, not always as a sidebar button.

---

## 12. Daily, weekly, monthly operating routine

## Daily
1. Check dashboard KPIs (requests, occupancy, checklist completion).
2. Review onboarding in-progress tenants.
3. Review urgent staffing gaps.

## Weekly
1. Export tenant and staff reports.
2. Check unassigned staff pool and reduce pending assignments.
3. Verify any overdue requests trend.

## Monthly
1. Review subscription and payment notes per tenant.
2. Audit inactive or archived records.
3. Review feature flag settings before releases.

---

## 13. Common issues and quick fixes

### Issue: Cannot activate tenant
Check:
- Required staff assigned
- At least one hostel exists
- Curfew set
- Rooms/beds generated
- Rector and College Management phone numbers present

### Issue: Staff cannot log in
Check:
- Staff is active
- Staff has tenant assignment
- Staff has hostel assignment (for hostel-scoped roles)
- Role is correct

### Issue: Report download empty
Check:
- Date range
- Tenant filter
- Report type has data in that period

### Issue: Add hostel not visible
Check:
- Open tenant record in **View** page
- Use Hostels relation tab
- Confirm you are logged in as Super Admin

---

## 14. Simple glossary

- **Tenant**: One institution/college account.
- **Campus**: Main campus object under a tenant.
- **Hostel**: Hostel building under campus/tenant.
- **Provisioning**: Setup in progress, not yet fully live.
- **Active**: Live and operational.
- **Archived**: Historical/inactive record.
- **Assignment**: Linking a staff member to tenant + hostel + role.
- **Pre-flight check**: System validation before activation.

---

## 15. Suggested first training exercise (for client team)

Do this once as practice:
1. Create a demo tenant with one boys hostel and one girls hostel.
2. Assign one person each for Campus Manager, Warden, Guard, HK Supervisor, RM Supervisor.
3. Activate tenant.
4. Add one more hostel post-activation.
5. Generate one occupancy report for this tenant.
6. Deactivate and reactivate one staff user to understand staff lifecycle.

This single exercise covers most day-to-day Super Admin work.

---


