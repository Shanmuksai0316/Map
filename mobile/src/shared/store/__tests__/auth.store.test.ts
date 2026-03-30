import { useAuthStore } from '../auth.store';
import { User } from '../../types';

// Mock dependencies
jest.mock('../../services/storage.service');
jest.mock('../../services/api.service');
jest.mock('../../services/tenant.service');
jest.mock('../../services/feature-flags.service');
jest.mock('../../config/app.config');

describe('AuthStore - Role Validation', () => {
  let store: ReturnType<typeof useAuthStore>;

  beforeEach(() => {
    // Reset store state
    store = useAuthStore.getState();
    useAuthStore.setState({
      user: null,
      token: null,
      isAuthenticated: false,
      isLoading: false,
      error: null,
      selectedTenant: null,
      roleValidation: {
        mismatch: false,
        expected: 'student',
        actual: null,
      },
      featureFlags: null,
    });
  });

  describe('validateRole', () => {
    it('should validate student role correctly', () => {
      const studentUser: User = {
        id: '1',
        name: 'John Doe',
        phone: '+1234567890',
        email: 'john@example.com',
        role: 'student',
        tenant_id: 'tenant-1',
      };

      // Mock student app
      jest.doMock('../../config/app.config', () => ({
        isStudentApp: () => true,
      }));

      const result = store.validateRole(studentUser);
      expect(result).toBe(true);
    });

    it('should validate staff role correctly', () => {
      const staffUser: User = {
        id: '1',
        name: 'Jane Manager',
        phone: '+1234567890',
        email: 'jane@example.com',
        role: 'campus_manager',
        tenant_id: 'tenant-1',
      };

      // Mock staff app
      jest.doMock('../../config/app.config', () => ({
        isStudentApp: () => false,
      }));

      const result = store.validateRole(staffUser);
      expect(result).toBe(true);
    });

    it('should reject mismatched roles', () => {
      const studentUser: User = {
        id: '1',
        name: 'John Doe',
        phone: '+1234567890',
        email: 'john@example.com',
        role: 'student',
        tenant_id: 'tenant-1',
      };

      // Mock staff app expecting staff roles
      jest.doMock('../../config/app.config', () => ({
        isStudentApp: () => false,
      }));

      const result = store.validateRole(studentUser);
      expect(result).toBe(false);
    });
  });

  describe('validateRoleScoping', () => {
    it('should validate campus manager tenant-wide access', () => {
      const campusManager: User = {
        id: '1',
        name: 'Campus Manager',
        phone: '+1234567890',
        email: 'manager@example.com',
        role: 'campus_manager',
        tenant_id: 'tenant-1',
      };

      const result = store.validateRoleScoping(campusManager);
      expect(result.valid).toBe(true);
    });

    it('should validate rector tenant-wide access', () => {
      const rector: User = {
        id: '1',
        name: 'Rector',
        phone: '+1234567890',
        email: 'rector@example.com',
        role: 'rector',
        tenant_id: 'tenant-1',
      };

      const result = store.validateRoleScoping(rector);
      expect(result.valid).toBe(true);
    });

    it('should validate warden hostel-scoped access', () => {
      const warden: User = {
        id: '1',
        name: 'Warden',
        phone: '+1234567890',
        email: 'warden@example.com',
        role: 'warden',
        tenant_id: 'tenant-1',
        staff_assignment: {
          hostel_id: 'hostel-1',
          hostel_name: 'Hostel A',
          assigned_at: '2024-01-01T00:00:00Z',
          assignment_status: 'active',
        },
      };

      const result = store.validateRoleScoping(warden);
      expect(result.valid).toBe(true);
    });

    it('should reject warden without hostel assignment', () => {
      const wardenWithoutAssignment: User = {
        id: '1',
        name: 'Warden',
        phone: '+1234567890',
        email: 'warden@example.com',
        role: 'warden',
        tenant_id: 'tenant-1',
        // No staff_assignment
      };

      const result = store.validateRoleScoping(wardenWithoutAssignment);
      expect(result.valid).toBe(false);
      expect(result.error).toContain('hostel assignment');
    });

    it('should validate supervisor roles with hostel assignment', () => {
      const supervisor: User = {
        id: '1',
        name: 'Housekeeping Supervisor',
        phone: '+1234567890',
        email: 'hk@example.com',
        role: 'hk_supervisor',
        tenant_id: 'tenant-1',
        staff_assignment: {
          hostel_id: 'hostel-1',
          hostel_name: 'Hostel A',
          assigned_at: '2024-01-01T00:00:00Z',
          assignment_status: 'active',
        },
      };

      const result = store.validateRoleScoping(supervisor);
      expect(result.valid).toBe(true);
    });

    it('should handle unknown roles gracefully', () => {
      const unknownUser: User = {
        id: '1',
        name: 'Unknown',
        phone: '+1234567890',
        email: 'unknown@example.com',
        role: 'unknown_role' as any,
        tenant_id: 'tenant-1',
      };

      const result = store.validateRoleScoping(unknownUser);
      expect(result.valid).toBe(false);
      expect(result.error).toContain('Unknown role');
    });
  });

  describe('TEST_AUTH_OVERRIDES', () => {
    it('should be empty in production', () => {
      // This test ensures TEST_AUTH_OVERRIDES is properly gated
      // In production (__DEV__ = false), it should be an empty object
      const originalDev = __DEV__;
      const originalEnv = process.env.EXPO_PUBLIC_ENABLE_TEST_AUTH;

      // Simulate production environment
      (global as any).__DEV__ = false;
      delete process.env.EXPO_PUBLIC_ENABLE_TEST_AUTH;

      // Re-import to test the conditional
      jest.resetModules();
      const authStore = require('../auth.store');

      // TEST_AUTH_OVERRIDES should be empty
      expect(authStore.TEST_AUTH_OVERRIDES).toEqual({});

      // Restore
      (global as any).__DEV__ = originalDev;
      process.env.EXPO_PUBLIC_ENABLE_TEST_AUTH = originalEnv;
    });

    it('should be populated in development with flag', () => {
      const originalDev = __DEV__;
      const originalEnv = process.env.EXPO_PUBLIC_ENABLE_TEST_AUTH;

      // Simulate development with flag
      (global as any).__DEV__ = true;
      process.env.EXPO_PUBLIC_ENABLE_TEST_AUTH = 'true';

      jest.resetModules();
      const authStore = require('../auth.store');

      // Should have test credentials
      expect(Object.keys(authStore.TEST_AUTH_OVERRIDES)).toContain('8888888888');

      // Restore
      (global as any).__DEV__ = originalDev;
      process.env.EXPO_PUBLIC_ENABLE_TEST_AUTH = originalEnv;
    });
  });
});
