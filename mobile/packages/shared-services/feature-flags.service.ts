import { apiService } from './api.service';
import { StorageService } from './storage.service';

export interface TenantFeatureFlags {
  sports_module_enabled: boolean;
  laundry_module_enabled: boolean;
  security_module_enabled: boolean;
  visitor_management_enabled: boolean;
  medical_module_enabled: boolean;
  emergency_contacts_enabled: boolean;
  sms_notifications_enabled: boolean;
  email_notifications_enabled: boolean;
  push_notifications_enabled: boolean;
  payment_integration_enabled: boolean;
  qr_gate_pass_enabled: boolean;
  biometric_attendance_enabled: boolean;
  hostel_transfer_enabled: boolean;
  bulk_import_enabled: boolean;
}

const DEFAULT_FLAGS: TenantFeatureFlags = {
  sports_module_enabled: false,
  laundry_module_enabled: false,
  security_module_enabled: true,
  visitor_management_enabled: false,
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
};

const STORAGE_KEY = 'feature_flags';
const CACHE_EXPIRY_MS = 15 * 60 * 1000; // 15 minutes

class FeatureFlagsService {
  private flags: TenantFeatureFlags = DEFAULT_FLAGS;
  private lastFetchTime: number = 0;
  private isFetching: boolean = false;

  /**
   * Fetch feature flags from API
   */
  async fetchFlags(): Promise<TenantFeatureFlags> {
    // Check if already fetching
    if (this.isFetching) {
      return this.flags;
    }

    // Check cache validity
    const now = Date.now();
    if (now - this.lastFetchTime < CACHE_EXPIRY_MS) {
      return this.flags;
    }

    this.isFetching = true;

    try {
      const response = await apiService.get<{ data: TenantFeatureFlags }>(
        '/mobile/tenant/feature-flags'
      );

      if (response.data) {
        this.flags = { ...DEFAULT_FLAGS, ...response.data };
        this.lastFetchTime = now;

        // Cache to storage
        StorageService.setObject(STORAGE_KEY, {
          flags: this.flags,
          timestamp: now,
        });
      }

      return this.flags;
    } catch (error) {
      console.error('Failed to fetch feature flags:', error);
      
      // Try to load from cache
      const cached = StorageService.getObject<{
        flags: TenantFeatureFlags;
        timestamp: number;
      }>(STORAGE_KEY);

      if (cached && cached.flags) {
        this.flags = cached.flags;
        this.lastFetchTime = cached.timestamp;
      }

      return this.flags;
    } finally {
      this.isFetching = false;
    }
  }

  /**
   * Get current flags (may be cached)
   */
  getFlags(): TenantFeatureFlags {
    return this.flags;
  }

  /**
   * Check if a specific feature is enabled
   */
  isEnabled(feature: keyof TenantFeatureFlags): boolean {
    return this.flags[feature] || false;
  }

  /**
   * Load flags from cache on app start
   */
  loadFromCache(): TenantFeatureFlags {
    const cached = StorageService.getObject<{
      flags: TenantFeatureFlags;
      timestamp: number;
    }>(STORAGE_KEY);

    if (cached && cached.flags) {
      this.flags = cached.flags;
      this.lastFetchTime = cached.timestamp;
    }

    return this.flags;
  }

  /**
   * Clear cached flags
   */
  clearCache(): void {
    this.flags = DEFAULT_FLAGS;
    this.lastFetchTime = 0;
    StorageService.delete(STORAGE_KEY);
  }

  /**
   * Force refresh flags (bypass cache)
   */
  async refresh(): Promise<TenantFeatureFlags> {
    this.lastFetchTime = 0;
    return this.fetchFlags();
  }
}

export const featureFlagsService = new FeatureFlagsService();

