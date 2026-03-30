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
import type { OutPass } from '../../../shared/types';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface Props {
  navigation: any;
}

const getStatusColor = (status: string): string => {
  const colors: Record<string, string> = {
    pending: theme.colors.warning,
    approved: theme.colors.success,
    rejected: theme.colors.error,
    declined: theme.colors.error, // backend may return declined; treat same as rejected
    completed: theme.colors.textSecondary,
    emergency_exit: theme.colors.error,
  };
  return colors[status.toLowerCase()] || theme.colors.textSecondary;
};

export const OutpassListScreen: React.FC<Props> = ({ navigation }) => {
  const [outpasses, setOutpasses] = useState<OutPass[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [filter, setFilter] = useState<'all' | 'pending' | 'approved' | 'rejected'>('pending');
  const fetchOutpasses = useCallback(async () => {
    try {
      const params: Record<string, string> = {};
      if (filter !== 'all') {
        params.status = filter;
      }
      const response = await apiService.get<{ data: OutPass[] }>('/mobile/rector/outpasses', { params });
      setOutpasses(response.data || []);
    } catch (error) {
      console.error('Failed to fetch outpasses:', error);
    } finally {
      setIsLoading(false);
    }
  }, [filter]);

  useEffect(() => {
    fetchOutpasses();
  }, [fetchOutpasses]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await fetchOutpasses();
    setRefreshing(false);
  }, [fetchOutpasses]);

  const formatDateTime = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const renderOutpassCard = ({ item }: { item: OutPass }) => {
    const statusColor = getStatusColor(item.status);

    return (
      <TouchableOpacity
        style={styles.card}
        onPress={() => navigation.navigate('RectorOutpassDetail', { outpass: item })}
        activeOpacity={0.7}
      >
        <View style={styles.cardHeader}>
          <View style={styles.studentInfo}>
            <View style={styles.avatarContainer}>
              <Text style={styles.avatarText}>
                {item.student_name
                  ?.split(' ')
                  .map((n) => n[0])
                  .join('')
                  .substring(0, 2)}
              </Text>
            </View>
            <View>
              <Text style={styles.studentName}>{item.student_name}</Text>
              <View style={styles.roomRow}>
                {item.hostel && (
                  <>
                    <Icon name="office-building" size={12} color={theme.colors.textSecondary} />
                    <Text style={styles.roomText}>{item.hostel}</Text>
                  </>
                )}
                {item.room && (
                  <>
                    <Text style={styles.divider}>•</Text>
                    <Text style={styles.roomText}>Room {item.room}</Text>
                  </>
                )}
              </View>
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
            {item.reason}
          </Text>
        </View>

        <View style={styles.cardFooter}>
          <View style={styles.dateTimeRow}>
            <Icon name="calendar-clock" size={14} color={theme.colors.textMuted} />
            <Text style={styles.dateTimeText}>
              {formatDateTime(item.requested_at)} - {formatDateTime(item.valid_until)}
            </Text>
          </View>
          <View style={styles.viewDetailsRow}>
            <Text style={styles.viewDetailsText}>View Details</Text>
            <Icon name="chevron-right" size={16} color={theme.colors.primary} />
          </View>
        </View>

        {item.overnight && (
          <View style={styles.overnightBadge}>
            <Icon name="weather-night" size={14} color={theme.colors.primaryLight} />
            <Text style={styles.overnightText}>Overnight</Text>
          </View>
        )}
      </TouchableOpacity>
    );
  };

  const renderEmptyState = () => (
    <View style={styles.emptyState}>
      <Icon name="exit-run" size={64} color={theme.colors.border} />
      <Text style={styles.emptyTitle}>No Outpass Requests</Text>
      <Text style={styles.emptySubtitle}>
        {filter === 'pending'
          ? 'No pending requests to review'
          : 'No outpass requests found'}
      </Text>
    </View>
  );

  const pendingCount = outpasses.filter((o) => o.status === 'pending').length;

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Outpass Requests" />
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

      {/* Outpass List */}
      <FlatList
        data={outpasses}
        keyExtractor={(item) => String(item.id)}
        renderItem={renderOutpassCard}
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
    paddingBottom: 16,
    paddingHorizontal: 16,
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
    backgroundColor: theme.colors.primary,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
  },
  avatarText: {
    color: theme.colors.white,
    fontSize: 16,
    fontWeight: '700',
  },
  studentName: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.text,
  },
  roomRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: 2,
  },
  roomText: {
    fontSize: 13,
    color: theme.colors.textSecondary,
    marginLeft: 4,
  },
  divider: {
    marginHorizontal: 6,
    color: theme.colors.textMuted,
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
  cardFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  dateTimeRow: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  dateTimeText: {
    fontSize: 12,
    color: theme.colors.textMuted,
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
  overnightBadge: {
    position: 'absolute',
    top: 12,
    right: 12,
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: theme.colors.background,
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
  },
  overnightText: {
    fontSize: 11,
    fontWeight: '500',
    color: theme.colors.primaryLight,
    marginLeft: 4,
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

export default OutpassListScreen;
