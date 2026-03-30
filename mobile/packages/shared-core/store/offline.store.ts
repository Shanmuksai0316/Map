import NetInfo from '@react-native-community/netinfo';
import { create } from 'zustand';
import { apiService } from '../services/api.service';
import { StorageService } from '../services/storage.service';

export type OfflineActionType = 'GATE_EXIT' | 'GATE_ENTRY' | 'ATTENDANCE_MARK' | 'TICKET_CREATE';

export interface QueuedAction {
  id: string;
  type: OfflineActionType;
  endpoint: string;
  method: 'POST' | 'PUT' | 'PATCH';
  data: Record<string, any>;
  timestamp: string;
  retryCount: number;
  status: 'pending' | 'syncing' | 'failed';
  error?: string;
}

interface OfflineState {
  queue: QueuedAction[];
  isOnline: boolean;
  isSyncing: boolean;
  lastSyncedAt: string | null;

  addToQueue: (action: Omit<QueuedAction, 'id' | 'timestamp' | 'retryCount' | 'status'>) => void;
  removeFromQueue: (id: string) => void;
  updateActionStatus: (id: string, status: QueuedAction['status'], error?: string) => void;
  syncQueue: () => Promise<void>;
  clearQueue: () => void;
  loadQueue: () => void;
  setOnlineStatus: (isOnline: boolean) => void;
}

const QUEUE_STORAGE_KEY = 'offline_queue';
const LAST_SYNC_STORAGE_KEY = 'offline_last_sync';
const MAX_RETRIES = 3;

export const useOfflineStore = create<OfflineState>((set, get) => ({
  queue: [],
  isOnline: true,
  isSyncing: false,
  lastSyncedAt: StorageService.get(LAST_SYNC_STORAGE_KEY) ?? null,

  addToQueue: (action) => {
    const queuedAction: QueuedAction = {
      ...action,
      id: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
      timestamp: new Date().toISOString(),
      retryCount: 0,
      status: 'pending',
    };

    const updatedQueue = [...get().queue, queuedAction];
    set({ queue: updatedQueue });
    StorageService.setObject(QUEUE_STORAGE_KEY, updatedQueue);

    if (get().isOnline && !get().isSyncing) {
      void get().syncQueue();
    }
  },

  removeFromQueue: (id) => {
    const updatedQueue = get().queue.filter((action) => action.id !== id);
    set({ queue: updatedQueue });
    StorageService.setObject(QUEUE_STORAGE_KEY, updatedQueue);
  },

  updateActionStatus: (id, status, error) => {
    const updatedQueue = get().queue.map((action) =>
      action.id === id
        ? {
            ...action,
            status,
            retryCount: status === 'failed' ? action.retryCount + 1 : action.retryCount,
            error,
          }
        : action,
    );

    set({ queue: updatedQueue });
    StorageService.setObject(QUEUE_STORAGE_KEY, updatedQueue);
  },

  syncQueue: async () => {
    const { queue, isOnline, isSyncing } = get();

    if (!isOnline || isSyncing || queue.length === 0) {
      return;
    }

    set({ isSyncing: true });

    try {
      for (const action of queue) {
        if (action.retryCount >= MAX_RETRIES) {
          continue;
        }

        if (action.status === 'syncing') {
          continue;
        }

        get().updateActionStatus(action.id, 'syncing');

        try {
          switch (action.method) {
            case 'POST':
              await apiService.post(action.endpoint, action.data);
              break;
            case 'PUT':
              await apiService.put(action.endpoint, action.data);
              break;
            case 'PATCH':
              await apiService.patch?.(action.endpoint, action.data) ?? apiService.put(action.endpoint, action.data);
              break;
            default:
              throw new Error(`Unsupported method: ${action.method}`);
          }

          get().removeFromQueue(action.id);
        } catch (error: any) {
          const errorMessage = error?.response?.data?.message || error?.message || 'Sync failed';
          get().updateActionStatus(action.id, 'failed', errorMessage);
        }
      }

      const remainingQueue = get().queue.filter((action) => action.status !== 'pending' && action.status !== 'syncing');
      if (remainingQueue.length === 0) {
        const timestamp = new Date().toISOString();
        StorageService.set(LAST_SYNC_STORAGE_KEY, timestamp);
        set({ lastSyncedAt: timestamp });
      }
    } finally {
      set({ isSyncing: false });
      StorageService.setObject(QUEUE_STORAGE_KEY, get().queue);
    }
  },

  clearQueue: () => {
    set({ queue: [] });
    StorageService.delete(QUEUE_STORAGE_KEY);
  },

  loadQueue: () => {
    const storedQueue = StorageService.getObject<QueuedAction[]>(QUEUE_STORAGE_KEY) ?? [];
    set({ queue: storedQueue });

    if (storedQueue.length > 0 && get().isOnline) {
      void get().syncQueue();
    }
  },

  setOnlineStatus: (isOnline) => {
    set({ isOnline });

    if (isOnline) {
      void get().syncQueue();
    }
  },
}));

NetInfo.addEventListener((state) => {
  const isConnected = Boolean(state.isConnected);
  useOfflineStore.getState().setOnlineStatus(isConnected);
});

