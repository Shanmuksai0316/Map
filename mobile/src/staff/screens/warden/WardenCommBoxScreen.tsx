import React, { useEffect, useCallback, useState } from 'react';
import {
  View,
  Text,
  FlatList,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  Modal,
  Pressable,
  ScrollView,
  Image,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useNotificationStore } from '../../../shared/store/notification.store';
import type { Notification } from '../../../shared/types';
import { colors } from '../../../shared/theme/colors';
import { format } from 'date-fns';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface Props {
  navigation: any;
}

const getNotificationIcon = (type: string): string => {
  const icons: Record<string, string> = {
    emergency: 'alert-circle',
    incident: 'shield',
    approval_needed: 'clipboard',
    outpass_request: 'exit',
    leave_request: 'calendar',
    guest_entry_request: 'people',
    announcement: 'megaphone',
    notice: 'document-text',
    default: 'notifications',
  };
  return icons[type] || icons.default;
};

export const WardenCommBoxScreen: React.FC<Props> = ({ navigation }) => {
  const {
    notifications,
    isLoading,
    fetchCommBox,
    markAsRead,
    markAllAsRead,
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

  const handleCloseModal = () => {
    setModalVisible(false);
    setSelectedNotification(null);
  };

  const formatTime = (dateString: string) => {
    const date = new Date(dateString);
    const now = new Date();
    const diff = now.getTime() - date.getTime();
    const minutes = Math.floor(diff / (1000 * 60));
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);

    if (minutes < 1) return 'just now';
    if (minutes < 60) return `${minutes}m ago`;
    if (hours < 24) return `${hours}h ago`;
    if (days < 7) return `${days}d ago`;
    return format(date, 'MMM dd, yyyy');
  };

  const renderNotificationItem = ({ item }: { item: Notification }) => {
    const iconName = getNotificationIcon(item.type);
    const isUnread = !item.read_at;

    return (
      <TouchableOpacity
        style={[styles.notificationCard, isUnread && styles.notificationUnread]}
        onPress={() => handleNotificationPress(item)}
        activeOpacity={0.7}
      >
        <View style={[styles.iconContainer, isUnread && styles.iconContainerUnread]}>
          <Ionicons 
            name={iconName as any} 
            size={24} 
            color={isUnread ? colors.primary : colors.textSecondary} 
          />
        </View>

        <View style={styles.notificationContent}>
          <View style={styles.notificationHeader}>
            <Text 
              style={[styles.notificationTitle, isUnread && styles.titleUnread]} 
              numberOfLines={1}
            >
              {item.title}
            </Text>
            {item.is_urgent && (
              <View style={styles.urgentBadge}>
                <Text style={styles.urgentText}>URGENT</Text>
              </View>
            )}
          </View>
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
      <Ionicons name="megaphone-outline" size={64} color={colors.textMuted} />
      <Text style={styles.emptyTitle}>No Messages</Text>
      <Text style={styles.emptySubtitle}>
        Your communication box is empty
      </Text>
    </View>
  );

  const postNoticeAction = (
    <TouchableOpacity
      onPress={() => navigation.navigate('PostNotice')}
      style={styles.headerIconButton}
      accessibilityLabel="Post Notice"
    >
      <Ionicons name="add" size={22} color={colors.primary} />
    </TouchableOpacity>
  );

  return (
    <View style={styles.container}>
      <StaffScreenHeader
        onBack={() => navigation.goBack()}
        showBell={false}
        rightSlot={postNoticeAction}  title="Notice Board" />

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

      {/* Notification Detail Modal */}
      <Modal
        animationType="slide"
        transparent={true}
        visible={modalVisible}
        onRequestClose={handleCloseModal}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            {/* Modal Header with Close Button */}
            <View style={styles.modalHeader}>
              <GradientButton onPress={handleCloseModal} style={styles.modalCloseButton}>
                <Ionicons name="close" size={24} color={colors.textSecondary} />
              </GradientButton>
            </View>

            <ScrollView style={styles.modalBody} showsVerticalScrollIndicator={false}>
              {/* Header Section */}
              <Text style={styles.modalTitle}>{selectedNotification?.title}</Text>
              
              {/* Description */}
              <Text style={styles.modalBodyText}>{selectedNotification?.body}</Text>
              
              {/* Image if present */}
              {selectedNotification?.image_url && (
                <Image 
                  source={{ uri: selectedNotification.image_url }} 
                  style={styles.modalImage} 
                  resizeMode="cover"
                />
              )}
              
              {/* Time */}
              <Text style={styles.modalTimeText}>
                {selectedNotification?.created_at 
                  ? format(new Date(selectedNotification.created_at), 'EEEE, MMMM dd, yyyy • hh:mm a') 
                  : ''
                }
              </Text>
            </ScrollView>
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
  headerIconButton: {
    padding: 6,
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
    shadowColor: colors.black,
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05,
    shadowRadius: 2,
    elevation: 1,
  },
  notificationUnread: {
    backgroundColor: colors.surfaceElevated,
    borderColor: colors.primaryLight,
    borderLeftWidth: 4,
    borderLeftColor: colors.primary,
  },
  iconContainer: {
    width: 48,
    height: 48,
    borderRadius: 12,
    backgroundColor: colors.surfaceMuted,
    justifyContent: 'center',
    alignItems: 'center',
  },
  iconContainerUnread: {
    backgroundColor: colors.accentMuted,
  },
  notificationContent: {
    flex: 1,
    marginLeft: 12,
  },
  notificationHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 4,
  },
  notificationTitle: {
    fontSize: 15,
    fontWeight: '500',
    color: colors.text,
    flex: 1,
  },
  titleUnread: {
    fontWeight: '600',
    color: colors.textHeading,
  },
  urgentBadge: {
    backgroundColor: colors.errorLight,
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 8,
    marginLeft: 8,
  },
  urgentText: {
    fontSize: 10,
    fontWeight: '700',
    color: colors.error,
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
    color: colors.textSecondary,
    marginTop: 16,
  },
  emptySubtitle: {
    fontSize: 14,
    color: colors.textMuted,
    marginTop: 4,
  },
  modalOverlay: {
    flex: 1,
    justifyContent: 'flex-end',
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
  },
  modalContent: {
    backgroundColor: colors.surface,
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    maxHeight: '80%',
    overflow: 'hidden',
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'flex-start',
    alignItems: 'center',
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  modalCloseButton: {
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
    marginBottom: 16,
  },
  modalBodyText: {
    fontSize: 16,
    color: colors.text,
    lineHeight: 24,
    marginBottom: 16,
  },
  modalImage: {
    width: '100%',
    height: 200,
    borderRadius: 12,
    marginBottom: 16,
  },
  modalTimeText: {
    fontSize: 13,
    color: colors.textMuted,
    marginTop: 8,
    paddingBottom: 32,
  },
});

export default WardenCommBoxScreen;
