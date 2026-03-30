/**
 * useOfflineQueue Hook
 * 
 * React hook for accessing offline queue functionality
 */

import { useState, useEffect } from 'react';
import { offlineQueueService, OfflineAction } from '../services/offline-queue.service';

interface UseOfflineQueueResult {
  queueCount: number;
  queue: OfflineAction[];
  isOnline: boolean;
  isSyncing: boolean;
  addAction: (
    actionType: OfflineAction['action_type'],
    payload: Record<string, any>
  ) => Promise<void>;
  sync: () => Promise<void>;
  clearQueue: () => Promise<void>;
}

export const useOfflineQueue = (): UseOfflineQueueResult => {
  const [queueCount, setQueueCount] = useState(0);
  const [isSyncing, setIsSyncing] = useState(false);
  const [isOnline, setIsOnline] = useState(true);

  useEffect(() => {
    // Subscribe to queue count changes
    const unsubscribe = offlineQueueService.subscribe((count) => {
      setQueueCount(count);
    });

    // Setup network listener
    import('@react-native-community/netinfo').then(({ default: NetInfo }) => {
      const unsubscribeNet = NetInfo.addEventListener(state => {
        setIsOnline(state.isConnected || false);
      });

      return () => {
        unsubscribe();
        unsubscribeNet();
      };
    });

    return unsubscribe;
  }, []);

  const addAction = async (
    actionType: OfflineAction['action_type'],
    payload: Record<string, any>
  ): Promise<void> => {
    await offlineQueueService.addAction(actionType, payload);
  };

  const sync = async (): Promise<void> => {
    setIsSyncing(true);
    try {
      await offlineQueueService.sync();
    } finally {
      setIsSyncing(false);
    }
  };

  const clearQueue = async (): Promise<void> => {
    await offlineQueueService.clearQueue();
  };

  return {
    queueCount,
    queue: offlineQueueService.getQueue(),
    isOnline,
    isSyncing,
    addAction,
    sync,
    clearQueue,
  };
};

