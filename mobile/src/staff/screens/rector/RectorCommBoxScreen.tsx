import React, { useEffect, useCallback } from 'react';
import {
  View,
  Text,
  FlatList,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { useNotificationStore } from '../../../shared/store/notification.store';
import { theme } from '../../../shared/theme/theme';
import type { Notification } from '../../../shared/types';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface Props {
  navigation: any;
}

const getNotificationIcon = (type: string): { name: string; color: string } => {
  const icons: Record<string, { name: string; color: string }> = {
    outpass_request: { name: 'exit-run', color: theme.colors.success },
    leave_request: { name: 'calendar-remove', color: theme.colors.primaryLight },
    guest_entry_request: { name: 'account-multiple', color: theme.colors.info },
    approval_needed: { name: 'clipboard-check', color: theme.colors.warning },
    default: { name: 'bell', color: theme.colors.textSecondary },
  };
  return icons[type] || icons.default;
};

export const RectorCommBoxScreen: React.FC<Props> = ({ navigation }) => {
  const {
    notifications,
    isLoading,
    fetchCommBox,
    markAsRead,
    markAllAsRead,
  } = useNotificationStore();

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
    
    // Navigate based on notification type
    if (notification.type === 'outpass_request') {
      navigation.navigate('RectorOutpassList');
    } else if (notification.type === 'leave_request') {
      navigation.navigate('RectorLeaveList');
    }
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

  const renderNotificationItem = ({ item }: { item: Notification }) => {
    const icon = getNotificationIcon(item.type);
    const isUnread = !item.read_at;

    return (
      <TouchableOpacity
        style={[styles.notificationCard, isUnread && styles.notificationUnread]}
        onPress={() => handleNotificationPress(item)}
        activeOpacity={0.7}
      >
        <View style={[styles.iconContainer, { backgroundColor: icon.color + '20' }]}>
          <Icon name={icon.name} size={24} color={icon.color} />
        </View>

        <View style={styles.notificationContent}>
          <View style={styles.notificationHeader}>
            <Text style={[styles.notificationTitle, isUnread && styles.titleUnread]} numberOfLines={1}>
              {item.title}
            </Text>
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
      <Icon name="message-text-outline" size={64} color={theme.colors.border} />
      <Text style={styles.emptyTitle}>No Messages</Text>
      <Text style={styles.emptySubtitle}>
        New request notifications will appear here
      </Text>
    </View>
  );

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Notice Board" />

      {/* Info Banner */}
      <View style={styles.infoBanner}>
        <Icon name="information-outline" size={20} color={theme.colors.primary} />
        <Text style={styles.infoText}>
          Notifications appear when students raise outpass or leave requests
        </Text>
      </View>

      {/* Notification List */}
      <FlatList
        data={notifications}
        keyExtractor={(item) => String(item.id)}
        renderItem={renderNotificationItem}
        ListEmptyComponent={!isLoading ? renderEmptyState : null}
        contentContainerStyle={styles.listContent}
        refreshControl={
          <RefreshControl refreshing={isLoading} onRefresh={onRefresh} />
        }
      />
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  infoBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: theme.colors.infoLight,
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
  },
  infoText: {
    flex: 1,
    marginLeft: 10,
    fontSize: 13,
    color: theme.colors.info,
  },
  listContent: {
    padding: 16,
    flexGrow: 1,
  },
  notificationCard: {
    flexDirection: 'row',
    backgroundColor: theme.colors.white,
    padding: 16,
    borderRadius: 12,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  notificationUnread: {
    backgroundColor: theme.colors.infoLight,
    borderColor: theme.colors.border,
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
  notificationHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 4,
  },
  notificationTitle: {
    fontSize: 15,
    fontWeight: '500',
    color: theme.colors.text,
    flex: 1,
  },
  titleUnread: {
    fontWeight: '600',
    color: theme.colors.text,
  },
  notificationBody: {
    fontSize: 14,
    color: theme.colors.textSecondary,
    lineHeight: 20,
    marginBottom: 6,
  },
  notificationTime: {
    fontSize: 12,
    color: theme.colors.textMuted,
  },
  unreadDot: {
    width: 10,
    height: 10,
    borderRadius: 5,
    backgroundColor: theme.colors.primary,
    marginLeft: 8,
    alignSelf: 'center',
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
});

export default RectorCommBoxScreen;
