import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { colors } from '../../../shared/theme/colors';
import { format } from 'date-fns';
import { apiService } from '../../../shared/services/api.service';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface Leave {
  id: number;
  student_name: string;
  student_id?: string;
  room_number?: string;
  leave_type: string;
  status: string;
  from_date: string;
  to_date: string;
  time?: string;
  emergency_contact?: string;
  actual_departure_time?: string | null;
}

interface Props {
  navigation: any;
}

export const GuardLeaveListScreen: React.FC<Props> = ({ navigation }) => {
  const [leaves, setLeaves] = useState<Leave[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const fetchData = useCallback(async () => {
    try {
      setError(null);
      const res = await apiService.get<{ data: Leave[] }>('/guard/leaves/active');
      setLeaves(res?.data ?? []);
    } catch (err: any) {
      if (err?.response?.status === 401) {
        setError('Session expired. Please log in again.');
      } else {
        setError('Could not load leaves. Pull down to refresh.');
      }
      setLeaves([]);
    } finally {
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    fetchData();
  }, [fetchData]);

  const todayStr = format(new Date(), 'yyyy-MM-dd');
  const sortedLeaves = [...leaves].sort((a, b) => {
    const aToday = a.from_date === todayStr ? 1 : 0;
    const bToday = b.from_date === todayStr ? 1 : 0;
    if (bToday !== aToday) return bToday - aToday;
    return new Date(b.from_date).getTime() - new Date(a.from_date).getTime();
  });

  const getStatusColor = (status: string) => {
    const m: Record<string, string> = {
      active: '#4CAF50',
      approved: '#2196F3',
      pending: '#FF9800',
      expired: '#F44336',
    };
    return m[(status || '').toLowerCase()] || colors.textMuted;
  };

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation?.goBack?.()} showBell={false}  title="Leave List" />

      {error ? (
        <View style={styles.errorBanner}>
          <Text style={styles.errorText}>{error}</Text>
        </View>
      ) : null}

      <ScrollView
        style={styles.content}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
      >
        {sortedLeaves.length === 0 ? (
          <View style={styles.empty}>
            <Ionicons name="document-text-outline" size={48} color={colors.textMuted} />
            <Text style={styles.emptyText}>No leave requests</Text>
          </View>
        ) : (
          sortedLeaves.map((leave) => (
            <View key={leave.id} style={styles.card}>
              <Text style={styles.cardIdLabel}>Leave #{leave.id}</Text>
              <View style={styles.cardHeader}>
                <View>
                  <Text style={styles.cardTitle}>{leave.student_name}</Text>
                  <Text style={styles.cardSubtitle}>Room {leave.room_number || 'N/A'}</Text>
                </View>
                <View style={[styles.statusBadge, { backgroundColor: getStatusColor(leave.status) + '30' }]}>
                  <Text style={[styles.statusText, { color: getStatusColor(leave.status) }]}>
                    {(leave.status || '').toUpperCase()}
                  </Text>
                </View>
              </View>
              <Text style={styles.cardReason}>Leave Type: {leave.leave_type || '—'}</Text>
              <View style={styles.cardFooter}>
                <Text style={styles.cardTime}>
                  {format(new Date(leave.from_date), 'MMM dd')} - {format(new Date(leave.to_date), 'MMM dd')}
                </Text>
              </View>
              <GradientButton
                style={styles.viewButton}
                onPress={() => navigation.navigate('GuardLeaveDetail', { leave })}
              >
                <Text style={styles.viewButtonText}>View details</Text>
              </GradientButton>
            </View>
          ))
        )}
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  errorBanner: { backgroundColor: '#FEE2E2', padding: 12, marginHorizontal: 16, marginTop: 12, borderRadius: 8 },
  errorText: { color: '#DC2626', fontSize: 14 },
  content: { flex: 1, padding: 16 },
  empty: { alignItems: 'center', paddingVertical: 48 },
  emptyText: { marginTop: 12, fontSize: 16, color: colors.textMuted },
  card: {
    backgroundColor: colors.surface,
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.06,
    shadowRadius: 4,
    elevation: 2,
  },
  cardIdLabel: { fontSize: 12, color: colors.textMuted, marginBottom: 8 },
  cardHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 8 },
  cardTitle: { fontSize: 16, fontWeight: '600', color: colors.textPrimary },
  cardSubtitle: { fontSize: 14, color: colors.textMuted, marginTop: 2 },
  statusBadge: { paddingHorizontal: 10, paddingVertical: 4, borderRadius: 8 },
  statusText: { fontSize: 11, fontWeight: '600' },
  cardReason: { fontSize: 14, color: colors.textPrimary, marginBottom: 8 },
  cardFooter: { marginBottom: 12 },
  cardTime: { fontSize: 13, color: colors.textMuted },
  viewButton: { backgroundColor: colors.primary, paddingVertical: 10, borderRadius: 8, alignItems: 'center' },
  viewButtonText: { color: colors.surface, fontSize: 14, fontWeight: '600' },
});
