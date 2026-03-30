/**
 * Guard Visitor Management Screen
 * 
 * Allows Guards to view today's visitors and approve/deny them
 * based on visiting hours window.
 */

import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  Alert,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { apiService } from '../../../shared/services/api.service';
import { APP_CONFIG } from '../../../shared/config/app.config';
import { colors } from '../../../shared/theme/colors';
import { format } from 'date-fns';
import { useOfflineQueue } from '../../../shared/hooks/useOfflineQueue';
import { OfflineIndicator } from '../../../shared/components/shared/OfflineIndicator';
import { errorHandler } from '../../../shared/utils/errorHandler';
import { ErrorState } from '../../../shared/components/shared/ErrorState';
import { ActivityIndicator } from 'react-native';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface Visitor {
  id: number;
  visitor_name: string;
  visitor_phone: string;
  student_id: number;
  student_name: string;
  hostel_name: string;
  purpose: string;
  status: 'pending' | 'allowed' | 'denied';
  created_at: string;
  decided_at?: string;
  decided_by?: string;
}

interface VisitingHours {
  start_time: string; // e.g., "16:00"
  end_time: string; // e.g., "19:00"
  is_active: boolean;
}

export const GuardVisitorManagementScreen = ({ navigation }: any) => {
  const { addAction, isOnline } = useOfflineQueue();
  const [visitors, setVisitors] = useState<Visitor[]>([]);
  const [visitingHours, setVisitingHours] = useState<VisitingHours | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [filter, setFilter] = useState<'all' | 'pending' | 'allowed' | 'denied'>('pending');

  const [error, setError] = useState<any>(null);

  const fetchVisitors = async () => {
    try {
      setError(null);
      const response = await apiService.get<{ data: Visitor[], visiting_hours?: VisitingHours }>(
        `${APP_CONFIG.ENDPOINTS.GUEST_ENTRIES}?today=true&status=all`
      );
      
      setVisitors(response.data?.data || response.data || []);
      if (response.data?.visiting_hours) {
        setVisitingHours(response.data.visiting_hours);
      } else {
        // Default visiting hours
        setVisitingHours({
          start_time: '16:00',
          end_time: '19:00',
          is_active: isWithinVisitingHours('16:00', '19:00'),
        });
      }
    } catch (err) {
      console.error('Visitor fetch error:', err);
      const errorDetails = errorHandler.handleError(err);
      setError(errorDetails);
      setVisitors([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchVisitors();
    // Refresh visiting hours status every minute
    const interval = setInterval(() => {
      if (visitingHours) {
        setVisitingHours(prev => prev ? {
          ...prev,
          is_active: isWithinVisitingHours(prev.start_time, prev.end_time),
        } : null);
      }
    }, 60000);
    return () => clearInterval(interval);
  }, []);

  const isWithinVisitingHours = (start: string, end: string): boolean => {
    const now = new Date();
    const currentTime = `${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}`;
    return currentTime >= start && currentTime <= end;
  };

  const handleAllow = async (visitor: Visitor) => {
    if (visitingHours && !visitingHours.is_active) {
      Alert.alert(
        'Outside Visiting Hours',
        `Visiting hours are ${visitingHours.start_time} - ${visitingHours.end_time}.\n\nAllow anyway?`,
        [
          { text: 'Cancel', style: 'cancel' },
          { text: 'Allow Anyway', onPress: () => processAllow(visitor) },
        ]
      );
    } else {
      processAllow(visitor);
    }
  };

  const processAllow = async (visitor: Visitor) => {
    try {
      if (isOnline) {
        await apiService.post(`${APP_CONFIG.ENDPOINTS.GUEST_ENTRIES}/${visitor.id}/allow`);
        Alert.alert('Success', 'Visitor allowed entry');
        fetchVisitors();
      } else {
        await addAction('visitor_allow', { visitor_id: visitor.id });
        Alert.alert('Offline', 'Visitor allowance queued for sync when online');
        // Update local state optimistically
        setVisitors(prev => prev.map(v => 
          v.id === visitor.id 
            ? { ...v, status: 'allowed', decided_at: new Date().toISOString(), decided_by: 'You (Offline)' }
            : v
        ));
      }
    } catch (error: any) {
      console.error('Allow visitor error:', error);
      // Queue for offline sync even on error
      await addAction('visitor_allow', { visitor_id: visitor.id });
      Alert.alert('Queued', 'Failed to allow visitor. Added to offline queue.');
    }
  };

  const handleDeny = async (visitor: Visitor) => {
    Alert.alert(
      'Deny Visitor',
      `Deny entry for ${visitor.visitor_name}?`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Deny',
          style: 'destructive',
          onPress: () => processDeny(visitor),
        },
      ]
    );
  };

  const processDeny = async (visitor: Visitor) => {
    try {
      if (isOnline) {
        await apiService.post(`${APP_CONFIG.ENDPOINTS.GUEST_ENTRIES}/${visitor.id}/deny`, {
          denial_reason: 'Entry denied by guard'
        });
        Alert.alert('Success', 'Visitor entry denied');
        fetchVisitors();
      } else {
        await addAction('visitor_deny', { 
          visitor_id: visitor.id, 
          denial_reason: 'Entry denied by guard (offline)' 
        });
        Alert.alert('Offline', 'Visitor denial queued for sync when online');
        // Update local state optimistically
        setVisitors(prev => prev.map(v => 
          v.id === visitor.id 
            ? { ...v, status: 'denied', decided_at: new Date().toISOString(), decided_by: 'You (Offline)' }
            : v
        ));
      }
    } catch (error: any) {
      console.error('Deny visitor error:', error);
      // Queue for offline sync even on error
      await addAction('visitor_deny', { 
        visitor_id: visitor.id, 
        denial_reason: 'Entry denied by guard (offline)' 
      });
      Alert.alert('Queued', 'Failed to deny visitor. Added to offline queue.');
    }
  };

  const filteredVisitors = visitors.filter(v => {
    if (filter === 'all') return true;
    return v.status === filter;
  });

  return (
    <View style={styles.container}>
      <OfflineIndicator />
      
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Visitor Management" />

      {/* Visiting Hours Banner */}
      {visitingHours && (
        <View style={[
          styles.hoursBanner,
          visitingHours.is_active ? styles.hoursActive : styles.hoursInactive
        ]}>
          <Ionicons
            name={visitingHours.is_active ? 'time-outline' : 'time'}
            size={20}
            color={colors.white}
          />
          <View style={styles.hoursText}>
            <Text style={styles.hoursTitle}>
              {visitingHours.is_active ? 'Visiting Hours Active' : 'Outside Visiting Hours'}
            </Text>
            <Text style={styles.hoursSubtitle}>
              {visitingHours.start_time} - {visitingHours.end_time}
            </Text>
          </View>
        </View>
      )}

      {/* Filter Tabs */}
      <View style={styles.filterContainer}>
        {(['all', 'pending', 'allowed', 'denied'] as const).map(f => (
          <TouchableOpacity
            key={f}
            style={[styles.filterTab, filter === f && styles.filterTabActive]}
            onPress={() => setFilter(f)}>
            <Text style={[styles.filterText, filter === f && styles.filterTextActive]}>
              {f.charAt(0).toUpperCase() + f.slice(1)}
            </Text>
            {f !== 'all' && (
              <View style={[styles.badge, filter === f && styles.badgeActive]}>
                <Text style={[styles.badgeText, filter === f && styles.badgeTextActive]}>
                  {visitors.filter(v => v.status === f).length}
                </Text>
              </View>
            )}
          </TouchableOpacity>
        ))}
      </View>

      {/* Visitors List */}
      <ScrollView
        style={styles.list}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={fetchVisitors} />}
      >
        {loading ? (
          <View style={styles.loadingContainer}>
            <ActivityIndicator size="large" color={colors.primary} />
            <Text style={styles.loadingText}>Loading visitors...</Text>
          </View>
        ) : error ? (
          <ErrorState error={error} onRetry={fetchVisitors} />
        ) : filteredVisitors.length === 0 ? (
          <View style={styles.emptyState}>
            <Ionicons name="people-outline" size={64} color={colors.textMuted} />
            <Text style={styles.emptyText}>No {filter !== 'all' && filter} visitors today</Text>
          </View>
        ) : (
          filteredVisitors.map(visitor => (
            <View key={visitor.id} style={styles.visitorCard}>
              <View style={styles.visitorHeader}>
                <View style={styles.visitorInfo}>
                  <Text style={styles.visitorName}>{visitor.visitor_name}</Text>
                  <Text style={styles.visitorPhone}>{visitor.visitor_phone}</Text>
                </View>
                <View style={[
                  styles.statusBadge,
                  visitor.status === 'allowed' && styles.statusAllowed,
                  visitor.status === 'denied' && styles.statusDenied,
                  visitor.status === 'pending' && styles.statusPending,
                ]}>
                  <Text style={styles.statusText}>
                    {visitor.status.toUpperCase()}
                  </Text>
                </View>
              </View>

              <View style={styles.visitorDetails}>
                <View style={styles.detailRow}>
                  <Ionicons name="person-outline" size={16} color={colors.textMuted} />
                  <Text style={styles.detailText}>Visiting: {visitor.student_name}</Text>
                </View>
                <View style={styles.detailRow}>
                  <Ionicons name="home-outline" size={16} color={colors.textMuted} />
                  <Text style={styles.detailText}>{visitor.hostel_name}</Text>
                </View>
                <View style={styles.detailRow}>
                  <Ionicons name="document-text-outline" size={16} color={colors.textMuted} />
                  <Text style={styles.detailText}>Purpose: {visitor.purpose}</Text>
                </View>
                <View style={styles.detailRow}>
                  <Ionicons name="time-outline" size={16} color={colors.textMuted} />
                  <Text style={styles.detailText}>
                    {format(new Date(visitor.created_at), 'HH:mm')}
                  </Text>
                </View>
              </View>

              {visitor.status === 'pending' && (
                <View style={styles.actions}>
                  <GradientButton
                    style={[styles.actionButton, styles.allowButton]}
                    onPress={() => handleAllow(visitor)}>
                    <Ionicons name="checkmark-circle-outline" size={20} color={colors.white} />
                    <Text style={styles.actionButtonText}>Allow</Text>
                  </GradientButton>
                  <GradientButton
                    style={[styles.actionButton, styles.denyButton]}
                    onPress={() => handleDeny(visitor)}>
                    <Ionicons name="close-circle-outline" size={20} color={colors.white} />
                    <Text style={styles.actionButtonText}>Deny</Text>
                  </GradientButton>
                </View>
              )}

              {visitor.decided_at && (
                <View style={styles.decisionInfo}>
                  <Text style={styles.decisionText}>
                    {visitor.status === 'allowed' ? 'Allowed' : 'Denied'} by {visitor.decided_by} at{' '}
                    {format(new Date(visitor.decided_at), 'HH:mm')}
                  </Text>
                </View>
              )}
            </View>
          ))
        )}
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  hoursBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 12,
    marginHorizontal: 16,
    marginTop: 12,
    borderRadius: 8,
  },
  hoursActive: { backgroundColor: colors.success },
  hoursInactive: { backgroundColor: colors.error },
  hoursText: { marginLeft: 8, flex: 1 },
  hoursTitle: { color: colors.white, fontSize: 14, fontWeight: '600' },
  hoursSubtitle: { color: colors.white, fontSize: 12, marginTop: 2, opacity: 0.9 },
  filterContainer: {
    flexDirection: 'row',
    padding: 16,
    gap: 8,
  },
  filterTab: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: colors.background,
    borderWidth: 1,
    borderColor: colors.border,
  },
  filterTabActive: {
    backgroundColor: colors.primary,
    borderColor: colors.primary,
  },
  filterText: { fontSize: 14, color: colors.textMuted },
  filterTextActive: { color: colors.white, fontWeight: '600' },
  badge: {
    backgroundColor: colors.border,
    borderRadius: 10,
    paddingHorizontal: 6,
    paddingVertical: 2,
    marginLeft: 6,
    minWidth: 20,
    alignItems: 'center',
  },
  badgeActive: { backgroundColor: 'rgba(255,255,255,0.3)' },
  badgeText: { fontSize: 11, color: colors.textMuted, fontWeight: 'bold' },
  badgeTextActive: { color: colors.white },
  list: { flex: 1, paddingHorizontal: 16 },
  emptyState: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 60,
  },
  emptyText: { fontSize: 16, color: colors.textMuted, marginTop: 16 },
  visitorCard: {
    backgroundColor: colors.white,
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 2,
  },
  visitorHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 12,
  },
  visitorInfo: { flex: 1 },
  visitorName: { fontSize: 16, fontWeight: 'bold', color: colors.text },
  visitorPhone: { fontSize: 14, color: colors.textMuted, marginTop: 2 },
  statusBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
  },
  statusPending: { backgroundColor: colors.warning },
  statusAllowed: { backgroundColor: colors.success },
  statusDenied: { backgroundColor: colors.error },
  statusText: { fontSize: 11, fontWeight: 'bold', color: colors.white },
  visitorDetails: { marginBottom: 12, gap: 6 },
  detailRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  detailText: { fontSize: 13, color: colors.text },
  actions: { flexDirection: 'row', gap: 12 },
  actionButton: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 12,
    borderRadius: 8,
    gap: 6,
  },
  allowButton: { backgroundColor: colors.success },
  denyButton: { backgroundColor: colors.error },
  actionButtonText: { color: colors.white, fontSize: 14, fontWeight: '600' },
  decisionInfo: {
    marginTop: 8,
    paddingTop: 8,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  decisionText: { fontSize: 12, color: colors.textMuted, fontStyle: 'italic' },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
    marginTop: 100,
  },
  loadingText: {
    fontSize: 16,
    color: colors.textMuted,
    marginTop: 16,
  },
});
