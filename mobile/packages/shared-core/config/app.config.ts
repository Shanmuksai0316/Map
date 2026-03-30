import { NativeModules, Platform } from 'react-native';

const AndroidBuildConfigModule = NativeModules.BuildConfig || {};
const IOSBuildConfigModule = NativeModules.BuildConfigModule || {};

const BUILD_VARIANT_FROM_NATIVE =
  Platform.OS === 'android'
    ? (AndroidBuildConfigModule.BUILD_VARIANT as string | undefined)
    : Platform.OS === 'ios'
      ? (IOSBuildConfigModule.BUILD_VARIANT as string | undefined)
      : undefined;

const BUILD_VARIANT = (BUILD_VARIANT_FROM_NATIVE || 'student') as 'student' | 'staff';

// Environment-specific configuration
const ENV_CONFIG = {
  production: {
    API_BASE_DOMAIN: 'mapservices.in',
    API_PROTOCOL: 'https' as const,
  },
  development: {
    API_BASE_DOMAIN: 'localhost:8000',
    API_PROTOCOL: 'http' as const,
  },
};

const IS_PRODUCTION = !__DEV__;
const ENV = IS_PRODUCTION ? ENV_CONFIG.production : ENV_CONFIG.development;

export const APP_CONFIG = {
  // Environment configuration
  API_BASE_DOMAIN: ENV.API_BASE_DOMAIN,
  API_PROTOCOL: ENV.API_PROTOCOL,
  // In development, use localhost:8000/api/v1 directly (no api.localhost subdomain needed)
  // In production, use api.mapservices.in/v1
  CENTRAL_API_URL: IS_PRODUCTION 
    ? `${ENV.API_PROTOCOL}://api.${ENV.API_BASE_DOMAIN}/api/v1`
    : `${ENV.API_PROTOCOL}://${ENV.API_BASE_DOMAIN}/api/v1`,
  
  APP_NAME: 'MAP HMS',
  APP_VERSION: '1.0.0',
  
  // Build variants
  BUILD_VARIANT,
  
  // API Endpoints
  ENDPOINTS: {
    // Authentication (mobile routes under /v1/mobile)
    SEND_OTP: '/mobile/auth/send-otp',
    VERIFY_OTP: '/mobile/auth/verify-otp',
    LOGOUT: '/auth/logout',
    PROFILE: '/mobile/profile',
    ACCOUNT_DELETION_REQUEST: '/mobile/account/deletion-request',
    
    // Common endpoints (backend uses /mobile prefix)
    DASHBOARD: '/mobile/dashboard',
    ATTENDANCE: '/mobile/attendance',
    TICKETS: '/mobile/tickets',
    NOTICES: '/mobile/notices',
    NOTIFICATIONS: '/mobile/notifications',
    
    // Gate Pass / OutPass (backend uses 'outpasses')
    GATE_PASSES: '/mobile/gate-passes',
    OUTPASSES: '/outpasses',
    COMPLAINTS: '/mobile/tickets',
    WARDEN_ROOMS: '/mobile/warden/rooms',
    WARDEN_STUDENTS: '/mobile/warden/students',
    WARDEN_REQUESTS: '/mobile/warden/requests',
    WARDEN_CHECKLIST: '/mobile/warden/checklist',
    WARDEN_UNMARKED: '/mobile/warden/unmarked',
    
    // Gate operations (Guard role)
    GATE_ENTRIES: '/gate/entries',
    GATE_IN: '/gate/in',
    GATE_OUT: '/gate/out',
    GATE_SCAN: '/gate/scan',
    
    // Offline queue sync
    OFFLINE_SYNC: '/offline/sync',
    OFFLINE_HISTORY: '/offline/history',
    
    // Rector-specific
    RECTOR_APPROVALS: '/rector/approvals',
    RECTOR_DASHBOARD: '/rector/dashboard',
    RECTOR_INCIDENTS: '/rector/incidents',
    RECTOR_INSIGHTS: '/rector/insights',
    
    // PII Reveal (Audit)
    PII_REVEAL: '/audit/pii/reveal',
    
    // Admin/Campus Manager
    ADMIN_CAMPUSES: '/admin/campuses',
    ADMIN_HOSTELS: '/admin/hostels',
    ADMIN_ROOMS: '/admin/rooms',
    ADMIN_CHECKLISTS: '/admin/checklists',
    ADMIN_NOTICES: '/admin/notices',
    ADMIN_ALLOCATIONS: '/admin/allocations',
    ADMIN_IMPORTS_STUDENTS: '/admin/imports/students',
    ADMIN_IMPORTS_ROOM_ALLOTMENTS: '/admin/imports/room-allotments',
    STUDENTS: '/students',
    ROOM_ALLOCATIONS: '/room-allocations',
    
    // Feature-flagged modules
    SPORTS: '/sports',
    SPORTS_FACILITIES: '/sports/facilities',
    SPORTS_BLOCKOUTS: '/sports/facilities',
    SPORTS_EVENTS: '/sports/events',
    SPORTS_MONITORING: '/sports/facilities',
    LAUNDRY: '/laundry',
    LAUNDRY_REQUESTS: '/laundry/requests',
    LAUNDRY_METRICS: '/laundry/metrics',
    LAUNDRY_CYCLES: '/laundry/cycles',
    
    // New endpoints
    LEAVES: '/leaves',
    SICK_LEAVES: '/sick-leaves',
    GUEST_ENTRIES: '/guest-entries',
    ROOM_CHANGES: '/room-changes',
  },
  
  // Storage Keys
  STORAGE_KEYS: {
    AUTH_TOKEN: 'auth_token',
    USER_DATA: 'user_data',
    USER_ROLE: 'user_role',
    PHONE_NUMBER: 'phone_number',
  },
};

export const isStudentApp = () => APP_CONFIG.BUILD_VARIANT === 'student';
export const isStaffApp = () => APP_CONFIG.BUILD_VARIANT === 'staff';

if (__DEV__) {
  // eslint-disable-next-line no-console
  console.log('[APP] Build variant:', APP_CONFIG.BUILD_VARIANT);
}

export default APP_CONFIG;
