import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  FlatList,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { apiService } from '../../../shared/services/api.service';
import { theme } from '../../../shared/theme/theme';
import type { Leave } from '../../../shared/types';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface Props {
  navigation: any;
}

const getStatusColor = (status: string): string => {
  const colors: Record<string, string> = {
    pending: theme.colors.warning,
    approved: theme.colors.success,
    rejected: theme.colors.error,
  };
  return colors[status.toLowerCase()] || theme.colors.textSecondary;
};

export const LeaveListScreen: React.FC<Props> = ({ navigation }) => {
  const [leaves, setLeaves] = useState<Leave[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [filter, setFilter] = useState<'all' | 'pending' | 'approved' | 'rejected'>('all');

  const fetchLeaves = useCallback(async () => {
    try {
      const params: Record<string, string> = {};
      if (filter !== 'all') {
        params.status = filter;
      }
      const response = await apiService.get<{ data: Leave[]; meta?: any }>('/mobile/rector/leaves', { params });
      setLeaves(response.data || []);
    } catch (error) {
      console.error('Failed to fetch leaves:', error);
      setLeaves([]);
    } finally {
      setIsLoading(false);
    }
  }, [filter]);

  useEffect(() => {
    fetchLeaves();
  }, [fetchLeaves]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await fetchLeaves();
    setRefreshing(false);
  }, [fetchLeaves]);

  const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    });
  };

  const calculateDuration = (from: string, to: string) => {
    const fromDate = new Date(from);
    const toDate = new Date(to);
    const days = Math.ceil((toDate.getTime() - fromDate.getTime()) / (1000 * 60 * 60 * 24)) + 1;
    return `${days} day${days > 1 ? 's' : ''}`;
  };

  const renderLeaveCard = ({ item }: { item: Leave }) => {
    const statusColor = getStatusColor(item.status);

    return (
      <TouchableOpacity
        style={styles.card}
        onPress={() => navigation.navigate('RectorLeaveDetail', { leave: item })}
        activeOpacity={0.7}
      >
        <View style={styles.cardHeader}>
          <View style={styles.studentInfo}>
            <View style={styles.avatarContainer}>
              <Icon name="account" size={24} color={theme.colors.white} />
            </View>
            <View style={styles.studentDetails}>
              <Text style={styles.studentName}>Leave Request</Text>
              <Text style={styles.leaveId}>#{item.unique_id}</Text>
            </View>
          </View>

          <View style={[styles.statusBadge, { backgroundColor: statusColor + '20' }]}>
            <Text style={[styles.statusText, { color: statusColor }]}>
              {item.status.toUpperCase()}
            </Text>
          </View>
        </View>

        <View style={styles.reasonContainer}>
          <Text style={styles.reasonLabel}>Reason</Text>
          <Text style={styles.reasonText} numberOfLines={2}>
            {item.reason_for_leave}
          </Text>
        </View>

        <View style={styles.dateContainer}>
          <View style={styles.dateItem}>
            <Icon name="calendar-arrow-right" size={16} color={theme.colors.textSecondary} />
            <Text style={styles.dateLabel}>From</Text>
            <Text style={styles.dateValue}>{formatDate(item.from_date)}</Text>
          </View>
          <View style={styles.dateArrow}>
            <Icon name="arrow-right" size={16} color={theme.colors.border} />
          </View>
          <View style={styles.dateItem}>
            <Icon name="calendar-arrow-left" size={16} color={theme.colors.textSecondary} />
            <Text style={styles.dateLabel}>To</Text>
            <Text style={styles.dateValue}>{formatDate(item.to_date)}</Text>
          </View>
        </View>

        <View style={styles.cardFooter}>
          <View style={styles.durationBadge}>
            <Icon name="clock-outline" size={14} color={theme.colors.primaryLight} />
            <Text style={styles.durationText}>
              {calculateDuration(item.from_date, item.to_date)}
            </Text>
          </View>
          <View style={styles.viewDetailsRow}>
            <Text style={styles.viewDetailsText}>View Details</Text>
            <Icon name="chevron-right" size={16} color={theme.colors.primary} />
          </View>
        </View>
      </TouchableOpacity>
    );
  };

  const renderEmptyState = () => (
    <View style={styles.emptyState}>
      <Icon name="calendar-remove-outline" size={64} color={theme.colors.border} />
      <Text style={styles.emptyTitle}>No Leave Requests</Text>
      <Text style={styles.emptySubtitle}>
        {filter === 'pending'
          ? 'No pending requests to review'
          : 'No leave requests found'}
      </Text>
    </View>
  );

  const pendingCount = leaves.filter((l) => l.status === 'pending').length;

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Leave Requests" />
      {/* Filters */}
      <View style={styles.filtersContainer}>
        {(['all', 'pending', 'approved', 'rejected'] as const).map((f) => (
          <TouchableOpacity
            key={f}
            style={[styles.filterPill, filter === f && styles.filterActive]}
            onPress={() => setFilter(f)}
          >
            <Text style={[styles.filterText, filter === f && styles.filterTextActive]}>
              {f.charAt(0).toUpperCase() + f.slice(1)}
            </Text>
          </TouchableOpacity>
        ))}
      </View>

      {/* Leave List */}
      <FlatList
        data={leaves}
        keyExtractor={(item) => String(item.id)}
        renderItem={renderLeaveCard}
        ListEmptyComponent={!isLoading ? renderEmptyState : null}
        contentContainerStyle={styles.listContent}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
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
  header: {
    backgroundColor: theme.colors.white,
    paddingBottom: 20,
    paddingHorizontal: 20,
    flexDirection: 'row',
    alignItems: 'center',
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: theme.colors.border,
  },
  headerTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: theme.colors.primary,
  },
  headerSubtitle: {
    fontSize: 14,
    color: theme.colors.textSecondary,
    marginTop: 2,
  },
  filtersContainer: {
    flexDirection: 'row',
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: theme.colors.white,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
    gap: 8,
  },
  filterPill: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: theme.colors.divider,
  },
  filterActive: {
    backgroundColor: theme.colors.primaryLight,
  },
  filterText: {
    fontSize: 14,
    fontWeight: '500',
    color: theme.colors.textSecondary,
  },
  filterTextActive: {
    color: theme.colors.white,
  },
  listContent: {
    padding: 16,
    flexGrow: 1,
  },
  card: {
    backgroundColor: theme.colors.white,
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 12,
  },
  studentInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  avatarContainer: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: theme.colors.primaryLight,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
  },
  studentDetails: {
    flex: 1,
  },
  studentName: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.text,
  },
  leaveId: {
    fontSize: 13,
    color: theme.colors.textSecondary,
    marginTop: 2,
  },
  statusBadge: {
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderRadius: 12,
  },
  statusText: {
    fontSize: 11,
    fontWeight: '600',
  },
  reasonContainer: {
    backgroundColor: theme.colors.background,
    padding: 12,
    borderRadius: 8,
    marginBottom: 12,
  },
  reasonLabel: {
    fontSize: 11,
    fontWeight: '600',
    color: theme.colors.textSecondary,
    marginBottom: 4,
    textTransform: 'uppercase',
  },
  reasonText: {
    fontSize: 14,
    color: theme.colors.text,
    lineHeight: 20,
  },
  dateContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 12,
  },
  dateItem: {
    flex: 1,
    alignItems: 'center',
  },
  dateLabel: {
    fontSize: 11,
    color: theme.colors.textMuted,
    marginTop: 4,
  },
  dateValue: {
    fontSize: 13,
    fontWeight: '500',
    color: theme.colors.text,
    marginTop: 2,
  },
  dateArrow: {
    paddingHorizontal: 8,
  },
  cardFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  durationBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: theme.colors.background,
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 8,
  },
  durationText: {
    fontSize: 12,
    fontWeight: '500',
    color: theme.colors.primaryLight,
    marginLeft: 4,
  },
  viewDetailsRow: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  viewDetailsText: {
    fontSize: 13,
    fontWeight: '500',
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
  },
});

export default LeaveListScreen;
