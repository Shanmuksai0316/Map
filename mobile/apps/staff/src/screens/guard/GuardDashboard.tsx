import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  Alert,
  Image,
} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useAuthStore } from '../../store/auth.store';
import { apiService } from '../../services/api.service';
import { APP_CONFIG } from '../../config/app.config';
import { GatePass } from '../../types';
import { format, formatDistanceToNow } from 'date-fns';
import { useOfflineQueue } from '../../hooks/useOfflineQueue';
import { OfflineIndicator } from '../../components/shared/OfflineIndicator';
import { OfflineSyncBanner } from '../../components/OfflineSyncBanner';
import { EmergencyExitModal } from '../../components/guard/EmergencyExitModal';
import { KebabMenu } from '../../components/shared/KebabMenu';
import { getGreeting } from '../../utils/greeting.util';
import { colors } from '../../theme/colors';
import { errorHandler } from '../../utils/errorHandler';
import { ErrorState } from '../../components/shared/ErrorState';

interface GateActivity {
  id: number;
  student_name: string;
  action_type: 'exit' | 'entry';
  timestamp: string;
  pass_id?: number;
  status?: string;
}

export const GuardDashboard = ({ navigation }: any) => {
  const { user, logout } = useAuthStore();
  const { addAction, isOnline } = useOfflineQueue();
  const [activeGatePasses, setActiveGatePasses] = useState<GatePass[]>([]);
  const [recentEntries, setRecentEntries] = useState<GatePass[]>([]);
  const [recentActivities, setRecentActivities] = useState<GateActivity[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<any>(null);
  const [emergencyExitModalVisible, setEmergencyExitModalVisible] = useState(false);
  const [verificationCounts, setVerificationCounts] = useState({
    outpass: 0,
    leave: 0,
    guestEntry: 0,
  });

  const fetchDashboardData = async () => {
    try {
      setError(null);
      const [activePassesData, recentEntriesData, outpassData, leaveData, guestData, gateLogsData] = await Promise.all([
        apiService.get<{ data: GatePass[] }>(APP_CONFIG.ENDPOINTS.GUARD_OUTPASSES_ACTIVE).catch(() => ({ data: [] })),
        apiService.get<{ data: GatePass[] }>(`${APP_CONFIG.ENDPOINTS.GATE_ENTRIES}?limit=10`).catch(() => ({ data: [] })),
        apiService.get<{ data: GatePass[] }>(APP_CONFIG.ENDPOINTS.GUARD_OUTPASSES_ACTIVE).catch(() => ({ data: [] })),
        apiService.get<{ data: any[] }>(APP_CONFIG.ENDPOINTS.GUARD_LEAVES_ACTIVE).catch(() => ({ data: [] })),
        apiService.get<{ data: any[] }>(APP_CONFIG.ENDPOINTS.GUARD_GUEST_ENTRIES_ACTIVE).catch(() => ({ data: [] })),
        apiService.get<{ data: any[] }>(`${APP_CONFIG.ENDPOINTS.GATE_ENTRIES}?limit=3`).catch(() => ({ data: [] })),
      ]);

      setActiveGatePasses(activePassesData.data);
      setRecentEntries(recentEntriesData.data);
      
      setVerificationCounts({
        outpass: outpassData.data.length,
        leave: leaveData.data.length,
        guestEntry: guestData.data.length,
      });

      // Process recent activities
      const activities: GateActivity[] = gateLogsData.data.slice(0, 3).map((entry: any) => ({
        id: entry.id,
        student_name: entry.student_name || 'Unknown',
        action_type: entry.actual_in_time ? 'entry' : 'exit',
        timestamp: entry.created_at || entry.timestamp,
        pass_id: entry.pass_id,
        status: entry.status,
      }));
      setRecentActivities(activities);
    } catch (err) {
      console.error('Dashboard fetch error:', err);
      const errorDetails = errorHandler.handleError(err);
      setError(errorDetails);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchDashboardData();
  }, []);

  const onRefresh = () => {
    setRefreshing(true);
    fetchDashboardData();
  };

  const handleLogout = () => {
    Alert.alert(
      'Logout',
      'Are you sure you want to logout?',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Logout',
          style: 'destructive',
          onPress: async () => {
            await logout();
          },
        },
      ]
    );
  };

  const handleGateEntry = async (passId: number, action: 'exit' | 'entry') => {
    const payload = {
      timestamp: new Date().toISOString(),
      guard_name: user?.name,
    };

    try {
      if (isOnline) {
        await apiService.post(`${APP_CONFIG.ENDPOINTS.GATE_ENTRIES}`, {
          outpass_id: passId,
          action: action,
          ...payload,
        });
        Alert.alert('Success', `Gate ${action} recorded successfully`);
        fetchDashboardData();
      } else {
        await addAction('gate_entry', {
          outpass_id: passId,
          action: action,
          ...payload,
        });
        Alert.alert('Queued', `No network. Gate ${action} will sync when connected.`);
      }
    } catch (error) {
      await addAction('gate_entry', {
        outpass_id: passId,
        action: action,
        ...payload,
      });
      Alert.alert('Queued', `Failed to record gate ${action}. Added to offline queue.`);
    }
  };

  const handleEmergencyExit = async (studentId: number, reason: string) => {
    try {
      if (!isOnline) {
        Alert.alert(
          'Offline Mode',
          'Emergency exit requires online connection. Please connect to the internet and try again.',
          [{ text: 'OK' }]
        );
        return;
      }

      // Validate reason length (backend requires min 10 chars)
      if (reason.trim().length < 10) {
        Alert.alert('Invalid Reason', 'Please provide a detailed reason (minimum 10 characters)');
        return;
      }

      // Use correct emergency exit endpoint per PRD §4.4
      const response = await apiService.post(`${APP_CONFIG.ENDPOINTS.GATE_ENTRIES.replace('/entries', '/emergency-exit')}`, {
        student_id: studentId,
        note: reason,
        hostel_id: user?.hostel_id, // Optional, will use student's hostel if not provided
      });

      Alert.alert(
        'Success',
        'Emergency exit recorded successfully. Rector has been notified and can convert this to an approved leave within 24 hours.',
        [{ text: 'OK', onPress: () => {
          setEmergencyExitModalVisible(false);
          fetchDashboardData();
        }}]
      );
    } catch (error: any) {
      console.error('Emergency exit error:', error);
      const errorMessage = error.response?.data?.message || error.message || 'Failed to record emergency exit';
      
      // Show specific validation errors
      if (error.response?.status === 422) {
        const validationErrors = error.response?.data?.errors;
        if (validationErrors?.note) {
          Alert.alert('Validation Error', validationErrors.note[0]);
        } else {
          Alert.alert('Validation Error', errorMessage);
        }
      } else {
        Alert.alert('Error', errorMessage);
      }
    }
  };

  const isPassExpired = (pass: GatePass) => {
    const now = new Date();
    const expectedIn = new Date(`${pass.expected_in_date}T${pass.expected_in_time}`);
    return now > expectedIn;
  };

  return (
    <>
      <OfflineSyncBanner />
      <ScrollView
      style={styles.container}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
      }>
      {/* Error State */}
      {error && !loading && (
        <ErrorState error={error} onRetry={fetchDashboardData} />
      )}

      {/* Loading State */}
      {loading && !error && (
        <View style={styles.loadingContainer}>
          <Text style={styles.loadingText}>Loading dashboard...</Text>
        </View>
      )}

      {/* Content */}
      {!loading && !error && (
        <>
      {/* Header */}
      <View style={styles.header}>
        <View style={styles.headerTop}>
          <View style={styles.logoContainer}>
            <Image
              source={require('../../assets/map-logo.png')}
              style={styles.logoImage}
              resizeMode="contain"
            />
            <Text style={styles.appTitle}>Guard App</Text>
          </View>
          <KebabMenu
            options={[
              {
                label: 'Profile',
                icon: 'person-outline',
                onPress: () => navigation.navigate('Profile'),
              },
              {
                label: 'History',
                icon: 'time-outline',
                onPress: () => navigation.navigate('GuardHistory'),
              },
              {
                label: 'Notifications',
                icon: 'notifications-outline',
                onPress: () => navigation.navigate('Notifications'),
              },
              {
                label: 'Announcements',
                icon: 'megaphone-outline',
                onPress: () => navigation.navigate('Announcements'),
              },
            ]}
          />
        </View>
        <View style={styles.greetingContainer}>
          <Text style={styles.greeting}>{getGreeting()},</Text>
          <Text style={styles.userName}>Hi Security Guard {user?.name}</Text>
        </View>
      </View>

      {/* Verification Breakdown */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Verification Breakdown</Text>
        <View style={styles.verificationGrid}>
          <View style={styles.verificationCard}>
            <Text style={styles.verificationNumber}>{verificationCounts.outpass}</Text>
            <Text style={styles.verificationLabel}>Outpass</Text>
          </View>
          <View style={styles.verificationCard}>
            <Text style={styles.verificationNumber}>{verificationCounts.leave}</Text>
            <Text style={styles.verificationLabel}>Leave</Text>
          </View>
          <View style={styles.verificationCard}>
            <Text style={styles.verificationNumber}>{verificationCounts.guestEntry}</Text>
            <Text style={styles.verificationLabel}>Guest Entry</Text>
          </View>
        </View>
      </View>

      {/* Recent Activity Feed */}
      {recentActivities.length > 0 && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Recent Activity</Text>
          {recentActivities.map((activity) => (
            <View key={activity.id} style={styles.activityCard}>
              <View style={styles.activityHeader}>
                <Text style={styles.activityStudent}>{activity.student_name}</Text>
                <Text style={styles.activityTime}>
                  {formatDistanceToNow(new Date(activity.timestamp), { addSuffix: true })}
                </Text>
              </View>
              <Text style={styles.activityType}>
                {activity.action_type === 'exit' ? 'Exited' : 'Entered'} Campus
              </Text>
              {activity.pass_id && (
                <Text style={styles.activityPassId}>Pass ID: {activity.pass_id}</Text>
              )}
            </View>
          ))}
        </View>
      )}

      {/* Quick Actions */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Gate Operations</Text>
        <View style={styles.actionsGrid}>
          <TouchableOpacity
            style={styles.actionCard}
            onPress={() => navigation.navigate('QRScanner')}>
            <View style={[styles.actionIconContainer, { backgroundColor: 'rgba(233, 30, 99, 0.1)' }]}>
              <Ionicons name="qr-code-outline" size={24} color="#E91E63" />
            </View>
            <Text style={styles.actionText}>QR Scanner</Text>
            <Text style={styles.actionSubtext}>Scan & Verify</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.actionCard}
            onPress={() => navigation.navigate('ManualEntry')}>
            <View style={[styles.actionIconContainer, { backgroundColor: 'rgba(0, 150, 136, 0.1)' }]}>
              <Ionicons name="create-outline" size={24} color="#009688" />
            </View>
            <Text style={styles.actionText}>Manual Entry</Text>
            <Text style={styles.actionSubtext}>Enter Details</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.actionCard}
            onPress={() => navigation.navigate('ActivePasses')}>
            <View style={[styles.actionIconContainer, { backgroundColor: 'rgba(63, 81, 181, 0.1)' }]}>
              <Ionicons name="log-in-outline" size={24} color="#3F51B5" />
            </View>
            <Text style={styles.actionText}>Active Passes</Text>
            <Text style={styles.actionSubtext}>View All</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.actionCard}
            onPress={() => navigation.navigate('GateLogs')}>
            <View style={[styles.actionIconContainer, { backgroundColor: 'rgba(96, 125, 139, 0.1)' }]}>
              <Ionicons name="list-outline" size={24} color="#607D8B" />
            </View>
            <Text style={styles.actionText}>Gate Logs</Text>
            <Text style={styles.actionSubtext}>Today's Activity</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.actionCard}
            onPress={() => setEmergencyExitModalVisible(true)}>
            <View style={[styles.actionIconContainer, { backgroundColor: 'rgba(244, 67, 54, 0.1)' }]}>
              <Ionicons name="warning-outline" size={24} color="#F44336" />
            </View>
            <Text style={styles.actionText}>Emergency</Text>
            <Text style={styles.actionSubtext}>Exit</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.actionCard}
            onPress={() => navigation.navigate('GuardVisitorManagement')}>
            <View style={[styles.actionIconContainer, { backgroundColor: 'rgba(76, 175, 80, 0.1)' }]}>
              <Ionicons name="people-outline" size={24} color="#4CAF50" />
            </View>
            <Text style={styles.actionText}>Visitors</Text>
            <Text style={styles.actionSubtext}>Management</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.actionCard}
            onPress={() => navigation.navigate('Profile')}>
            <View style={[styles.actionIconContainer, { backgroundColor: 'rgba(66, 66, 66, 0.1)' }]}>
              <Ionicons name="person-outline" size={24} color="#424242" />
            </View>
            <Text style={styles.actionText}>Profile</Text>
            <Text style={styles.actionSubtext}>Settings</Text>
          </TouchableOpacity>
        </View>
      </View>

      {/* Active Gate Passes */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Active Gate Passes ({activeGatePasses.length})</Text>
        {activeGatePasses.length === 0 ? (
          <View style={styles.emptyState}>
            <Text style={styles.emptyIcon}>🚪</Text>
            <Text style={styles.emptyTitle}>No Active Passes</Text>
            <Text style={styles.emptySubtitle}>
              No students currently outside the campus
            </Text>
          </View>
        ) : (
          activeGatePasses.map((pass) => (
            <View key={pass.id} style={[
              styles.passCard,
              isPassExpired(pass) && styles.expiredCard
            ]}>
              <View style={styles.passHeader}>
                <View>
                  <Text style={styles.passStudent}>{pass.student_name}</Text>
                  <Text style={styles.passHostel}>{pass.hostel_name}</Text>
                </View>
                <View style={styles.passStatus}>
                  {isPassExpired(pass) ? (
                    <View style={styles.expiredBadge}>
                      <Text style={styles.expiredText}>EXPIRED</Text>
                    </View>
                  ) : (
                    <View style={styles.activeBadge}>
                      <Text style={styles.activeText}>ACTIVE</Text>
                    </View>
                  )}
                </View>
              </View>
              
              <Text style={styles.passPurpose}>{pass.purpose}</Text>
              
              <View style={styles.passDetails}>
                <Text style={styles.passDetail}>
                  <Text style={styles.passDetailLabel}>Out:</Text> {format(new Date(pass.out_date), 'MMM dd')} {pass.out_time}
                </Text>
                <Text style={styles.passDetail}>
                  <Text style={styles.passDetailLabel}>Expected In:</Text> {format(new Date(pass.expected_in_date), 'MMM dd')} {pass.expected_in_time}
                </Text>
              </View>

              <View style={styles.passActions}>
                <TouchableOpacity
                  style={[styles.actionButton, styles.exitButton]}
                  onPress={() => handleGateEntry(pass.id, 'exit')}>
                  <Text style={styles.exitButtonText}>Record Exit</Text>
                </TouchableOpacity>
                <TouchableOpacity
                  style={[styles.actionButton, styles.entryButton]}
                  onPress={() => handleGateEntry(pass.id, 'entry')}>
                  <Text style={styles.entryButtonText}>Record Entry</Text>
                </TouchableOpacity>
              </View>
            </View>
          ))
        )}
      </View>

      {/* Recent Entries */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Recent Gate Activity</Text>
        {recentEntries.length === 0 ? (
          <View style={styles.emptyState}>
            <Text style={styles.emptyIcon}>📋</Text>
            <Text style={styles.emptyTitle}>No Recent Activity</Text>
            <Text style={styles.emptySubtitle}>
              Gate entry/exit logs will appear here
            </Text>
          </View>
        ) : (
          recentEntries.map((pass) => (
            <View key={pass.id} style={styles.logCard}>
              <View style={styles.logHeader}>
                <Text style={styles.logStudent}>{pass.student_name}</Text>
                <Text style={styles.logTime}>
                  {format(new Date(pass.created_at), 'HH:mm')}
                </Text>
              </View>
              <Text style={styles.logAction}>
                {pass.actual_in_time ? 'Entered' : 'Exited'} Campus
              </Text>
              <Text style={styles.logHostel}>{pass.hostel_name}</Text>
            </View>
          ))
        )}
      </View>

      {/* Quick Stats */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Today's Summary</Text>
        <View style={styles.quickStats}>
          <View style={styles.quickStatItem}>
            <Text style={styles.quickStatNumber}>{activeGatePasses.length}</Text>
            <Text style={styles.quickStatLabel}>Active Passes</Text>
          </View>
          <View style={styles.quickStatItem}>
            <Text style={styles.quickStatNumber}>{recentEntries.length}</Text>
            <Text style={styles.quickStatLabel}>Total Entries</Text>
          </View>
          <View style={styles.quickStatItem}>
            <Text style={styles.quickStatNumber}>
              {activeGatePasses.filter(isPassExpired).length}
            </Text>
            <Text style={styles.quickStatLabel}>Expired Passes</Text>
          </View>
        </View>
      </View>
        </>
      )}
    </ScrollView>
    <EmergencyExitModal
      visible={emergencyExitModalVisible}
      onClose={() => setEmergencyExitModalVisible(false)}
      onEmergencyExit={handleEmergencyExit}
    />
    </>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  header: {
    backgroundColor: colors.primary,
    padding: 20,
    paddingTop: 60,
  },
  headerTop: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 16,
  },
  logoContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  logoImage: {
    width: 40,
    height: 40,
    marginRight: 12,
  },
  appTitle: {
    color: colors.surface,
    fontSize: 20,
    fontWeight: 'bold',
  },
  greetingContainer: {
    marginTop: 8,
  },
  greeting: {
    color: colors.surface,
    fontSize: 18,
    fontWeight: '600',
    marginBottom: 4,
  },
  userName: {
    color: colors.surface,
    fontSize: 24,
    fontWeight: 'bold',
  },
  section: {
    padding: 20,
  },
  sectionTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#333',
    marginBottom: 16,
  },
  actionsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
  },
  actionCard: {
    backgroundColor: '#fff',
    width: '48%',
    padding: 20,
    borderRadius: 12,
    alignItems: 'center',
    marginBottom: 16,
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  actionIconContainer: {
    width: 48,
    height: 48,
    borderRadius: 24,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 8,
  },
  actionText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#333',
    marginBottom: 2,
  },
  actionSubtext: {
    fontSize: 12,
    color: '#666',
  },
  emptyState: {
    backgroundColor: '#fff',
    padding: 40,
    borderRadius: 12,
    alignItems: 'center',
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  emptyIcon: {
    fontSize: 48,
    marginBottom: 16,
  },
  emptyTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#333',
    marginBottom: 8,
  },
  emptySubtitle: {
    fontSize: 14,
    color: '#666',
    textAlign: 'center',
  },
  passCard: {
    backgroundColor: '#fff',
    padding: 16,
    borderRadius: 12,
    marginBottom: 12,
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  expiredCard: {
    borderLeftWidth: 4,
    borderLeftColor: '#f44336',
  },
  passHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  passStudent: {
    fontSize: 16,
    fontWeight: '600',
    color: '#333',
  },
  passHostel: {
    fontSize: 14,
    color: '#666',
  },
  passStatus: {
    alignItems: 'flex-end',
  },
  activeBadge: {
    backgroundColor: '#4CAF50',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
  },
  expiredBadge: {
    backgroundColor: '#f44336',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
  },
  activeText: {
    color: '#fff',
    fontSize: 10,
    fontWeight: '600',
  },
  expiredText: {
    color: '#fff',
    fontSize: 10,
    fontWeight: '600',
  },
  passPurpose: {
    fontSize: 14,
    color: '#333',
    marginBottom: 8,
  },
  passDetails: {
    marginBottom: 12,
  },
  passDetail: {
    fontSize: 12,
    color: '#666',
    marginBottom: 2,
  },
  passDetailLabel: {
    fontWeight: '600',
  },
  passActions: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 12,
  },
  actionButton: {
    flex: 1,
    paddingVertical: 12,
    borderRadius: 8,
    alignItems: 'center',
  },
  exitButton: {
    backgroundColor: '#FF9800',
  },
  entryButton: {
    backgroundColor: '#4CAF50',
  },
  exitButtonText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '600',
  },
  entryButtonText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '600',
  },
  logCard: {
    backgroundColor: '#fff',
    padding: 12,
    borderRadius: 8,
    marginBottom: 8,
    elevation: 1,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.1,
    shadowRadius: 2,
  },
  logHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 4,
  },
  logStudent: {
    fontSize: 14,
    fontWeight: '600',
    color: '#333',
  },
  logTime: {
    fontSize: 12,
    color: '#999',
  },
  logAction: {
    fontSize: 12,
    color: '#4CAF50',
    fontWeight: '500',
    marginBottom: 2,
  },
  logHostel: {
    fontSize: 12,
    color: '#666',
  },
  quickStats: {
    backgroundColor: '#fff',
    padding: 20,
    borderRadius: 12,
    flexDirection: 'row',
    justifyContent: 'space-around',
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  quickStatItem: {
    alignItems: 'center',
  },
  quickStatNumber: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#4CAF50',
    marginBottom: 4,
  },
  quickStatLabel: {
    fontSize: 12,
    color: '#666',
    textAlign: 'center',
  },
  verificationGrid: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 12,
  },
  verificationCard: {
    flex: 1,
    backgroundColor: colors.surface,
    padding: 16,
    borderRadius: 12,
    alignItems: 'center',
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  verificationNumber: {
    fontSize: 24,
    fontWeight: 'bold',
    color: colors.primary,
    marginBottom: 4,
  },
  verificationLabel: {
    fontSize: 12,
    color: colors.textMuted,
    textAlign: 'center',
  },
  activityCard: {
    backgroundColor: colors.surface,
    padding: 12,
    borderRadius: 8,
    marginBottom: 8,
    elevation: 1,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.1,
    shadowRadius: 2,
  },
  activityHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 4,
  },
  activityStudent: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textPrimary,
  },
  activityTime: {
    fontSize: 12,
    color: colors.textMuted,
  },
  activityType: {
    fontSize: 12,
    color: colors.primary,
    fontWeight: '500',
    marginBottom: 2,
  },
  activityPassId: {
    fontSize: 12,
    color: colors.textMuted,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
  },
  loadingText: {
    fontSize: 16,
    color: colors.textSecondary,
    marginTop: 16,
  },
});
