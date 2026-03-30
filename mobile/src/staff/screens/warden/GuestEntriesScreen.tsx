import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  FlatList,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  Alert,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { apiService } from '../../../shared/services/api.service';
import { colors } from '../../../shared/theme/colors';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface GuestEntry {
  id: number;
  student_name: string;
  room_number: string;
  guest_name: string;
  guest_relation: string;
  guest_phone: string;
  purpose: string;
  entry_time: string;
  expected_exit_time: string;
  actual_exit_time?: string;
  status: string;
}

interface Props {
  navigation: any;
}

export const GuestEntriesScreen: React.FC<Props> = ({ navigation }) => {
  const [entries, setEntries] = useState<GuestEntry[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [filter, setFilter] = useState<'active' | 'all'>('active');

  const fetchEntries = useCallback(async () => {
    try {
      const response = await apiService.get<any>('/warden/guest-entries', {
        params: { status: filter },
      });
      // apiService returns data directly, but backend may wrap it in { data: ... }
      setEntries(response?.data || response || []);
    } catch (error) {
      console.error('Failed to fetch guest entries:', error);
      // Show empty state - no mock data in production
      setEntries([]);
    }
  }, [filter]);

  useEffect(() => {
    fetchEntries();
  }, [fetchEntries]);

  const onRefresh = async () => {
    setRefreshing(true);
    await fetchEntries();
    setRefreshing(false);
  };

  const handleCheckOut = async (entry: GuestEntry) => {
    try {
      await apiService.post(`/guest-entries/${entry.id}/checkout`);
      Alert.alert('Success', 'Guest checked out successfully');
      fetchEntries();
    } catch (error) {
      Alert.alert('Error', 'Failed to check out guest');
    }
  };

  const formatTime = (dateString: string) => {
    return new Date(dateString).toLocaleTimeString([], {
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const renderEntry = ({ item }: { item: GuestEntry }) => (
    <View style={styles.card}>
      <View style={styles.cardHeader}>
        <View>
          <Text style={styles.guestName}>{item.guest_name}</Text>
          <Text style={styles.relation}>{item.guest_relation}</Text>
        </View>
        <View style={[styles.statusBadge, item.status === 'active' && styles.activeBadge]}>
          <Text style={[styles.statusText, item.status === 'active' && styles.activeText]}>
            {item.status === 'active' ? 'Active' : 'Checked Out'}
          </Text>
        </View>
      </View>

      <View style={styles.detailRow}>
        <Icon name="account" size={16} color={colors.textSecondary} />
        <Text style={styles.detailText}>Visiting: {item.student_name} (Room {item.room_number})</Text>
      </View>

      <View style={styles.detailRow}>
        <Icon name="text-box-outline" size={16} color={colors.textSecondary} />
        <Text style={styles.detailText}>{item.purpose}</Text>
      </View>

      <View style={styles.timeRow}>
        <View style={styles.timeBlock}>
          <Text style={styles.timeLabel}>Entry</Text>
          <Text style={styles.timeValue}>{formatTime(item.entry_time)}</Text>
        </View>
        <View style={styles.timeBlock}>
          <Text style={styles.timeLabel}>Expected Exit</Text>
          <Text style={styles.timeValue}>{formatTime(item.expected_exit_time)}</Text>
        </View>
      </View>

      {item.status === 'active' && (
        <GradientButton
          style={styles.checkoutButton}
          onPress={() => handleCheckOut(item)}
        >
          <Icon name="exit-run" size={18} color={colors.white} />
          <Text style={styles.checkoutText}>Check Out Guest</Text>
        </GradientButton>
      )}
    </View>
  );

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Guest Entries" />

      <View style={styles.filterRow}>
        <TouchableOpacity
          style={[styles.filterButton, filter === 'active' && styles.filterActive]}
          onPress={() => setFilter('active')}
        >
          <Text style={[styles.filterText, filter === 'active' && styles.filterTextActive]}>Active</Text>
        </TouchableOpacity>
        <TouchableOpacity
          style={[styles.filterButton, filter === 'all' && styles.filterActive]}
          onPress={() => setFilter('all')}
        >
          <Text style={[styles.filterText, filter === 'all' && styles.filterTextActive]}>All</Text>
        </TouchableOpacity>
      </View>

      <FlatList
        data={entries}
        renderItem={renderEntry}
        keyExtractor={item => item.id.toString()}
        contentContainerStyle={styles.listContent}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
        ListEmptyComponent={
          <View style={styles.emptyState}>
            <Icon name="account-multiple" size={64} color={colors.border} />
            <Text style={styles.emptyText}>No guest entries</Text>
          </View>
        }
      />
    </View>
  );
};

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  header: { backgroundColor: colors.primary, paddingBottom: 20, paddingHorizontal: 20, flexDirection: 'row', alignItems: 'center' },
  filterRow: { flexDirection: 'row', padding: 16, gap: 8 },
  filterButton: { paddingHorizontal: 16, paddingVertical: 8, borderRadius: 20, backgroundColor: colors.white, borderWidth: 1, borderColor: colors.border },
  filterActive: { backgroundColor: colors.primary, borderColor: colors.primary },
  filterText: { fontSize: 14, color: colors.textSecondary, fontWeight: '500' },
  filterTextActive: { color: colors.white },
  listContent: { padding: 16, paddingTop: 0 },
  card: { backgroundColor: colors.white, borderRadius: 16, padding: 16, marginBottom: 12, borderWidth: 1, borderColor: colors.border },
  cardHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 12 },
  guestName: { fontSize: 16, fontWeight: '600', color: colors.text },
  relation: { fontSize: 13, color: colors.textSecondary, marginTop: 2 },
  statusBadge: { paddingHorizontal: 10, paddingVertical: 4, borderRadius: 8, backgroundColor: colors.surfaceMuted },
  activeBadge: { backgroundColor: colors.successLight },
  statusText: { fontSize: 12, fontWeight: '600', color: colors.textSecondary },
  activeText: { color: colors.success },
  detailRow: { flexDirection: 'row', alignItems: 'center', marginBottom: 8 },
  detailText: { fontSize: 13, color: colors.textSecondary, marginLeft: 8 },
  timeRow: { flexDirection: 'row', paddingTop: 12, borderTopWidth: 1, borderTopColor: colors.divider, marginTop: 4 },
  timeBlock: { flex: 1 },
  timeLabel: { fontSize: 11, color: colors.textMuted, textTransform: 'uppercase' },
  timeValue: { fontSize: 14, fontWeight: '600', color: colors.text, marginTop: 2 },
  checkoutButton: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', backgroundColor: colors.primary, paddingVertical: 12, borderRadius: 10, marginTop: 12 },
  checkoutText: { color: colors.white, fontSize: 14, fontWeight: '600', marginLeft: 8 },
  emptyState: { alignItems: 'center', justifyContent: 'center', paddingVertical: 60 },
  emptyText: { fontSize: 16, color: colors.textMuted, marginTop: 16 },
});

export default GuestEntriesScreen;
