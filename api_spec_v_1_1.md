openapi: 3.0.3
info:
  title: MAP Hostel Management API (Consolidated)
  version: "1.1"
  description: |
    Multi-tenant API for MAP-HMS v1.0. Auth via Phone+OTP (MSG91) with JWT.
    All endpoints are /v1, JSON. Idempotency supported on selected routes.
    Step-up OTP (`/stepup-otp/*`) is required for tenant activation/rollback, Rector approvals, manual payment edits, and CSV exports.
    Fee tracking is manual-only (`/students/{id}/payment-status`, `/payments/summary`); no Razorpay or online capture.
servers:
  - url: https://api.map-hostels.app/v1
x-background-jobs:
  room-changes:escalate:
    schedule: "hourly"
    description: |
      Scans `room_changes` for pending requests beyond SLA (`sla_due_at`) and dispatches SMS + push notifications (Campus Manager role).
      Command: `php artisan room-changes:escalate [--tenant=UUID …]`
    payload:
      sms_template: room_change_sla_breach
      push_type: room_change_escalation
  checklists:remind:
    schedule: "daily 09:00 / 15:00 & hourly overdue sweep"
    description: |
      Morning & afternoon reminder windows notify checklist assignees; overdue sweep escalates to assignee + manager after grace.
      Command: `php artisan checklists:remind --window={morning|afternoon|overdue}`
    payload:
      sms_template: checklist_{window}
      push_type: checklist_reminder
security:
  - BearerAuth: []
components:
  securitySchemes:
    BearerAuth:
      type: http
      scheme: bearer
      bearerFormat: JWT
  headers:
    Idempotency-Key:
      description: Unique key supplied by client for idempotent operations.
      schema: { type: string }
  parameters:
    TenantId:
      in: header
      name: X-Tenant-ID
      required: false
      schema: { type: string }
    HostelId:
      in: query
      name: hostel_id
      schema: { type: string }
    Page:
      in: query
      name: page
      schema: { type: integer, minimum: 1, default: 1 }
    PerPage:
      in: query
      name: per_page
      schema: { type: integer, minimum: 1, maximum: 100, default: 20 }
    Shift:
      in: query
      name: shift
      schema: { type: string, enum: [Morning, Evening, Night] }
    Date:
      in: query
      name: date
      schema: { type: string, format: date }
    StudentId:
      in: path
      name: student_id
      required: true
      schema: { type: string }
  schemas:
    Error:
      type: object
      required: [code, message]
      properties:
        code: { type: string }
        message: { type: string }
        details: { type: object, additionalProperties: true }
    # ——— Core Models (subset; rest align to Part-1/2 earlier) ———
    User:
      type: object
      properties:
        id: { type: string }
        phone: { type: string }
        name: { type: string }
        role: { type: string, enum: [SuperAdmin, CampusManager, Rector, Warden, HKSupervisor, RMSupervisor, Guard, LaundryManager, SportsManager, CollegeMgmt, Student] }
        tenant_id: { type: string }
        scopes:
          type: object
          properties:
            campus_ids: { type: array, items: { type: string } }
            hostel_ids: { type: array, items: { type: string } }
    Student:
      type: object
      properties:
        id: { type: string }
        map_student_id: { type: string }
        name: { type: string }
        roll_no: { type: string }
        hostel_id: { type: string, nullable: true }
        bed: { type: string, nullable: true }
    OutPass:
      type: object
      properties:
        id: { type: string }
        student_id: { type: string }
        hostel_id: { type: string }
        reason: { type: string, enum: [Normal, Leave, Sick] }
        overnight: { type: boolean }
        status: { type: string, enum: [Pending, Approved, Declined, Expired, Cancelled] }
        requested_at: { type: string, format: date-time }
        requested_for: { type: string, format: date }
        decided_at: { type: string, format: date-time, nullable: true }
        valid_until: { type: string, format: date-time }
    GateEvent:
      type: object
      properties:
        id: { type: string }
        campus_id: { type: string, nullable: true }
        student_id: { type: string }
        hostel_id: { type: string }
        direction: { type: string, enum: [IN, OUT] }
        method: { type: string, enum: [QR, LIST, OTP, MANUAL, EMERGENCY] }
        verified: { type: boolean }
        verified_at: { type: string, format: date-time, nullable: true }
        guard_user_id: { type: string, nullable: true }
        occurred_at: { type: string, format: date-time }
        late_minutes: { type: integer, nullable: true }
    AttendanceMark:
      type: object
      properties:
        student_id: { type: string }
        status: { type: string, enum: [present, absent, excused, late, leave] }
        comment: { type: string, nullable: true }
    PaymentStatus:
      type: object
      properties:
        student_id: { type: string }
        hostel_fee_paid: { type: boolean }
        payment_mode: { type: string, enum: [cash, upi, card, bank, cheque], nullable: true }
        payment_amount: { type: number, format: double, nullable: true }
        payment_date: { type: string, format: date, nullable: true }
        payment_reference: { type: string, nullable: true }
        payment_notes: { type: string, nullable: true }
    PaymentSummary:
      type: object
      properties:
        total_students: { type: integer }
        paid: { type: integer }
        unpaid: { type: integer }
        last_updated_at: { type: string, format: date-time }
    LeaveList:
      type: object
      properties:
        data:
          type: array
          items:
            type: object
            properties:
              id: { type: string }
              type: { type: string, enum: [leave, sick_leave] }
              unique_id: { type: string }
              student_name: { type: string }
              hostel_name: { type: string }
              title: { type: string }
              description: { type: string }
              reason: { type: string }
              from_date: { type: string, format: date, nullable: true }
              to_date: { type: string, format: date, nullable: true }
              submitted_at: { type: string, format: date-time }
              sla_due_at: { type: string, format: date-time, nullable: true }
              sla_breached_at: { type: string, format: date-time, nullable: true }
              sla_status:
                type: object
                properties:
                  status: { type: string, enum: [ok, warning, breached] }
                  message: { type: string }
                  color: { type: string, enum: [success, warning, danger] }
        meta:
          type: object
          properties:
            total: { type: integer }
    ApprovalHistory:
      type: object
      properties:
        data:
          type: array
          items:
            type: object
            properties:
              type: { type: string, enum: [Out-Pass, Leave, Sick Leave] }
              unique_id: { type: string }
              student_name: { type: string }
              hostel_name: { type: string, nullable: true }
              decision: { type: string, enum: [approved, rejected, declined] }
              decided_at: { type: string, format: date-time }
              decided_by: { type: string }
              note: { type: string, nullable: true }
        meta:
          type: object
          properties:
            current_page: { type: integer }
            per_page: { type: integer }
            total: { type: integer }
            last_page: { type: integer }
    ReportResponse:
      type: object
      properties:
        data:
          type: object
          properties:
            download_url: { type: string, format: uri }
            format: { type: string, enum: [pdf, csv] }
            month: { type: integer }
            year: { type: integer }
            generated_at: { type: string, format: date-time }
    StepUpRequest:
      type: object
      required: [action]
      properties:
        action: { type: string, enum: [rector_approval, sensitive_action] }
        channel: { type: string, enum: [sms], default: sms }
    StepUpVerify:
      type: object
      required: [action, code]
      properties:
        action: { type: string, enum: [rector_approval, sensitive_action] }
        code: { type: string, pattern: '^\\d{6}$' }
    StepUpState:
      type: object
      properties:
        action: { type: string, enum: [rector_approval, sensitive_action] }
        step_up_required: { type: boolean }
        recently_verified: { type: boolean }
        can_proceed: { type: boolean }
paths:
  /auth/otp/send:
    post:
      summary: Send OTP for login or step-up
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [phone, purpose]
              properties:
                phone: { type: string }
                purpose: { type: string, enum: [login, stepup] }
      responses:
        "202": { description: OTP sent }
        "429": { description: Rate limited, content: { application/json: { schema: { $ref: '#/components/schemas/Error' }}}}
  /auth/otp/verify:
    post:
      summary: Verify OTP and create session
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [phone, otp, device_id]
              properties:
                phone: { type: string }
                otp: { type: string }
                device_id: { type: string }
      responses:
        "200": { description: Logged in, content: { application/json: { schema: { $ref: '#/components/schemas/User' }}}}
        "400": { description: Invalid OTP, content: { application/json: { schema: { $ref: '#/components/schemas/Error' }}}}
  /stepup-otp/request:
    post:
      summary: Request step-up OTP for sensitive actions
      requestBody:
        required: true
        content:
          application/json:
            schema: { $ref: '#/components/schemas/StepUpRequest' }
      responses:
        "200": { description: OTP dispatched }
        "429": { description: Rate limited, content: { application/json: { schema: { $ref: '#/components/schemas/Error' }}}}
  /stepup-otp/verify:
    post:
      summary: Verify step-up OTP
      requestBody:
        required: true
        content:
          application/json:
            schema: { $ref: '#/components/schemas/StepUpVerify' }
      responses:
        "200": { description: Verified, content: { application/json: { schema: { type: object, properties: { data: { type: object, properties: { verified: { type: boolean }, action: { type: string }, expires_in_minutes: { type: integer }}}}}}}}
        "422": { description: Invalid OTP, content: { application/json: { schema: { $ref: '#/components/schemas/Error' }}}}
  /stepup-otp/status:
    get:
      summary: Check if a step-up OTP is required
      parameters:
        - in: query
          name: action
          required: true
          schema: { type: string, enum: [rector_approval, sensitive_action] }
      responses:
        "200": { description: Status, content: { application/json: { schema: { $ref: '#/components/schemas/StepUpState' }}}}
  /me:
    get:
      summary: Get my profile & scopes
      responses:
        "200": { description: OK, content: { application/json: { schema: { $ref: '#/components/schemas/User' }}}}
  /students/{student_id}/payment-status:
    get:
      summary: Get manual payment status for a student (Campus Manager only; requires recent step-up OTP)
      parameters:
        - $ref: '#/components/parameters/StudentId'
      responses:
        "200": { description: OK, content: { application/json: { schema: { $ref: '#/components/schemas/PaymentStatus' }}}}
        "403": { description: Forbidden, content: { application/json: { schema: { $ref: '#/components/schemas/Error' }}}}
  /payments/summary:
    get:
      summary: Aggregate manual payment summary (requires recent step-up OTP)
      responses:
        "200": { description: OK, content: { application/json: { schema: { $ref: '#/components/schemas/PaymentSummary' }}}}
        "403": { description: Forbidden, content: { application/json: { schema: { $ref: '#/components/schemas/Error' }}}}
  /rector/leaves:
    get:
      summary: List pending leaves for Rector approval
      responses:
        "200": { description: OK, content: { application/json: { schema: { $ref: '#/components/schemas/LeaveList' }}}}
        "403": { description: Forbidden, content: { application/json: { schema: { $ref: '#/components/schemas/Error' }}}}
  /rector/leaves/{id}/approve:
    post:
      summary: Approve a leave request (Rector only, requires step-up OTP)
      parameters:
        - name: id
          in: path
          required: true
          schema: { type: string }
      requestBody:
        content:
          application/json:
            schema:
              type: object
              properties:
                note: { type: string, maxLength: 500 }
      responses:
        "200": { description: Leave approved, notifications sent }
        "403": { description: Forbidden, content: { application/json: { schema: { $ref: '#/components/schemas/Error' }}}}
        "428": { description: Step-up OTP required, content: { application/json: { schema: { $ref: '#/components/schemas/Error' }}}}
  /rector/leaves/{id}/reject:
    post:
      summary: Reject a leave request
      parameters:
        - name: id
          in: path
          required: true
          schema: { type: string }
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [rejection_reason]
              properties:
                rejection_reason: { type: string, maxLength: 500 }
      responses:
        "200": { description: Leave rejected, notifications sent }
        "403": { description: Forbidden, content: { application/json: { schema: { $ref: '#/components/schemas/Error' }}}}
  /rector/leaves/bulk-approve:
    post:
      summary: Bulk approve multiple leave requests
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [leave_ids]
              properties:
                leave_ids: { type: array, items: { type: string } }
                note: { type: string, maxLength: 500 }
      responses:
        "200": { description: Leaves approved, notifications sent }
        "403": { description: Forbidden, content: { application/json: { schema: { $ref: '#/components/schemas/Error' }}}}
  /rector/approval-history:
    get:
      summary: Get approval history (all types)
      parameters:
        - name: type
          in: query
          schema: { type: string, enum: [outpass, leave, sick_leave] }
        - name: from_date
          in: query
          schema: { type: string, format: date }
        - name: to_date
          in: query
          schema: { type: string, format: date }
        - name: per_page
          in: query
          schema: { type: integer, minimum: 1, maximum: 100, default: 20 }
        - name: page
          in: query
          schema: { type: integer, minimum: 1, default: 1 }
      responses:
        "200": { description: OK, content: { application/json: { schema: { $ref: '#/components/schemas/ApprovalHistory' }}}}
        "403": { description: Forbidden, content: { application/json: { schema: { $ref: '#/components/schemas/Error' }}}}
  /rector/reports/monthly:
    post:
      summary: Generate monthly approval report
      security:
        - BearerAuth: []
        - StepUpOTP: [] # CSV exports require step-up per PRD § 6
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [month, year, format]
              properties:
                month: { type: integer, minimum: 1, maximum: 12 }
                year: { type: integer }
                format: { type: string, enum: [pdf, csv] }
      responses:
        "200": { description: Report generated, content: { application/json: { schema: { $ref: '#/components/schemas/ReportResponse' }}}}
        "403": { description: Forbidden, content: { application/json: { schema: { $ref: '#/components/schemas/Error' }}}}
  # …
  # NOTE: Full path set is identical to API_Spec_Part-1_v1.0 + Part-2_v1.0
  # consolidated here. For brevity in this view, only headers are shown.
  # Export endpoints (`/exports/*`) and onboarding activate/rollback routes require
  # a step-up OTP verification within the last 10 minutes.
