import { NativeModules, Platform } from 'react-native';

// =============================================================================
// BUILD VARIANT DETECTION (student | staff)
// =============================================================================
const AndroidBuildConfigModule = NativeModules.BuildConfig || {};
const IOSBuildConfigModule = NativeModules.BuildConfigModule || {};

const BUILD_VARIANT_FROM_NATIVE =
  Platform.OS === 'android'
    ? (AndroidBuildConfigModule.BUILD_VARIANT as string | undefined)
    : Platform.OS === 'ios'
      ? (IOSBuildConfigModule.BUILD_VARIANT as string | undefined)
      : undefined;

const BUILD_VARIANT = (BUILD_VARIANT_FROM_NATIVE || 'student') as 'student' | 'staff';

const DEBUG_FROM_NATIVE =
  Platform.OS === 'android'
    ? Boolean(AndroidBuildConfigModule.DEBUG)
    : Platform.OS === 'ios'
      ? Boolean(IOSBuildConfigModule.DEBUG)
      : __DEV__;

const IS_DEBUG_BUILD = DEBUG_FROM_NATIVE || __DEV__;

/**
 * Dev-only: override central API URL when the app cannot reach the default server
 * (e.g. on device/emulator "localhost" is the device itself).
 * Set and reload the app:
 *   - Android emulator: 'http://10.0.2.2:8000/api/v1' (10.0.2.2 = host machine)
 *   - Physical device: your machine's LAN IP, e.g. 'http://192.168.1.5:8000/api/v1'
 *   - iOS simulator: localhost usually works; if not, use your machine IP.
 * Leave null to use the default (production or local from build env).
 */
const DEV_CENTRAL_API_OVERRIDE: string | null = null;

// =============================================================================
// ENVIRONMENT DETECTION (local | production)
// =============================================================================
const BUILD_ENV_FROM_NATIVE =
  Platform.OS === 'android'
    ? (AndroidBuildConfigModule.BUILD_ENV as string | undefined)
    : Platform.OS === 'ios'
      ? (IOSBuildConfigModule.BUILD_ENV as string | undefined)
      : undefined;

type Environment = 'local' | 'production';

/**
 * Detect the current environment:
 * 1. Native build config takes priority (from build flavors)
 * 2. If native says "production", always use production (even with Metro)
 * 3. Otherwise default to production for safety (production debug APK = production backend)
 *
 * Workflow: local development → production (no staging)
 */
const detectEnvironment = (): Environment => {
  // Native build config takes priority (studentProductionDebug → "production")
  if (BUILD_ENV_FROM_NATIVE) {
    const env = BUILD_ENV_FROM_NATIVE.toLowerCase();
    if (env === 'local' || env === 'production') {
      return env;
    }
  }

  // Default to production so production debug APKs always hit production backend
  return 'production';
};

const CURRENT_ENV = detectEnvironment();

// =============================================================================
// ENVIRONMENT-SPECIFIC CONFIGURATION
// =============================================================================
interface EnvConfig {
  API_BASE_DOMAIN: string;
  API_PROTOCOL: 'http' | 'https';
  TENANT_DOMAIN_SUFFIX: string;
  CENTRAL_DOMAIN: string;
}

const ENV_CONFIG: Record<Environment, EnvConfig> = {
  local: {
    API_BASE_DOMAIN: 'api.localhost',
    API_PROTOCOL: 'http',
    TENANT_DOMAIN_SUFFIX: '.localhost:8000',
    CENTRAL_DOMAIN: 'api.localhost:8000',
  },
  production: {
    API_BASE_DOMAIN: 'api.mapservices.in',
    API_PROTOCOL: 'https',
    TENANT_DOMAIN_SUFFIX: '.mapservices.in',
    CENTRAL_DOMAIN: 'api.mapservices.in',
  },
};

const ENV = ENV_CONFIG[CURRENT_ENV];

// =============================================================================
// APP CONFIGURATION EXPORT
// =============================================================================
export const APP_CONFIG = {
  // Current environment (local → production workflow, no staging)
  ENVIRONMENT: CURRENT_ENV,
  IS_PRODUCTION: CURRENT_ENV === 'production',
  IS_LOCAL: CURRENT_ENV === 'local',
  
  // API configuration
  API_BASE_DOMAIN: ENV.API_BASE_DOMAIN,
  API_PROTOCOL: ENV.API_PROTOCOL,
  TENANT_DOMAIN_SUFFIX: ENV.TENANT_DOMAIN_SUFFIX,
  
  // Central API URL (for tenant lookup, auth, etc.)
  // In __DEV__, DEV_CENTRAL_API_OVERRIDE (if set) is used so device/emulator can reach your machine.
  CENTRAL_API_URL:
    (__DEV__ && typeof DEV_CENTRAL_API_OVERRIDE === 'string' && DEV_CENTRAL_API_OVERRIDE.trim() !== '')
      ? DEV_CENTRAL_API_OVERRIDE.trim().replace(/\/$/, '') // no trailing slash
      : `${ENV.API_PROTOCOL}://${ENV.CENTRAL_DOMAIN}/api/v1`,
  
  /**
   * Build tenant-specific API URL from tenant code
   * @param tenantCode - The tenant code (e.g., 'stxav', 'demo')
   * @returns Full API URL for the tenant (e.g., 'https://stxav.mapservices.in/api/v1')
   */
  getTenantApiUrl: (tenantCode: string): string => {
    const code = tenantCode.toLowerCase();
    return `${ENV.API_PROTOCOL}://${code}${ENV.TENANT_DOMAIN_SUFFIX}/api/v1`;
  },
  
  /**
   * Build tenant domain from tenant code
   * @param tenantCode - The tenant code (e.g., 'stxav', 'demo')
   * @returns Full domain for the tenant (e.g., 'stxav.mapservices.in')
   */
  getTenantDomain: (tenantCode: string): string => {
    const code = tenantCode.toLowerCase();
    return `${code}${ENV.TENANT_DOMAIN_SUFFIX}`;
  },
  
  APP_NAME: 'MAP HMS',
  APP_VERSION: '1.0.0',
  IS_DEBUG_BUILD,
  
  // Build variants
  BUILD_VARIANT,
  
  // API Endpoints
  // All mobile endpoints use /v1/mobile prefix (handled by baseURL)
  ENDPOINTS: {
    // Authentication (mobile endpoints are namespaced under /v1/mobile)
    SEND_OTP: '/mobile/auth/send-otp',
    VERIFY_OTP: '/mobile/auth/verify-otp',
    LOGOUT: '/auth/logout',
    PROFILE: '/mobile/profile',
    ACCOUNT_DELETION_REQUEST: '/mobile/account/deletion-request',
    
    // Common endpoints
    DASHBOARD: '/mobile/dashboard',
    ATTENDANCE: '/mobile/attendance',
    ATTENDANCE_STATS: '/mobile/attendance/stats',
    ATTENDANCE_SESSION_TODAY: '/attendance/session/today',
    ATTENDANCE_SESSIONS: '/attendance/sessions',
    TICKETS: '/mobile/tickets',  // Student tickets (housekeeping, repair)
    NOTICES: '/mobile/notices',
    // In-app notifications (bell list); backend route is under /mobile prefix.
    NOTIFICATIONS: '/mobile/notifications',
    STUDENT_EMERGENCY_MEDICAL: '/mobile/emergency/medical',
    STUDENT_EMERGENCY_INCIDENT: '/mobile/emergency/incident',
    
    // Gate Pass / OutPass (backend uses 'outpasses')
    GATE_PASSES: '/mobile/gate-passes',  // Student endpoint (via mobile API)
    OUTPASSES: '/outpasses',             // Staff endpoint (backend name)

    // Supervisor tickets (staff)
    SUPERVISOR_TICKETS: '/supervisor/tickets',
    
    // Complaints (maps to tickets)
    COMPLAINTS: '/mobile/tickets',
    
    // Warden-specific endpoints (mobile API)
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

    // Guard-specific active requests and stats
    GUARD_OUTPASSES_ACTIVE: '/guard/outpasses/active',
    GUARD_LEAVES_ACTIVE: '/guard/leaves/active',
    GUARD_GUEST_ENTRIES_ACTIVE: '/guard/guest-entries/active',
    GUARD_DASHBOARD_STATS: '/guard/dashboard/stats',
    GUARD_HISTORY: '/guard/history',
    
    // Offline queue sync
    OFFLINE_SYNC: '/mobile/offline/sync',
    OFFLINE_HISTORY: '/mobile/offline/history',
    
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
    SPORTS_FACILITIES: '/mobile/sports/facilities',
    SPORTS_BOOKINGS: '/mobile/sports/bookings',
    SPORTS_BLOCKOUTS: '/sports/facilities',
    SPORTS_EVENTS: '/sports/events',
    SPORTS_MONITORING: '/sports/facilities',
    LAUNDRY: '/laundry',
    LAUNDRY_REQUESTS: '/mobile/laundry/requests',
    PARCELS: '/mobile/parcels',
    LAUNDRY_METRICS: '/laundry/metrics',
    LAUNDRY_CYCLES: '/laundry/cycles',
    
    // Student mobile endpoints (use /mobile prefix)
    LEAVES: '/mobile/leaves',
    SICK_LEAVES: '/mobile/sick-leaves',
    GUEST_ENTRIES: '/mobile/guest-entries',
    ROOM_CHANGES: '/mobile/room-changes',
    STUDENT_PROFILE: '/mobile/profile',
    STUDENT_DASHBOARD: '/mobile/dashboard',
    STUDENT_NOTICES: '/mobile/notices',
    STUDENT_ATTENDANCE: '/mobile/attendance',
    STUDENT_MESSAGES: '/mobile/messages',
    STUDENT_FEEDBACK: '/mobile/feedback',
  },
  
  // Storage Keys
  STORAGE_KEYS: {
    AUTH_TOKEN: 'auth_token',
    USER_DATA: 'user_data',
    USER_ROLE: 'user_role',
    PHONE_NUMBER: 'phone_number',
  },
};

// =============================================================================
// HELPER FUNCTIONS
// =============================================================================
export const isStudentApp = () => APP_CONFIG.BUILD_VARIANT === 'student';
export const isStaffApp = () => APP_CONFIG.BUILD_VARIANT === 'staff';

// Log environment in development
if (__DEV__) {
  // eslint-disable-next-line no-console
  console.log('[APP] Environment:', APP_CONFIG.ENVIRONMENT);
  // eslint-disable-next-line no-console
  console.log('[APP] Build variant:', APP_CONFIG.BUILD_VARIANT);
  // eslint-disable-next-line no-console
  console.log('[APP] Central API:', APP_CONFIG.CENTRAL_API_URL);
  if (DEV_CENTRAL_API_OVERRIDE) {
    // eslint-disable-next-line no-console
    console.log('[APP] Using DEV_CENTRAL_API_OVERRIDE for tenant lookup');
  }
}

export default APP_CONFIG;
