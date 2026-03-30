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
import { useAuthStore } from '../../store/auth.store';
import { apiService } from '../../services/api.service';
import { DashboardStats, GatePass, Complaint, Request } from '../../types';
import { APP_CONFIG } from '../../config/app.config';
import { format } from 'date-fns';
import { StatusBadge, Card, CardContent, ComplaintSkeleton } from '../../components';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { theme } from '../../theme/theme';
import { colors } from '../../theme/colors';
import { hapticService } from '../../services/haptic.service';
import { useOfflineQueue } from '../../hooks/useOfflineQueue';
import { OfflineIndicator } from '../../components/shared/OfflineIndicator';
import { KebabMenu } from '../../components/shared/KebabMenu';
import { getGreeting } from '../../utils/greeting.util';
import { errorHandler } from '../../utils/errorHandler';
import { ErrorState } from '../../components/shared/ErrorState';

export const WardenDashboard = ({ navigation }: any) => {
  const { user, logout } = useAuthStore();
  const { addAction, isOnline } = useOfflineQueue();
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [pendingApprovals, setPendingApprovals] = useState<GatePass[]>([]);
  const [recentComplaints, setRecentComplaints] = useState<Complaint[]>([]);
  const [todayRequests, setTodayRequests] = useState<Request[]>([]);
  const [unmarkedCount, setUnmarkedCount] = useState<number>(0);
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<any>(null);

  const fetchDashboardData = async () => {
    try {
      setError(null);
      const [dashboardData, approvalsData, complaintsData, requestsData, unmarkedData] = await Promise.all([
        apiService.get<{ data: DashboardStats }>(APP_CONFIG.ENDPOINTS.DASHBOARD),
        apiService.get<{ data: GatePass[] }>(`${APP_CONFIG.ENDPOINTS.OUTPASSES}?status=pending`).catch(() => ({ data: [] })),
        apiService.get<{ data: Complaint[] }>(`${APP_CONFIG.ENDPOINTS.TICKETS}?limit=5`).catch(() => ({ data: [] })),
        apiService.get<{ data: Request[] }>(`${APP_CONFIG.ENDPOINTS.WARDEN_REQUESTS}?today=true&limit=5`).catch(() => ({ data: [] })),
        apiService.get<{ data: any[], count?: number }>(APP_CONFIG.ENDPOINTS.WARDEN_UNMARKED).catch(() => ({ data: [], count: 0 })),
      ]);

      setStats(dashboardData.data);
      setPendingApprovals(approvalsData.data);
      setRecentComplaints(complaintsData.data);
      setTodayRequests(requestsData.data);
      setUnmarkedCount(unmarkedData.count ?? unmarkedData.data?.length ?? 0);
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

  const handleApproval = async (passId: number, action: 'approve' | 'reject') => {
    try {
      const payload = {
        approved_by: user?.name,
        role: 'warden',
        action: action,
      };

      if (isOnline) {
        await apiService.put(`/outpasses/${passId}/${action}`, payload);
        Alert.alert('Success', `Gate pass ${action}d successfully`);
        fetchDashboardData();
      } else {
        await addAction('gatepass_approval', {
          pass_id: passId,
          ...payload
        });
        Alert.alert('Offline', `Gate pass ${action} queued for sync when online`);
        // Update local state optimistically
        setPendingApprovals(prev => prev.filter(pass => pass.id !== passId));
      }
    } catch (error) {
      console.error('Approval error:', error);
      // Queue for offline sync even on error
      await addAction('gatepass_approval', {
        pass_id: passId,
        approved_by: user?.name,
        role: 'warden',
        action: action,
      });
      Alert.alert('Queued', `Failed to ${action} gate pass. Added to offline queue.`);
    }
  };

  const StatCard = ({ title, value, icon, color = theme.colors.primary }: any) => (
    <Card style={styles.statCard} variant="outlined">
      <CardContent>
        <View style={styles.statHeader}>
          <Ionicons name={icon} size={20} color={color} />
          <Text style={styles.statTitle}>{title}</Text>
        </View>
        <Text style={styles.statValue}>{value}</Text>
      </CardContent>
    </Card>
  );

  return (
    <ScrollView
      style={styles.container}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
      }>
      <OfflineIndicator />
      
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
            <Text style={styles.appTitle}>Warden App</Text>
          </View>
          <KebabMenu
            options={[
              {
                label: 'Profile',
                icon: 'person-outline',
                onPress: () => {
                  hapticService.onButtonPress();
                  navigation.navigate('Profile');
                },
              },
              {
                label: 'Announcements',
                icon: 'megaphone-outline',
                onPress: () => {
                  hapticService.onButtonPress();
                  navigation.navigate('Announcements');
                },
              },
              {
                label: 'Notifications',
                icon: 'notifications-outline',
                onPress: () => {
                  hapticService.onButtonPress();
                  navigation.navigate('Notifications');
                },
              },
              {
                label: 'Logout',
                icon: 'log-out-outline',
                onPress: () => {
                  hapticService.onButtonPress();
                  handleLogout();
                },
                destructive: true,
              },
            ]}
          />
        </View>
        <View style={styles.greetingContainer}>
          <Text style={styles.greeting}>{getGreeting()},</Text>
          <Text style={styles.userName}>Warden {user?.name}</Text>
        </View>
      </View>

      {/* Requests Raised Today */}
      {todayRequests.length > 0 && (
        <View style={styles.section}>
          <View style={styles.sectionHeader}>
            <Text style={styles.sectionTitle}>Requests Raised Today</Text>
            <View style={styles.requestCountBadge}>
              <Text style={styles.requestCountText}>{todayRequests.length}</Text>
            </View>
          </View>
          
          {/* Group requests by department */}
          {(() => {
            const groupedRequests = todayRequests.reduce((acc, req) => {
              const type = req.type;
              if (!acc[type]) acc[type] = [];
              acc[type].push(req);
              return acc;
            }, {} as Record<string, Request[]>);

            const requestTypes = [
              'gate_pass', 'housekeeping', 'repair_maintenance', 'laundry',
              'sports', 'sick_leave', 'leave', 'guest_entry', 'room_change'
            ];

            const getRequestTypeLabel = (type: string) => {
              const labels: Record<string, string> = {
                'gate_pass': 'Gate Pass',
                'housekeeping': 'Housekeeping',
                'repair_maintenance': 'Repair & Maintenance',
                'laundry': 'Laundry',
                'sports': 'Sports',
                'sick_leave': 'Sick Leave Token',
                'leave': 'Leaves',
                'guest_entry': 'Guest Entry',
                'room_change': 'Room Change',
              };
              return labels[type] || type.replace('_', ' ').toUpperCase();
            };

            return (
              <View style={styles.requestsGrid}>
                {requestTypes.map((type) => {
                  const requests = groupedRequests[type] || [];
                  if (requests.length === 0) return null;
                  
                  return (
                    <View key={type} style={styles.requestColumn}>
                      <Text style={styles.requestTypeLabel}>{getRequestTypeLabel(type)}</Text>
                      {requests.map((request) => (
                        <TouchableOpacity
                          key={request.id}
                          style={styles.requestCard}
                          onPress={() => {
                            hapticService.onButtonPress();
                            navigation.navigate('WardenRequestDetail', { requestId: request.id });
                          }}>
                          <View style={styles.requestHeader}>
                            <Text style={styles.requestTitle} numberOfLines={1}>{request.title}</Text>
                            <View
                              style={[
                                styles.priorityBadge,
                                {
                                  backgroundColor:
                                    request.priority === 'high'
                                      ? colors.error
                                      : request.priority === 'medium'
                                      ? colors.warning
                                      : colors.success,
                                },
                              ]}>
                              <Text style={styles.priorityText}>{request.priority.toUpperCase()}</Text>
                            </View>
                          </View>
                          <Text style={styles.requestStudent}>
                            {request.student_name} • Room {request.room_number || 'N/A'}
                          </Text>
                          <Text style={styles.requestTime}>
                            {format(new Date(request.created_at), 'HH:mm')}
                          </Text>
                        </TouchableOpacity>
                      ))}
                    </View>
                  );
                })}
              </View>
            );
          })()}
        </View>
      )}

          {/* Unmarked Students Badge - Phase 1.5 */}
          {unmarkedCount > 0 && (
        <View style={styles.section}>
          <TouchableOpacity
            style={styles.unmarkedBadge}
            onPress={() => {
              // Navigate to attendance screen or show unmarked students list
              navigation.navigate('WardenStudents');
            }}>
            <View style={styles.unmarkedBadgeContent}>
              <Ionicons name="alert-circle" size={24} color={colors.error} />
              <View style={styles.unmarkedBadgeText}>
                <Text style={styles.unmarkedBadgeTitle}>Unmarked Students</Text>
                <Text style={styles.unmarkedBadgeSubtitle}>
                  {unmarkedCount} student{unmarkedCount !== 1 ? 's' : ''} need attendance marking
                </Text>
              </View>
              <Ionicons name="chevron-forward" size={20} color={colors.textMuted} />
            </View>
          </TouchableOpacity>
        </View>
          )}

          {/* Pending Approvals */}
          {pendingApprovals.length > 0 && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Pending Gate Pass Approvals</Text>
          {pendingApprovals.slice(0, 3).map((pass) => (
            <View key={pass.id} style={styles.approvalCard}>
              <View style={styles.approvalHeader}>
                <View>
                  <Text style={styles.approvalStudent}>{pass.student_name}</Text>
                  <Text style={styles.approvalHostel}>{pass.hostel_name}</Text>
                </View>
                <Text style={styles.approvalTime}>
                  {format(new Date(pass.created_at), 'HH:mm')}
                </Text>
              </View>
              <Text style={styles.approvalPurpose}>{pass.purpose}</Text>
              <Text style={styles.approvalDetails}>
                {format(new Date(pass.out_date), 'MMM dd')} {pass.out_time} -{' '}
                {format(new Date(pass.expected_in_date), 'MMM dd')} {pass.expected_in_time}
              </Text>
              <View style={styles.approvalActions}>
                <TouchableOpacity
                  style={[styles.approvalButton, styles.rejectButton]}
                  onPress={() => handleApproval(pass.id, 'reject')}>
                  <Text style={styles.rejectButtonText}>Reject</Text>
                </TouchableOpacity>
                <TouchableOpacity
                  style={[styles.approvalButton, styles.approveButton]}
                  onPress={() => handleApproval(pass.id, 'approve')}>
                  <Text style={styles.approveButtonText}>Approve</Text>
                </TouchableOpacity>
              </View>
            </View>
          ))}
          {pendingApprovals.length > 3 && (
            <TouchableOpacity
              style={styles.viewAllButton}
              onPress={() => navigation.navigate('GatePassApproval')}>
              <Text style={styles.viewAllText}>View All ({pendingApprovals.length})</Text>
            </TouchableOpacity>
          )}
        </View>
          )}

          {/* Recent Complaints */}
          {recentComplaints.length > 0 && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Recent Complaints</Text>
          {recentComplaints.map((complaint) => (
            <View key={complaint.id} style={styles.complaintCard}>
              <View style={styles.complaintHeader}>
                <Text style={styles.complaintCategory}>{complaint.category}</Text>
                <View
                  style={[
                    styles.complaintPriorityBadge,
                    {
                      backgroundColor:
                        complaint.priority === 'high'
                          ? colors.error
                          : complaint.priority === 'medium'
                          ? colors.warning
                          : colors.success,
                    },
                  ]}>
                  <Text style={styles.complaintPriorityText}>
                    {complaint.priority.toUpperCase()}
                  </Text>
                </View>
              </View>
              <Text style={styles.complaintDescription} numberOfLines={2}>
                {complaint.description}
              </Text>
              <Text style={styles.complaintStudent}>
                {complaint.student_name} • {complaint.hostel_name}
              </Text>
              <Text style={styles.complaintDate}>
                {format(new Date(complaint.created_at), 'MMM dd, HH:mm')}
              </Text>
            </View>
          ))}
        </View>
      )}
        </>
      )}
    </ScrollView>
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
  userRole: {
    color: colors.surface,
    fontSize: 14,
    opacity: 0.8,
  },
  section: {
    padding: 20,
  },
  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 16,
  },
  sectionTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: colors.textPrimary,
  },
  requestCountBadge: {
    backgroundColor: colors.primary,
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 16,
  },
  requestCountText: {
    color: colors.surface,
    fontSize: 14,
    fontWeight: '600',
  },
  requestsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
  },
  requestColumn: {
    width: '48%',
    marginBottom: 16,
  },
  requestTypeLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.primary,
    marginBottom: 8,
  },
  statsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
  },
  statCard: {
    backgroundColor: colors.surface,
    width: '48%',
    padding: 16,
    borderRadius: 12,
    marginBottom: 12,
    borderLeftWidth: 4,
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  statHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 8,
  },
  statIcon: {
    fontSize: 20,
    marginRight: 8,
  },
  statTitle: {
    fontSize: 14,
    color: colors.textMuted,
    fontWeight: '500',
  },
  statValue: {
    fontSize: 24,
    fontWeight: 'bold',
    color: colors.textPrimary,
  },
  approvalCard: {
    backgroundColor: colors.surface,
    padding: 16,
    borderRadius: 12,
    marginBottom: 12,
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  approvalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  approvalStudent: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.textPrimary,
  },
  approvalHostel: {
    fontSize: 14,
    color: colors.textMuted,
  },
  approvalTime: {
    fontSize: 12,
    color: colors.textMuted,
  },
  approvalPurpose: {
    fontSize: 14,
    color: colors.textPrimary,
    marginBottom: 4,
  },
  approvalDetails: {
    fontSize: 12,
    color: colors.textMuted,
    marginBottom: 12,
  },
  approvalActions: {
    flexDirection: 'row',
    justifyContent: 'flex-end',
    gap: 12,
  },
  approvalButton: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 6,
  },
  rejectButton: {
    backgroundColor: colors.error,
  },
  approveButton: {
    backgroundColor: colors.success,
  },
  rejectButtonText: {
    color: colors.surface,
    fontSize: 14,
    fontWeight: '600',
  },
  approveButtonText: {
    color: colors.surface,
    fontSize: 14,
    fontWeight: '600',
  },
  requestCard: {
    backgroundColor: colors.surface,
    padding: 16,
    borderRadius: 12,
    marginBottom: 12,
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  requestHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  requestTypeContainer: {
    backgroundColor: colors.primary,
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
  },
  requestType: {
    color: colors.surface,
    fontSize: 10,
    fontWeight: '600',
  },
  priorityBadge: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
  },
  priorityText: {
    color: colors.surface,
    fontSize: 10,
    fontWeight: '600',
  },
  requestTitle: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: 4,
  },
  requestDescription: {
    fontSize: 14,
    color: colors.textMuted,
    marginBottom: 8,
  },
  requestStudent: {
    fontSize: 12,
    color: colors.textMuted,
    marginBottom: 4,
  },
  requestTime: {
    fontSize: 12,
    color: colors.textMuted,
  },
  viewAllButton: {
    alignItems: 'center',
    padding: 12,
  },
  viewAllText: {
    color: colors.primary,
    fontSize: 14,
    fontWeight: '600',
  },
  complaintCard: {
    backgroundColor: colors.surface,
    padding: 16,
    borderRadius: 12,
    marginBottom: 12,
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  complaintHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  complaintCategory: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textPrimary,
  },
  complaintPriorityBadge: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
  },
  complaintPriorityText: {
    color: colors.surface,
    fontSize: 10,
    fontWeight: '600',
  },
  complaintDescription: {
    fontSize: 14,
    color: colors.textMuted,
    marginBottom: 8,
  },
  complaintStudent: {
    fontSize: 12,
    color: colors.textMuted,
    marginBottom: 4,
  },
  complaintDate: {
    fontSize: 12,
    color: colors.textMuted,
  },
  unmarkedBadge: {
    backgroundColor: colors.surface,
    padding: 16,
    borderRadius: 12,
    borderLeftWidth: 4,
    borderLeftColor: colors.error,
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  unmarkedBadgeContent: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  unmarkedBadgeText: {
    flex: 1,
    marginLeft: 12,
  },
  unmarkedBadgeTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: 4,
  },
  unmarkedBadgeSubtitle: {
    fontSize: 14,
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
