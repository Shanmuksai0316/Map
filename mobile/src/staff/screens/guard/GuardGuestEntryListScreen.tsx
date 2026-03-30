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

interface GuestEntry {
  id: number;
  student_name: string;
  student_id?: string;
  room_number?: string;
  visitor_name?: string;
  status: string;
  reason?: string;
  guest_relationship?: string;
  guest_phone?: string;
  number_of_guests?: number;
  visit_date?: string;
  time?: string;
}

interface Props {
  navigation: any;
}

export const GuardGuestEntryListScreen: React.FC<Props> = ({ navigation }) => {
  const [guests, setGuests] = useState<GuestEntry[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const fetchData = useCallback(async () => {
    try {
      setError(null);
      const res = await apiService.get<{ data: GuestEntry[] }>('/guard/guest-entries/active');
      setGuests(res?.data ?? []);
    } catch (err: any) {
      if (err?.response?.status === 401) {
        setError('Session expired. Please log in again.');
      } else {
        setError('Could not load guest entries. Pull down to refresh.');
      }
      setGuests([]);
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

  const getStatusColor = (status: string) => {
    const m: Record<string, string> = {
      active: '#4CAF50',
      approved: '#2196F3',
      pending: '#FF9800',
      completed: '#4CAF50',
    };
    return m[(status || '').toLowerCase()] || colors.textMuted;
  };

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation?.goBack?.()} showBell={false}  title="Guest Entries" />

      {error ? (
        <View style={styles.errorBanner}>
          <Text style={styles.errorText}>{error}</Text>
        </View>
      ) : null}

      <ScrollView
        style={styles.content}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
      >
        {guests.length === 0 ? (
          <View style={styles.empty}>
            <Ionicons name="people-outline" size={48} color={colors.textMuted} />
            <Text style={styles.emptyText}>No guest entry requests</Text>
          </View>
        ) : (
          guests.map((guest) => (
            <View key={guest.id} style={styles.card}>
              <Text style={styles.cardIdLabel}>Request #{guest.id}</Text>
              <Text style={styles.cardRow}>
                <Text style={styles.cardLabel}>Student name: </Text>
                <Text style={styles.cardValue}>{guest.student_name}</Text>
              </Text>
              {guest.student_id ? (
                <Text style={styles.cardRow}>
                  <Text style={styles.cardLabel}>Student ID: </Text>
                  <Text style={styles.cardValue}>{guest.student_id}</Text>
                </Text>
              ) : null}
              <Text style={styles.cardRow}>
                <Text style={styles.cardLabel}>Number of guests: </Text>
                <Text style={styles.cardValue}>{guest.number_of_guests ?? 1}</Text>
              </Text>
              <Text style={styles.cardRow}>
                <Text style={styles.cardLabel}>Date of arrival: </Text>
                <Text style={styles.cardValue}>
                  {guest.visit_date ? format(new Date(guest.visit_date), 'MMM dd, yyyy') : guest.time || '—'}
                </Text>
              </Text>
              <View style={styles.cardHeader}>
                <View style={[styles.statusBadge, { backgroundColor: getStatusColor(guest.status) + '30' }]}>
                  <Text style={[styles.statusText, { color: getStatusColor(guest.status) }]}>
                    {(guest.status || '').toUpperCase()}
                  </Text>
                </View>
              </View>
              <GradientButton
                style={styles.viewButton}
                onPress={() => navigation.navigate('GuardGuestEntryDetail', { guest })}
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
  cardHeader: { marginBottom: 8 },
  cardRow: { fontSize: 14, marginBottom: 4 },
  cardLabel: { color: colors.textMuted },
  cardValue: { color: colors.textPrimary, fontWeight: '500' },
  statusBadge: { alignSelf: 'flex-start', paddingHorizontal: 10, paddingVertical: 4, borderRadius: 8, marginBottom: 12 },
  statusText: { fontSize: 11, fontWeight: '600' },
  viewButton: { backgroundColor: colors.primary, paddingVertical: 10, borderRadius: 8, alignItems: 'center' },
  viewButtonText: { color: colors.surface, fontSize: 14, fontWeight: '600' },
});
