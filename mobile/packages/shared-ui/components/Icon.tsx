/**
 * MAP-HMS Icon Component
 * Centralized icon system with semantic naming and theme integration
 * 
 * Uses Ionicons with curated selections for hostel management context
 * Icons are organized by category for easy discovery
 */

import React from 'react';
import { StyleProp, ViewStyle } from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { theme } from '../theme/theme';

// ═══════════════════════════════════════════════════════════════
// ICON SIZE PRESETS
// ═══════════════════════════════════════════════════════════════
export const IconSizes = {
  xs: 14,      // Inline text icons
  sm: 16,      // Small badges, chips
  md: 20,      // Default, buttons
  lg: 24,      // Navigation, list items
  xl: 32,      // Section headers
  xxl: 48,     // Empty states, features
  hero: 64,    // Hero sections
} as const;

export type IconSize = keyof typeof IconSizes;

// ═══════════════════════════════════════════════════════════════
// ICON CATALOG - Semantic names mapped to best Ionicons
// ═══════════════════════════════════════════════════════════════
export const IconCatalog = {
  // ─────────────────────────────────────────────────────────────
  // NAVIGATION & UI
  // ─────────────────────────────────────────────────────────────
  home: 'home',
  homeOutline: 'home-outline',
  dashboard: 'grid',
  dashboardOutline: 'grid-outline',
  menu: 'menu',
  menuOutline: 'menu-outline',
  back: 'chevron-back',
  forward: 'chevron-forward',
  up: 'chevron-up',
  down: 'chevron-down',
  close: 'close',
  closeCircle: 'close-circle',
  closeCircleOutline: 'close-circle-outline',
  search: 'search',
  searchOutline: 'search-outline',
  filter: 'filter',
  filterOutline: 'filter-outline',
  sort: 'swap-vertical',
  settings: 'settings',
  settingsOutline: 'settings-outline',
  more: 'ellipsis-horizontal',
  moreVertical: 'ellipsis-vertical',
  
  // ─────────────────────────────────────────────────────────────
  // USER & PROFILE
  // ─────────────────────────────────────────────────────────────
  user: 'person',
  userOutline: 'person-outline',
  userCircle: 'person-circle',
  userCircleOutline: 'person-circle-outline',
  users: 'people',
  usersOutline: 'people-outline',
  student: 'school',
  studentOutline: 'school-outline',
  staff: 'briefcase',
  staffOutline: 'briefcase-outline',
  guard: 'shield',
  guardOutline: 'shield-outline',
  warden: 'key',
  wardenOutline: 'key-outline',
  rector: 'ribbon',
  rectorOutline: 'ribbon-outline',
  manager: 'business',
  managerOutline: 'business-outline',
  
  // ─────────────────────────────────────────────────────────────
  // HOSTEL & FACILITIES
  // ─────────────────────────────────────────────────────────────
  building: 'business',
  buildingOutline: 'business-outline',
  hostel: 'bed',
  hostelOutline: 'bed-outline',
  room: 'cube',
  roomOutline: 'cube-outline',
  bed: 'bed',
  bedOutline: 'bed-outline',
  door: 'enter',
  doorOutline: 'enter-outline',
  gate: 'log-in',
  gateOutline: 'log-in-outline',
  gateExit: 'log-out',
  gateExitOutline: 'log-out-outline',
  campus: 'map',
  campusOutline: 'map-outline',
  floor: 'layers',
  floorOutline: 'layers-outline',
  
  // ─────────────────────────────────────────────────────────────
  // REQUESTS & APPROVALS
  // ─────────────────────────────────────────────────────────────
  request: 'document-text',
  requestOutline: 'document-text-outline',
  outpass: 'exit',
  outpassOutline: 'exit-outline',
  leave: 'airplane',
  leaveOutline: 'airplane-outline',
  sickLeave: 'medkit',
  sickLeaveOutline: 'medkit-outline',
  guestEntry: 'people',
  guestEntryOutline: 'people-outline',
  roomChange: 'swap-horizontal',
  roomChangeOutline: 'swap-horizontal-outline',
  complaint: 'chatbox-ellipses',
  complaintOutline: 'chatbox-ellipses-outline',
  ticket: 'ticket',
  ticketOutline: 'ticket-outline',
  
  // ─────────────────────────────────────────────────────────────
  // STATUS INDICATORS
  // ─────────────────────────────────────────────────────────────
  approved: 'checkmark-circle',
  approvedOutline: 'checkmark-circle-outline',
  pending: 'time',
  pendingOutline: 'time-outline',
  rejected: 'close-circle',
  rejectedOutline: 'close-circle-outline',
  active: 'radio-button-on',
  activeOutline: 'radio-button-off',
  completed: 'checkmark-done',
  completedOutline: 'checkmark-done-outline',
  cancelled: 'ban',
  cancelledOutline: 'ban-outline',
  expired: 'hourglass',
  expiredOutline: 'hourglass-outline',
  
  // ─────────────────────────────────────────────────────────────
  // ACTIONS
  // ─────────────────────────────────────────────────────────────
  add: 'add',
  addCircle: 'add-circle',
  addCircleOutline: 'add-circle-outline',
  edit: 'create',
  editOutline: 'create-outline',
  delete: 'trash',
  deleteOutline: 'trash-outline',
  save: 'save',
  saveOutline: 'save-outline',
  send: 'send',
  sendOutline: 'send-outline',
  refresh: 'refresh',
  refreshOutline: 'refresh-outline',
  sync: 'sync',
  syncOutline: 'sync-outline',
  download: 'download',
  downloadOutline: 'download-outline',
  upload: 'cloud-upload',
  uploadOutline: 'cloud-upload-outline',
  share: 'share',
  shareOutline: 'share-outline',
  copy: 'copy',
  copyOutline: 'copy-outline',
  print: 'print',
  printOutline: 'print-outline',
  scan: 'scan',
  scanOutline: 'scan-outline',
  qrCode: 'qr-code',
  qrCodeOutline: 'qr-code-outline',
  
  // ─────────────────────────────────────────────────────────────
  // COMMUNICATION
  // ─────────────────────────────────────────────────────────────
  notification: 'notifications',
  notificationOutline: 'notifications-outline',
  notificationOff: 'notifications-off',
  notificationOffOutline: 'notifications-off-outline',
  message: 'chatbubble',
  messageOutline: 'chatbubble-outline',
  messages: 'chatbubbles',
  messagesOutline: 'chatbubbles-outline',
  announcement: 'megaphone',
  announcementOutline: 'megaphone-outline',
  mail: 'mail',
  mailOutline: 'mail-outline',
  call: 'call',
  callOutline: 'call-outline',
  
  // ─────────────────────────────────────────────────────────────
  // EMERGENCY & SAFETY
  // ─────────────────────────────────────────────────────────────
  emergency: 'warning',
  emergencyOutline: 'warning-outline',
  alert: 'alert-circle',
  alertOutline: 'alert-circle-outline',
  medical: 'medical',
  medicalOutline: 'medical-outline',
  sos: 'radio',
  sosOutline: 'radio-outline',
  fire: 'flame',
  fireOutline: 'flame-outline',
  security: 'shield-checkmark',
  securityOutline: 'shield-checkmark-outline',
  incident: 'flash',
  incidentOutline: 'flash-outline',
  
  // ─────────────────────────────────────────────────────────────
  // SERVICES
  // ─────────────────────────────────────────────────────────────
  laundry: 'shirt',
  laundryOutline: 'shirt-outline',
  sports: 'football',
  sportsOutline: 'football-outline',
  gym: 'barbell',
  gymOutline: 'barbell-outline',
  food: 'restaurant',
  foodOutline: 'restaurant-outline',
  maintenance: 'construct',
  maintenanceOutline: 'construct-outline',
  cleaning: 'sparkles',
  cleaningOutline: 'sparkles-outline',
  housekeeping: 'home',
  housekeepingOutline: 'home-outline',
  
  // ─────────────────────────────────────────────────────────────
  // TIME & CALENDAR
  // ─────────────────────────────────────────────────────────────
  calendar: 'calendar',
  calendarOutline: 'calendar-outline',
  time: 'time',
  timeOutline: 'time-outline',
  clock: 'timer',
  clockOutline: 'timer-outline',
  schedule: 'calendar-number',
  scheduleOutline: 'calendar-number-outline',
  history: 'time',
  historyOutline: 'time-outline',
  
  // ─────────────────────────────────────────────────────────────
  // ATTENDANCE & CHECK-IN
  // ─────────────────────────────────────────────────────────────
  checkin: 'enter',
  checkinOutline: 'enter-outline',
  checkout: 'exit',
  checkoutOutline: 'exit-outline',
  attendance: 'calendar-clear',
  attendanceOutline: 'calendar-clear-outline',
  present: 'checkmark',
  presentOutline: 'checkmark-outline',
  absent: 'close',
  absentOutline: 'close-outline',
  late: 'timer',
  lateOutline: 'timer-outline',
  
  // ─────────────────────────────────────────────────────────────
  // DOCUMENTS & REPORTS
  // ─────────────────────────────────────────────────────────────
  document: 'document',
  documentOutline: 'document-outline',
  documents: 'documents',
  documentsOutline: 'documents-outline',
  report: 'bar-chart',
  reportOutline: 'bar-chart-outline',
  analytics: 'analytics',
  analyticsOutline: 'analytics-outline',
  stats: 'stats-chart',
  statsOutline: 'stats-chart-outline',
  list: 'list',
  listOutline: 'list-outline',
  checklist: 'checkbox',
  checklistOutline: 'checkbox-outline',
  
  // ─────────────────────────────────────────────────────────────
  // MEDIA
  // ─────────────────────────────────────────────────────────────
  camera: 'camera',
  cameraOutline: 'camera-outline',
  image: 'image',
  imageOutline: 'image-outline',
  images: 'images',
  imagesOutline: 'images-outline',
  attachment: 'attach',
  attachmentOutline: 'attach-outline',
  
  // ─────────────────────────────────────────────────────────────
  // GENERAL INFO
  // ─────────────────────────────────────────────────────────────
  info: 'information-circle',
  infoOutline: 'information-circle-outline',
  help: 'help-circle',
  helpOutline: 'help-circle-outline',
  question: 'help',
  questionOutline: 'help-outline',
  success: 'checkmark-circle',
  successOutline: 'checkmark-circle-outline',
  error: 'close-circle',
  errorOutline: 'close-circle-outline',
  warning: 'warning',
  warningOutline: 'warning-outline',
  
  // ─────────────────────────────────────────────────────────────
  // MISC
  // ─────────────────────────────────────────────────────────────
  location: 'location',
  locationOutline: 'location-outline',
  phone: 'call',
  phoneOutline: 'call-outline',
  email: 'mail',
  emailOutline: 'mail-outline',
  link: 'link',
  linkOutline: 'link-outline',
  eye: 'eye',
  eyeOutline: 'eye-outline',
  eyeOff: 'eye-off',
  eyeOffOutline: 'eye-off-outline',
  lock: 'lock-closed',
  lockOutline: 'lock-closed-outline',
  unlock: 'lock-open',
  unlockOutline: 'lock-open-outline',
  star: 'star',
  starOutline: 'star-outline',
  heart: 'heart',
  heartOutline: 'heart-outline',
  flag: 'flag',
  flagOutline: 'flag-outline',
  bookmark: 'bookmark',
  bookmarkOutline: 'bookmark-outline',
  pin: 'pin',
  pinOutline: 'pin-outline',
  
  // ─────────────────────────────────────────────────────────────
  // PRIORITY LEVELS
  // ─────────────────────────────────────────────────────────────
  priorityHigh: 'alert-circle',
  priorityMedium: 'alert',
  priorityLow: 'remove-circle',
  
  // ─────────────────────────────────────────────────────────────
  // ARROWS & INDICATORS
  // ─────────────────────────────────────────────────────────────
  arrowUp: 'arrow-up',
  arrowDown: 'arrow-down',
  arrowLeft: 'arrow-back',
  arrowRight: 'arrow-forward',
  caretUp: 'caret-up',
  caretDown: 'caret-down',
  caretLeft: 'caret-back',
  caretRight: 'caret-forward',
  trendUp: 'trending-up',
  trendDown: 'trending-down',
  
} as const;

export type IconName = keyof typeof IconCatalog;

// ═══════════════════════════════════════════════════════════════
// ICON COMPONENT PROPS
// ═══════════════════════════════════════════════════════════════
export interface IconProps {
  /** Semantic icon name from IconCatalog */
  name: IconName;
  /** Icon size - use preset or custom number */
  size?: IconSize | number;
  /** Icon color - use theme token or custom color */
  color?: 'primary' | 'accent' | 'success' | 'warning' | 'error' | 'info' | 'muted' | 'white' | string;
  /** Custom style */
  style?: StyleProp<ViewStyle>;
  /** Test ID for testing */
  testID?: string;
}

// ═══════════════════════════════════════════════════════════════
// COLOR RESOLVER
// ═══════════════════════════════════════════════════════════════
const resolveColor = (color: IconProps['color']): string => {
  switch (color) {
    case 'primary':
      return theme.colors.primary;
    case 'accent':
      return theme.colors.accent;
    case 'success':
      return theme.colors.success;
    case 'warning':
      return theme.colors.warning;
    case 'error':
      return theme.colors.error;
    case 'info':
      return theme.colors.info;
    case 'muted':
      return theme.colors.textMuted;
    case 'white':
      return theme.colors.white;
    default:
      return color || theme.colors.primary;
  }
};

// ═══════════════════════════════════════════════════════════════
// ICON COMPONENT
// ═══════════════════════════════════════════════════════════════
export const Icon: React.FC<IconProps> = ({
  name,
  size = 'md',
  color = 'primary',
  style,
  testID,
}) => {
  const iconName = IconCatalog[name];
  const iconSize = typeof size === 'number' ? size : IconSizes[size];
  const iconColor = resolveColor(color);

  return (
    <Ionicons
      name={iconName}
      size={iconSize}
      color={iconColor}
      style={style}
      testID={testID}
    />
  );
};

// ═══════════════════════════════════════════════════════════════
// CONVENIENCE EXPORTS
// ═══════════════════════════════════════════════════════════════

/** Primary colored icon (Military Green) */
export const PrimaryIcon: React.FC<Omit<IconProps, 'color'>> = (props) => (
  <Icon {...props} color="primary" />
);

/** Accent colored icon (Golden Yellow) */
export const AccentIcon: React.FC<Omit<IconProps, 'color'>> = (props) => (
  <Icon {...props} color="accent" />
);

/** Success colored icon (Teal) */
export const SuccessIcon: React.FC<Omit<IconProps, 'color'>> = (props) => (
  <Icon {...props} color="success" />
);

/** Warning colored icon (Orange) */
export const WarningIcon: React.FC<Omit<IconProps, 'color'>> = (props) => (
  <Icon {...props} color="warning" />
);

/** Error colored icon (Red) */
export const ErrorIcon: React.FC<Omit<IconProps, 'color'>> = (props) => (
  <Icon {...props} color="error" />
);

/** Muted colored icon (Gray) */
export const MutedIcon: React.FC<Omit<IconProps, 'color'>> = (props) => (
  <Icon {...props} color="muted" />
);

export default Icon;

