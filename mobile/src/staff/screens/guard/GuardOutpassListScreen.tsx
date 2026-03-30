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

interface Outpass {
  id: number;
  student_name: string;
  room_number?: string;
  reason: string;
  status: string;
  out_date: string;
  out_time: string;
  expected_in_date: string;
  expected_in_time: string;
}

interface Props {
  navigation: any;
}

export const GuardOutpassListScreen: React.FC<Props> = ({ navigation }) => {
  const [outpasses, setOutpasses] = useState<Outpass[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const fetchData = useCallback(async () => {
    try {
      setError(null);
      const res = await apiService.get<{ data: Outpass[] }>('/guard/outpasses/active');
      setOutpasses(res?.data ?? []);
    } catch (err: any) {
      if (err?.response?.status === 401) {
        setError('Session expired. Please log in again.');
      } else {
        setError('Could not load outpasses. Pull down to refresh.');
      }
      setOutpasses([]);
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
  const sortedOutpasses = [...outpasses].sort((a, b) => {
    const aToday = a.out_date === todayStr ? 1 : 0;
    const bToday = b.out_date === todayStr ? 1 : 0;
    if (bToday !== aToday) return bToday - aToday;
    return new Date(b.out_date).getTime() - new Date(a.out_date).getTime();
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
      <StaffScreenHeader onBack={() => navigation?.goBack?.()} showBell={false}  title="Outpass List" />

      {error ? (
        <View style={styles.errorBanner}>
          <Text style={styles.errorText}>{error}</Text>
        </View>
      ) : null}

      <ScrollView
        style={styles.content}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
      >
        {sortedOutpasses.length === 0 ? (
          <View style={styles.empty}>
            <Ionicons name="document-text-outline" size={48} color={colors.textMuted} />
            <Text style={styles.emptyText}>No outpass requests</Text>
          </View>
        ) : (
          sortedOutpasses.map((outpass) => (
            <View key={outpass.id} style={styles.card}>
              <Text style={styles.cardIdLabel}>Outpass #{outpass.id}</Text>
              <View style={styles.cardHeader}>
                <View>
                  <Text style={styles.cardTitle}>{outpass.student_name}</Text>
                  <Text style={styles.cardSubtitle}>Room {outpass.room_number || 'N/A'}</Text>
                </View>
                <View style={[styles.statusBadge, { backgroundColor: getStatusColor(outpass.status) + '30' }]}>
                  <Text style={[styles.statusText, { color: getStatusColor(outpass.status) }]}>
                    {(outpass.status || '').toUpperCase()}
                  </Text>
                </View>
              </View>
              <Text style={styles.cardReason}>{outpass.reason || '—'}</Text>
              <View style={styles.cardFooter}>
                <Text style={styles.cardTime}>
                  Exit: {format(new Date(outpass.out_date), 'MMM dd')} {outpass.out_time}
                </Text>
                <Text style={styles.cardTime}>
                  Entry: {format(new Date(outpass.expected_in_date), 'MMM dd')} {outpass.expected_in_time}
                </Text>
              </View>
              <GradientButton
                style={styles.viewButton}
                onPress={() => navigation.navigate('GuardOutpassDetail', { outpassId: outpass.id })}
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
