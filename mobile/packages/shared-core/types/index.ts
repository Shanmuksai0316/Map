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
}

export interface GatePass {
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
  history?: GatePassHistory[];
}

export interface GatePassHistory {
  from: string | null;
  to: string;
  label: string;
  description: string;
  actor?: string;
  changed_at: string;
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

