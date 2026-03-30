import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  FlatList,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  Image,
  Modal,
  ScrollView,
  Alert,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { useNotificationStore } from '../../../shared/store/notification.store';
import { theme } from '../../../shared/theme/theme';
import type { Notification } from '../../../shared/types';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface Notice extends Notification {
  posted_by?: string;
  image_url?: string;
  hostel_names?: string[];
}

interface Props {
  navigation: any;
  route?: { params?: { hideFilter?: boolean } };
}

type FilterType = 'all' | 'unread';

export const CommBoxScreen: React.FC<Props> = ({ navigation, route }) => {
  const hideFilter = route?.params?.hideFilter === true;
  const {
    notifications,
    isLoading,
    fetchCommBox,
    markAsRead,
    markAllAsRead,
  } = useNotificationStore();

  const [filter, setFilter] = useState<FilterType>('all');
  const [selectedNotice, setSelectedNotice] = useState<Notice | null>(null);
  const [showDetailModal, setShowDetailModal] = useState(false);

  useEffect(() => {
    fetchCommBox();
  }, [fetchCommBox]);

  const onRefresh = useCallback(() => {
    fetchCommBox();
  }, [fetchCommBox]);

  const handleNotificationPress = async (notification: Notice) => {
    if (!notification.read_at) {
      await markAsRead(notification.id);
    }
    setSelectedNotice(notification);
    setShowDetailModal(true);
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

  const formatFullDateTime = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
      weekday: 'long',
      month: 'long',
      day: 'numeric',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const getFilteredNotifications = () => {
    switch (filter) {
      case 'unread':
        return notifications.filter((n) => !n.read_at);
      default:
        return notifications;
    }
  };

  const filteredNotifications = getFilteredNotifications();
  const unreadCount = notifications.filter((n) => !n.read_at).length;

  const renderNotificationItem = ({ item }: { item: Notice }) => {
    const isUnread = !item.read_at;

    return (
      <TouchableOpacity
        style={[styles.noticeCard, isUnread && styles.noticeUnread]}
        onPress={() => handleNotificationPress(item)}
        activeOpacity={0.7}
      >
        {/* Header: Title and Date */}
        <View style={styles.noticeHeader}>
          <View style={styles.noticeTitleRow}>
            {isUnread && <View style={styles.unreadDot} />}
            <Text style={[styles.noticeTitle, isUnread && styles.titleUnread]} numberOfLines={1}>
              {item.title}
            </Text>
            {item.is_urgent && (
              <View style={styles.urgentBadge}>
                <Text style={styles.urgentText}>URGENT</Text>
              </View>
            )}
          </View>
          <Text style={styles.noticeDate}>{formatTime(item.created_at)}</Text>
        </View>

        {/* Posted By */}
        <Text style={styles.postedBy}>
          Posted by: {item.posted_by || 'Admin'}
        </Text>

        {/* Image if available */}
        {item.image_url && (
          <Image source={{ uri: item.image_url }} style={styles.noticeImage} />
        )}

        {/* Body Preview */}
        <Text style={styles.noticeBody} numberOfLines={3}>
          {item.body}
        </Text>

        {/* View Details Button */}
        <TouchableOpacity
          style={styles.viewDetailsButton}
          onPress={() => handleNotificationPress(item)}
        >
          <Text style={styles.viewDetailsText}>View Details</Text>
          <Icon name="chevron-right" size={16} color={theme.colors.primary} />
        </TouchableOpacity>
      </TouchableOpacity>
    );
  };

  const renderEmptyState = () => (
    <View style={styles.emptyState}>
      <Icon name="message-text-outline" size={64} color={theme.colors.border} />
      <Text style={styles.emptyTitle}>No Messages</Text>
      <Text style={styles.emptySubtitle}>
        {filter === 'unread'
          ? 'All messages have been read'
          : 'Your communication box is empty'}
      </Text>
    </View>
  );

  const handlePostNoticePress = () => {
    // Walk up nested navigators so this tap never fails silently on role-specific stacks.
    let currentNav: any = navigation;
    while (currentNav) {
      const routeNames: string[] = currentNav?.getState?.()?.routeNames || [];
      if (routeNames.includes('PostNotice') && typeof currentNav.navigate === 'function') {
        currentNav.navigate('PostNotice');
        return;
      }
      currentNav = currentNav?.getParent?.();
    }

    Alert.alert('Navigation unavailable', 'Unable to open Post Notice. Please reopen the app and try again.');
  };

  const postNoticeAction = (
    <GradientButton
      style={styles.headerIconButton}
      onPress={handlePostNoticePress}
      accessibilityLabel="Post Notice"
    >
      <Icon name="plus" size={22} color={theme.colors.primary} />
    </GradientButton>
  );

  return (
    <View style={styles.container}>
      <StaffScreenHeader
        onBack={() => navigation.goBack()}
        showBell={false}
        rightSlot={postNoticeAction}  title="Notice Board" />

      {/* Filters – hidden when hideFilter (e.g. Guard) */}
      {!hideFilter && (
        <View style={styles.filterContainer}>
          <ScrollView horizontal showsHorizontalScrollIndicator={false}>
            <TouchableOpacity
              style={[styles.filterPill, filter === 'all' && styles.filterActive]}
              onPress={() => setFilter('all')}
            >
              <Text style={[styles.filterText, filter === 'all' && styles.filterTextActive]}>
                All
              </Text>
            </TouchableOpacity>
            <TouchableOpacity
              style={[styles.filterPill, filter === 'unread' && styles.filterActive]}
              onPress={() => setFilter('unread')}
            >
              <Text style={[styles.filterText, filter === 'unread' && styles.filterTextActive]}>
                Unread
              </Text>
            </TouchableOpacity>
          </ScrollView>

          {unreadCount > 0 && (
            <GradientButton style={styles.markAllButton} onPress={markAllAsRead}>
              <Icon name="check-all" size={18} color={theme.colors.primary} />
              <Text style={styles.markAllText}>Mark all read</Text>
            </GradientButton>
          )}
        </View>
      )}

      {/* Notification List */}
      <FlatList
        data={filteredNotifications}
        keyExtractor={(item) => String(item.id)}
        renderItem={renderNotificationItem}
        ListEmptyComponent={!isLoading ? renderEmptyState : null}
        contentContainerStyle={styles.listContent}
        refreshControl={
          <RefreshControl refreshing={isLoading} onRefresh={onRefresh} />
        }
      />

      {/* Detail Modal */}
      <Modal
        visible={showDetailModal}
        animationType="slide"
        transparent
        onRequestClose={() => setShowDetailModal(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <View style={styles.modalHeader}>
              <View style={styles.modalTitleContainer}>
                <Text style={styles.modalTitle}>{selectedNotice?.title}</Text>
                {selectedNotice?.is_urgent && (
                  <View style={styles.modalUrgentBadge}>
                    <Text style={styles.modalUrgentText}>URGENT</Text>
                  </View>
                )}
              </View>
              <TouchableOpacity onPress={() => setShowDetailModal(false)}>
                <Icon name="close" size={24} color={theme.colors.text} />
              </TouchableOpacity>
            </View>

            <ScrollView style={styles.modalBody} showsVerticalScrollIndicator={false}>
              {/* Posted By and Date */}
              <View style={styles.modalMeta}>
                <Text style={styles.modalPostedBy}>
                  Posted by: {selectedNotice?.posted_by || 'Admin'}
                </Text>
                <Text style={styles.modalDate}>
                  {selectedNotice ? formatFullDateTime(selectedNotice.created_at) : ''}
                </Text>
              </View>

              {/* Image if available */}
              {selectedNotice?.image_url && (
                <Image
                  source={{ uri: selectedNotice.image_url }}
                  style={styles.modalImage}
                  resizeMode="cover"
                />
              )}

              {/* Full Content */}
              <Text style={styles.modalBodyText}>{selectedNotice?.body}</Text>

              {/* Target Hostels */}
              {selectedNotice?.hostel_names && selectedNotice.hostel_names.length > 0 && (
                <View style={styles.hostelsContainer}>
                  <Text style={styles.hostelsLabel}>Targeted Hostels:</Text>
                  <View style={styles.hostelTags}>
                    {selectedNotice.hostel_names.map((hostel, index) => (
                      <View key={index} style={styles.hostelTag}>
                        <Text style={styles.hostelTagText}>{hostel}</Text>
                      </View>
                    ))}
                  </View>
                </View>
              )}
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
    backgroundColor: theme.colors.background,
  },
  headerIconButton: {
    padding: 6,
  },
  filterContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: theme.colors.card,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
    gap: 8,
  },
  filterPill: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: theme.colors.surfaceMuted,
    marginRight: 8,
  },
  filterActive: {
    backgroundColor: theme.colors.primary,
  },
  filterText: {
    fontSize: 14,
    fontWeight: '500',
    color: theme.colors.textSecondary,
  },
  filterTextActive: {
    color: theme.colors.white,
  },
  markAllButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    marginLeft: 'auto',
  },
  markAllText: {
    fontSize: 13,
    fontWeight: '500',
    color: theme.colors.primary,
  },
  listContent: {
    padding: 16,
    flexGrow: 1,
  },
  noticeCard: {
    backgroundColor: theme.colors.card,
    padding: 16,
    borderRadius: 12,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  noticeUnread: {
    backgroundColor: `${theme.colors.primary}08`,
    borderColor: `${theme.colors.primary}30`,
  },
  noticeHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 8,
  },
  noticeTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
    marginRight: 8,
  },
  unreadDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: theme.colors.primary,
    marginRight: 8,
  },
  noticeTitle: {
    fontSize: 16,
    fontWeight: '500',
    color: theme.colors.text,
    flex: 1,
  },
  titleUnread: {
    fontWeight: '600',
  },
  urgentBadge: {
    backgroundColor: theme.colors.errorLight,
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 8,
    marginLeft: 8,
  },
  urgentText: {
    fontSize: 10,
    fontWeight: '700',
    color: theme.colors.error,
  },
  noticeDate: {
    fontSize: 12,
    color: theme.colors.textMuted,
  },
  postedBy: {
    fontSize: 13,
    color: theme.colors.textSecondary,
    marginBottom: 12,
  },
  noticeImage: {
    width: '100%',
    height: 160,
    borderRadius: 8,
    marginBottom: 12,
    backgroundColor: theme.colors.surfaceMuted,
  },
  noticeBody: {
    fontSize: 14,
    color: theme.colors.text,
    lineHeight: 20,
    marginBottom: 12,
  },
  viewDetailsButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: theme.colors.surfaceMuted,
    paddingVertical: 10,
    borderRadius: 8,
  },
  viewDetailsText: {
    fontSize: 14,
    fontWeight: '600',
    color: theme.colors.primary,
  },
  emptyState: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: 48,
  },
  emptyTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: theme.colors.textSecondary,
    marginTop: 16,
  },
  emptySubtitle: {
    fontSize: 14,
    color: theme.colors.textMuted,
    marginTop: 4,
    textAlign: 'center',
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'flex-end',
  },
  modalContent: {
    backgroundColor: theme.colors.background,
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    maxHeight: '85%',
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    padding: 20,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
  },
  modalTitleContainer: {
    flex: 1,
    marginRight: 16,
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: theme.colors.text,
  },
  modalUrgentBadge: {
    backgroundColor: theme.colors.errorLight,
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 8,
    marginTop: 8,
    alignSelf: 'flex-start',
  },
  modalUrgentText: {
    fontSize: 11,
    fontWeight: '700',
    color: theme.colors.error,
  },
  modalBody: {
    padding: 20,
  },
  modalMeta: {
    marginBottom: 16,
    paddingBottom: 16,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
  },
  modalPostedBy: {
    fontSize: 14,
    fontWeight: '500',
    color: theme.colors.text,
    marginBottom: 4,
  },
  modalDate: {
    fontSize: 13,
    color: theme.colors.textSecondary,
  },
  modalImage: {
    width: '100%',
    height: 200,
    borderRadius: 12,
    marginBottom: 16,
    backgroundColor: theme.colors.surfaceMuted,
  },
  modalBodyText: {
    fontSize: 15,
    color: theme.colors.text,
    lineHeight: 24,
    marginBottom: 20,
  },
  hostelsContainer: {
    backgroundColor: theme.colors.surfaceMuted,
    padding: 16,
    borderRadius: 12,
  },
  hostelsLabel: {
    fontSize: 13,
    fontWeight: '600',
    color: theme.colors.textSecondary,
    marginBottom: 8,
  },
  hostelTags: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  hostelTag: {
    backgroundColor: theme.colors.primary,
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 16,
  },
  hostelTagText: {
    fontSize: 12,
    fontWeight: '500',
    color: theme.colors.white,
  },
});

export default CommBoxScreen;
