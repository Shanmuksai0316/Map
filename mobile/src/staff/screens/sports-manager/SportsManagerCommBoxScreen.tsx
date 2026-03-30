import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  Modal,
  Image,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { apiService } from '../../../shared/services/api.service';
import { theme } from '../../../shared/theme/theme';
import { formatDistanceToNow } from 'date-fns';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface Notification {
  id: number;
  title: string;
  content: string;
  posted_by: string;
  posted_at: string;
  image_url?: string;
  read: boolean;
}

interface Props {
  navigation: any;
}

export const SportsManagerCommBoxScreen: React.FC<Props> = ({ navigation }) => {
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [selectedNotification, setSelectedNotification] = useState<Notification | null>(null);
  const [showDetailModal, setShowDetailModal] = useState(false);

  const fetchNotifications = async () => {
    try {
      const response = await apiService.get('/mobile/notifications');
      setNotifications(response.data.data || []);
    } catch (error) {
      console.error('Failed to fetch notifications:', error);
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

  const handleViewDetails = (notification: Notification) => {
    setSelectedNotification(notification);
    setShowDetailModal(true);
  };

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Notice Board" />

      <ScrollView
        style={styles.scrollView}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
      >
        <View style={styles.content}>
          {notifications.map((notification) => (
            <TouchableOpacity
              key={notification.id}
              style={[
                styles.notificationCard,
                !notification.read && styles.notificationCardUnread,
              ]}
              onPress={() => handleViewDetails(notification)}
            >
              <View style={styles.notificationHeader}>
                <Text style={styles.notificationTitle}>{notification.title}</Text>
                <Text style={styles.notificationTime}>
                  {formatDistanceToNow(new Date(notification.posted_at), { addSuffix: true })}
                </Text>
              </View>
              <Text style={styles.notificationPostedBy}>by {notification.posted_by}</Text>
              {notification.image_url && (
                <Image source={{ uri: notification.image_url }} style={styles.notificationImage} />
              )}
              <Text style={styles.notificationContent} numberOfLines={2}>
                {notification.content}
              </Text>
              <View style={styles.viewDetailsButton}>
                <Text style={styles.viewDetailsButtonText}>View Details</Text>
                <Icon name="chevron-right" size={20} color={theme.colors.primary} />
              </View>
            </TouchableOpacity>
          ))}

          {notifications.length === 0 && (
            <View style={styles.emptyState}>
              <Icon name="bell-outline" size={64} color={theme.colors.textMuted} />
              <Text style={styles.emptyStateText}>No notifications yet</Text>
            </View>
          )}
        </View>
      </ScrollView>

      {/* Detail Modal */}
      <Modal
        visible={showDetailModal}
        animationType="slide"
        transparent={false}
        onRequestClose={() => setShowDetailModal(false)}
      >
        <View style={styles.modalContainer}>
          <View style={styles.modalHeader}>
            <TouchableOpacity
              onPress={() => setShowDetailModal(false)}
              style={styles.closeButton}
            >
              <Icon name="close" size={24} color={theme.colors.white} />
            </TouchableOpacity>
            <View style={styles.modalHeaderContent}>
              <Text style={styles.modalTitle}>{selectedNotification?.title}</Text>
              <Text style={styles.modalPostedBy}>by {selectedNotification?.posted_by}</Text>
              <Text style={styles.modalTime}>
                {selectedNotification && formatDistanceToNow(new Date(selectedNotification.posted_at), { addSuffix: true })}
              </Text>
            </View>
          </View>

          <ScrollView style={styles.modalContent}>
            {selectedNotification?.image_url && (
              <Image
                source={{ uri: selectedNotification.image_url }}
                style={styles.modalImage}
              />
            )}
            <Text style={styles.modalText}>{selectedNotification?.content}</Text>
          </ScrollView>
        </View>
      </Modal>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  scrollView: {
    flex: 1,
  },
  content: {
    padding: 16,
  },
  notificationCard: {
    backgroundColor: theme.colors.white,
    padding: 16,
    borderRadius: 12,
    marginBottom: 12,
    ...theme.shadows.medium,
  },
  notificationCardUnread: {
    borderLeftWidth: 4,
    borderLeftColor: theme.colors.accent,
  },
  notificationHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 4,
  },
  notificationTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.textHeading,
    flex: 1,
    marginRight: 8,
  },
  notificationTime: {
    fontSize: 12,
    color: theme.colors.textSecondary,
  },
  notificationPostedBy: {
    fontSize: 14,
    color: theme.colors.textSecondary,
    marginBottom: 12,
  },
  notificationImage: {
    width: '100%',
    height: 150,
    borderRadius: 8,
    marginBottom: 12,
  },
  notificationContent: {
    fontSize: 14,
    color: theme.colors.text,
    marginBottom: 12,
    lineHeight: 20,
  },
  viewDetailsButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'flex-end',
  },
  viewDetailsButtonText: {
    fontSize: 14,
    fontWeight: '600',
    color: theme.colors.primary,
    marginRight: 4,
  },
  emptyState: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 60,
  },
  emptyStateText: {
    fontSize: 16,
    color: theme.colors.textMuted,
    marginTop: 16,
  },
  modalContainer: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  modalHeader: {
    backgroundColor: theme.colors.primary,
    paddingTop: 60,
    paddingBottom: 20,
    paddingHorizontal: 20,
  },
  closeButton: {
    alignSelf: 'flex-start',
    marginBottom: 12,
  },
  modalHeaderContent: {
    marginTop: 8,
  },
  modalTitle: {
    fontSize: 22,
    fontWeight: '700',
    color: theme.colors.white,
    marginBottom: 8,
  },
  modalPostedBy: {
    fontSize: 14,
    color: theme.colors.white,
    opacity: 0.9,
    marginBottom: 4,
  },
  modalTime: {
    fontSize: 12,
    color: theme.colors.white,
    opacity: 0.8,
  },
  modalContent: {
    flex: 1,
    padding: 20,
  },
  modalImage: {
    width: '100%',
    height: 250,
    borderRadius: 12,
    marginBottom: 20,
  },
  modalText: {
    fontSize: 16,
    color: theme.colors.text,
    lineHeight: 24,
  },
});

export default SportsManagerCommBoxScreen;
