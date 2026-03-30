export interface User {
  id: number;
  name: string;
  phone: string;
  email?: string;
  role: UserRole;
  avatar?: string;
  tenant_id?: string;
  student_uid?: string;
  hostel_id?: number;
  hostel_name?: string;
  campus_id?: number;
  campus_name?: string;
}

export type UserRole =
  | 'student'
  | 'campus_manager'
  | 'rector'
  | 'warden'
  | 'guard'
  | 'hk_supervisor'
  | 'rm_supervisor'
  | 'laundry_manager'
  | 'sports_manager'
  | 'supervisor'
  | 'manager';

export interface AuthResponse {
  success: boolean;
  message: string;
  data?: {
    token: string;
    user: User;
  };
}

export interface OTPResponse {
  success: boolean;
  message: string;
  /** Set when server could not send SMS (e.g. dev or SMS not configured) so user can still sign in */
  otp?: string;
}

export interface GatePass {
  id: number;
  student_id: number;
  student_name: string;
  hostel_name: string;
  purpose: string;
  out_date: string;
  out_time: string;
  expected_in_date: string;
  expected_in_time: string;
  actual_in_date?: string;
  actual_in_time?: string;
  status: 'pending' | 'approved' | 'rejected' | 'active' | 'completed';
  approved_by?: string;
  created_at: string;
}

export interface Attendance {
  id: number;
  student_id: number;
  date: string;
  status: 'present' | 'absent' | 'on_leave';
  marked_by?: string;
  marked_at?: string;
}

export interface Complaint {
  id: number;
  student_id: number;
  student_name: string;
  hostel_name: string;
  category: string;
  description: string;
  status: 'pending' | 'in_progress' | 'resolved' | 'closed';
  priority: 'low' | 'medium' | 'high';
  created_at: string;
  resolved_at?: string;
}

export interface Notice {
  id: number;
  title: string;
  description: string;
  type: 'general' | 'urgent' | 'event';
  target_audience: 'all' | 'students' | 'staff';
  created_by: string;
  created_at: string;
  expires_at?: string;
  images?: string[];
}

export interface Ticket {
  id: number;
  student_id: number;
  student_name: string;
  student_room?: string;
  title: string;
  description: string;
  request_type?: 'repair_maintenance' | 'housekeeping';
  category: string;
  status: 'pending' | 'done' | 'in_progress' | 'resolved' | 'closed';
  submitted_date_time?: string;
  time_elapsed?: string;
  department?: string;
  photos?: string[];
  created_at: string;
  updated_at: string;
}

export interface DashboardStats {
  // General metrics
  total_students?: number;
  present_today?: number;
  absent_today?: number;
  active_gate_passes?: number;
  pending_complaints?: number;
  pending_approvals?: number;

  // Sports Manager specific metrics
  total_facilities?: number;
  active_bookings?: number;
  upcoming_bookings?: number;
  today_bookings?: number;
  available_facilities?: number;
  pending_checklists?: number;

  // Student specific metrics
  active_bookings_student?: number;
  upcoming_bookings_student?: number;
  pending_requests?: number;

  // Laundry Manager specific metrics
  pending_laundry_requests?: number;
  in_progress_laundry_requests?: number;
  completed_today_laundry?: number;
  ready_for_pickup_laundry?: number;
  total_laundry_requests?: number;
  completion_rate_laundry?: number;
}

export interface HostelRoom {
  id: number;
  room_number: string;
  hostel_id: number;
  hostel_name: string;
  capacity: number;
  allocated_students: number;
  tenant_id: string;
  created_at: string;
  updated_at: string;
}

export interface Student {
  id: number;
  name: string;
  email?: string;
  phone: string;
  room_id?: number;
  room_number?: string;
  hostel_id: number;
  hostel_name: string;
  tenant_id: string;
  created_at: string;
  updated_at: string;
}

export interface Request {
  id: number;
  type: 'housekeeping' | 'repair_maintenance' | 'outpass' | 'guest_entry';
  title: string;
  description: string;
  status: 'pending' | 'in_progress' | 'completed' | 'cancelled';
  priority: 'low' | 'medium' | 'high';
  student_id: number;
  student_name: string;
  hostel_name: string;
  created_by: string;
  assigned_to?: string;
  tenant_id: string;
  created_at: string;
  updated_at: string;
  resolved_at?: string;
}

export interface Leave {
  id: number;
  unique_id: string;
  title: string;
  description: string;
  reason_for_leave: string;
  from_date: string;
  to_date: string;
  emergency_contact?: string;
  status: 'pending' | 'approved' | 'rejected';
  rejection_reason?: string;
  submitted_date: string;
  created_at: string;
}

export interface SickLeave {
  id: number;
  unique_id: string;
  title: string;
  description: string;
  illness: string;
  illness_details: string;
  need_medical_attention: boolean;
  contact_parents: boolean;
  status: 'pending' | 'approved' | 'rejected';
  rejection_reason?: string;
  submitted_date: string;
  created_at: string;
}

export interface GuestEntryGuest {
  name: string;
  phone?: string;
  relationship: string;
  id_type: 'aadhar_card' | 'driving_license' | 'passport' | 'voter_id';
  id_number: string;
}

export interface GuestEntry {
  id: number;
  unique_id: string;
  title: string;
  description: string;
  guests: GuestEntryGuest[];
  primary_contact_mobile: string;
  visit_date: string;
  check_in_time: string;
  check_out_time: string;
  purpose_to_visit: string;
  status: 'pending' | 'approved' | 'rejected';
  rejection_reason?: string;
  submitted_date: string;
  created_at: string;
}

export interface RoomChange {
  id: number;
  unique_id: string;
  title: string;
  description: string;
  preferred_room_number?: string;
  preferred_floor?: string;
  sharing_preference?: 'single' | 'double' | 'triple' | 'quad';
  date_required?: string;
  status: 'pending' | 'approved' | 'rejected';
  rejection_reason?: string;
  submitted_date: string;
  created_at: string;
}

// ============ Staff App Types ============

export interface StaffMember {
  id: number;
  name: string;
  email?: string;
  phone?: string;
  employee_id?: string;
  role: string;
  assigned_hostels: { id: number; name: string }[];
  is_active: boolean;
  last_active_at?: string;
}

export interface Incident {
  id: number;
  type: 'LateReturn' | 'MissedAttendance' | 'EmergencyExit' | 'Security' | 'Medical';
  status: 'Open' | 'Closed';
  hostel: { id: number; name: string };
  student?: { id: number; name: string; map_student_id?: string };
  note: string;
  opened_by: { id: number; name: string };
  opened_at: string;
  acknowledged: boolean;
  acknowledged_at?: string;
  acknowledged_by?: { id: number; name: string };
  closed_at?: string;
  closure_note?: string;
}

export interface MedicalEmergency {
  id: number;
  student_name: string;
  student_phone?: string;
  hostel: string;
  room?: string;
  symptoms: string;
  status: string;
  acknowledged: boolean;
  acknowledged_at?: string;
  acknowledged_by?: string;
  created_at: string;
}

export type ChecklistItemState = 'Pending' | 'Done' | 'NA';

export interface ChecklistTask {
  index: number;
  code?: string;
  title: string;
  description?: string;
  requires_photo: boolean;
  requires_comment?: boolean;
  completed: boolean;
  completed_at?: string;
  photo_url?: string;
  photo_urls?: string[];
  comment?: string;
}

export interface ChecklistInstance {
  id: number;
  template_name: string;
  due_at: string;
  status: 'Pending' | 'Submitted' | 'Approved' | 'SentBack' | 'pending' | 'in_progress' | 'completed';
  tasks: ChecklistTask[];
  completed_count: number;
  total_count: number;
  manager_note?: string;
  submitted_at?: string;
  reviewed_at?: string;
}

export interface SportsCourt {
  id: number;
  name: string;
  category: string;
  location?: string;
  capacity?: number;
  description?: string;
  is_active: boolean;
  created_at: string;
}

export interface SportsBooking {
  id: number;
  user_name: string;
  facility: {
    id: number;
    name: string;
    type: string;
  };
  booking_date: string;
  start_time: string;
  end_time: string;
  status: string;
  purpose?: string;
  created_at: string;
}

export interface LaundryRequest {
  id: number;
  student_name: string;
  hostel?: string;
  room?: string;
  item_count?: number;
  weight_kg?: number;
  status: string;
  created_at: string;
  sla_deadline?: string;
}

export interface OutPass {
  id: string;
  reason: 'normal' | 'leave' | 'sick';
  reason_label: string;
  overnight: boolean;
  status: 'pending' | 'approved' | 'declined' | 'cancelled' | 'expired';
  status_label: string;
  status_color?: string;
  hostel?: string;
  requested_at: string;
  valid_until: string;
  decided_at?: string;
  note?: string;
  created_at: string;
  updated_at: string;
  history?: OutPassHistory[];
}

export interface OutPassHistory {
  from: string | null;
  to: string;
  label: string;
  description: string;
  actor?: string;
  changed_at: string;
}

export interface Notification {
  id: number;
  type: string;
  title: string;
  body: string;
  data?: Record<string, unknown>;
  read_at?: string;
  created_at: string;
  is_urgent?: boolean;
}

export interface ActionTile {
  id: string;
  title: string;
  icon?: string;
  iconSvgXml?: string;
  color: string;
  badge?: number;
  onPress: () => void;
}

export interface CampusManagerStats {
  active_hostels: number;
  resident_students: number;
  open_requests: number;
  completed_requests_today: number;
}

export interface RectorStats {
  tickets_raised: number;
  tickets_pending: number;
  tickets_completed: number;
  total_tickets: number;
}

export interface GuardStats {
  total_verifications_today: number;
  check_outs_today: number;
  check_ins_today: number;
  active_outpasses: number;
  pending_guest_entries: number;
  emergency_exits_today: number;
}

export interface SupervisorStats {
  pending_requests: number;
  in_progress_requests: number;
  completed_today: number;
  total_assigned: number;
}

export interface TimeVerification {
  type: 'outpass' | 'leave';
  id: number;
  direction: 'out' | 'in';
  timestamp?: string;
}

