import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  ActivityIndicator,
  Alert,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { colors } from '../../../shared/theme/colors';
import { format } from 'date-fns';
import { apiService } from '../../../shared/services/api.service';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface OutpassDetail {
  id: number;
  student_name: string;
  student_id?: string;
  room_number?: string;
  reason: string;
  status: string;
  out_date: string;
  out_time: string;
  expected_in_date: string;
  expected_in_time: string;
  qr_scanned_at?: string;
  backup_code_used_at?: string;
}

export const GuardOutpassDetailStandaloneScreen = ({ navigation, route }: any) => {
  const outpassId = route?.params?.outpassId;
  const [outpass, setOutpass] = useState<OutpassDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    if (!outpassId) {
      setError('Invalid outpass');
      setLoading(false);
      return;
    }
    let cancelled = false;
    (async () => {
      try {
        const res = await apiService.get<{ data: OutpassDetail }>(`/guard/outpasses/${outpassId}`);
        if (!cancelled && res?.data) setOutpass(res.data);
        else if (!cancelled) setError('Not found');
      } catch (e) {
        if (!cancelled) setError('Failed to load details');
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => { cancelled = true; };
  }, [outpassId]);

  const verifiedAt = outpass?.qr_scanned_at || outpass?.backup_code_used_at;
  const canMarkExit = (outpass?.status || '').toLowerCase() === 'approved' && !verifiedAt;

  const markAsExit = async () => {
    if (!outpass) return;
    try {
      setIsSubmitting(true);
      await apiService.post('/guard/verify-time', {
        type: 'outpass',
        id: outpass.id,
        direction: 'out',
        timestamp: new Date().toISOString(),
      });
      Alert.alert('Success', 'Exit recorded. Request marked as completed.');
      navigation?.goBack?.();
    } catch (e) {
      Alert.alert('Error', 'Failed to record. Please try again.');
    } finally {
      setIsSubmitting(false);
    }
  };

  if (loading) {
    return (
      <View style={styles.container}>
        <StaffScreenHeader onBack={() => navigation?.goBack?.()} showBell={false}  title="Outpass Details" />
        <View style={styles.centeredContent}>
          <ActivityIndicator size="large" color={colors.primary} />
          <Text style={styles.loadingText}>Loading outpass details...</Text>
        </View>
      </View>
    );
  }

  if (error || !outpass) {
    return (
      <View style={styles.container}>
        <StaffScreenHeader onBack={() => navigation?.goBack?.()} showBell={false}  title="Outpass Details" />
        <View style={styles.centeredContent}>
          <Text style={styles.errorText}>{error || 'Outpass not found'}</Text>
          <TouchableOpacity style={styles.backBtn} onPress={() => navigation?.goBack?.()}>
            <Text style={styles.backBtnText}>Go back</Text>
          </TouchableOpacity>
        </View>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation?.goBack?.()} showBell={false}  title="Outpass Details" />
      <ScrollView style={styles.content}>
        {verifiedAt && (
          <View style={styles.verifiedBanner}>
            <Ionicons name="checkmark-circle" size={24} color={colors.success} />
            <Text style={styles.verifiedText}>
              Verified at gate • {format(new Date(verifiedAt), 'MMM dd, yyyy HH:mm')}
            </Text>
          </View>
        )}

        <View style={styles.detailSection}>
          <Text style={styles.label}>Outpass #</Text>
          <Text style={styles.value}>{outpass.id}</Text>
        </View>
        <View style={styles.detailSection}>
          <Text style={styles.label}>Student Name</Text>
          <Text style={styles.value}>{outpass.student_name}</Text>
        </View>
        {outpass.student_id && (
          <View style={styles.detailSection}>
            <Text style={styles.label}>Student ID</Text>
            <Text style={styles.value}>{outpass.student_id}</Text>
          </View>
        )}
        <View style={styles.detailSection}>
          <Text style={styles.label}>Room Number</Text>
          <Text style={styles.value}>Room {outpass.room_number || 'N/A'}</Text>
        </View>
        <View style={styles.detailSection}>
          <Text style={styles.label}>Reason</Text>
          <Text style={styles.value}>{outpass.reason || '—'}</Text>
        </View>
        <View style={styles.detailSection}>
          <Text style={styles.label}>Status</Text>
          <View style={[styles.statusBadge, { backgroundColor: '#4CAF5020' }]}>
            <Text style={[styles.statusText, { color: '#4CAF50' }]}>{outpass.status.toUpperCase()}</Text>
          </View>
        </View>
        <View style={styles.detailSection}>
          <Text style={styles.label}>Exit (from)</Text>
          <Text style={styles.value}>
            {format(new Date(outpass.out_date), 'MMM dd, yyyy')} at {outpass.out_time}
          </Text>
        </View>
        <View style={styles.detailSection}>
          <Text style={styles.label}>Expected return</Text>
          <Text style={styles.value}>
            {format(new Date(outpass.expected_in_date), 'MMM dd, yyyy')} at {outpass.expected_in_time}
          </Text>
        </View>

        {canMarkExit && (
          <GradientButton
            style={[styles.actionButton, isSubmitting && styles.actionButtonDisabled]}
            onPress={markAsExit}
            disabled={isSubmitting}
          >
            {isSubmitting ? (
              <ActivityIndicator color={colors.surface} size="small" />
            ) : (
              <>
                <Ionicons name="checkmark-circle-outline" size={18} color={colors.surface} />
                <Text style={styles.actionButtonText}>Mark as exit</Text>
              </>
            )}
          </GradientButton>
        )}
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  centeredContent: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 24 },
  loadingText: { marginTop: 12, fontSize: 16, color: colors.textSecondary },
  errorText: { fontSize: 16, color: colors.error, marginBottom: 16 },
  backBtn: { backgroundColor: colors.primary, paddingHorizontal: 24, paddingVertical: 12, borderRadius: 8 },
  backBtnText: { color: colors.white, fontSize: 16, fontWeight: '600' },
  subHeader: { paddingHorizontal: 16, paddingTop: 12, paddingBottom: 8 },
  subHeaderTitle: { fontSize: 20, fontWeight: '700', color: colors.textHeading },
  content: { flex: 1, padding: 20 },
  verifiedBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.success + '18',
    padding: 14,
    borderRadius: 12,
    marginBottom: 20,
    gap: 10,
  },
  verifiedText: { fontSize: 15, fontWeight: '600', color: colors.success },
  detailSection: { marginBottom: 24 },
  label: { fontSize: 14, fontWeight: '600', color: colors.textMuted, marginBottom: 8 },
  value: { fontSize: 16, color: colors.text },
  statusBadge: { alignSelf: 'flex-start', paddingHorizontal: 12, paddingVertical: 6, borderRadius: 8 },
  statusText: { fontSize: 12, fontWeight: '600' },
  actionButton: {
    marginTop: 24,
    backgroundColor: colors.primary,
    paddingVertical: 14,
    borderRadius: 10,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  actionButtonDisabled: { opacity: 0.7 },
  actionButtonText: { color: colors.surface, fontSize: 16, fontWeight: '600' },
});
