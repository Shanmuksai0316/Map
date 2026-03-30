/**
 * Gate Pass Detail Screen
 * 
 * Shows detailed information about a specific gate pass.
 * Allows Guards to view pass details and take actions.
 */

import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  Alert,
  RefreshControl,
  ActivityIndicator,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useAuthStore } from '../../../shared/store/auth.store';
import { apiService } from '../../../shared/services/api.service';
import { colors } from '../../../shared/theme/colors';
import { format } from 'date-fns';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface GatePass {
  id: number;
  student_id: number;
  student_name: string;
  student_roll_no: string;
  hostel_name: string;
  reason: string;
  status: string;
  overnight: boolean;
  requested_at: string;
  valid_until: string;
  approved_at?: string;
  approved_by?: string;
  created_at: string;
}

interface GateEntry {
  id: number;
  direction: 'in' | 'out';
  out_time?: string;
  in_time?: string;
  recorded_at: string;
  recorded_by: string;
}

export const GatePassDetailScreen = ({ navigation, route }: any) => {
  const { user } = useAuthStore();
  const { gatePassId } = route.params;
  const [gatePass, setGatePass] = useState<GatePass | null>(null);
  const [gateEntries, setGateEntries] = useState<GateEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const fetchGatePassDetails = async () => {
    try {
      setLoading(true);
      setError(null);
      const [passResponse, entriesResponse] = await Promise.all([
        apiService.get<{ data: GatePass }>(`/outpasses/${gatePassId}`),
        apiService.get<{ data: GateEntry[] }>(`/gate/entries?outpass_id=${gatePassId}`)
      ]);
      
      setGatePass(passResponse.data);
      setGateEntries(entriesResponse.data);
    } catch (error) {
      console.error('Error fetching gate pass details:', error);
      setError('Failed to load gate pass details. Please try again.');
      setGatePass(null);
      setGateEntries([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchGatePassDetails();
  }, [gatePassId]);

  const onRefresh = () => {
    setRefreshing(true);
    fetchGatePassDetails();
  };

  const getStatusColor = (status: string) => {
    switch (status.toLowerCase()) {
      case 'approved':
        return colors.success;
      case 'pending':
        return colors.warning;
      case 'rejected':
        return colors.error;
      case 'expired':
        return colors.gray;
      default:
        return colors.primary;
    }
  };

  const getStatusIcon = (status: string) => {
    switch (status.toLowerCase()) {
      case 'approved':
        return 'checkmark-circle';
      case 'pending':
        return 'time';
      case 'rejected':
        return 'close-circle';
      case 'expired':
        return 'alert-circle';
      default:
        return 'help-circle';
    }
  };

  const isExpired = () => {
    if (!gatePass) return false;
    return new Date() > new Date(gatePass.valid_until);
  };

  const hasExited = () => {
    return gateEntries.some(entry => entry.direction === 'out');
  };

  const hasReturned = () => {
    return gateEntries.some(entry => entry.direction === 'in');
  };

  if (loading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color={colors.primary} />
        <Text style={styles.loadingText}>Loading gate pass details...</Text>
      </View>
    );
  }

  if (!gatePass || error) {
    return (
      <View style={styles.errorContainer}>
        <Ionicons name="alert-circle" size={48} color={colors.error} />
        <Text style={styles.errorText}>{error || 'Gate pass not found'}</Text>
        <GradientButton
          style={styles.retryButton}
          onPress={() => fetchGatePassDetails()}>
          <Text style={styles.retryButtonText}>Retry</Text>
        </GradientButton>
        <GradientButton
          style={[styles.retryButton, { backgroundColor: colors.gray, marginTop: 8 }]}
          onPress={() => navigation.goBack()}>
          <Text style={styles.retryButtonText}>Go Back</Text>
        </GradientButton>
      </View>
    );
  }

  return (
    <ScrollView
      style={styles.container}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
      }>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Gate Pass Details" />

      {/* Status Card */}
      <View style={styles.statusCard}>
        <View style={styles.statusHeader}>
          <Ionicons
            name={getStatusIcon(gatePass.status)}
            size={24}
            color={getStatusColor(gatePass.status)}
          />
          <Text style={[styles.statusText, { color: getStatusColor(gatePass.status) }]}>
            {gatePass.status.toUpperCase()}
          </Text>
        </View>
        {isExpired() && (
          <Text style={styles.expiredText}>This pass has expired</Text>
        )}
      </View>

      {/* Student Information */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Student Information</Text>
        <View style={styles.infoCard}>
          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>Name:</Text>
            <Text style={styles.infoValue}>{gatePass.student_name}</Text>
          </View>
          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>Roll Number:</Text>
            <Text style={styles.infoValue}>{gatePass.student_roll_no}</Text>
          </View>
          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>Hostel:</Text>
            <Text style={styles.infoValue}>{gatePass.hostel_name}</Text>
          </View>
        </View>
      </View>

      {/* Pass Details */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Pass Details</Text>
        <View style={styles.infoCard}>
          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>Reason:</Text>
            <Text style={styles.infoValue}>{gatePass.reason}</Text>
          </View>
          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>Overnight:</Text>
            <Text style={styles.infoValue}>
              {gatePass.overnight ? 'Yes' : 'No'}
            </Text>
          </View>
          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>Requested At:</Text>
            <Text style={styles.infoValue}>
              {format(new Date(gatePass.requested_at), 'MMM dd, yyyy HH:mm')}
            </Text>
          </View>
          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>Valid Until:</Text>
            <Text style={styles.infoValue}>
              {format(new Date(gatePass.valid_until), 'MMM dd, yyyy HH:mm')}
            </Text>
          </View>
          {gatePass.approved_at && (
            <View style={styles.infoRow}>
              <Text style={styles.infoLabel}>Approved At:</Text>
              <Text style={styles.infoValue}>
                {format(new Date(gatePass.approved_at), 'MMM dd, yyyy HH:mm')}
              </Text>
            </View>
          )}
          {gatePass.approved_by && (
            <View style={styles.infoRow}>
              <Text style={styles.infoLabel}>Approved By:</Text>
              <Text style={styles.infoValue}>{gatePass.approved_by}</Text>
            </View>
          )}
        </View>
      </View>

      {/* Gate Entries */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Gate Entries</Text>
        {gateEntries.length > 0 ? (
          <View style={styles.entriesList}>
            {gateEntries.map((entry, index) => (
              <View key={entry.id} style={styles.entryItem}>
                <View style={styles.entryHeader}>
                  <Ionicons
                    name={entry.direction === 'out' ? 'log-out' : 'log-in'}
                    size={20}
                    color={entry.direction === 'out' ? colors.error : colors.success}
                  />
                  <Text style={styles.entryDirection}>
                    {entry.direction === 'out' ? 'Exit' : 'Entry'}
                  </Text>
                  <Text style={styles.entryTime}>
                    {format(new Date(entry.recorded_at), 'HH:mm')}
                  </Text>
                </View>
                <Text style={styles.entryDetails}>
                  Recorded by: {entry.recorded_by}
                </Text>
                {entry.out_time && (
                  <Text style={styles.entryDetails}>
                    Out time: {format(new Date(entry.out_time), 'MMM dd, HH:mm')}
                  </Text>
                )}
                {entry.in_time && (
                  <Text style={styles.entryDetails}>
                    In time: {format(new Date(entry.in_time), 'MMM dd, HH:mm')}
                  </Text>
                )}
              </View>
            ))}
          </View>
        ) : (
          <View style={styles.noEntries}>
            <Ionicons name="time" size={32} color={colors.gray} />
            <Text style={styles.noEntriesText}>No gate entries recorded yet</Text>
          </View>
        )}
      </View>

      {/* Current Status */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Current Status</Text>
        <View style={styles.statusInfo}>
          <View style={styles.statusItem}>
            <Ionicons
              name={hasExited() ? 'checkmark-circle' : 'close-circle'}
              size={20}
              color={hasExited() ? colors.success : colors.gray}
            />
            <Text style={styles.statusItemText}>
              {hasExited() ? 'Student has exited' : 'Student has not exited'}
            </Text>
          </View>
          <View style={styles.statusItem}>
            <Ionicons
              name={hasReturned() ? 'checkmark-circle' : 'close-circle'}
              size={20}
              color={hasReturned() ? colors.success : colors.gray}
            />
            <Text style={styles.statusItemText}>
              {hasReturned() ? 'Student has returned' : 'Student has not returned'}
            </Text>
          </View>
        </View>
      </View>
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: colors.background,
  },
  loadingText: {
    marginTop: 16,
    fontSize: 16,
    color: colors.gray,
  },
  errorContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: colors.background,
    padding: 20,
  },
  errorText: {
    fontSize: 18,
    color: colors.error,
    marginTop: 16,
    textAlign: 'center',
  },
  retryButton: {
    marginTop: 20,
    paddingHorizontal: 24,
    paddingVertical: 12,
    backgroundColor: colors.primary,
    borderRadius: 8,
  },
  retryButtonText: {
    color: colors.white,
    fontSize: 16,
    fontWeight: '500',
  },
  statusCard: {
    margin: 16,
    padding: 16,
    backgroundColor: colors.white,
    borderRadius: 8,
    shadowColor: colors.black,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  statusHeader: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  statusText: {
    fontSize: 18,
    fontWeight: '600',
    marginLeft: 8,
  },
  expiredText: {
    fontSize: 14,
    color: colors.error,
    marginTop: 8,
    fontStyle: 'italic',
  },
  section: {
    marginHorizontal: 16,
    marginBottom: 16,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.text,
    marginBottom: 12,
  },
  infoCard: {
    backgroundColor: colors.white,
    borderRadius: 8,
    padding: 16,
    shadowColor: colors.black,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  infoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 8,
  },
  infoLabel: {
    fontSize: 14,
    color: colors.gray,
    fontWeight: '500',
    flex: 1,
  },
  infoValue: {
    fontSize: 14,
    color: colors.text,
    flex: 2,
    textAlign: 'right',
  },
  entriesList: {
    backgroundColor: colors.white,
    borderRadius: 8,
    shadowColor: colors.black,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  entryItem: {
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  entryHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 8,
  },
  entryDirection: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.text,
    marginLeft: 8,
    flex: 1,
  },
  entryTime: {
    fontSize: 14,
    color: colors.gray,
  },
  entryDetails: {
    fontSize: 12,
    color: colors.gray,
    marginTop: 4,
  },
  noEntries: {
    alignItems: 'center',
    padding: 32,
    backgroundColor: colors.white,
    borderRadius: 8,
    shadowColor: colors.black,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  noEntriesText: {
    fontSize: 16,
    color: colors.gray,
    marginTop: 12,
    textAlign: 'center',
  },
  statusInfo: {
    backgroundColor: colors.white,
    borderRadius: 8,
    padding: 16,
    shadowColor: colors.black,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  statusItem: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 8,
  },
  statusItemText: {
    fontSize: 14,
    color: colors.text,
    marginLeft: 8,
  },
});
