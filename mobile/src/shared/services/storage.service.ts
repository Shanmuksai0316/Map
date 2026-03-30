import AsyncStorage from '@react-native-async-storage/async-storage';

/**
 * Cross-platform storage service using AsyncStorage
 * Compatible with both Android and iOS for cross-platform development
 * 
 * Uses in-memory cache for frequently accessed values (like auth token)
 * to support synchronous access in axios interceptors
 */
export class StorageService {
  // In-memory cache for frequently accessed values
  private static cache: Map<string, string> = new Map();
  private static cacheInitialized = false;

  /**
   * Initialize cache by loading critical values from AsyncStorage
   */
  private static async initializeCache(): Promise<void> {
    if (this.cacheInitialized) return;

    // #region agent log
    fetch('http://127.0.0.1:7242/ingest/26316810-a694-48b7-8f83-116907028f19', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Debug-Session-Id': '0a1483',
      },
      body: JSON.stringify({
        sessionId: '0a1483',
        runId: 'pre-fix',
        hypothesisId: 'H_async_init',
        location: 'storage.service.ts:42',
        message: 'StorageService.initializeCache start',
        data: {},
        timestamp: Date.now(),
      }),
    }).catch(() => {});
    // #endregion
    try {
      // Pre-load critical values into cache for sync access (axios interceptors, tenant routing)
      const keysToPreload = ['auth_token', 'user_data', 'selected_tenant'];
      const pairs = await AsyncStorage.multiGet(keysToPreload);
      for (const [key, value] of pairs) {
        if (value != null) {
          this.cache.set(key, value);
        }
      }
      this.cacheInitialized = true;
    } catch (error) {
      console.error('[StorageService] Error initializing cache:', error);
    }
  }

  /**
   * Store a string value
   */
  static async set(key: string, value: string): Promise<void> {
    try {
      await AsyncStorage.setItem(key, value);
      // Update cache for frequently accessed keys
      if (key === 'auth_token') {
        this.cache.set(key, value);
      }
    } catch (error) {
      console.error('[StorageService] Error setting key:', key, error);
      throw error;
    }
  }

  /**
   * Get a string value (async)
   */
  static async get(key: string): Promise<string | null> {
    try {
      // Check cache first for frequently accessed keys
      if (key === 'auth_token' && this.cache.has(key)) {
        return this.cache.get(key) || null;
      }
      
      const value = await AsyncStorage.getItem(key);
      if (value && key === 'auth_token') {
        this.cache.set(key, value);
      }
      return value;
    } catch (error) {
      console.error('[StorageService] Error getting key:', key, error);
      return null;
    }
  }

  /**
   * Get a string value synchronously (from cache only)
   * Use this for axios interceptors and other sync contexts
   * Returns null if not in cache - use async get() to load from storage
   */
  static getSync(key: string): string | null {
    // For auth_token, ensure cache is initialized
    if (key === 'auth_token' && !this.cacheInitialized) {
      // Trigger async initialization (fire and forget)
      this.initializeCache();
    }
    return this.cache.get(key) || null;
  }

  /**
   * Store an object (serialized as JSON)
   */
  static async setObject<T>(key: string, value: T): Promise<void> {
    try {
      const jsonValue = JSON.stringify(value);
      await AsyncStorage.setItem(key, jsonValue);
      // Update cache for sync reads (any key).
      this.cache.set(key, jsonValue);
    } catch (error) {
      console.error('[StorageService] Error setting object for key:', key, error);
      throw error;
    }
  }

  /**
   * Get an object (deserialized from JSON)
   */
  static async getObject<T>(key: string): Promise<T | null> {
    try {
      const value = await AsyncStorage.getItem(key);
      if (!value) return null;
      return JSON.parse(value) as T;
    } catch (error) {
      console.error('[StorageService] Error getting object for key:', key, error);
      return null;
    }
  }

  /**
   * Get an object synchronously (from cache only)
   */
  static getObjectSync<T>(key: string): T | null {
    const cached = this.cache.get(key);
    if (!cached) return null;
    try {
      return JSON.parse(cached) as T;
    } catch {
      return null;
    }
  }

  /**
   * Delete a key
   */
  static async delete(key: string): Promise<void> {
    try {
      await AsyncStorage.removeItem(key);
      this.cache.delete(key);
    } catch (error) {
      console.error('[StorageService] Error deleting key:', key, error);
    }
  }

  /**
   * Clear all storage
   */
  static async clear(): Promise<void> {
    try {
      await AsyncStorage.clear();
      this.cache.clear();
      this.cacheInitialized = false;
    } catch (error) {
      console.error('[StorageService] Error clearing storage:', error);
    }
  }

  /**
   * Check if a key exists
   */
  static async has(key: string): Promise<boolean> {
    try {
      const value = await AsyncStorage.getItem(key);
      return value !== null;
    } catch (error) {
      console.error('[StorageService] Error checking key:', key, error);
      return false;
    }
  }
}

// Initialize cache on module load (fire and forget)
// This is safe because it's async and won't block module loading
StorageService.initializeCache().catch((error) => {
  console.warn('[StorageService] Cache initialization failed:', error);
});
