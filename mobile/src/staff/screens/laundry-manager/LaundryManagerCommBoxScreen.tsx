/**
 * Laundry Manager Notice Board Screen
 * Requirements:
 * - Header: "Notice Board" center aligned, back arrow left aligned
 * - Notification Cards: Show communications with popup detail on click
 * - Close icon on popup header
 */

import React, { useEffect, useCallback, useState } from 'react';
import {
  View,
  Text,
  FlatList,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  Modal,
  Image,
  ScrollView,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useNotificationStore } from '../../../shared/store/notification.store';
import { colors } from '../../../shared/theme/colors';
import type { Notification } from '../../../shared/types';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface Props {
  navigation: any;
}

export const LaundryManagerCommBoxScreen: React.FC<Props> = ({ navigation }) => {
  const insets = useSafeAreaInsets();
  const {
    notifications,
    isLoading,
    fetchCommBox,
    markAsRead,
  } = useNotificationStore();

  const [selectedNotification, setSelectedNotification] = useState<Notification | null>(null);
  const [modalVisible, setModalVisible] = useState(false);

  useEffect(() => {
    fetchCommBox();
  }, [fetchCommBox]);

  const onRefresh = useCallback(() => {
    fetchCommBox();
  }, [fetchCommBox]);

  const handleNotificationPress = async (notification: Notification) => {
    if (!notification.read_at) {
      await markAsRead(notification.id);
    }
    setSelectedNotification(notification);
    setModalVisible(true);
  };

  const closeModal = () => {
    setModalVisible(false);
    setSelectedNotification(null);
  };

  const formatTime = (dateString: string) => {
    const date = new Date(dateString);
    const now = new Date();
    const diff = now.getTime() - date.getTime();
    const hours = Math.floor(diff / (1000 * 60 * 60));
    const days = Math.floor(hours / 24);

    if (hours < 1) {
      const minutes = Math.floor(diff / (1000 * 60));
      return `${minutes}m ago`;
    }
    if (hours < 24) {
      return `${hours}h ago`;
    }
    if (days < 7) {
      return `${days}d ago`;
    }
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  };

  const getNotificationIcon = (type: string): string => {
    switch (type) {
      case 'emergency':
        return 'alert-circle';
      case 'laundry':
        return 'shirt';
      case 'announcement':
        return 'megaphone';
      default:
        return 'notifications';
    }
  };

  const renderNotificationItem = ({ item }: { item: Notification }) => {
    const isUnread = !item.read_at;

    return (
      <TouchableOpacity
        style={[styles.notificationCard, isUnread && styles.notificationUnread]}
        onPress={() => handleNotificationPress(item)}
        activeOpacity={0.7}
      >
        <View style={[styles.iconContainer, { backgroundColor: colors.primary + '15' }]}>
          <Ionicons 
            name={getNotificationIcon(item.type)} 
            size={24} 
            color={colors.primary} 
          />
        </View>

        <View style={styles.notificationContent}>
          <Text style={[styles.notificationTitle, isUnread && styles.titleUnread]} numberOfLines={1}>
            {item.title}
          </Text>
          <Text style={styles.notificationBody} numberOfLines={2}>
            {item.body}
          </Text>
          <Text style={styles.notificationTime}>{formatTime(item.created_at)}</Text>
        </View>

        {isUnread && <View style={styles.unreadDot} />}
      </TouchableOpacity>
    );
  };

  const renderEmptyState = () => (
    <View style={styles.emptyState}>
      <Ionicons name="chatbubble-ellipses-outline" size={64} color={colors.textMuted} />
      <Text style={styles.emptyTitle}>No Communications</Text>
      <Text style={styles.emptySubtitle}>
        Your notice board is empty
      </Text>
    </View>
  );

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Notice Board" />

      {/* Notification List */}
      <FlatList
        data={notifications}
        keyExtractor={(item) => String(item.id)}
        renderItem={renderNotificationItem}
        ListEmptyComponent={!isLoading ? renderEmptyState : null}
        contentContainerStyle={styles.listContent}
        refreshControl={
          <RefreshControl 
            refreshing={isLoading} 
            onRefresh={onRefresh}
            tintColor={colors.primary}
          />
        }
      />

      {/* Detail Modal */}
      <Modal
        visible={modalVisible}
        animationType="slide"
        transparent={true}
        onRequestClose={closeModal}
      >
        <View style={styles.modalOverlay}>
          <View style={[styles.modalContent, { paddingBottom: Math.max(insets.bottom, 20) }]}>
            {/* Modal Header */}
            <View style={styles.modalHeader}>
              <GradientButton style={styles.closeButton} onPress={closeModal}>
                <Ionicons name="close" size={24} color={colors.text} />
              </GradientButton>
            </View>

            {selectedNotification && (
              <ScrollView style={styles.modalBody} showsVerticalScrollIndicator={false}>
                <Text style={styles.modalTitle}>{selectedNotification.title}</Text>
                <Text style={styles.modalDescription}>{selectedNotification.body}</Text>
                
                {/* If there's an image in the notification data */}
                {selectedNotification.data?.image_url && (
                  <View style={styles.imageContainer}>
                    <Image 
                      source={{ uri: selectedNotification.data.image_url }} 
                      style={styles.notificationImage}
                      resizeMode="cover"
                    />
                  </View>
                )}

                <Text style={styles.modalTime}>
                  {formatTime(selectedNotification.created_at)}
                </Text>
              </ScrollView>
            )}
          </View>
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
  listContent: {
    padding: 16,
    flexGrow: 1,
  },
  notificationCard: {
    flexDirection: 'row',
    backgroundColor: colors.surface,
    padding: 16,
    borderRadius: 12,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: colors.border,
  },
  notificationUnread: {
    backgroundColor: colors.primaryLight + '10',
    borderColor: colors.primary + '30',
  },
  iconContainer: {
    width: 48,
    height: 48,
    borderRadius: 12,
    justifyContent: 'center',
    alignItems: 'center',
  },
  notificationContent: {
    flex: 1,
    marginLeft: 12,
  },
  notificationTitle: {
    fontSize: 15,
    fontWeight: '500',
    color: colors.text,
    marginBottom: 4,
  },
  titleUnread: {
    fontWeight: '600',
    color: colors.textHeading,
  },
  notificationBody: {
    fontSize: 14,
    color: colors.textSecondary,
    lineHeight: 20,
    marginBottom: 6,
  },
  notificationTime: {
    fontSize: 12,
    color: colors.textMuted,
  },
  unreadDot: {
    width: 10,
    height: 10,
    borderRadius: 5,
    backgroundColor: colors.primary,
    marginLeft: 8,
    alignSelf: 'center',
  },
  emptyState: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: 80,
  },
  emptyTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: colors.text,
    marginTop: 16,
  },
  emptySubtitle: {
    fontSize: 14,
    color: colors.textSecondary,
    marginTop: 4,
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'flex-end',
  },
  modalContent: {
    backgroundColor: colors.surface,
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    maxHeight: '80%',
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'flex-start',
    alignItems: 'center',
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  closeButton: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: colors.surfaceMuted,
    justifyContent: 'center',
    alignItems: 'center',
  },
  modalBody: {
    padding: 20,
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: colors.textHeading,
    marginBottom: 12,
  },
  modalDescription: {
    fontSize: 16,
    color: colors.text,
    lineHeight: 24,
    marginBottom: 16,
  },
  imageContainer: {
    marginVertical: 16,
    borderRadius: 12,
    overflow: 'hidden',
  },
  notificationImage: {
    width: '100%',
    height: 200,
    borderRadius: 12,
  },
  modalTime: {
    fontSize: 14,
    color: colors.textMuted,
    marginTop: 8,
  },
});

export default LaundryManagerCommBoxScreen;
