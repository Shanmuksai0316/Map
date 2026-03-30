import { create } from 'zustand';
import { User, AuthResponse, OTPResponse, UserRole } from '../types';
import { apiService } from '../services/api.service';
import { StorageService } from '../services/storage.service';
import { APP_CONFIG, isStudentApp } from '../config/app.config';
import { featureFlagsService, TenantFeatureFlags } from '../services/feature-flags.service';
import { tenantService, Tenant } from '../services/tenant.service';

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

      if (isStudentApp()) {
        selectedTenant = await get().autoDetectTenant(phone);
      } else if (!selectedTenant) {
        throw new Error('Please select your institution first.');
      }

      if (!selectedTenant) {
        throw new Error('Unable to determine your institution. Please contact support.');
      }

      const response = await apiService.post<OTPResponse>(
        APP_CONFIG.ENDPOINTS.SEND_OTP,
        { phone }
      );
      set({ isLoading: false });
      return response;
    } catch (error: any) {
      // User-friendly error messages
      let errorMessage =
        error?.response?.data?.message ||
        error?.message ||
        'Failed to send OTP';
      
      if (error.response?.status === 429) {
        errorMessage = 'Too many OTP requests. Please wait a moment before requesting again.';
      } else if (error.response?.status === 422) {
        errorMessage = 'Invalid phone number. Please check and try again.';
      } else if (error.response?.status === 404) {
        errorMessage = 'Phone number not found. Please contact your administrator.';
      }
      
      set({ isLoading: false, error: errorMessage });
      throw new Error(errorMessage);
    }
  },

  verifyOTP: async (phone: string, otp: string) => {
    set({ isLoading: true, error: null, roleValidation: { mismatch: false, expected: get().roleValidation.expected, actual: null } });
    
    // Test mode - bypass for specific credentials
    // Match TEST_CREDENTIALS.md: https://github.com/paragmasteh/mapmars/blob/main/mobile/TEST_CREDENTIALS.md
    const STAFF_TEST_ROLES: Record<string, string> = {
      '8888888888': 'campus_manager', // Campus Manager
      '8888888890': 'warden', // Warden (also used for Rector - see TEST_CREDENTIALS.md)
      '8888888891': 'guard', // Security Guard
      '8888888892': 'hk_supervisor', // HK Supervisor
      '8888888893': 'rm_supervisor', // RM Supervisor
      '8888888896': 'laundry_manager', // Laundry Manager
      '8888888897': 'sports_manager', // Sports Manager
    };

    const isStudentTest = phone === '9999999999';
    const isStaffTest = STAFF_TEST_ROLES[phone];
    const override = TEST_AUTH_OVERRIDES[phone];

    if ((isStudentTest || isStaffTest) && otp === '123456') {
      if (override) {
        if (!get().validateRole(override.user)) {
          set({
            isLoading: false,
            roleValidation: {
              mismatch: true,
              expected: isStudentApp() ? 'student' : 'staff',
              actual: override.user.role,
            },
          });
          throw new Error('ROLE_MISMATCH');
        }

        StorageService.set(APP_CONFIG.STORAGE_KEYS.AUTH_TOKEN, override.token);
        StorageService.setObject(APP_CONFIG.STORAGE_KEYS.USER_DATA, override.user);
        StorageService.set(APP_CONFIG.STORAGE_KEYS.USER_ROLE, override.user.role);
        StorageService.set(APP_CONFIG.STORAGE_KEYS.PHONE_NUMBER, phone);

        if (override.tenant) {
          tenantService.setSelectedTenant(override.tenant);
          apiService.updateBaseUrl();
          set({ selectedTenant: override.tenant });
        }

        set({
          user: override.user,
          token: override.token,
          isAuthenticated: true,
          isLoading: false,
          error: null,
          featureFlags: override.featureFlags ?? null,
        });

        // Load feature flags from API (will use override token)
        get().loadFeatureFlags();
        return;
      }

      const roleMap: Record<string, string> = {
        'campus_manager': 'campus_manager',
        'rector': 'rector',
        'warden': 'warden',
        'guard': 'guard',
        'hk_supervisor': 'hk_supervisor',
        'rm_supervisor': 'rm_supervisor',
        'laundry_manager': 'laundry_manager',
        'sports_manager': 'sports_manager',
      };

      const displayNameMap: Record<string, string> = {
        'campus_manager': 'Campus Manager',
        'rector': 'Rector',
        'warden': 'Warden',
        'guard': 'Security Guard',
        'hk_supervisor': 'HK Supervisor',
        'rm_supervisor': 'RM Supervisor',
        'laundry_manager': 'Laundry Manager',
        'sports_manager': 'Sports Manager',
      };

      // Map phone to role - handle special cases
      let userRole = 'campus_manager';
      const roleKey = STAFF_TEST_ROLES[phone];
      if (roleKey) {
        userRole = roleMap[roleKey] || 'campus_manager';
      } else if (phone === '8888888890') {
        // Special case: 8888888890 can be either Rector or Warden
        // Default to warden as per TEST_CREDENTIALS.md
        userRole = 'warden';
      }

      const mockUser: User = {
        id: 1,
        name: isStudentTest ? 'Test Student' : displayNameMap[roleKey || ''] || 'Test Staff',
        phone: phone,
        email: isStudentTest ? 'student@test.com' : 'staff@test.com',
        role: (isStudentTest ? 'student' : userRole) as UserRole,
        tenant_id: '1',
      };
      const mockToken = 'test-token-' + Date.now();
      
      if (!get().validateRole(mockUser)) {
        set({
          isLoading: false,
          roleValidation: {
            mismatch: true,
            expected: isStudentApp() ? 'student' : 'staff',
            actual: mockUser.role,
          },
        });
        throw new Error('ROLE_MISMATCH');
      }
      
      StorageService.set(APP_CONFIG.STORAGE_KEYS.AUTH_TOKEN, mockToken);
      StorageService.setObject(APP_CONFIG.STORAGE_KEYS.USER_DATA, mockUser);
      StorageService.set(APP_CONFIG.STORAGE_KEYS.USER_ROLE, mockUser.role);
      StorageService.set(APP_CONFIG.STORAGE_KEYS.PHONE_NUMBER, phone);

      set({
        user: mockUser,
        token: mockToken,
        isAuthenticated: true,
        isLoading: false,
        error: null,
      });

      // Load mock feature flags for test mode
      set({ featureFlags: {
        sports_module_enabled: true,
        laundry_module_enabled: true,
        security_module_enabled: true,
        visitor_management_enabled: true,
        medical_module_enabled: false,
        emergency_contacts_enabled: true,
        sms_notifications_enabled: false,
        email_notifications_enabled: false,
        push_notifications_enabled: true,
        payment_integration_enabled: false,
        qr_gate_pass_enabled: true,
        biometric_attendance_enabled: false,
        hostel_transfer_enabled: false,
        bulk_import_enabled: false,
      }});
      
      return;
    }
    
    try {
      const response = await apiService.post<AuthResponse>(
        APP_CONFIG.ENDPOINTS.VERIFY_OTP,
        {
          phone,
          otp,
          device_name: isStudentApp() ? 'student-app' : 'staff-app',
        }
      );

      if (response.success && response.data) {
        const { token, user } = response.data;

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
        if (user.tenant_id && !get().selectedTenant) {
          try {
            console.log('[Auth] Auto-detecting tenant:', user.tenant_id);
            const tenant = await tenantService.getTenantById(user.tenant_id);
            
            if (tenant) {
              tenantService.setSelectedTenant(tenant);
              apiService.updateBaseUrl();
              set({ selectedTenant: tenant });
              console.log('[Auth] Tenant auto-detected:', tenant.name);
            } else {
              console.warn('[Auth] Tenant not found in list, constructing from user data');
              // Fallback: construct tenant from user data
              // This will use central API URL until tenant details are fetched
              const fallbackTenant: Tenant = {
                id: user.tenant_id,
                code: 'UNKNOWN',
                name: 'Your Institution',
                domain: '',
                apiUrl: APP_CONFIG.CENTRAL_API_URL,
              };
              tenantService.setSelectedTenant(fallbackTenant);
              set({ selectedTenant: fallbackTenant });
            }
          } catch (error) {
            console.error('[Auth] Failed to auto-detect tenant:', error);
            // Continue with login even if tenant detection fails
            // User can manually select tenant if needed
          }
        }
        
        // Save to storage
        StorageService.set(APP_CONFIG.STORAGE_KEYS.AUTH_TOKEN, token);
        StorageService.setObject(APP_CONFIG.STORAGE_KEYS.USER_DATA, user);
        StorageService.set(APP_CONFIG.STORAGE_KEYS.USER_ROLE, user.role);
        StorageService.set(APP_CONFIG.STORAGE_KEYS.PHONE_NUMBER, phone);

            set({
              user,
              token,
              isAuthenticated: true,
              isLoading: false,
              error: null,
            });

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
      StorageService.clear();
      set({
        user: null,
        token: null,
        isAuthenticated: false,
        isLoading: false,
        error: null,
      });
    }
  },

  loadStoredAuth: () => {
    const token = StorageService.getSync(APP_CONFIG.STORAGE_KEYS.AUTH_TOKEN);
    const user = StorageService.getObjectSync<User>(APP_CONFIG.STORAGE_KEYS.USER_DATA);

    if (token && user) {
      if (!get().validateRole(user)) {
        set({
          roleValidation: {
            mismatch: true,
            expected: isStudentApp() ? 'student' : 'staff',
            actual: user.role,
          },
        });
        StorageService.clear();
        return;
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
    // Skip validation for students
    if (user.role === 'student') {
      return { valid: true };
    }

    // Campus Manager is tenant-wide (no hostel_id required)
    if (user.role === 'campus_manager') {
      if (!user.tenant_id) {
        return {
          valid: false,
          error: 'Campus Manager account is not assigned to a tenant. Please contact your administrator.',
        };
      }
      return { valid: true };
    }

    // All other staff roles require hostel assignment
    const hostelScopedRoles = [
      'rector',
      'warden',
      'guard',
      'hk_supervisor',
      'rm_supervisor',
      'laundry_manager',
      'sports_manager',
    ];

    if (hostelScopedRoles.includes(user.role)) {
      if (!user.tenant_id) {
        return {
          valid: false,
          error: 'Your account is not assigned to a tenant. Please contact your administrator.',
        };
      }
      if (!user.hostel_id) {
        return {
          valid: false,
          error: `Your ${user.role.replace('_', ' ')} account is not assigned to a hostel. Please contact your administrator.`,
        };
      }
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
      // Load from cache first
      const cachedFlags = featureFlagsService.loadFromCache();
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

  setSelectedTenant: (tenant: Tenant) => {
    tenantService.setSelectedTenant(tenant);
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
      apiUrl: 'http://192.168.29.90:8000/api/v1'  // Use your actual local IP
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
        apiUrl: data.api_url ?? '',
      };

      tenantService.setSelectedTenant(detectedTenant);
      const normalizedTenant = tenantService.getSelectedTenant() ?? detectedTenant;
      apiService.updateBaseUrl();
      set({ selectedTenant: normalizedTenant });

      return normalizedTenant;
    } catch (error: any) {
      const message = error?.message || 'Failed to auto-detect your institution.';
      console.error('[Auth] Tenant auto-detect failed:', message);
      throw new Error(message);
    }
  },
}));

