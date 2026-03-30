import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  ActivityIndicator,
} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { apiService } from '../../../shared/services/api.service';
import { colors } from '../../../shared/theme/colors';
import { format } from 'date-fns';
import { ErrorState } from '../../../shared/components/shared/ErrorState';
import { errorHandler } from '../../../shared/utils/errorHandler';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface LeaveOutpassHistoryItem {
  id: number;
  leave_id?: number | null;
  outpass_id?: number | null;
  student_id: number;
  student_name: string;
  hostel_name: string;
  direction: string;
  type: 'leave' | 'outpass' | 'other';
  out_time?: string;
  in_time?: string;
  timestamp: string;
}

interface GuestHistoryItem {
  id: number;
  student_name: string;
  student_id?: string;
  room_number?: string;
  number_of_guests?: number;
  visit_date?: string;
  status: string;
  check_in_time?: string;
}

export const GuardHistoryScreen = ({ navigation }: any) => {
  const [activeTab, setActiveTab] = useState<'leave' | 'outpass' | 'guest'>('leave');
  const [leaveItems, setLeaveItems] = useState<LeaveOutpassHistoryItem[]>([]);
  const [outpassItems, setOutpassItems] = useState<LeaveOutpassHistoryItem[]>([]);
  const [guestItems, setGuestItems] = useState<GuestHistoryItem[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<any>(null);

  const fetchHistory = async () => {
    try {
      setError(null);
      const [leaveRes, outpassRes, guestRes] = await Promise.all([
        apiService.get<{ data: LeaveOutpassHistoryItem[] }>('/guard/history/leave?per_page=100').catch((e) => {
          if (e?.response?.status === 401) throw e;
          return { data: [] };
        }),
        apiService.get<{ data: LeaveOutpassHistoryItem[] }>('/guard/history/outpass?per_page=100').catch((e) => {
          if (e?.response?.status === 401) throw e;
          return { data: [] };
        }),
        apiService.get<{ data: GuestHistoryItem[] }>('/guard/guest-entries/completed').catch((e) => {
          if (e?.response?.status === 401) throw e;
          return { data: [] };
        }),
      ]);
      setLeaveItems(leaveRes?.data ?? []);
      setOutpassItems(outpassRes?.data ?? []);
      setGuestItems(guestRes?.data ?? []);
    } catch (err: any) {
      console.error('Guard history fetch error:', err);
      const errorDetails = errorHandler.handleError(err);
      setError(errorDetails);
      setLeaveItems([]);
      setOutpassItems([]);
      setGuestItems([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchHistory();
  }, []);

  const onRefresh = () => {
    setRefreshing(true);
    fetchHistory();
  };

  const getStatusBadge = (status: string) => {
    const statusColors: Record<string, string> = {
      completed: '#4CAF50',
      approved: '#2196F3',
    };
    return statusColors[status] || colors.textMuted;
  };

  const renderLeaveCard = (item: LeaveOutpassHistoryItem) => (
    <View key={`leave-${item.id}`} style={styles.card}>
      <Text style={styles.cardIdLabel}>Leave #{item.leave_id ?? item.id}</Text>
      <View style={styles.cardHeader}>
        <View>
          <Text style={styles.cardTitle}>{item.student_name}</Text>
          <Text style={styles.cardSubtitle}>{item.hostel_name}</Text>
        </View>
        <View style={[styles.statusBadge, { backgroundColor: getStatusBadge('completed') + '30' }]}>
          <Text style={[styles.statusText, { color: getStatusBadge('completed') }]}>COMPLETED</Text>
        </View>
      </View>
      <View style={styles.cardFooter}>
        <Text style={styles.cardTime}>
          Verified at gate: {item.timestamp ? format(new Date(item.timestamp), 'MMM dd, HH:mm') : '—'}
        </Text>
      </View>
    </View>
  );

  const renderOutpassCard = (item: LeaveOutpassHistoryItem) => (
    <View key={`outpass-${item.id}`} style={styles.card}>
      <Text style={styles.cardIdLabel}>Outpass #{item.outpass_id ?? item.id}</Text>
      <View style={styles.cardHeader}>
        <View>
          <Text style={styles.cardTitle}>{item.student_name}</Text>
          <Text style={styles.cardSubtitle}>{item.hostel_name}</Text>
        </View>
        <View style={[styles.statusBadge, { backgroundColor: getStatusBadge('completed') + '30' }]}>
          <Text style={[styles.statusText, { color: getStatusBadge('completed') }]}>COMPLETED</Text>
        </View>
      </View>
      <View style={styles.cardFooter}>
        <Text style={styles.cardTime}>
          Verified at gate: {item.timestamp ? format(new Date(item.timestamp), 'MMM dd, HH:mm') : '—'}
        </Text>
      </View>
    </View>
  );

  const renderGuestCard = (item: GuestHistoryItem) => (
    <View key={`guest-${item.id}`} style={styles.card}>
      <Text style={styles.cardIdLabel}>Request #{item.id}</Text>
      <View style={styles.cardHeader}>
        <View>
          <Text style={styles.cardTitle}>{item.student_name}</Text>
          <Text style={styles.cardSubtitle}>
            {item.student_id ? `ID: ${item.student_id} • ` : ''}Room {item.room_number || 'N/A'}
          </Text>
        </View>
        <View style={[styles.statusBadge, { backgroundColor: getStatusBadge('completed') + '30' }]}>
          <Text style={[styles.statusText, { color: getStatusBadge('completed') }]}>COMPLETED</Text>
        </View>
      </View>
      <View style={styles.cardFooter}>
        <Text style={styles.cardTime}>
          {item.number_of_guests != null && `${item.number_of_guests} guest(s)`}
          {item.visit_date && ` • ${format(new Date(item.visit_date), 'MMM dd, yyyy')}`}
          {item.check_in_time && ` • Entry: ${item.check_in_time}`}
        </Text>
      </View>
    </View>
  );

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="History" />

      <View style={styles.tabs}>
        <TouchableOpacity
          style={[styles.tab, activeTab === 'leave' && styles.tabActive]}
          onPress={() => setActiveTab('leave')}>
          <Text style={[styles.tabText, activeTab === 'leave' && styles.tabTextActive]}>Leave</Text>
          {leaveItems.length > 0 && (
            <View style={styles.badge}>
              <Text style={styles.badgeText}>{leaveItems.length}</Text>
            </View>
          )}
        </TouchableOpacity>
        <TouchableOpacity
          style={[styles.tab, activeTab === 'outpass' && styles.tabActive]}
          onPress={() => setActiveTab('outpass')}>
          <Text style={[styles.tabText, activeTab === 'outpass' && styles.tabTextActive]}>Outpass</Text>
          {outpassItems.length > 0 && (
            <View style={styles.badge}>
              <Text style={styles.badgeText}>{outpassItems.length}</Text>
            </View>
          )}
        </TouchableOpacity>
        <TouchableOpacity
          style={[styles.tab, activeTab === 'guest' && styles.tabActive]}
          onPress={() => setActiveTab('guest')}>
          <Text style={[styles.tabText, activeTab === 'guest' && styles.tabTextActive]}>Guest Entry</Text>
          {guestItems.length > 0 && (
            <View style={styles.badge}>
              <Text style={styles.badgeText}>{guestItems.length}</Text>
            </View>
          )}
        </TouchableOpacity>
      </View>

      {error && (
        <View style={styles.errorBanner}>
          <ErrorState error={error} onRetry={fetchHistory} />
        </View>
      )}

      <ScrollView
        style={styles.content}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}>
        {loading ? (
          <View style={styles.loadingContainer}>
            <ActivityIndicator size="large" color={colors.primary} />
            <Text style={styles.loadingText}>Loading...</Text>
          </View>
        ) : (
          <View style={styles.tabContent}>
            {activeTab === 'leave' && (
              leaveItems.length === 0 ? (
                <View style={styles.emptyState}>
                  <Ionicons name="document-text-outline" size={48} color={colors.textMuted} />
                  <Text style={styles.emptyText}>No completed leave verifications</Text>
                </View>
              ) : (
                leaveItems.map(renderLeaveCard)
              )
            )}
            {activeTab === 'outpass' && (
              outpassItems.length === 0 ? (
                <View style={styles.emptyState}>
                  <Ionicons name="document-text-outline" size={48} color={colors.textMuted} />
                  <Text style={styles.emptyText}>No completed outpass verifications</Text>
                </View>
              ) : (
                outpassItems.map(renderOutpassCard)
              )
            )}
            {activeTab === 'guest' && (
              guestItems.length === 0 ? (
                <View style={styles.emptyState}>
                  <Ionicons name="people-outline" size={48} color={colors.textMuted} />
                  <Text style={styles.emptyText}>No completed guest entries</Text>
                </View>
              ) : (
                guestItems.map(renderGuestCard)
              )
            )}
          </View>
        )}
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  tabs: {
    flexDirection: 'row',
    backgroundColor: colors.surface,
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  tab: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 10,
    marginHorizontal: 4,
    borderRadius: 8,
  },
  tabActive: {
    backgroundColor: colors.primary + '20',
  },
  tabText: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textSecondary,
  },
  tabTextActive: {
    color: colors.primary,
  },
  badge: {
    backgroundColor: colors.primary,
    borderRadius: 10,
    paddingHorizontal: 6,
    marginLeft: 4,
  },
  badgeText: {
    fontSize: 12,
    fontWeight: '600',
    color: colors.surface,
  },
  errorBanner: {
    padding: 12,
  },
  content: {
    flex: 1,
  },
  tabContent: {
    padding: 16,
    paddingBottom: 32,
  },
  card: {
    backgroundColor: colors.surface,
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: colors.border,
  },
  cardIdLabel: {
    fontSize: 12,
    color: colors.textMuted,
    marginBottom: 8,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 8,
  },
  cardTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.text,
  },
  cardSubtitle: {
    fontSize: 14,
    color: colors.textMuted,
    marginTop: 2,
  },
  statusBadge: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
  },
  statusText: {
    fontSize: 10,
    fontWeight: '600',
  },
  cardFooter: {
    marginTop: 4,
  },
  cardTime: {
    fontSize: 12,
    color: colors.textMuted,
  },
  loadingContainer: {
    padding: 40,
    alignItems: 'center',
  },
  loadingText: {
    marginTop: 12,
    fontSize: 14,
    color: colors.textSecondary,
  },
  emptyState: {
    alignItems: 'center',
    paddingVertical: 48,
  },
  emptyText: {
    marginTop: 12,
    fontSize: 16,
    color: colors.textMuted,
  },
});

export default GuardHistoryScreen;
