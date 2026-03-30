/**
 * Student: My Parcels – list parcels (arrived / received) and show 4-digit code for pickup.
 */
import React, { useCallback, useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  RefreshControl,
  ActivityIndicator,
  TouchableOpacity,
} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { apiService } from '../../shared/services/api.service';
import { colors } from '../../shared/theme/colors';
import { theme } from '../../shared/theme/theme';

type ParcelItem = {
  id: string;
  status: string;
  code: string | null;
  room_number: string | null;
  notes: string | null;
  informed_at: string | null;
  received_at: string | null;
  hostel_name: string;
};

export const ParcelListScreen: React.FC<{ navigation: any }> = ({ navigation }) => {
  const insets = useSafeAreaInsets();
  const [parcels, setParcels] = useState<ParcelItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const fetchParcels = useCallback(async () => {
    try {
      const res = await apiService.get<{ data: ParcelItem[] }>('/mobile/parcels');
      setParcels(res.data?.data ?? []);
    } catch (e) {
      console.error('Fetch parcels error', e);
      setParcels([]);
    } finally {
      setLoading(false);
    }
  }, []);

  React.useEffect(() => {
    fetchParcels();
  }, [fetchParcels]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await fetchParcels();
    setRefreshing(false);
  }, [fetchParcels]);

  const formatDate = (iso: string | null) => {
    if (!iso) return '—';
    try {
      const d = new Date(iso);
      return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    } catch {
      return iso;
    }
  };

  const HEADER_ROW_HEIGHT = 52;
  const HEADER_PADDING_TOP = Math.max(insets.top, 10);
  const HEADER_PADDING_BOTTOM = 6;

  return (
    <View style={styles.container}>
      <View
        style={[
          styles.header,
          {
            paddingTop: HEADER_PADDING_TOP,
            paddingBottom: HEADER_PADDING_BOTTOM,
            minHeight: HEADER_PADDING_TOP + HEADER_ROW_HEIGHT + HEADER_PADDING_BOTTOM,
          },
        ]}>
        <View style={[styles.headerRow, { height: HEADER_ROW_HEIGHT }]}>
          <TouchableOpacity
            style={styles.backButton}
            onPress={() => (navigation?.canGoBack?.() ? navigation.goBack() : navigation.navigate('Home'))}
            accessibilityLabel="Go back">
            <Ionicons name="arrow-back" size={24} color={theme.colors.primary} />
          </TouchableOpacity>
          <Text style={styles.title}>My Parcels</Text>
          <View style={styles.headerSpacer} />
        </View>
      </View>
      <ScrollView
        style={styles.scroll}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={colors.primary} />}
      >
        {loading ? (
          <ActivityIndicator style={styles.loader} color={colors.primary} />
        ) : parcels.length === 0 ? (
          <Text style={styles.empty}>No parcels yet. When the warden logs a parcel for you, it will appear here and you’ll get a notification.</Text>
        ) : (
          parcels.map((p) => (
            <View key={p.id} style={styles.card}>
              <View style={styles.cardRow}>
                <Text style={styles.statusBadge}>{p.status === 'informed' ? 'Arrived' : 'Received'}</Text>
                <Text style={styles.hostel}>{p.hostel_name}</Text>
              </View>
              {p.room_number && <Text style={styles.meta}>Room: {p.room_number}</Text>}
              {p.status === 'informed' && p.code && (
                <View style={styles.codeBlock}>
                  <Text style={styles.codeLabel}>Show this 4-digit code to the warden to collect:</Text>
                  <Text style={styles.code}>{p.code}</Text>
                </View>
              )}
              {p.status === 'received' && (
                <Text style={styles.receivedText}>Collected on {formatDate(p.received_at)}</Text>
              )}
              {p.notes && <Text style={styles.notes}>Note: {p.notes}</Text>}
              <Text style={styles.date}>Informed: {formatDate(p.informed_at)}</Text>
            </View>
          ))
        )}
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: theme.colors.white },
  header: {
    backgroundColor: theme.colors.white,
    paddingHorizontal: 16,
    ...theme.shadows.medium,
  },
  headerRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  backButton: { padding: 8 },
  headerSpacer: { width: 40 },
  title: { fontSize: 20, fontWeight: '700', color: theme.colors.primary },
  scroll: { flex: 1, padding: 16 },
  loader: { marginVertical: 24 },
  empty: { color: '#666', marginTop: 24, lineHeight: 22 },
  card: { backgroundColor: '#fff', borderRadius: 12, padding: 16, marginBottom: 12 },
  cardRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 6 },
  statusBadge: { fontSize: 13, fontWeight: '600', color: colors.primary, textTransform: 'uppercase' },
  hostel: { fontSize: 13, color: '#666' },
  meta: { fontSize: 14, color: '#555', marginBottom: 8 },
  codeBlock: { backgroundColor: '#f0f7f0', borderRadius: 8, padding: 12, marginVertical: 8 },
  codeLabel: { fontSize: 13, color: '#555', marginBottom: 6 },
  code: { fontSize: 28, fontWeight: '700', letterSpacing: 6, color: colors.primary, textAlign: 'center' },
  receivedText: { fontSize: 14, color: '#2e7d32', marginTop: 6 },
  notes: { fontSize: 13, color: '#666', marginTop: 6, fontStyle: 'italic' },
  date: { fontSize: 12, color: '#999', marginTop: 8 },
});

export default ParcelListScreen;
