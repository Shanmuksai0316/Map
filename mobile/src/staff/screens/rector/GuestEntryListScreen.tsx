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
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

export interface GuestEntryItem {
  id: number;
  unique_id?: string;
  student_name: string;
  student_id?: string;
  room_number?: string;
  guest_name: string;
  guest_relation?: string;
  visit_date?: string;
  purpose_to_visit?: string;
  status: string;
  created_at?: string;
}

interface Props {
  navigation: any;
}

const getStatusColor = (status: string): string => {
  const colors: Record<string, string> = {
    pending: theme.colors.warning,
    approved: theme.colors.success,
    rejected: theme.colors.error,
    completed: theme.colors.textSecondary,
  };
  return colors[status.toLowerCase()] || theme.colors.textSecondary;
};

export const GuestEntryListScreen: React.FC<Props> = ({ navigation }) => {
  const [entries, setEntries] = useState<GuestEntryItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [filter, setFilter] = useState<'all' | 'pending' | 'approved' | 'rejected'>('all');

  const fetchEntries = useCallback(async () => {
    try {
      const params: Record<string, string> = {};
      if (filter !== 'all') params.status = filter;
      const response = await apiService.get<{ data: GuestEntryItem[] }>('/mobile/rector/guest-entries', { params });
      setEntries(response?.data ?? []);
    } catch (error) {
      console.error('Failed to fetch guest entries:', error);
      setEntries([]);
    } finally {
      setIsLoading(false);
    }
  }, [filter]);

  useEffect(() => {
    fetchEntries();
  }, [fetchEntries]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await fetchEntries();
    setRefreshing(false);
  }, [fetchEntries]);

  const formatDate = (dateString?: string) => {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  };

  const renderCard = ({ item }: { item: GuestEntryItem }) => {
    const statusColor = getStatusColor(item.status);
    return (
      <TouchableOpacity
        style={styles.card}
        onPress={() => navigation.navigate('RectorGuestEntryDetail', { entry: item })}
        activeOpacity={0.7}
      >
        <Text style={styles.cardIdLabel}>Request {item.unique_id ?? '#' + item.id}</Text>
        <View style={styles.cardHeader}>
          <View>
            <Text style={styles.studentName}>{item.student_name}</Text>
            <Text style={styles.roomText}>
              {item.room_number ? `Room ${item.room_number}` : ''}
              {item.student_id ? ` • ID: ${item.student_id}` : ''}
            </Text>
          </View>
          <View style={[styles.statusBadge, { backgroundColor: statusColor + '20' }]}>
            <Text style={[styles.statusText, { color: statusColor }]}>{item.status.toUpperCase()}</Text>
          </View>
        </View>
        {item.guest_name ? (
          <Text style={styles.guestRow}>
            Guest: {item.guest_name}{item.guest_relation ? ` (${item.guest_relation})` : ''}
          </Text>
        ) : null}
        {item.visit_date && (
          <View style={styles.dateRow}>
            <Icon name="calendar" size={14} color={theme.colors.textMuted} />
            <Text style={styles.dateText}>{formatDate(item.visit_date)}</Text>
          </View>
        )}
        {item.purpose_to_visit ? (
          <Text style={styles.purpose} numberOfLines={2}>{item.purpose_to_visit}</Text>
        ) : null}
      </TouchableOpacity>
    );
  };

  const renderEmpty = () => (
    <View style={styles.emptyState}>
      <Icon name="account-multiple" size={64} color={theme.colors.border} />
      <Text style={styles.emptyTitle}>No Guest Entry Requests</Text>
      <Text style={styles.emptySubtitle}>
        {filter === 'all' ? 'No guest entry requests found' : `No ${filter} requests`}
      </Text>
    </View>
  );

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Guest Entry List" />
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
      <FlatList
        data={entries}
        keyExtractor={(item) => String(item.id)}
        renderItem={renderCard}
        ListEmptyComponent={!isLoading ? renderEmpty : null}
        contentContainerStyle={styles.listContent}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
      />
    </View>
  );
};

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: theme.colors.background },
  filtersContainer: {
    flexDirection: 'row',
    paddingVertical: 12,
    paddingHorizontal: 16,
    gap: 8,
    backgroundColor: theme.colors.surface,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
  },
  filterPill: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: theme.colors.surfaceMuted,
  },
  filterActive: { backgroundColor: theme.colors.primary },
  filterText: { fontSize: 14, fontWeight: '500', color: theme.colors.textSecondary },
  filterTextActive: { color: theme.colors.white },
  listContent: { padding: 16, paddingBottom: 32 },
  card: {
    backgroundColor: theme.colors.surface,
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  cardIdLabel: { fontSize: 12, color: theme.colors.textSecondary, marginBottom: 8 },
  cardHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 8 },
  studentName: { fontSize: 16, fontWeight: '600', color: theme.colors.text },
  roomText: { fontSize: 13, color: theme.colors.textSecondary, marginTop: 2 },
  statusBadge: { paddingHorizontal: 8, paddingVertical: 4, borderRadius: 8 },
  statusText: { fontSize: 10, fontWeight: '600' },
  guestRow: { fontSize: 14, color: theme.colors.textSecondary, marginBottom: 4 },
  dateRow: { flexDirection: 'row', alignItems: 'center', gap: 6, marginBottom: 4 },
  dateText: { fontSize: 13, color: theme.colors.textSecondary },
  purpose: { fontSize: 13, color: theme.colors.textSecondary, marginTop: 4 },
  emptyState: { alignItems: 'center', paddingVertical: 48 },
  emptyTitle: { fontSize: 18, fontWeight: '600', color: theme.colors.textSecondary, marginTop: 16 },
  emptySubtitle: { fontSize: 14, color: theme.colors.textMuted, marginTop: 8 },
});

export default GuestEntryListScreen;
