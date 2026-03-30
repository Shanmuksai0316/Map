/**
 * Offline Queue Service
 * 
 * Manages offline actions for Guard (gate operations) and Warden (attendance).
 * Queues actions when offline and syncs when network is restored.
 */

import AsyncStorage from '@react-native-async-storage/async-storage';
import NetInfo from '@react-native-community/netinfo';
import { apiService } from './api.service';
import { APP_CONFIG } from '../config/app.config';

const OFFLINE_QUEUE_KEY = '@map/offline_queue';
const MAX_QUEUE_SIZE = 500; // Prevent queue overflow
const SYNC_RETRY_DELAY = 30000; // 30 seconds
const MAX_RETRY_COUNT = 5; // Maximum retries before giving up

export interface OfflineAction {
  id: string; // Client-generated UUID
  action_type: 'gate_out' | 'gate_in' | 'attendance_mark' | 'emergency_exit';
  client_timestamp: string; // ISO 8601
  payload: Record<string, any>;
  retry_count?: number;
  method?: 'POST' | 'PATCH' | 'PUT'; // HTTP method (default: POST)
}

interface SyncResult {
  processed: number;
  succeeded: number;
  failed: number;
  errors: Array<{
    index: number;
    action_type: string;
    error: string;
  }>;
}

class OfflineQueueService {
  private queue: OfflineAction[] = [];
  private isSyncing = false;
  private syncInterval: NodeJS.Timeout | null = null;
  private listeners: Set<(count: number) => void> = new Set();

  constructor() {
    this.loadQueue();
    this.setupNetworkListener();
  }

  /**
   * Load queue from 'AsyncStorage'
   */
  private async loadQueue(): Promise<void> {
    try {
      const stored = await AsyncStorage.getItem(OFFLINE_QUEUE_KEY);
      if (stored) {
        this.queue = JSON.parse(stored);
        this.notifyListeners();
      }
    } catch (error) {
      console.error('Failed to load offline queue:', error);
    }
  }

  /**
   * Save queue to AsyncStorage
   */
  private async saveQueue(): Promise<void> {
    try {
      await AsyncStorage.setItem(OFFLINE_QUEUE_KEY, JSON.stringify(this.queue));
      this.notifyListeners();
    } catch (error) {
      console.error('Failed to save offline queue:', error);
    }
  }

  /**
   * Setup network state listener
   */
  private setupNetworkListener(): void {
    NetInfo.addEventListener(state => {
      if (state.isConnected && this.queue.length > 0) {
        this.sync();
      }
    });

    // Also try syncing periodically if there's a queue
    this.syncInterval = setInterval(() => {
      if (this.queue.length > 0) {
        this.sync();
      }
    }, SYNC_RETRY_DELAY);
  }

  /**
   * Add an action to the offline queue
   */
  async addAction(
    actionType: OfflineAction['action_type'],
    payload: Record<string, any>,
    method: 'POST' | 'PATCH' | 'PUT' = 'POST'
  ): Promise<void> {
    // Check queue size limit
    if (this.queue.length >= MAX_QUEUE_SIZE) {
      throw new Error('Offline queue is full. Please sync your pending actions first.');
    }

    const action: OfflineAction = {
      id: this.generateUUID(),
      action_type: actionType,
      client_timestamp: new Date().toISOString(),
      payload,
      retry_count: 0,
      method,
    };

    this.queue.push(action);
    await this.saveQueue();

    // Try to sync immediately (if online)
    this.sync();
  }

  /**
   * Sync all queued actions with backend
   */
  async sync(): Promise<SyncResult | null> {
    if (this.isSyncing || this.queue.length === 0) {
      return null;
    }

    // Filter out actions that have exceeded max retries
    const validActions = this.queue.filter(action => (action.retry_count || 0) < MAX_RETRY_COUNT);

    if (validActions.length === 0) {
      return null;
    }

    // Check network connectivity
    const netInfo = await NetInfo.fetch();
    if (!netInfo.isConnected) {
      return null;
    }

    this.isSyncing = true;

    try {
      const response = await apiService.post<{ data: SyncResult }>(
        `${APP_CONFIG.ENDPOINTS.OFFLINE_SYNC || '/offline/sync'}`,
        {
          actions: validActions,
        }
      );

      const result = response.data;

      // Remove succeeded actions from 'queue'
      if (result.succeeded > 0) {
        // Keep only failed actions (by matching errors)
        const failedIndices = new Set(result.errors.map(e => e.index));
        this.queue = this.queue.filter((_, index) => failedIndices.has(index));
        await this.saveQueue();
      }

      // Increment retry count for failed actions
      if (result.failed > 0) {
        this.queue.forEach((action, index) => {
          if (result.errors.some(e => e.index === index)) {
            action.retry_count = (action.retry_count || 0) + 1;
          }
        });
        await this.saveQueue();
      }

      this.isSyncing = false;
      return result;

    } catch (error) {
      console.error('Sync failed:', error);
      this.isSyncing = false;
      
      // Increment retry count for all actions
      this.queue.forEach(action => {
        action.retry_count = (action.retry_count || 0) + 1;
      });
      await this.saveQueue();

      throw error;
    }
  }

  /**
   * Clean up actions that have exceeded maximum retries
   */
  async cleanupFailedActions(): Promise<number> {
    const initialCount = this.queue.length;
    this.queue = this.queue.filter(action => (action.retry_count || 0) < MAX_RETRY_COUNT);
    const removedCount = initialCount - this.queue.length;

    if (removedCount > 0) {
      await this.saveQueue();
    }

    return removedCount;
  }

  /**
   * Get current queue
   */
  getQueue(): OfflineAction[] {
    return [...this.queue];
  }

  /**
   * Get queue count
   */
  getCount(): number {
    return this.queue.length;
  }

  /**
   * Clear the queue (use with caution!)
   */
  async clearQueue(): Promise<void> {
    this.queue = [];
    await this.saveQueue();
  }

  /**
   * Subscribe to queue count changes
   */
  subscribe(listener: (count: number) => void): () => void {
    this.listeners.add(listener);
    listener(this.queue.length); // Call immediately with current count
    
    return () => {
      this.listeners.delete(listener);
    };
  }

  /**
   * Notify all listeners of queue count change
   */
  private notifyListeners(): void {
    const count = this.queue.length;
    this.listeners.forEach(listener => listener(count));
  }

  /**
   * Generate UUID for client-side action IDs
   */
  private generateUUID(): string {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
      const r = Math.random() * 16 | 0;
      const v = c === 'x' ? r : (r & 0x3 | 0x8);
      return v.toString(16);
    });
  }

  /**
   * Cleanup (call when app is closing)
   */
  cleanup(): void {
    if (this.syncInterval) {
      clearInterval(this.syncInterval);
      this.syncInterval = null;
    }
    this.listeners.clear();
  }
}

// Singleton instance
export const offlineQueueService = new OfflineQueueService();

