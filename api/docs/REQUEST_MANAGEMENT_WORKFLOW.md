# Request Management – Complete Workflow

This document describes how each request type works end-to-end: creation, status flow, SLA/delayed logic, and campus manager handling.

---

## 1. Request Types Overview

| Type | Model | Statuses | SLA (72h) | Delayed view |
|------|--------|----------|-----------|--------------|
| **Housekeeping** | `Ticket` (category: `housekeeping`) | Pending → In Progress → Resolved | Yes | Yes |
| **Repair & Maintenance** | `Ticket` (category: `maintenance` / `repair_maintenance`) | Pending → In Progress → Resolved | Yes | Yes |
| **Laundry** | `LaundryRequest` | Pending → … → Ready → Delivered/Completed | Yes | Yes |
| **Leave** | `Leave` | Pending → Approved / Rejected | No | No |

---

## 2. Housekeeping Requests (Ticket)

### 2.1 Creation (raise)

- **Who:** Student (mobile app).
- **API:** `POST /api/v1/tickets`
- **Payload:** `title`, `issue`, `description`, `request_type: "housekeeping"`, optional `photos`.
- **Backend:** `Ticket::create()` with:
  - `category` = `housekeeping`
  - `status` = `open` (Pending)
  - `reporter_student_id`, `hostel_id`, `tenant_id`, `created_by_user_id`
  - `sla_due_at` = now + 4h (legacy; SLA for “delayed” uses 72h from `created_at`)

### 2.2 Status flow (display names)

- **open** → **Pending**
- **in_progress** → **In Progress**
- **resolved** / **closed** → **Resolved**

Allowed transitions (Ticket model):

- `open` → `in_progress`, `on_hold`, `closed`
- `in_progress` → `on_hold`, `resolved`, `closed`
- `on_hold` → `in_progress`, `closed`
- `resolved` → `closed`, `open`
- `closed` → `open`

### 2.3 Where status is updated

- **Campus Manager (Filament):** **Requests → Housekeeping** (table) or **Tickets** resource → **View** → **Change Status**.
- Status change sets `updated_by_user_id`; for `resolved`/`closed` also sets `closed_at`.

### 2.4 SLA and “Delayed” tag

- **Rule:** If the ticket is still **not** `resolved` or `closed` **and** `created_at` is more than **72 hours** ago → it is **delayed** (soft SLA).
- **Delayed** is a **tag/indicator**, not a status: status stays Pending or In Progress; the ticket is just marked as delayed.
- **Housekeeping table:** Shows an **SLA** column with badge **“Delayed”** (red) or **“On time”** (green) using `Ticket::isDelayed()`.

### 2.5 Campus manager

- **Lists:** **Requests → Housekeeping** (all housekeeping tickets); **Requests → Delayed Requests** (only delayed housekeeping + R&M tickets).
- **Notification:** When a housekeeping ticket first becomes delayed (past 72h, unnotified), campus managers get an **in-app push** (no SMS). See “Delayed notification” below.

---

## 3. Repair & Maintenance Requests (Ticket)

### 3.1 Creation (raise)

- **Who:** Student (mobile app).
- **API:** `POST /api/v1/tickets`
- **Payload:** `request_type: "repair_maintenance"` (stored as `category: "maintenance"`).
- **Backend:** Same as Housekeeping; only `category` differs (`maintenance`).

### 3.2 Status flow

- Same as Housekeeping: **Pending (open)** → **In Progress (in_progress)** → **Resolved (resolved/closed)**. Optional **On Hold (on_hold)**.

### 3.3 Where status is updated

- **Campus Manager:** **Requests → Repair & Maintenance** (table) or **Tickets** resource → **View** → **Change Status**.

### 3.4 SLA and “Delayed” tag

- Same 72h rule as Housekeeping: not resolved/closed and created &gt; 72h ago → **Delayed**.
- **Repair & Maintenance table:** Same **SLA** column (Delayed / On time).

### 3.5 Campus manager

- **Lists:** **Requests → Repair & Maintenance**; **Requests → Delayed Requests** (delayed R&M + housekeeping).
- **Notification:** Same in-app push when the R&M ticket first becomes delayed.

---

## 4. Laundry Requests (LaundryRequest)

### 4.1 Creation (raise)

- **Who:** Student (mobile) or Laundry Manager / Campus Manager (on behalf of student).
- **API:** `POST /api/v1/laundry/requests/raise`
- **Backend:** `LaundryRequest::create()` with:
  - `status` = **PENDING** (e.g. `LaundryRequestStatus::PENDING`)
  - `requested_at` (or equivalent); `student_id`, `hostel_id`, etc.

### 4.2 Status flow (LaundryRequestStatus)

- **Pending** → **Scheduled** → **Collected** → **Washing** → **Drying** → **Ready** → **Delivered** / **Completed**
- Terminal: **Completed**, **Delivered**, **Cancelled**, **Lost**, **Damaged**

(Exact labels and transitions are defined in `LaundryRequestStatus` enum.)

### 4.3 Where status is updated

- **Laundry staff / Campus Manager:** Filament **Laundry Requests** resource (or equivalent); mobile **mark ready / verify pickup** APIs.
- Transitions use `LaundryRequest::transitionTo(...)` (e.g. Ready → Delivered when student picks up).

### 4.4 SLA and “Delayed” tag

- **Rule:** If the laundry request is **not** in a terminal completed state (e.g. not Delivered/Completed/Cancelled/Lost/Damaged) **and** (`requested_at` or `created_at`) is more than **72 hours** ago → **delayed**.
- **Delayed** is again a tag/indicator; status values stay as-is.
- **Delayed Requests page:** Second table lists delayed laundry requests (Request ID like LR-xxxx, Student–Room, Status, Requested).

### 4.5 Campus manager

- **Lists:** **Laundry Requests** resource; **Requests → Delayed Requests** (laundry section).
- **Notification:** In-app push when that laundry request first becomes delayed (see below).

---

## 5. Leave Requests (Leave)

- **Statuses:** **Pending** → **Approved** or **Rejected** (no 72h SLA / delayed logic).
- **Campus Manager:** **Requests → Leave** (table); approve/reject actions.
- **Notification:** Student gets SMS/push on decision (separate from request SLA flow).

---

## 6. Delayed logic (72h) – How it runs

### 6.1 Definition of “delayed”

- **Tickets (Housekeeping & R&M):**  
  `status` NOT IN (`resolved`, `closed`) **and** `created_at` &lt; now − 72 hours.
- **Laundry:**  
  `status` NOT IN (Delivered, Completed, Cancelled, Lost, Damaged) **and** (`requested_at` or `created_at`) &lt; now − 72 hours.

72h comes from `config('requests.sla_hours', 72)` (env: `REQUEST_SLA_HOURS`).

### 6.2 One-time campus manager notification

- **Command:** `php artisan requests:check-delayed` (scheduled **hourly** in `app/Console/Kernel.php`).
- **Logic:**
  1. For each tenant, find:
     - **Tickets:** `Ticket::where('tenant_id', $tenant->id)->delayedUnnotified()` (i.e. delayed and `delayed_notified_at` is null).
     - **Laundry:** `LaundryRequest::delayedUnnotified()` (scoped by tenant_id or tenant DB).
  2. For each such ticket/laundry request:
     - Send **in-app push** to all **Campus Manager** users of that tenant (no SMS).
     - Set `delayed_notified_at = now()` so we do not notify again for the same request.
- **Service:** `App\Services\Notifications\DelayedRequestNotifier::notifyCampusManagersForDelayed($request)`.

### 6.3 Delayed Requests view (Campus Manager)

- **Menu:** **Requests → Delayed Requests** (badge shows count of delayed requests).
- **Content:**
  - First table: **Delayed housekeeping & repair requests** (HK-xxxx, RM-xxxx), with Request ID, Type, Student–Room, Status, Created.
  - Second section (if any): **Delayed laundry requests** (LR-xxxx), with Request ID, Student–Room, Status, Requested.
- **Goal:** Keep delayed count at zero.

---

## 7. End-to-end flow summary

| Step | Housekeeping / R&M (Ticket) | Laundry (LaundryRequest) | Leave (Leave) |
|------|----------------------------|---------------------------|---------------|
| 1. Create | Student: POST `/tickets` with `request_type` housekeeping or repair_maintenance | Student/Staff: POST `/laundry/requests/raise` | Student submits leave |
| 2. Initial status | `open` (Pending) | Pending | Pending |
| 3. Staff updates | Campus Manager / HK/RM: Change status (In Progress, Resolved, etc.) | Laundry staff: transition (Scheduled → … → Ready → Delivered) | Rector/Campus: Approve / Reject |
| 4. SLA 72h | If not resolved/closed after 72h → “Delayed” tag | If not completed/delivered after 72h → “Delayed” tag | N/A |
| 5. Delayed notification | Campus managers get **one** in-app push when first delayed; `delayed_notified_at` set | Same | N/A |
| 6. Delayed view | **Requests → Delayed Requests** (first table) + SLA column on Housekeeping/R&M tables | **Requests → Delayed Requests** (second table) | N/A |

---

## 8. API: `is_delayed` for mobile

The following API responses include **`is_delayed`** (boolean) so the mobile app can show a “Delayed” badge or filter:

| Endpoint | Who | Response |
|----------|-----|----------|
| `GET /api/v1/tickets` | Student | Each ticket in `data` has `is_delayed`. |
| `GET /api/v1/tickets/{id}` | Student | Single ticket `data` has `is_delayed`. |
| `GET /api/v1/laundry/requests` | Student / Staff | Each laundry request in `data` has `is_delayed`. |
| `GET /api/v1/laundry/requests/{id}` | Student / Staff | Single laundry `data` has `is_delayed`. |
| `GET /api/v1/requests/housekeeping` | Campus Manager | Each item in `data` has `is_delayed`. |
| `GET /api/v1/requests/maintenance` | Campus Manager | Each item in `data` has `is_delayed`. |
| `GET /api/v1/requests/laundry` | Campus Manager | Each item in `data` has `is_delayed`. |

`TicketResource` (when used) also includes `is_delayed`.

---

## 9. Key files reference

| Concern | Files |
|--------|--------|
| Ticket model (HK & R&M) | `app/Domain/Tickets/Models/Ticket.php` (status, `isDelayed()`, `scopeDelayed()`, `delayed_notified_at`) |
| Laundry model | `app/Models/LaundryRequest.php` (status enum, `isDelayed()`, `scopeDelayed()`, `delayed_notified_at`) |
| SLA config | `config/requests.php` (`sla_hours`) |
| Delayed notification | `app/Services/Notifications/DelayedRequestNotifier.php`, `app/Console/Commands/CheckDelayedRequests.php` |
| Scheduler | `app/Console/Kernel.php` (`requests:check-delayed` hourly) |
| Campus Manager lists | `app/Filament/CampusManager/Pages/Requests/HousekeepingRequests.php`, `MaintenanceRequests.php`, `DelayedRequests.php`; LaundryRequest resource |
| Mobile create ticket | `routes/api.php` (POST `/tickets`) |
| Mobile laundry raise | `app/Http/Controllers/Api/V1/Mobile/LaundryController.php` (`raiseRequest`) |
| Ticket status change (Filament) | `app/Filament/CampusManager/Resources/TicketResource/Pages/ViewTicket.php` (Change Status action) |
| API `is_delayed` | `routes/api.php` (GET tickets), `TicketResource`, `StaffController` (housekeeping/maintenance/laundry), `LaundryController` (getRequests, show) |

This is the complete workflow for how each request type works today.
