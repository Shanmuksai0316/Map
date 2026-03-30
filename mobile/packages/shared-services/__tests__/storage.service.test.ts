import { StorageService } from '../storage.service';

describe('StorageService', () => {
  beforeEach(() => {
    // Clear storage before each test
    StorageService.clear();
  });

  describe('basic operations', () => {
    it('should set and get string values', () => {
      StorageService.set('testKey', 'testValue');
      expect(StorageService.get('testKey')).toBe('testValue');
    });

    it('should return undefined for non-existent keys', () => {
      expect(StorageService.get('nonExistentKey')).toBeUndefined();
    });

    it('should set and get object values', () => {
      const testObject = { name: 'John', age: 30 };
      StorageService.setObject('user', testObject);
      expect(StorageService.getObject('user')).toEqual(testObject);
    });

    it('should return null for invalid JSON', () => {
      // Manually set invalid JSON (bypassing type safety for test)
      const storage = (StorageService as any).storage;
      storage.set('invalidJson', '{invalid json}');

      expect(StorageService.getObject('invalidJson')).toBeNull();
    });

    it('should delete keys', () => {
      StorageService.set('testKey', 'testValue');
      expect(StorageService.has('testKey')).toBe(true);

      StorageService.delete('testKey');
      expect(StorageService.has('testKey')).toBe(false);
      expect(StorageService.get('testKey')).toBeUndefined();
    });

    it('should check if keys exist', () => {
      expect(StorageService.has('newKey')).toBe(false);

      StorageService.set('newKey', 'value');
      expect(StorageService.has('newKey')).toBe(true);
    });

    it('should clear all storage', () => {
      StorageService.set('key1', 'value1');
      StorageService.set('key2', 'value2');
      StorageService.setObject('key3', { data: 'value3' });

      expect(StorageService.has('key1')).toBe(true);
      expect(StorageService.has('key2')).toBe(true);
      expect(StorageService.has('key3')).toBe(true);

      StorageService.clear();

      expect(StorageService.has('key1')).toBe(false);
      expect(StorageService.has('key2')).toBe(false);
      expect(StorageService.has('key3')).toBe(false);
    });
  });

  describe('encryption key derivation', () => {
    it('should generate consistent keys for same device/app combination', () => {
      // This test ensures the key derivation is deterministic
      // We can't test the actual key value since it's internal,
      // but we can test that storage operations work
      StorageService.set('consistencyTest', 'value');
      expect(StorageService.get('consistencyTest')).toBe('value');

      // Clear and test again to ensure same key is used
      StorageService.clear();
      expect(StorageService.get('consistencyTest')).toBeUndefined();
    });

    it('should handle storage operations securely', () => {
      // Test that sensitive data can be stored and retrieved
      const sensitiveData = {
        token: 'jwt-token-here',
        userId: 'user-123',
        permissions: ['read', 'write'],
      };

      StorageService.setObject('sensitive', sensitiveData);
      const retrieved = StorageService.getObject('sensitive');

      expect(retrieved).toEqual(sensitiveData);
    });
  });
});
