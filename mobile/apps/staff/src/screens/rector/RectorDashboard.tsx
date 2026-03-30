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
import { useAuthStore } from '../../store/auth.store';
import { apiService } from '../../services/api.service';
import { DashboardStats, GatePass, Complaint, Notice } from '../../types';
import { APP_CONFIG } from '../../config/app.config';
import { format } from 'date-fns';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { theme } from '../../theme/theme';

export const RectorDashboard = ({ navigation }: any) => {
  const { user, logout } = useAuthStore();
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [recentGatePasses, setRecentGatePasses] = useState<GatePass[]>([]);
  const [recentComplaints, setRecentComplaints] = useState<Complaint[]>([]);
  const [notices, setNotices] = useState<Notice[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);

  const fetchDashboardData = async () => {
    try {
      const [dashboardData, gatePassesData, complaintsData, noticesData] = await Promise.all([
        apiService.get<{ data: DashboardStats }>(APP_CONFIG.ENDPOINTS.RECTOR_DASHBOARD),
        apiService.get<{ data: GatePass[] }>(`${APP_CONFIG.ENDPOINTS.RECTOR_APPROVALS}?limit=5`),
        apiService.get<{ data: Complaint[] }>(`${APP_CONFIG.ENDPOINTS.TICKETS}?limit=5`),
        apiService.get<{ data: Notice[] }>(`${APP_CONFIG.ENDPOINTS.NOTICES}?limit=3`),
      ]);

      setStats(dashboardData.data);
      setRecentGatePasses(gatePassesData.data);
      setRecentComplaints(complaintsData.data);
      setNotices(noticesData.data);
    } catch (error) {
      console.error('Dashboard fetch error:', error);
      // Mock data for demo
      setStats({
        total_students: 150,
        present_today: 142,
        absent_today: 8,
        active_gate_passes: 12,
        pending_complaints: 5,
      });
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

  const StatCard = ({ title, value, icon, color = '#4CAF50' }: any) => (
    <View style={[styles.statCard, { borderLeftColor: color }]}>
      <View style={styles.statHeader}>
        <Ionicons name={icon} size={20} color={color} style={styles.statIcon} />
        <Text style={styles.statTitle}>{title}</Text>
      </View>
      <Text style={styles.statValue}>{value}</Text>
    </View>
  );

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'approved':
      case 'active':
        return '#4CAF50';
      case 'pending':
        return '#FF9800';
      case 'rejected':
        return '#f44336';
      default:
        return '#999';
    }
  };

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'approved':
        return 'checkmark-circle-outline';
      case 'pending':
        return 'time-outline';
      case 'rejected':
        return 'close-circle-outline';
      case 'active':
        return 'walk-outline';
      case 'completed':
        return 'flag-outline';
      default:
        return 'document-text-outline';
    }
  };

  const statusConfig: Record<string, { icon: string; color: string; background: string }> = {
    high: { icon: 'alert-circle', color: theme.colors.error, background: 'rgba(244, 67, 54, 0.1)' },
    medium: { icon: 'alert-circle-outline', color: theme.colors.warning, background: 'rgba(255, 152, 0, 0.1)' },
    low: { icon: 'checkmark-circle-outline', color: theme.colors.success, background: 'rgba(76, 175, 80, 0.1)' },
    default: { icon: 'information-circle-outline', color: theme.colors.info, background: 'rgba(33, 150, 243, 0.1)' },
  };

  return (
    <ScrollView
      style={styles.container}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
      }>
      {/* Header */}
      <View style={styles.header}>
        <View>
          <Text style={styles.greeting}>Hello,</Text>
          <Text style={styles.userName}>{user?.name}</Text>
          <Text style={styles.userRole}>Rector</Text>
        </View>
        <TouchableOpacity onPress={handleLogout} style={styles.logoutButton}>
          <Text style={styles.logoutText}>Logout</Text>
        </TouchableOpacity>
      </View>

      {/* Quick Actions */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Quick Actions</Text>
        <View style={styles.actionsGrid}>
          <TouchableOpacity
            style={styles.actionCard}
            onPress={() => navigation.navigate('GatePassApprovals')}>
            <View style={[styles.actionIconContainer, { backgroundColor: 'rgba(76, 175, 80, 0.1)' }]}>
              <Ionicons name="checkmark-done-circle-outline" size={24} color={theme.colors.success} />
            </View>
            <Text style={styles.actionText}>Approvals</Text>
            <Text style={[
              styles.actionSubtext,
              stats?.pending_approvals && styles.pendingCount
            ]}>
              {stats?.pending_approvals || 0} Pending
            </Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.actionCard}
            onPress={() => navigation.navigate('RectorInsights')}>
            <View style={[styles.actionIconContainer, { backgroundColor: 'rgba(0, 150, 136, 0.1)' }]}>
              <Ionicons name="analytics-outline" size={24} color="#009688" />
            </View>
            <Text style={styles.actionText}>Insights</Text>
            <Text style={styles.actionSubtext}>Dashboard</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.actionCard}
            onPress={() => navigation.navigate('Reports')}>
            <View style={[styles.actionIconContainer, { backgroundColor: 'rgba(63, 81, 181, 0.1)' }]}>
              <Ionicons name="bar-chart-outline" size={24} color="#3F51B5" />
            </View>
            <Text style={styles.actionText}>Reports</Text>
            <Text style={styles.actionSubtext}>View All</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.actionCard}
            onPress={() => navigation.navigate('NoticeView')}>
            <View style={[styles.actionIconContainer, { backgroundColor: 'rgba(255, 107, 53, 0.1)' }]}>
              <Ionicons name="megaphone-outline" size={24} color="#FF6B35" />
            </View>
            <Text style={styles.actionText}>Notices</Text>
            <Text style={styles.actionSubtext}>View All</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.actionCard}
            onPress={() => navigation.navigate('RectorInsights')}>
            <View style={[styles.actionIconContainer, { backgroundColor: 'rgba(33, 150, 243, 0.1)' }]}>
              <Ionicons name="eye-outline" size={24} color="#2196F3" />
            </View>
            <Text style={styles.actionText}>Insights</Text>
            <Text style={styles.actionSubtext}>Student PII</Text>
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

      {/* Statistics */}
      {stats && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Campus Overview</Text>
          <View style={styles.statsGrid}>
            <StatCard
              title="Total Students"
              value={stats.total_students || 0}
              icon="people-outline"
              color="#2196F3"
            />
            <StatCard
              title="Present Today"
              value={stats.present_today || 0}
              icon="checkmark-circle-outline"
              color="#4CAF50"
            />
            <StatCard
              title="Absent Today"
              value={stats.absent_today || 0}
              icon="close-circle-outline"
              color="#f44336"
            />
            <StatCard
              title="Active Gate Passes"
              value={stats.active_gate_passes || 0}
              icon="log-out-outline"
              color="#FF9800"
            />
          </View>
        </View>
      )}

      {/* Recent Gate Passes */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Recent Gate Passes</Text>
        {recentGatePasses.length === 0 ? (
          <View style={styles.emptyState}>
            <Ionicons name="log-out-outline" size={48} color="#999" style={{ marginBottom: 16 }} />
            <Text style={styles.emptyTitle}>No Gate Passes</Text>
            <Text style={styles.emptySubtitle}>
              Recent gate pass activities will appear here
            </Text>
          </View>
        ) : (
          recentGatePasses.map((pass) => (
            <View key={pass.id} style={styles.passCard}>
              <View style={styles.passHeader}>
                <View>
                  <Text style={styles.passStudent}>{pass.student_name}</Text>
                  <Text style={styles.passHostel}>{pass.hostel_name}</Text>
                </View>
                <View
                  style={[
                    styles.statusBadge,
                    { backgroundColor: getStatusColor(pass.status) },
                  ]}>
                  <Ionicons name={getStatusIcon(pass.status)} size={12} color="#fff" style={{ marginRight: 4 }} />
                  <Text style={styles.statusText}>
                    {pass.status.toUpperCase()}
                  </Text>
                </View>
              </View>
              <Text style={styles.passPurpose}>{pass.purpose}</Text>
              <Text style={styles.passDetails}>
                {format(new Date(pass.out_date), 'MMM dd')} {pass.out_time} -{' '}
                {format(new Date(pass.expected_in_date), 'MMM dd')} {pass.expected_in_time}
              </Text>
              {pass.approved_by && (
                <Text style={styles.approvedBy}>
                  Approved by: {pass.approved_by}
                </Text>
              )}
            </View>
          ))
        )}
      </View>

      {/* Recent Complaints */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Recent Complaints</Text>
        {recentComplaints.length === 0 ? (
          <View style={styles.emptyState}>
            <Ionicons name="document-text-outline" size={48} color="#999" style={{ marginBottom: 16 }} />
            <Text style={styles.emptyTitle}>No Complaints</Text>
            <Text style={styles.emptySubtitle}>
              Recent complaints will appear here
            </Text>
          </View>
        ) : (
          recentComplaints.map((complaint) => (
            <View key={complaint.id} style={styles.complaintCard}>
              <View style={styles.complaintHeader}>
                <Text style={styles.complaintCategory}>{complaint.category}</Text>
                <View
                  style={[
                    styles.priorityBadge,
                    { backgroundColor: (statusConfig[complaint.priority]?.background) || statusConfig.default.background }
                  ]}>
                  <Ionicons
                    name={(statusConfig[complaint.priority]?.icon) || statusConfig.default.icon}
                    size={16}
                    color={(statusConfig[complaint.priority]?.color) || statusConfig.default.color}
                    style={styles.priorityIcon}
                  />
                  <Text style={styles.priorityText}>{complaint.priority.toUpperCase()}</Text>
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
          ))
        )}
      </View>

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
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
  },
  header: {
    backgroundColor: '#4CAF50',
    padding: 20,
    paddingTop: 60,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  greeting: {
    color: '#fff',
    fontSize: 16,
  },
  userName: {
    color: '#fff',
    fontSize: 24,
    fontWeight: 'bold',
  },
  userRole: {
    color: '#fff',
    fontSize: 14,
    opacity: 0.8,
  },
  logoutButton: {
    backgroundColor: 'rgba(255,255,255,0.2)',
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 8,
  },
  logoutText: {
    color: '#fff',
    fontWeight: '600',
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
  pendingCount: {
    color: '#FF9800',
    fontWeight: '600',
  },
  statsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
  },
  statCard: {
    backgroundColor: '#fff',
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
    color: '#666',
    fontWeight: '500',
  },
  statValue: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#333',
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
  statusBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 12,
  },
  statusText: {
    color: '#fff',
    fontSize: 10,
    fontWeight: '600',
  },
  passPurpose: {
    fontSize: 14,
    color: '#333',
    marginBottom: 4,
  },
  passDetails: {
    fontSize: 12,
    color: '#666',
    marginBottom: 4,
  },
  approvedBy: {
    fontSize: 12,
    color: '#4CAF50',
    fontWeight: '500',
  },
  complaintCard: {
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
  complaintHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  complaintCategory: {
    fontSize: 14,
    fontWeight: '600',
    color: '#333',
  },
  priorityBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: theme.spacing.sm,
    paddingVertical: theme.spacing.xs,
    borderRadius: theme.borderRadius.md,
  },
  priorityIcon: {
    marginRight: theme.spacing.xs,
  },
  priorityText: {
    color: '#fff',
    fontSize: 10,
    fontWeight: '600',
  },
  complaintDescription: {
    fontSize: 14,
    color: '#666',
    marginBottom: 8,
  },
  complaintStudent: {
    fontSize: 12,
    color: '#999',
    marginBottom: 4,
  },
  complaintDate: {
    fontSize: 12,
    color: '#999',
  },
  noticeCard: {
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
  noticeTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: '#333',
    marginBottom: 8,
  },
  noticeDescription: {
    fontSize: 14,
    color: '#666',
    marginBottom: 8,
  },
  noticeDate: {
    fontSize: 12,
    color: '#999',
  },
});
