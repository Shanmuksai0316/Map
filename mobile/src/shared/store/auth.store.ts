import { create } from 'zustand';
import { User, AuthResponse, OTPResponse, UserRole } from '../types';
import { apiService } from '../services/api.service';
import { StorageService } from '../services/storage.service';
import { APP_CONFIG, isStudentApp } from '../config/app.config';
import { featureFlagsService, TenantFeatureFlags } from '../services/feature-flags.service';
import { tenantService, Tenant } from '../services/tenant.service';
import { pushNotificationService } from '../services/push-notification.service';

// Test auth overrides - only enabled in development/demo mode
const TEST_AUTH_OVERRIDES: Record<string, {
  token: string;
  user: User;
  tenant?: Tenant;
  featureFlags?: TenantFeatureFlags;
}> = __DEV__ && process.env.EXPO_PUBLIC_ENABLE_TEST_AUTH === 'true' ? {
  // NOTE: Test credentials only available in development with explicit flag
  // Set EXPO_PUBLIC_ENABLE_TEST_AUTH=true in .env to enable test logins
  '8888888888': {
    token: '', // Token will be obtained via actual login
    user: {
      id: 27,
      name: 'John Stxaviers',
      phone: '+919876543677',
      email: 'cm1@stxaviers.edu',
      role: 'campus_manager',
      tenant_id: '49d4892b-99ca-415e-9dc2-926952848ecb',
    },
    tenant: {
      id: '49d4892b-99ca-415e-9dc2-926952848ecb',
      code: 'STXAV',
      name: "St. Xavier's College of Engineering",
      domain: 'stxaviers.mapservices.in',
      apiUrl: 'https://stxaviers.mapservices.in/v1',
    },
  },
} : {}; // Empty in production

interface AuthState {
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  error: string | null;
  selectedTenant: Tenant | null;
  roleValidation: {
    mismatch: boolean;
    expected: 'student' | 'staff';
    actual: string | null;
  };
  featureFlags: TenantFeatureFlags | null;

  // Actions
  sendOTP: (phone: string) => Promise<OTPResponse>;
  verifyOTP: (phone: string, otp: string) => Promise<void>;
  logout: () => Promise<void>;
  loadStoredAuth: () => void;
  clearError: () => void;
  validateRole: (user: User) => boolean;
  validateRoleScoping: (user: User) => { valid: boolean; error?: string };
  clearRoleValidation: () => void;
  loadFeatureFlags: () => Promise<void>;
  isFeatureEnabled: (feature: keyof TenantFeatureFlags) => boolean;
  setSelectedTenant: (tenant: Tenant) => void;
  setDefaultTestTenant: () => Tenant;
  clearSelectedTenant: () => void;
  autoDetectTenant: (phone: string) => Promise<Tenant>;
}

export const useAuthStore = create<AuthState>((set, get) => ({
  user: null,
  token: null,
  isAuthenticated: false,
  isLoading: false,
  error: null,
  selectedTenant: tenantService.getSelectedTenant(),
  roleValidation: {
    mismatch: false,
    expected: isStudentApp() ? 'student' : 'staff',
    actual: null,
  },
  featureFlags: null,

  sendOTP: async (phone: string) => {
    set({ isLoading: true, error: null });
    try {
      let selectedTenant = get().selectedTenant;

      // Auto-detect tenant for both student and staff using central lookup
      if (!selectedTenant) {
        selectedTenant = await get().autoDetectTenant(phone);
      }

      // Ensure phone is string and properly formatted
      const phoneString = String(phone).trim().replace(/[\s\-\(\)]/g, '');

      const response = await apiService.post<OTPResponse>(
        APP_CONFIG.ENDPOINTS.SEND_OTP,
        { phone: phoneString }
      );
      set({ isLoading: false });

      // Normalize response format - backend returns { data: { message, sms_delivered?, otp? } }
      const responseAny = response as any;
      const normalizedResponse: OTPResponse = {
        success: response?.success !== false, // Default to true if not explicitly false
        message: response?.message || responseAny?.data?.message || 'OTP sent successfully',
        otp: responseAny?.data?.otp ?? undefined, // When SMS not sent, server may return OTP for testing
      };

      return normalizedResponse;
    } catch (error: any) {
      // Log full error for debugging
      console.error('[Auth] sendOTP error:', {
        message: error?.message,
        status: error?.response?.status,
        statusText: error?.response?.statusText,
        data: error?.response?.data,
        errors: error?.response?.data?.errors,
        fullError: error,
      });

      // User-friendly error messages
      let errorMessage =
        error?.response?.data?.errors?.[0]?.detail ||
        error?.response?.data?.message ||
        error?.message ||
        'Failed to send OTP';
      
      if (error.response?.status === 429) {
        errorMessage = 'Too many OTP requests. Please wait a moment before requesting again.';
      } else if (error.response?.status === 422) {
        errorMessage = 'Invalid phone number. Please check and try again.';
      } else if (error.response?.status === 404) {
        errorMessage = 'Phone number not found. Please contact your administrator.';
      } else if (error.response?.status === 400) {
        const detail = error?.response?.data?.errors?.[0]?.detail || errorMessage;
        errorMessage = detail;
      }
      
      set({ isLoading: false, error: errorMessage });

      throw new Error(errorMessage);
    }
  },

  verifyOTP: async (phone: string, otp: string) => {
    set({ isLoading: true, error: null, roleValidation: { mismatch: false, expected: get().roleValidation.expected, actual: null } });

    // Test credentials configuration
    // Match TEST_CREDENTIALS.md: https://github.com/paragmasteh/mapmars/blob/main/mobile/TEST_CREDENTIALS.md
    const STAFF_TEST_ROLES: Record<string, string> = {
      '8888888888': 'campus_manager', // Campus Manager (test)
      '8888888890': 'warden', // Warden (test, also used for Rector - see TEST_CREDENTIALS.md)
      '8888888891': 'guard', // Security Guard (test)
      '8888888892': 'hk_supervisor', // HK Supervisor (test)
      '8888888893': 'rm_supervisor', // RM Supervisor (test)
      '8888888896': 'laundry_manager', // Laundry Manager (test)
      '8888888897': 'sports_manager', // Sports Manager (test)
    };

    // Real PPCU staff numbers - these should call backend API (not test mode)
    const REAL_PPCU_STAFF_PHONES = [
      '9663275871', // PPCU Rector
      '9739557963', // PPCU Warden
      '7676000129', // PPCU Sports Manager
      '7200658181', // PPCU HK Supervisor
      '9538678739', // PPCU Laundry Manager
    ];
    const isRealPPCUPhone = REAL_PPCU_STAFF_PHONES.includes(phone);
    const isStudentDevBypass = __DEV__ && isStudentApp() && phone === '9886179767';
    const isStudentTest = phone === '9999999999' || isStudentDevBypass;
    const isStaffTest = STAFF_TEST_ROLES[phone];
    const override = TEST_AUTH_OVERRIDES[phone];
    
    try {
      // Ensure tenant is set before making API call (for real phone numbers with bypass OTP)
      let selectedTenant = get().selectedTenant;
      if (!selectedTenant) {
        try {
          selectedTenant = await get().autoDetectTenant(phone);
        } catch (error: any) {
          console.error('[Auth] Failed to auto-detect tenant in verifyOTP:', error.message);
          // Re-throw the same message (e.g. network error with "select institution" hint)
          throw new Error(error?.message || 'Unable to detect your institution. Please try again.');
        }
      }

      // Ensure phone and OTP are strings and properly formatted
      const phoneString = String(phone).trim().replace(/[\s\-\(\)]/g, '');
      const otpString = String(otp).trim().replace(/\D/g, ''); // Remove non-digits

      const response = await apiService.post<AuthResponse>(
        APP_CONFIG.ENDPOINTS.VERIFY_OTP,
        {
          phone: phoneString,
          otp: otpString,
          device_name: isStudentApp() ? 'student-app' : 'staff-app',
        }
      );

      if (response.success && response.data) {
        const { token: rawToken, user } = response.data;

        // Ensure token is a string (defensive coding)
        let token: string;
        if (typeof rawToken === 'string') {
          token = rawToken;
        } else if (rawToken && typeof rawToken === 'object' && (rawToken as any).plainTextToken) {
          token = (rawToken as any).plainTextToken;
        } else if (rawToken && typeof rawToken === 'object' && (rawToken as any).token) {
          token = (rawToken as any).token;
        } else {
          token = String(rawToken || '');
        }

        if (!get().validateRole(user)) {
          set({
            isLoading: false,
            roleValidation: {
              mismatch: true,
              expected: isStudentApp() ? 'student' : 'staff',
              actual: user.role,
            },
          });
          throw new Error('ROLE_MISMATCH');
        }

        // Validate role scoping (Campus Manager tenant-wide, others hostel-scoped)
        const scopingValidation = get().validateRoleScoping(user);
        if (!scopingValidation.valid) {
          set({
            isLoading: false,
            error: scopingValidation.error || 'Role assignment error',
          });
          throw new Error(scopingValidation.error || 'SCOPING_ERROR');
        }
        
        // AUTO-DETECT TENANT FOR STUDENTS (and staff if not already selected)
        if (user.tenant_id && (!get().selectedTenant || get().selectedTenant?.id !== user.tenant_id)) {
          try {
            const tenant = await tenantService.getTenantById(user.tenant_id);
            
            if (tenant) {
              await tenantService.setSelectedTenant(tenant);
              apiService.updateBaseUrl();
              set({ selectedTenant: tenant });
            } else {
              console.warn('[Auth] Tenant not found in list, constructing from user data');
              // Fallback: construct tenant from 'user data'
              // This will use central API URL until tenant details are fetched
              const fallbackTenant: Tenant = {
                id: user.tenant_id,
                code: 'UNKNOWN',
                name: 'Your Institution',
                domain: '',
                apiUrl: APP_CONFIG.CENTRAL_API_URL,
              };
              await tenantService.setSelectedTenant(fallbackTenant);
              apiService.updateBaseUrl();
              set({ selectedTenant: fallbackTenant });
            }
          } catch (error) {
            console.error('[Auth] Failed to auto-detect tenant:', error);
            // Continue with login even if tenant detection fails
            // User can manually select tenant if needed
          }
        }
        
        // Save to storage - ensure token is a string, not an object
        if (typeof token !== 'string' || token.trim().length === 0) {
          console.error('[Auth] Invalid token format:', typeof token, token);
          throw new Error('Invalid token received from server');
        }
        
        // Double-check token is not a Zustand state object
        if (token.startsWith('{') && (token.includes('"_h"') || token.includes('"_i"'))) {
          console.error('[Auth] Token appears to be a Zustand state object, rejecting');
          throw new Error('Invalid token format');
        }
        
        // Ensure token is a clean string (no object references)
        const cleanToken = String(token).trim();
        if (cleanToken.length === 0) {
          console.error('[Auth] Token is empty after cleaning');
          throw new Error('Invalid token received from server');
        }
        
        await StorageService.set(APP_CONFIG.STORAGE_KEYS.AUTH_TOKEN, cleanToken);
        await StorageService.setObject(APP_CONFIG.STORAGE_KEYS.USER_DATA, user);
        await StorageService.set(APP_CONFIG.STORAGE_KEYS.USER_ROLE, user.role);
        await StorageService.set(APP_CONFIG.STORAGE_KEYS.PHONE_NUMBER, phone);

            // Use cleanToken (already validated) instead of token
            // This ensures we're storing the validated string, not the potentially corrupted original
            const storeToken = cleanToken;
            
            set({
              user,
              token: storeToken,
              isAuthenticated: true,
              isLoading: false,
              error: null,
            });
            
            // Verify token was stored correctly in Zustand
            const storedToken = get().token;
            if (storedToken !== storeToken) {
              console.error('[Auth] Token mismatch after Zustand set:', {
                expected: storeToken.substring(0, 20),
                actual: typeof storedToken === 'string' ? storedToken.substring(0, 20) : String(storedToken),
              });
            }

            // Load feature flags after successful login
            get().loadFeatureFlags();
      } else {
        throw new Error(response.message || 'Invalid OTP');
      }
    } catch (error: any) {
      if (error.message === 'ROLE_MISMATCH') {
        throw error;
      }
      if (error.message === 'SCOPING_ERROR') {
        throw error;
      }
      
      // User-friendly error messages
      let errorMessage = error.response?.data?.message || error.message || 'Failed to verify OTP';
      
      // Map common API errors to user-friendly messages
      if (error.response?.status === 422) {
        errorMessage = 'Invalid OTP. Please check and try again.';
      } else if (error.response?.status === 429) {
        errorMessage = 'Too many attempts. Please wait a moment and try again.';
      } else if (error.response?.status === 401) {
        errorMessage = 'OTP expired or invalid. Please request a new OTP.';
      } else if (error.message?.includes('hostel') || error.message?.includes('assignment')) {
        errorMessage = 'Your account is not assigned to a hostel. Please contact your administrator.';
      }
      
      set({ isLoading: false, error: errorMessage });
      throw new Error(errorMessage);
    }
  },

  logout: async () => {
    set({ isLoading: true });
    try {
      await apiService.post(APP_CONFIG.ENDPOINTS.LOGOUT);
    } catch (error) {
      // Continue with logout even if API call fails
      console.error('Logout API error:', error);
    } finally {
      // Clear storage and state
      await StorageService.clear();
      await pushNotificationService.clearCachedToken();
      pushNotificationService.teardown();
      set({
        user: null,
        token: null,
        isAuthenticated: false,
        isLoading: false,
        error: null,
      });
    }
  },

  loadStoredAuth: async () => {
    const token = await StorageService.get(APP_CONFIG.STORAGE_KEYS.AUTH_TOKEN);
    const user = await StorageService.getObject<User>(APP_CONFIG.STORAGE_KEYS.USER_DATA);
    const storedTenant = tenantService.getSelectedTenant();

    if (token && user) {
      if (!get().validateRole(user)) {
        set({
          roleValidation: {
            mismatch: true,
            expected: isStudentApp() ? 'student' : 'staff',
            actual: user.role,
          },
        });
        await StorageService.clear();
        return;
      }

      // Restore tenant if available
      if (storedTenant) {
        apiService.updateBaseUrl();
        set({ selectedTenant: storedTenant });
      } else if (user.tenant_id) {
        // Try to load tenant by ID if not already set
        tenantService.getTenantById(user.tenant_id).then(async (tenant) => {
          if (tenant) {
            await tenantService.setSelectedTenant(tenant);
            apiService.updateBaseUrl();
            set({ selectedTenant: tenant });
          }
        }).catch(() => {
          // Ignore errors - tenant will be set on next login
        });
      }

      set({
        token,
        user,
        isAuthenticated: true,
        roleValidation: {
          mismatch: false,
          expected: isStudentApp() ? 'student' : 'staff',
          actual: null,
        },
      });

      // Load feature flags on app startup
      get().loadFeatureFlags();
    }
  },

  clearError: () => set({ error: null }),

  validateRole: (user: User) => {
    const expectedVariant = isStudentApp() ? 'student' : 'staff';
    const userRole = user.role?.toLowerCase();
    const isStudentRole = userRole === 'student';

    if (expectedVariant === 'student') {
      return isStudentRole;
    }

    return !isStudentRole;
  },

  validateRoleScoping: (user: User) => {
    // Normalize role for comparison (backend may return "Guard", "Campus Manager", etc.)
    const normalizeRoleForComparison = (role: string | null | undefined): string | null => {
      if (!role) return null;
      return role.toLowerCase().replace(/\s+/g, '_');
    };
    
    const normalizedRole = normalizeRoleForComparison(user.role);
    
    // Skip validation for students
    if (normalizedRole === 'student') {
      return { valid: true };
    }

    // Campus Manager is tenant-wide (no hostel_id required)
    if (normalizedRole === 'campus_manager') {
      if (!user.tenant_id) {
        return {
          valid: false,
          error: 'Campus Manager account is not assigned to a tenant. Please contact your administrator.',
        };
      }
      return { valid: true };
    }

    // All other staff roles normally require hostel assignment.
    // TEMPORARY: During initial rollout, allow all staff roles (including Warden/Guard)
    // to log in even if hostel_id is missing, as long as tenant_id exists.
    const hostelScopedRoles = [
      'rector',
      'warden',
      'guard',
      'hk_supervisor',
      'rm_supervisor',
      'laundry_manager',
      'sports_manager',
    ];

    if (normalizedRole && hostelScopedRoles.includes(normalizedRole)) {
      if (!user.tenant_id) {
        return {
          valid: false,
          error: 'Your account is not assigned to a tenant. Please contact your administrator.',
        };
      }

      // NOTE: We are intentionally NOT blocking login when hostel_id is missing.
      // The hostel assignment for Warden/Guard/etc. will be enforced later at the
      // API/permission level once data is fully configured.
      return { valid: true };
    }

    // College Management and other web-only roles may not have hostel_id
    // Allow them to proceed
    return { valid: true };
  },

  clearRoleValidation: () =>
    set({
      roleValidation: {
        mismatch: false,
        expected: isStudentApp() ? 'student' : 'staff',
        actual: null,
      },
    }),

  loadFeatureFlags: async () => {
    try {
      // Load from 'cache first'
      const cachedFlags = await featureFlagsService.loadFromCache();
      set({ featureFlags: cachedFlags });

      // Fetch fresh flags in background
      const freshFlags = await featureFlagsService.fetchFlags();
      set({ featureFlags: freshFlags });
    } catch (error) {
      console.error('Failed to load feature flags:', error);
      // Keep cached or default flags
    }
  },

  isFeatureEnabled: (feature: keyof TenantFeatureFlags) => {
    const flags = get().featureFlags;
    return flags ? flags[feature] : false;
  },

  setSelectedTenant: async (tenant: Tenant) => {
    await tenantService.setSelectedTenant(tenant);
    const normalizedTenant = tenantService.getSelectedTenant() ?? tenant;
    apiService.updateBaseUrl();
    set({ selectedTenant: normalizedTenant });
  },
  
  // Helper to set default tenant for testing
  setDefaultTestTenant: () => {
    const defaultTenant: Tenant = {
      id: '1',
      code: 'DEMO',
      name: 'Demo College',
      domain: 'demo-college',
      apiUrl: APP_CONFIG.CENTRAL_API_URL
    };
    set({ selectedTenant: defaultTenant });
    return defaultTenant;
  },

  clearSelectedTenant: () => {
    tenantService.clearSelectedTenant();
    set({ selectedTenant: null });
  },

  autoDetectTenant: async (phone: string) => {
    const endpoint = `${APP_CONFIG.CENTRAL_API_URL}/mobile/auth/tenant-lookup`;
    const networkErrorMessage =
      'Cannot reach the server. Check your internet connection and try again.';
    try {
      const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ phone }),
      });

      let payload: any = null;
      try {
        payload = await response.json();
      } catch (parseError) {
        // Ignore JSON parse issues so we can fall back to a generic error
      }

      if (!response.ok) {
        const detail =
          payload?.errors?.detail ||
          payload?.errors?.[0]?.detail ||
          payload?.message;
        throw new Error(detail || 'Unable to detect your institution. Please try again.');
      }

      const data = payload?.data;
      if (!data?.tenant_id || !data?.tenant_code) {
        throw new Error('Institution lookup returned an invalid response.');
      }

      const detectedTenant: Tenant = {
        id: data.tenant_id,
        code: data.tenant_code,
        name: data.tenant_name ?? data.tenant_code,
        domain: data.domain ?? '',
        apiUrl: data.api_url || APP_CONFIG.CENTRAL_API_URL,
      };

      await tenantService.setSelectedTenant(detectedTenant);
      const normalizedTenant = tenantService.getSelectedTenant() ?? detectedTenant;
      apiService.updateBaseUrl();
      set({ selectedTenant: normalizedTenant });

      return normalizedTenant;
    } catch (error: any) {
      const raw = error?.message || '';
      const isNetworkError =
        raw === 'Network request failed' ||
        raw.includes('Network request failed') ||
        raw.includes('Failed to fetch') ||
        raw.includes('network') ||
        (error?.name === 'TypeError' && (raw.includes('fetch') || raw.includes('network')));
      const message = isNetworkError ? networkErrorMessage : raw || 'Failed to auto-detect your institution.';
      console.error('[Auth] Tenant auto-detect failed:', raw);
      throw new Error(message);
    }
  },
}));
