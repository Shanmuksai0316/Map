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
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useAuthStore } from '../../store/auth.store';
import { apiService } from '../../services/api.service';
import { APP_CONFIG } from '../../config/app.config';
import { colors } from '../../theme/colors';
import { format, formatDistanceToNow } from 'date-fns';

interface Notification {
  id: number;
  title: string;
  message: string;
  type: 'gate_alert' | 'expired_pass' | 'visitor_request' | 'general';
  created_at: string;
  read: boolean;
}

export const NotificationsScreen = ({ navigation }: any) => {
  const { user } = useAuthStore();
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [selectedNotification, setSelectedNotification] = useState<Notification | null>(null);
  const [showDetailModal, setShowDetailModal] = useState(false);

  const fetchNotifications = async () => {
    try {
      const response = await apiService.get<{ data: Notification[] }>(
        `${APP_CONFIG.ENDPOINTS.NOTIFICATIONS}`
      );
      setNotifications(response.data);
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
  }, []);

  const onRefresh = () => {
    setRefreshing(true);
    fetchNotifications();
  };

  const getNotificationIcon = (type: string) => {
    switch (type) {
      case 'gate_alert':
        return 'notifications';
      case 'expired_pass':
        return 'warning';
      case 'visitor_request':
        return 'people';
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
      default:
        return colors.primary;
    }
  };

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.headerTitle}>Notifications</Text>
      </View>

      <ScrollView
        style={styles.content}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}>
        {notifications.map((notification) => (
          <TouchableOpacity
            key={notification.id}
            style={[
              styles.notificationCard,
              !notification.read && styles.notificationCardUnread,
            ]}
            onPress={() => {
              setSelectedNotification(notification);
              setShowDetailModal(true);
            }}>
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
        ))}
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
              <Ionicons name="close" size={24} color={colors.surface} />
            </TouchableOpacity>
          </View>

          <ScrollView style={styles.modalContent}>
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
    backgroundColor: colors.background,
  },
  header: {
    backgroundColor: colors.surface,
    padding: 20,
    paddingTop: 60,
  },
  headerTitle: {
    color: colors.primary,
    fontSize: 24,
    fontWeight: 'bold',
  },
  content: {
    flex: 1,
    padding: 20,
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
    backgroundColor: colors.primary,
    padding: 20,
    paddingTop: 60,
  },
  modalTitle: {
    color: colors.surface,
    fontSize: 20,
    fontWeight: 'bold',
  },
  closeButton: {
    padding: 8,
  },
  modalContent: {
    flex: 1,
    padding: 20,
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

