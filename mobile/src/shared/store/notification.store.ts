import { create } from 'zustand';
import { apiService } from '../services/api.service';
import { APP_CONFIG } from '../config/app.config';
import type { Notification } from '../types';

const NOTIFICATIONS_BASE = APP_CONFIG.ENDPOINTS.NOTIFICATIONS; // '/mobile/notifications'

interface NotificationListMeta {
  current_page: number;
  per_page: number;
  total: number;
}

interface NotificationListResponse {
  data: Notification[];
  meta?: NotificationListMeta;
}

interface NotificationState {
  notifications: Notification[];
  unreadCount: number;
  commBoxUnreadCount: number;
  isLoading: boolean;
  error: string | null;
  hasMore: boolean;
  currentPage: number;
  currentFeed: 'notifications' | 'commbox' | null;
  
  // Actions
  fetchUnreadCount: () => Promise<void>;
  fetchCommBoxUnreadCount: () => Promise<void>;
  fetchNotifications: (page?: number) => Promise<void>;
  markAsRead: (notificationId: number) => Promise<void>;
  markAllAsRead: () => Promise<void>;
  fetchCommBox: () => Promise<void>;
  resetState: () => void;
}

export const useNotificationStore = create<NotificationState>((set, get) => ({
  notifications: [],
  unreadCount: 0,
  commBoxUnreadCount: 0,
  isLoading: false,
  error: null,
  hasMore: true,
  currentPage: 1,
  currentFeed: null,

  fetchUnreadCount: async () => {
    try {
      const response = await apiService.get<{ data: { unread_count: number } }>(`${NOTIFICATIONS_BASE}/unread-count`);
      set({ unreadCount: response.data?.unread_count ?? 0 });
    } catch (error: any) {
      if (error?.response?.status === 404) {
        try {
          const fallbackResponse = await apiService.get<NotificationListResponse>(NOTIFICATIONS_BASE, {
            params: { page: 1, per_page: 50 },
          });
          const notices = Array.isArray(fallbackResponse.data) ? fallbackResponse.data : [];
          set({ unreadCount: notices.filter((n) => !n.read_at).length });
          return;
        } catch {
          set({ unreadCount: 0 });
          return;
        }
      }
      console.error('Failed to fetch unread count:', error);
    }
  },

  fetchCommBoxUnreadCount: async () => {
    try {
      const response = await apiService.get<{ data: { unread_count: number } }>(`${NOTIFICATIONS_BASE}/comm-box/unread`);
      const commBoxUnreadCount = response.data?.unread_count ?? 0;
      set({ commBoxUnreadCount });
    } catch (error: any) {
      if (error?.response?.status === 404) {
        set({ commBoxUnreadCount: 0 });
        return;
      }
      console.error('Failed to fetch comm box unread count:', error);
    }
  },

  fetchNotifications: async (page = 1) => {
    const state = get();
    if (state.isLoading) return;

    set({ isLoading: true, error: null });

    try {
      const response = await apiService.get<NotificationListResponse>(NOTIFICATIONS_BASE, {
        params: { page, per_page: 20 },
      });

      const newNotifications = Array.isArray(response.data) ? response.data : [];
      const meta = response.meta ?? { current_page: page, per_page: 20, total: newNotifications.length };

      set({
        notifications:
          page === 1
            ? newNotifications
            : [...state.notifications, ...newNotifications],
        currentPage: meta.current_page,
        hasMore: meta.current_page < Math.ceil(meta.total / meta.per_page),
        currentFeed: 'notifications',
        isLoading: false,
      });
    } catch (error: any) {
      set({
        error: error.message || 'Failed to fetch notifications',
        isLoading: false,
      });
    }
  },

  markAsRead: async (notificationId: number) => {
    try {
      await apiService.post(`${NOTIFICATIONS_BASE}/${notificationId}/read`);

      const state = get();
      const targetNotification = state.notifications.find((n) => n.id === notificationId);
      const wasUnread = Boolean(targetNotification && !targetNotification.read_at);
      set({
        notifications: state.notifications.map((n) =>
          n.id === notificationId ? { ...n, read_at: new Date().toISOString() } : n
        ),
        unreadCount:
          state.currentFeed === 'notifications' && wasUnread
            ? Math.max(0, state.unreadCount - 1)
            : state.unreadCount,
        commBoxUnreadCount:
          state.currentFeed === 'commbox' && wasUnread
            ? Math.max(0, state.commBoxUnreadCount - 1)
            : state.commBoxUnreadCount,
      });
    } catch (error) {
      console.error('Failed to mark notification as read:', error);
    }
  },

  markAllAsRead: async () => {
    try {
      await apiService.post(`${NOTIFICATIONS_BASE}/read-all`);

      const state = get();
      set({
        notifications: state.notifications.map((n) => ({
          ...n,
          read_at: n.read_at || new Date().toISOString(),
        })),
        unreadCount: state.currentFeed === 'notifications' ? 0 : state.unreadCount,
        commBoxUnreadCount: state.currentFeed === 'commbox' ? 0 : state.commBoxUnreadCount,
      });
    } catch (error) {
      console.error('Failed to mark all notifications as read:', error);
    }
  },

  fetchCommBox: async () => {
    set({ isLoading: true, error: null });

    try {
      const response = await apiService.get<NotificationListResponse>(`${NOTIFICATIONS_BASE}/comm-box`, {
        params: { page: 1, per_page: 20 },
      });
      const notices = Array.isArray(response.data) ? response.data : [];
      set({
        notifications: notices,
        currentFeed: 'commbox',
        isLoading: false,
      });
    } catch (error: any) {
      if (error?.response?.status === 404) {
        try {
          const fallbackResponse = await apiService.get<NotificationListResponse>(NOTIFICATIONS_BASE, {
            params: { page: 1, per_page: 20 },
          });
          const notices = Array.isArray(fallbackResponse.data) ? fallbackResponse.data : [];
          set({
            notifications: notices,
            currentFeed: 'commbox',
            isLoading: false,
          });
          return;
        } catch {
          // Continue to default error handling below
        }
      }
      set({
        error: error.message || 'Failed to fetch comm box',
        isLoading: false,
      });
    }
  },

  resetState: () => {
    set({
      notifications: [],
      unreadCount: 0,
      commBoxUnreadCount: 0,
      isLoading: false,
      error: null,
      hasMore: true,
      currentPage: 1,
      currentFeed: null,
    });
  },
}));

export default useNotificationStore;
