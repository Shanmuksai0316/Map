import AsyncStorage from '@react-native-async-storage/async-storage';

/**
 * StorageService - Unified storage interface using AsyncStorage
 * Replaced MMKV with AsyncStorage due to JSI initialization issues
 */

export class StorageService {
  /**
   * Set a string value
   */
  static async set(key: string, value: string): Promise<void> {
    try {
      await AsyncStorage.setItem(key, value);
    } catch (error) {
      console.error('[StorageService] Failed to set item:', key, error);
      throw error;
    }
  }

  /**
   * Get a string value
   */
  static async get(key: string): Promise<string | null> {
    try {
      return await AsyncStorage.getItem(key);
    } catch (error) {
      console.error('[StorageService] Failed to get item:', key, error);
      return null;
    }
  }

  /**
   * Set an object (serialized as JSON)
   */
  static async setObject<T>(key: string, value: T): Promise<void> {
    try {
      const jsonValue = JSON.stringify(value);
      await AsyncStorage.setItem(key, jsonValue);
    } catch (error) {
      console.error('[StorageService] Failed to set object:', key, error);
      throw error;
    }
  }

  /**
   * Get an object (parsed from JSON)
   */
  static async getObject<T>(key: string): Promise<T | null> {
    try {
      const jsonValue = await AsyncStorage.getItem(key);
      if (jsonValue === null) {
        return null;
      }
      return JSON.parse(jsonValue) as T;
    } catch (error) {
      console.error('[StorageService] Failed to get object:', key, error);
      return null;
    }
  }

  /**
   * Delete a key
   */
  static async delete(key: string): Promise<void> {
    try {
      await AsyncStorage.removeItem(key);
    } catch (error) {
      console.error('[StorageService] Failed to delete item:', key, error);
    }
  }

  /**
   * Clear all storage
   */
  static async clear(): Promise<void> {
    try {
      await AsyncStorage.clear();
    } catch (error) {
      console.error('[StorageService] Failed to clear storage:', error);
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
      console.error('[StorageService] Failed to check key:', key, error);
      return false;
    }
  }
}
