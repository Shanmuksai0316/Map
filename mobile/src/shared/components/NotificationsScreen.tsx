import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  Modal,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { apiService } from '../services/api.service';
import { APP_CONFIG } from '../config/app.config';
import { colors } from '../theme/colors';
import { theme } from '../theme/theme';
import { format, formatDistanceToNow } from 'date-fns';
import { useNotificationStore } from '../store/notification.store';

interface Notification {
  id: number;
  title: string;
  message: string;
  type: 'gate_alert' | 'expired_pass' | 'visitor_request' | 'leave_approved' | 'leave_rejected' | 'gate_pass_approved' | 'gate_pass_rejected' | 'laundry_ready' | 'booking_confirmed' | 'general';
  created_at: string;
  read: boolean;
}

const HEADER_ROW_HEIGHT = 52;
const HEADER_PADDING_BOTTOM = 6;

export const NotificationsScreen = ({ navigation, route }: any) => {
  const insets = useSafeAreaInsets();
  const headerPaddingTop = Math.max(insets.top, 10);
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [selectedNotification, setSelectedNotification] = useState<Notification | null>(null);
  const [showDetailModal, setShowDetailModal] = useState(false);
  const [autoOpenedFromPush, setAutoOpenedFromPush] = useState(false);
  const { fetchUnreadCount } = useNotificationStore();

  const fetchNotifications = async () => {
    const endpoint = APP_CONFIG.ENDPOINTS.NOTIFICATIONS;
    try {
      const response = await apiService.get<{ data: Notification[] }>(
        `${endpoint}`
      );
      setNotifications(Array.isArray(response.data) ? response.data : []);
    } catch (error) {
      console.error('Notifications fetch error:', error);
      // Mock data
      setNotifications([
        {
          id: 1,
          title: 'Gate Alert',
          message: 'Student John Doe has entered the campus',
          type: 'gate_alert',
          created_at: new Date(Date.now() - 30 * 60 * 1000).toISOString(),
          read: false,
        },
        {
          id: 2,
          title: 'Expired Pass Warning',
          message: 'Gate pass #123 has expired',
          type: 'expired_pass',
          created_at: new Date(Date.now() - 2 * 60 * 60 * 1000).toISOString(),
          read: false,
        },
      ]);
    } finally {
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchNotifications();
    fetchUnreadCount().catch(() => {});
  }, [fetchUnreadCount]);

  // If we were opened via a push tap, auto-open the latest notification detail once.
  useEffect(() => {
    const openLatest = route?.params?.openLatestNotification;
    if (!openLatest || autoOpenedFromPush || notifications.length === 0) {
      return;
    }

    const latest = notifications[0];
    handleNotificationPress(latest);
    setAutoOpenedFromPush(true);

    // Clear the param so future visits don't auto-open again
    if (navigation?.setParams) {
      navigation.setParams({ openLatestNotification: false });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [route?.params, notifications, autoOpenedFromPush]);

  const onRefresh = () => {
    setRefreshing(true);
    fetchNotifications();
  };

  const handleNotificationPress = async (notification: Notification) => {
    setSelectedNotification(notification);
    setShowDetailModal(true);

    if (notification.read) return;

    // Optimistically mark as read in local state
    setNotifications((prev) =>
      prev.map((n) => (n.id === notification.id ? { ...n, read: true } : n))
    );

    try {
      await apiService.post(
        `${APP_CONFIG.ENDPOINTS.NOTIFICATIONS}/${notification.id}/read`
      );
      await fetchUnreadCount();
    } catch (error) {
      console.error('Failed to mark notification as read:', error);
    }
  };

  const getNotificationIcon = (type: string) => {
    switch (type) {
      case 'gate_alert':
        return 'notifications';
      case 'expired_pass':
        return 'warning';
      case 'visitor_request':
        return 'people';
      case 'leave_approved':
        return 'checkmark-circle';
      case 'leave_rejected':
        return 'close-circle';
      case 'gate_pass_approved':
        return 'checkmark-circle';
      case 'gate_pass_rejected':
        return 'close-circle';
      case 'laundry_ready':
        return 'shirt';
      case 'booking_confirmed':
        return 'calendar-outline';
      default:
        return 'notifications-outline';
    }
  };

  const getNotificationColor = (type: string) => {
    switch (type) {
      case 'gate_alert':
        return '#4CAF50';
      case 'expired_pass':
        return '#F44336';
      case 'visitor_request':
        return '#2196F3';
      case 'leave_approved':
      case 'gate_pass_approved':
      case 'laundry_ready':
      case 'booking_confirmed':
        return '#4CAF50';
      case 'leave_rejected':
      case 'gate_pass_rejected':
        return '#F44336';
      default:
        return colors.primary;
    }
  };

  return (
    <View style={styles.container}>
      <View style={[styles.header, { paddingTop: headerPaddingTop, paddingBottom: HEADER_PADDING_BOTTOM, minHeight: headerPaddingTop + HEADER_ROW_HEIGHT + HEADER_PADDING_BOTTOM }]}>
        <View style={[styles.headerRow, { height: HEADER_ROW_HEIGHT }]}>
          <TouchableOpacity
            style={styles.backButton}
            onPress={() => (navigation?.canGoBack?.() ? navigation.goBack() : navigation.navigate('Home'))}
            accessibilityLabel="Go back">
            <Ionicons name="arrow-back" size={24} color={colors.primary} />
          </TouchableOpacity>
          <Text style={styles.headerTitle}>Notifications</Text>
          <View style={styles.headerPlaceholder} />
        </View>
      </View>

      <ScrollView
        style={styles.content}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}>
        {notifications.length === 0 ? (
          <View style={styles.emptyState}>
            <Ionicons name="notifications-off-outline" size={64} color={colors.textMuted} />
            <Text style={styles.emptyTitle}>No Notifications</Text>
            <Text style={styles.emptySubtitle}>
              You'll receive notifications here when your requests are approved, rejected, or need attention.
            </Text>
          </View>
        ) : (
          notifications.map((notification) => (
          <TouchableOpacity
            key={notification.id}
            style={[
              styles.notificationCard,
              !notification.read && styles.notificationCardUnread,
            ]}
            onPress={() => handleNotificationPress(notification)}>
            <View
              style={[
                styles.notificationIcon,
                { backgroundColor: getNotificationColor(notification.type) + '20' },
              ]}>
              <Ionicons
                name={getNotificationIcon(notification.type)}
                size={24}
                color={getNotificationColor(notification.type)}
              />
            </View>
            <View style={styles.notificationContent}>
              <View style={styles.notificationHeader}>
                <Text style={styles.notificationTitle}>{notification.title}</Text>
                {!notification.read && <View style={styles.unreadDot} />}
              </View>
              <Text style={styles.notificationMessage} numberOfLines={2}>
                {notification.message}
              </Text>
              <Text style={styles.notificationTime}>
                {formatDistanceToNow(new Date(notification.created_at), { addSuffix: true })}
              </Text>
            </View>
          </TouchableOpacity>
        ))
        )}
      </ScrollView>

      {/* Detail Modal */}
      <Modal
        visible={showDetailModal}
        animationType="slide"
        transparent={false}
        onRequestClose={() => setShowDetailModal(false)}>
        <View style={styles.modalContainer}>
          <View style={styles.modalHeader}>
          <Text style={styles.modalTitle}>Notification</Text>
            <TouchableOpacity
              onPress={() => setShowDetailModal(false)}
              style={styles.closeButton}>
            <Ionicons name="close" size={24} color={colors.primary} />
            </TouchableOpacity>
          </View>

          <ScrollView
            style={styles.modalContent}
            contentContainerStyle={styles.modalContentContainer}>
            {selectedNotification && (
              <>
                <View
                  style={[
                    styles.modalNotificationIcon,
                    { backgroundColor: getNotificationColor(selectedNotification.type) + '20' },
                  ]}>
                  <Ionicons
                    name={getNotificationIcon(selectedNotification.type)}
                    size={32}
                    color={getNotificationColor(selectedNotification.type)}
                  />
                </View>
                <Text style={styles.modalNotificationTitle}>{selectedNotification.title}</Text>
                <Text style={styles.modalNotificationTime}>
                  {format(new Date(selectedNotification.created_at), 'MMM dd, yyyy HH:mm')}
                </Text>
                <Text style={styles.modalNotificationMessage}>
                  {selectedNotification.message}
                </Text>
              </>
            )}
          </ScrollView>
        </View>
      </Modal>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.white,
  },
  header: {
    backgroundColor: colors.white,
    paddingHorizontal: 20,
    flexDirection: 'row',
    alignItems: 'flex-end',
    ...theme.shadows.medium,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    width: '100%',
  },
  backButton: {
    padding: 8,
    marginLeft: -8,
  },
  headerTitle: {
    color: colors.primary,
    fontSize: 20,
    fontWeight: 'bold',
    flex: 1,
    textAlign: 'center',
  },
  headerPlaceholder: {
    width: 40, // Same width as back button for centering
  },
  content: {
    flex: 1,
    padding: 20,
  },
  infoBanner: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    backgroundColor: `${colors.primary}10`,
    padding: 12,
    borderRadius: 8,
    marginBottom: 16,
    borderLeftWidth: 4,
    borderLeftColor: colors.primary,
    gap: 10,
  },
  infoBannerText: {
    flex: 1,
    fontSize: 13,
    color: colors.textPrimary,
    lineHeight: 18,
  },
  infoBannerLink: {
    color: colors.primary,
    fontWeight: '600',
  },
  emptyState: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 60,
  },
  emptyTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: colors.textPrimary,
    marginTop: 16,
    marginBottom: 8,
  },
  emptySubtitle: {
    fontSize: 14,
    color: colors.textMuted,
    textAlign: 'center',
    lineHeight: 20,
    paddingHorizontal: 40,
  },
  notificationCard: {
    flexDirection: 'row',
    backgroundColor: colors.surface,
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  notificationCardUnread: {
    borderLeftWidth: 4,
    borderLeftColor: colors.primary,
  },
  notificationIcon: {
    width: 48,
    height: 48,
    borderRadius: 24,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 12,
  },
  notificationContent: {
    flex: 1,
  },
  notificationHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 4,
  },
  notificationTitle: {
    flex: 1,
    fontSize: 16,
    fontWeight: '600',
    color: colors.textPrimary,
  },
  unreadDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: colors.primary,
  },
  notificationMessage: {
    fontSize: 14,
    color: colors.textMuted,
    marginBottom: 8,
    lineHeight: 20,
  },
  notificationTime: {
    fontSize: 12,
    color: colors.textMuted,
  },
  modalContainer: {
    flex: 1,
    backgroundColor: colors.background,
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: colors.surface,
    padding: 20,
    paddingTop: 60,
  },
  modalTitle: {
    color: colors.primary,
    fontSize: 20,
    fontWeight: 'bold',
  },
  closeButton: {
    padding: 8,
  },
  modalContent: {
    flex: 1,
    padding: 20,
  },
  modalContentContainer: {
    alignItems: 'center',
  },
  modalNotificationIcon: {
    width: 64,
    height: 64,
    borderRadius: 32,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 20,
  },
  modalNotificationTitle: {
    fontSize: 24,
    fontWeight: 'bold',
    color: colors.textPrimary,
    marginBottom: 8,
    textAlign: 'center',
  },
  modalNotificationTime: {
    fontSize: 14,
    color: colors.textMuted,
    marginBottom: 20,
    textAlign: 'center',
  },
  modalNotificationMessage: {
    fontSize: 16,
    color: colors.textPrimary,
    lineHeight: 24,
    textAlign: 'center',
  },
});
