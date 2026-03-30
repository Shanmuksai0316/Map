/**
 * MAP-HMS Icon Configuration
 * Curated icon selections for specific app contexts
 * 
 * This file provides ready-to-use icon configurations for
 * common UI patterns in the hostel management system.
 */

import { IconCatalog, IconName } from '../components/Icon';
import { theme } from '../theme/theme';

// ═══════════════════════════════════════════════════════════════
// NAVIGATION TAB ICONS
// ═══════════════════════════════════════════════════════════════

export type TabIconConfig = {
  active: string;
  inactive: string;
  label: string;
};

/** Student App Navigation Tabs */
export const StudentTabIcons: Record<string, TabIconConfig> = {
  home: {
    active: IconCatalog.home,
    inactive: IconCatalog.homeOutline,
    label: 'Home',
  },
  requests: {
    active: IconCatalog.request,
    inactive: IconCatalog.requestOutline,
    label: 'Requests',
  },
  attendance: {
    active: IconCatalog.attendance,
    inactive: IconCatalog.attendanceOutline,
    label: 'Attendance',
  },
  profile: {
    active: IconCatalog.userCircle,
    inactive: IconCatalog.userCircleOutline,
    label: 'Profile',
  },
};

/** Staff App Navigation Tabs - Guard */
export const GuardTabIcons: Record<string, TabIconConfig> = {
  dashboard: {
    active: IconCatalog.dashboard,
    inactive: IconCatalog.dashboardOutline,
    label: 'Dashboard',
  },
  gateEntry: {
    active: IconCatalog.gate,
    inactive: IconCatalog.gateOutline,
    label: 'Entry',
  },
  gateExit: {
    active: IconCatalog.gateExit,
    inactive: IconCatalog.gateExitOutline,
    label: 'Exit',
  },
  scan: {
    active: IconCatalog.qrCode,
    inactive: IconCatalog.qrCodeOutline,
    label: 'Scan',
  },
  checklist: {
    active: IconCatalog.checklist,
    inactive: IconCatalog.checklistOutline,
    label: 'Checklist',
  },
};

/** Staff App Navigation Tabs - Warden */
export const WardenTabIcons: Record<string, TabIconConfig> = {
  dashboard: {
    active: IconCatalog.dashboard,
    inactive: IconCatalog.dashboardOutline,
    label: 'Dashboard',
  },
  students: {
    active: IconCatalog.users,
    inactive: IconCatalog.usersOutline,
    label: 'Students',
  },
  requests: {
    active: IconCatalog.request,
    inactive: IconCatalog.requestOutline,
    label: 'Requests',
  },
  checklist: {
    active: IconCatalog.checklist,
    inactive: IconCatalog.checklistOutline,
    label: 'Checklist',
  },
  history: {
    active: IconCatalog.history,
    inactive: IconCatalog.historyOutline,
    label: 'History',
  },
};

/** Staff App Navigation Tabs - Rector */
export const RectorTabIcons: Record<string, TabIconConfig> = {
  dashboard: {
    active: IconCatalog.dashboard,
    inactive: IconCatalog.dashboardOutline,
    label: 'Dashboard',
  },
  outpass: {
    active: IconCatalog.outpass,
    inactive: IconCatalog.outpassOutline,
    label: 'Outpass',
  },
  leave: {
    active: IconCatalog.leave,
    inactive: IconCatalog.leaveOutline,
    label: 'Leave',
  },
  insights: {
    active: IconCatalog.analytics,
    inactive: IconCatalog.analyticsOutline,
    label: 'Insights',
  },
  commBox: {
    active: IconCatalog.messages,
    inactive: IconCatalog.messagesOutline,
    label: 'Notice Board',
  },
};

/** Staff App Navigation Tabs - Campus Manager */
export const CampusManagerTabIcons: Record<string, TabIconConfig> = {
  dashboard: {
    active: IconCatalog.dashboard,
    inactive: IconCatalog.dashboardOutline,
    label: 'Dashboard',
  },
  students: {
    active: IconCatalog.student,
    inactive: IconCatalog.studentOutline,
    label: 'Students',
  },
  requests: {
    active: IconCatalog.request,
    inactive: IconCatalog.requestOutline,
    label: 'Requests',
  },
  staff: {
    active: IconCatalog.staff,
    inactive: IconCatalog.staffOutline,
    label: 'Staff',
  },
  reports: {
    active: IconCatalog.report,
    inactive: IconCatalog.reportOutline,
    label: 'Reports',
  },
};

// ═══════════════════════════════════════════════════════════════
// ACTION TILE ICONS (Dashboard Quick Actions)
// ═══════════════════════════════════════════════════════════════

export type ActionTileConfig = {
  icon: string;
  label: string;
  color: string;
  backgroundColor: string;
};

/** Student Dashboard Quick Actions */
export const StudentActionTiles: Record<string, ActionTileConfig> = {
  outpass: {
    icon: IconCatalog.outpass,
    label: 'Outpass',
    color: theme.colors.primary,
    backgroundColor: theme.colors.surfaceMuted,
  },
  leave: {
    icon: IconCatalog.leave,
    label: 'Leave',
    color: theme.colors.info,
    backgroundColor: theme.colors.infoLight,
  },
  sickLeave: {
    icon: IconCatalog.sickLeave,
    label: 'Sick Leave',
    color: theme.colors.error,
    backgroundColor: theme.colors.errorLight,
  },
  guestEntry: {
    icon: IconCatalog.guestEntry,
    label: 'Guest Entry',
    color: theme.colors.success,
    backgroundColor: theme.colors.successLight,
  },
  roomChange: {
    icon: IconCatalog.roomChange,
    label: 'Room Change',
    color: theme.colors.warning,
    backgroundColor: theme.colors.warningLight,
  },
  complaint: {
    icon: IconCatalog.complaint,
    label: 'Complaint',
    color: theme.colors.textSecondary,
    backgroundColor: theme.colors.surfaceMuted,
  },
  laundry: {
    icon: IconCatalog.laundry,
    label: 'Laundry',
    color: theme.colors.info,
    backgroundColor: theme.colors.infoLight,
  },
  sports: {
    icon: IconCatalog.sports,
    label: 'Sports',
    color: theme.colors.success,
    backgroundColor: theme.colors.successLight,
  },
  attendance: {
    icon: IconCatalog.attendance,
    label: 'Attendance',
    color: theme.colors.primary,
    backgroundColor: theme.colors.surfaceMuted,
  },
  notices: {
    icon: IconCatalog.announcement,
    label: 'Notices',
    color: theme.colors.accent,
    backgroundColor: theme.colors.accentMuted,
  },
  emergency: {
    icon: IconCatalog.emergency,
    label: 'Emergency',
    color: theme.colors.error,
    backgroundColor: theme.colors.errorLight,
  },
  profile: {
    icon: IconCatalog.userCircle,
    label: 'Profile',
    color: theme.colors.textSecondary,
    backgroundColor: theme.colors.surfaceMuted,
  },
};

/** Staff Dashboard Quick Actions */
export const StaffActionTiles: Record<string, ActionTileConfig> = {
  // Guard Actions
  scanQR: {
    icon: IconCatalog.qrCode,
    label: 'Scan QR',
    color: theme.colors.primary,
    backgroundColor: theme.colors.surfaceMuted,
  },
  gateEntry: {
    icon: IconCatalog.gate,
    label: 'Gate Entry',
    color: theme.colors.success,
    backgroundColor: theme.colors.successLight,
  },
  gateExit: {
    icon: IconCatalog.gateExit,
    label: 'Gate Exit',
    color: theme.colors.warning,
    backgroundColor: theme.colors.warningLight,
  },
  visitorLog: {
    icon: IconCatalog.users,
    label: 'Visitor Log',
    color: theme.colors.info,
    backgroundColor: theme.colors.infoLight,
  },
  
  // Warden Actions
  approvals: {
    icon: IconCatalog.approved,
    label: 'Approvals',
    color: theme.colors.success,
    backgroundColor: theme.colors.successLight,
  },
  myStudents: {
    icon: IconCatalog.student,
    label: 'My Students',
    color: theme.colors.primary,
    backgroundColor: theme.colors.surfaceMuted,
  },
  checklist: {
    icon: IconCatalog.checklist,
    label: 'Checklist',
    color: theme.colors.info,
    backgroundColor: theme.colors.infoLight,
  },
  
  // Rector Actions
  outpassReview: {
    icon: IconCatalog.outpass,
    label: 'Outpass Review',
    color: theme.colors.primary,
    backgroundColor: theme.colors.surfaceMuted,
  },
  leaveReview: {
    icon: IconCatalog.leave,
    label: 'Leave Review',
    color: theme.colors.info,
    backgroundColor: theme.colors.infoLight,
  },
  
  // Campus Manager Actions
  studentMgmt: {
    icon: IconCatalog.student,
    label: 'Students',
    color: theme.colors.primary,
    backgroundColor: theme.colors.surfaceMuted,
  },
  roomAllocation: {
    icon: IconCatalog.room,
    label: 'Rooms',
    color: theme.colors.info,
    backgroundColor: theme.colors.infoLight,
  },
  postNotice: {
    icon: IconCatalog.announcement,
    label: 'Post Notice',
    color: theme.colors.accent,
    backgroundColor: theme.colors.accentMuted,
  },
  
  // Service Manager Actions
  laundryRequests: {
    icon: IconCatalog.laundry,
    label: 'Laundry',
    color: theme.colors.info,
    backgroundColor: theme.colors.infoLight,
  },
  sportsBookings: {
    icon: IconCatalog.sports,
    label: 'Sports',
    color: theme.colors.success,
    backgroundColor: theme.colors.successLight,
  },
  maintenance: {
    icon: IconCatalog.maintenance,
    label: 'Maintenance',
    color: theme.colors.warning,
    backgroundColor: theme.colors.warningLight,
  },
  housekeeping: {
    icon: IconCatalog.cleaning,
    label: 'Housekeeping',
    color: theme.colors.primary,
    backgroundColor: theme.colors.surfaceMuted,
  },
  
  // Common Actions
  tickets: {
    icon: IconCatalog.ticket,
    label: 'Tickets',
    color: theme.colors.textSecondary,
    backgroundColor: theme.colors.surfaceMuted,
  },
  emergency: {
    icon: IconCatalog.emergency,
    label: 'Emergency',
    color: theme.colors.error,
    backgroundColor: theme.colors.errorLight,
  },
  notifications: {
    icon: IconCatalog.notification,
    label: 'Notifications',
    color: theme.colors.info,
    backgroundColor: theme.colors.infoLight,
  },
  history: {
    icon: IconCatalog.history,
    label: 'History',
    color: theme.colors.textSecondary,
    backgroundColor: theme.colors.surfaceMuted,
  },
};

// ═══════════════════════════════════════════════════════════════
// REQUEST TYPE ICONS
// ═══════════════════════════════════════════════════════════════

export type RequestIconConfig = {
  icon: string;
  color: string;
  label: string;
};

export const RequestTypeIcons: Record<string, RequestIconConfig> = {
  outpass: {
    icon: IconCatalog.outpass,
    color: theme.colors.primary,
    label: 'Outpass',
  },
  leave: {
    icon: IconCatalog.leave,
    color: theme.colors.info,
    label: 'Leave',
  },
  sick_leave: {
    icon: IconCatalog.sickLeave,
    color: theme.colors.error,
    label: 'Sick Leave',
  },
  guest_entry: {
    icon: IconCatalog.guestEntry,
    color: theme.colors.success,
    label: 'Guest Entry',
  },
  room_change: {
    icon: IconCatalog.roomChange,
    color: theme.colors.warning,
    label: 'Room Change',
  },
  complaint: {
    icon: IconCatalog.complaint,
    color: theme.colors.textSecondary,
    label: 'Complaint',
  },
  ticket: {
    icon: IconCatalog.ticket,
    color: theme.colors.textSecondary,
    label: 'Ticket',
  },
  laundry: {
    icon: IconCatalog.laundry,
    color: theme.colors.info,
    label: 'Laundry',
  },
  sports: {
    icon: IconCatalog.sports,
    color: theme.colors.success,
    label: 'Sports Booking',
  },
};

// ═══════════════════════════════════════════════════════════════
// STATUS ICON MAPPING
// ═══════════════════════════════════════════════════════════════

export const StatusIcons: Record<string, { icon: string; color: string }> = {
  approved: {
    icon: IconCatalog.approved,
    color: theme.colors.success,
  },
  pending: {
    icon: IconCatalog.pending,
    color: theme.colors.warning,
  },
  pending_warden: {
    icon: IconCatalog.pending,
    color: theme.colors.warning,
  },
  pending_rector: {
    icon: IconCatalog.pending,
    color: theme.colors.info,
  },
  rejected: {
    icon: IconCatalog.rejected,
    color: theme.colors.error,
  },
  active: {
    icon: IconCatalog.active,
    color: theme.colors.info,
  },
  completed: {
    icon: IconCatalog.completed,
    color: theme.colors.textSecondary,
  },
  cancelled: {
    icon: IconCatalog.cancelled,
    color: theme.colors.textMuted,
  },
  expired: {
    icon: IconCatalog.expired,
    color: theme.colors.textMuted,
  },
  checked_in: {
    icon: IconCatalog.checkin,
    color: theme.colors.success,
  },
  checked_out: {
    icon: IconCatalog.checkout,
    color: theme.colors.info,
  },
};

// ═══════════════════════════════════════════════════════════════
// PRIORITY ICON MAPPING
// ═══════════════════════════════════════════════════════════════

export const PriorityIcons: Record<string, { icon: string; color: string }> = {
  high: {
    icon: IconCatalog.priorityHigh,
    color: theme.colors.error,
  },
  medium: {
    icon: IconCatalog.priorityMedium,
    color: theme.colors.warning,
  },
  low: {
    icon: IconCatalog.priorityLow,
    color: theme.colors.success,
  },
};

