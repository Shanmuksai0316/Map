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
import { DashboardStats, GatePass, Complaint, Notice } from '../../types';
import { APP_CONFIG } from '../../config/app.config';
import { format } from 'date-fns';
import { StatusBadge, Card, CardContent, GatePassSkeleton } from '../../components';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { theme } from '../../theme/theme';
import { hapticService } from '../../services/haptic.service';
import { errorHandler } from '../../utils/errorHandler';
import { ErrorState } from '../../components/shared/ErrorState';
import { colors } from '../../theme/colors';
import { KebabMenu } from '../../components/shared/KebabMenu';
import { getGreeting } from '../../utils/greeting.util';

interface ActionCardProps {
  title: string;
  subtitle: string;
  icon: string;
  onPress: () => void;
}

const requestTypeConfig: Record<string, { icon: string; color: string; background: string }> = {
  gate_pass: { icon: 'log-out-outline', color: theme.colors.info, background: 'rgba(33, 150, 243, 0.1)' },
  complaint: { icon: 'chatbubble-ellipses-outline', color: theme.colors.error, background: 'rgba(244, 67, 54, 0.1)' },
  payment: { icon: 'cash-outline', color: theme.colors.success, background: 'rgba(76, 175, 80, 0.1)' },
  attendance: { icon: 'calendar-outline', color: theme.colors.primary, background: 'rgba(255, 107, 53, 0.1)' },
  support: { icon: 'help-buoy-outline', color: theme.colors.warning, background: 'rgba(255, 152, 0, 0.1)' },
  default: { icon: 'document-text-outline', color: theme.colors.textSecondary, background: 'rgba(0, 0, 0, 0.05)' },
};

const ActionCard = ({ title, subtitle, icon, onPress }: ActionCardProps) => {
  const config = requestTypeConfig[icon] || requestTypeConfig.default;

  return (
    <TouchableOpacity
      style={styles.actionCard}
      onPress={() => {
        hapticService.onButtonPress();
        onPress();
      }}>
      <View style={[styles.actionIconContainer, { backgroundColor: config.background }] }>
        <Ionicons name={config.icon} size={24} color={config.color} />
      </View>
      <Text style={styles.actionTitle}>{title}</Text>
      <Text style={styles.actionSubtitle}>{subtitle}</Text>
    </TouchableOpacity>
  );
};

export const CampusManagerDashboard = ({ navigation }: any) => {
  const { user, logout } = useAuthStore();
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [pendingApprovals, setPendingApprovals] = useState<GatePass[]>([]);
  const [recentComplaints, setRecentComplaints] = useState<Complaint[]>([]);
  const [notices, setNotices] = useState<Notice[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<any>(null);

  const fetchDashboardData = async () => {
    try {
      setError(null);
      const [dashboardData, approvalsData, complaintsData, noticesData] = await Promise.all([
        apiService.get<{ data: DashboardStats }>(APP_CONFIG.ENDPOINTS.DASHBOARD),
        apiService.get<{ data: GatePass[] }>(`${APP_CONFIG.ENDPOINTS.OUTPASSES}?status=pending`).catch(() => ({ data: [] })),
        apiService.get<{ data: Complaint[] }>(`${APP_CONFIG.ENDPOINTS.TICKETS}?limit=5`).catch(() => ({ data: [] })),
        apiService.get<{ data: Notice[] }>(`${APP_CONFIG.ENDPOINTS.NOTICES}?limit=3`).catch(() => ({ data: [] })),
      ]);

      setStats(dashboardData.data || {
        total_students: 0,
        present_today: 0,
        absent_today: 0,
        active_gate_passes: 0,
        pending_complaints: 0,
        pending_approvals: 0,
      });
      setPendingApprovals(approvalsData.data || []);
      setRecentComplaints(complaintsData.data || []);
      setNotices(noticesData.data || []);
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
      await apiService.put(`/gate-passes/${passId}/${action}`);
      Alert.alert('Success', `Gate pass ${action}d successfully`);
      fetchDashboardData();
    } catch (error) {
      Alert.alert('Error', `Failed to ${action} gate pass`);
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
      {/* Error State */}
      {error && !loading && (
        <ErrorState error={error} onRetry={fetchDashboardData} />
      )}

      {/* Loading State */}
      {loading && !error && <GatePassSkeleton count={6} />}

      {/* Main Content */}
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
            <Text style={styles.appTitle}>Campus Manager</Text>
          </View>
          <KebabMenu
            options={[
              {
                label: 'Profile',
                icon: 'person-outline',
                onPress: () => navigation.navigate('Profile'),
              },
              {
                label: 'Notifications',
                icon: 'notifications-outline',
                onPress: () => navigation.navigate('Notifications'),
              },
              {
                label: 'Logout',
                icon: 'log-out-outline',
                onPress: handleLogout,
                destructive: true,
              },
            ]}
          />
        </View>
        <View style={styles.greetingContainer}>
          <Text style={styles.greeting}>{getGreeting()},</Text>
          <Text style={styles.userName}>{user?.name}</Text>
        </View>
      </View>

      {/* Quick Actions */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Quick Actions</Text>
        <View style={styles.actionsGrid}>
          <TouchableOpacity
            style={styles.actionCard}
            onPress={() => {
              hapticService.onButtonPress();
              navigation.navigate('GatePassApproval');
            }}>
            <View style={[styles.actionIconContainer, { backgroundColor: 'rgba(33, 150, 243, 0.1)' }]}>
              <Ionicons name="log-out-outline" size={24} color={theme.colors.info} />
            </View>
            <Text style={styles.actionText}>Gate Pass</Text>
            <Text style={styles.actionSubtext}>Approvals</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.actionCard}
            onPress={() => {
              hapticService.onButtonPress();
              navigation.navigate('StudentManagement');
            }}>
            <View style={[styles.actionIconContainer, { backgroundColor: 'rgba(76, 175, 80, 0.1)' }]}>
              <Ionicons name="people-outline" size={24} color={theme.colors.success} />
            </View>
            <Text style={styles.actionText}>Students</Text>
            <Text style={styles.actionSubtext}>Management</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.actionCard}
            onPress={() => {
              hapticService.onButtonPress();
              navigation.navigate('TicketList');
            }}>
            <View style={[styles.actionIconContainer, { backgroundColor: 'rgba(244, 67, 54, 0.1)' }]}>
              <Ionicons name="chatbubble-ellipses-outline" size={24} color={theme.colors.error} />
            </View>
            <Text style={styles.actionText}>Complaints</Text>
            <Text style={styles.actionSubtext}>Tickets</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.actionCard}
            onPress={() => {
              hapticService.onButtonPress();
              navigation.navigate('Reports');
            }}>
            <View style={[styles.actionIconContainer, { backgroundColor: 'rgba(255, 152, 0, 0.1)' }]}>
              <Ionicons name="analytics-outline" size={24} color={theme.colors.warning} />
            </View>
            <Text style={styles.actionText}>Reports</Text>
            <Text style={styles.actionSubtext}>Analytics</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.actionCard}
            onPress={() => {
              hapticService.onButtonPress();
              navigation.navigate('NoticeManagement');
            }}>
            <View style={[styles.actionIconContainer, { backgroundColor: 'rgba(156, 39, 176, 0.1)' }]}>
              <Ionicons name="megaphone-outline" size={24} color="#9C27B0" />
            </View>
            <Text style={styles.actionText}>Notices</Text>
            <Text style={styles.actionSubtext}>Management</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.actionCard}
            onPress={() => {
              hapticService.onButtonPress();
              navigation.navigate('RoomAllocation');
            }}>
            <View style={[styles.actionIconContainer, { backgroundColor: 'rgba(33, 150, 243, 0.1)' }]}>
              <Ionicons name="bed-outline" size={24} color={theme.colors.info} />
            </View>
            <Text style={styles.actionText}>Room</Text>
            <Text style={styles.actionSubtext}>Allocation</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.actionCard}
            onPress={() => {
              hapticService.onButtonPress();
              navigation.navigate('Profile');
            }}>
            <View style={[styles.actionIconContainer, { backgroundColor: 'rgba(0, 0, 0, 0.05)' }]}>
              <Ionicons name="person-outline" size={24} color={theme.colors.textSecondary} />
            </View>
            <Text style={styles.actionText}>Profile</Text>
            <Text style={styles.actionSubtext}>Settings</Text>
          </TouchableOpacity>
        </View>
      </View>

      {/* Statistics */}
      {stats && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Today's Overview</Text>
          <View style={styles.statsGrid}>
            <StatCard
              title="Total Students"
              value={stats.total_students || 0}
              icon="people-outline"
              color={theme.colors.info}
            />
            <StatCard
              title="Present Today"
              value={stats.present_today || 0}
              icon="checkmark-circle-outline"
              color={theme.colors.success}
            />
            <StatCard
              title="Absent Today"
              value={stats.absent_today || 0}
              icon="close-circle-outline"
              color={theme.colors.error}
            />
            <StatCard
              title="Active Gate Passes"
              value={stats.active_gate_passes || 0}
              icon="log-out-outline"
              color={theme.colors.warning}
            />
          </View>
        </View>
      )}

      {/* Pending Approvals */}
      {pendingApprovals.length > 0 && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Pending Approvals</Text>
          {pendingApprovals.slice(0, 3).map((pass) => (
            <Card key={pass.id} style={styles.approvalCard} variant="default">
              <CardContent>
                <View style={styles.approvalHeader}>
                  <View style={styles.approvalStudentInfo}>
                    <Ionicons name="person-outline" size={16} color={theme.colors.textSecondary} />
                    <View>
                      <Text style={styles.approvalStudent}>{pass.student_name}</Text>
                      <View style={styles.approvalHostelRow}>
                        <Ionicons name="business-outline" size={14} color={theme.colors.textMuted} />
                        <Text style={styles.approvalHostel}>{pass.hostel_name}</Text>
                      </View>
                    </View>
                  </View>
                  <View style={styles.approvalTimeContainer}>
                    <Ionicons name="time-outline" size={14} color={theme.colors.textMuted} />
                    <Text style={styles.approvalTime}>
                      {format(new Date(pass.created_at), 'HH:mm')}
                    </Text>
                  </View>
                </View>
                <Text style={styles.approvalPurpose}>{pass.purpose}</Text>
                <View style={styles.approvalDetailsRow}>
                  <Ionicons name="log-out-outline" size={14} color={theme.colors.textSecondary} />
                  <Text style={styles.approvalDetails}>
                    {format(new Date(pass.out_date), 'MMM dd')} {pass.out_time} -{' '}
                    {format(new Date(pass.expected_in_date), 'MMM dd')} {pass.expected_in_time}
                  </Text>
                </View>
                <View style={styles.approvalActions}>
                  <TouchableOpacity
                    style={[styles.approvalButton, styles.rejectButton]}
                    onPress={() => {
                      hapticService.onButtonPress();
                      handleApproval(pass.id, 'reject');
                    }}>
                    <Ionicons name="close" size={16} color={theme.colors.white} />
                    <Text style={styles.rejectButtonText}>Reject</Text>
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={[styles.approvalButton, styles.approveButton]}
                    onPress={() => {
                      hapticService.onButtonPress();
                      handleApproval(pass.id, 'approve');
                    }}>
                    <Ionicons name="checkmark" size={16} color={theme.colors.white} />
                    <Text style={styles.approveButtonText}>Approve</Text>
                  </TouchableOpacity>
                </View>
              </CardContent>
            </Card>
          ))}
          {pendingApprovals.length > 3 && (
            <TouchableOpacity
              style={styles.viewAllButton}
              onPress={() => {
                hapticService.onButtonPress();
                navigation.navigate('GatePassApprovals');
              }}>
              <Ionicons name="arrow-forward" size={16} color={theme.colors.primary} />
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
            <Card key={complaint.id} style={styles.complaintCard} variant="default">
              <CardContent>
                <View style={styles.complaintHeader}>
                  <View style={styles.categoryContainer}>
                    <Ionicons name="folder-outline" size={16} color={theme.colors.textSecondary} />
                    <Text style={styles.complaintCategory}>{complaint.category}</Text>
                  </View>
                  <StatusBadge
                    status={complaint.priority}
                    size="small"
                    variant="filled"
                  />
                </View>
                <Text style={styles.complaintDescription} numberOfLines={2}>
                  {complaint.description}
                </Text>
                <View style={styles.complaintFooter}>
                  <Ionicons name="person-outline" size={14} color={theme.colors.textMuted} />
                  <Text style={styles.complaintStudent}>
                    {complaint.student_name} • {complaint.hostel_name}
                  </Text>
                </View>
              </CardContent>
            </Card>
          ))}
        </View>
      )}

      {/* Notices */}
      {notices.length > 0 && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Recent Notices</Text>
          {notices.map((notice) => (
            <View key={notice.id} style={styles.noticeCard}>
              <Text style={styles.noticeTitle}>{notice.title}</Text>
              <Text style={styles.noticeDescription} numberOfLines={2}>
                {notice.description}
              </Text>
              <Text style={styles.noticeDate}>
                {format(new Date(notice.created_at), 'MMM dd, yyyy')}
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
    backgroundColor: theme.colors.surface,
  },
  header: {
    backgroundColor: theme.colors.primary,
    padding: theme.spacing.xl,
    paddingTop: theme.spacing.xl * 2.5,
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
    fontWeight: theme.fontWeight.bold,
  },
  section: {
    padding: theme.spacing.xl,
  },
  sectionTitle: {
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
    marginBottom: theme.spacing.md,
  },
  actionsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
  },
  actionCard: {
    backgroundColor: theme.colors.card,
    width: '48%',
    padding: theme.spacing.xl,
    borderRadius: theme.borderRadius.lg,
    alignItems: 'center',
    marginBottom: theme.spacing.md,
    ...theme.shadows.small,
  },
  actionIconContainer: {
    width: 48,
    height: 48,
    borderRadius: theme.borderRadius.full,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: theme.spacing.sm,
  },
  actionText: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
    marginBottom: theme.spacing.xs,
  },
  actionSubtext: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
  },
  statsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
  },
  statCard: {
    width: '48%',
    marginBottom: theme.spacing.md,
  },
  statHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: theme.spacing.sm,
  },
  statTitle: {
    fontSize: theme.fontSize.md,
    color: theme.colors.textSecondary,
    fontWeight: theme.fontWeight.medium,
    marginLeft: theme.spacing.xs,
  },
  statValue: {
    fontSize: theme.fontSize.xxl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
  },
  approvalCard: {
    marginBottom: theme.spacing.md,
  },
  approvalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: theme.spacing.sm,
  },
  approvalStudentInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  approvalHostelRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: theme.spacing.xs,
  },
  approvalTimeContainer: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  approvalDetailsRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: theme.spacing.md,
  },
  approvalPurpose: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
    marginBottom: theme.spacing.xs,
  },
  approvalDetails: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
    marginLeft: theme.spacing.xs,
  },
  approvalStudent: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
  },
  approvalHostel: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textMuted,
    marginLeft: theme.spacing.xs,
  },
  approvalTime: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textMuted,
    marginLeft: theme.spacing.xs,
  },
  approvalActions: {
    flexDirection: 'row',
    justifyContent: 'flex-end',
    gap: theme.spacing.md,
  },
  approvalButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.xs,
    paddingHorizontal: theme.spacing.md,
    paddingVertical: theme.spacing.sm,
    borderRadius: theme.borderRadius.md,
  },
  rejectButton: {
    backgroundColor: theme.colors.error,
  },
  approveButton: {
    backgroundColor: theme.colors.success,
  },
  rejectButtonText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
  },
  approveButtonText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
  },
  viewAllButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.xs,
    padding: theme.spacing.md,
    alignSelf: 'center',
  },
  viewAllText: {
    color: theme.colors.primary,
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
  },
  complaintCard: {
    marginBottom: theme.spacing.md,
  },
  complaintHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: theme.spacing.sm,
  },
  categoryContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: theme.spacing.xs,
  },
  complaintCategory: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
    marginLeft: theme.spacing.xs,
  },
  complaintDescription: {
    fontSize: theme.fontSize.md,
    color: theme.colors.textSecondary,
    marginBottom: theme.spacing.sm,
    lineHeight: 20,
  },
  complaintFooter: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: theme.spacing.xs,
  },
  complaintStudent: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textMuted,
    marginLeft: theme.spacing.xs,
  },
  noticeCard: {
    marginBottom: theme.spacing.md,
  },
  noticeTitle: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
    marginBottom: theme.spacing.sm,
  },
  noticeDescription: {
    fontSize: theme.fontSize.md,
    color: theme.colors.textSecondary,
    marginBottom: theme.spacing.sm,
    lineHeight: 20,
  },
  noticeDate: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textMuted,
  },
});
