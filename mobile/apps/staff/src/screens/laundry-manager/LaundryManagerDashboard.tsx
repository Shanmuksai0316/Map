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
import { DashboardStats } from '../../types';
import { APP_CONFIG } from '../../config/app.config';
import { format, formatDistanceToNow } from 'date-fns';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { theme } from '../../theme/theme';
import { colors } from '../../theme/colors';
import { KebabMenu } from '../../components/shared/KebabMenu';
import { getGreeting } from '../../utils/greeting.util';
import { errorHandler } from '../../utils/errorHandler';
import { ErrorState } from '../../components/shared/ErrorState';

interface LaundryRequest {
  id: number;
  student_name: string;
  student_uid?: string;
  room_no?: string;
  hostel_name?: string;
  status: string;
  service_type?: string;
  bag_count?: number;
  requested_at: string;
}

export const LaundryManagerDashboard = ({ navigation }: any) => {
  const { user, logout } = useAuthStore();
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<any>(null);
  const [recentRequests, setRecentRequests] = useState<LaundryRequest[]>([]);

  const fetchDashboardData = async () => {
    try {
      setError(null);
      const [metricsResponse, requestsResponse] = await Promise.all([
        apiService.get<{ data: DashboardStats }>(APP_CONFIG.ENDPOINTS.LAUNDRY_METRICS),
        apiService.get<{ data: LaundryRequest[] }>(
          `${APP_CONFIG.ENDPOINTS.LAUNDRY_REQUESTS}?limit=5&sort=requested_at:desc`
        ),
      ]);
      
      setStats(metricsResponse.data || {
        pending_laundry_requests: 0,
        in_progress_laundry_requests: 0,
        completed_today_laundry: 0,
        ready_for_pickup_laundry: 0,
      });

      setRecentRequests(requestsResponse.data || []);
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

  const StatCard = ({ title, value, icon, color = '#4CAF50' }: any) => (
    <View style={[styles.statCard, { borderLeftColor: color }]}>
      <View style={styles.statHeader}>
        <Ionicons name={icon} size={20} color={color} style={styles.statIcon} />
        <Text style={styles.statTitle}>{title}</Text>
      </View>
      <Text style={styles.statValue}>{value}</Text>
    </View>
  );

  return (
    <ScrollView
      style={styles.container}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
      }>
      {/* Header */}
      <View style={styles.header}>
        <View style={styles.headerTop}>
          <View style={styles.logoContainer}>
            <Image
              source={require('../../assets/map-logo.png')}
              style={styles.logoImage}
              resizeMode="contain"
            />
            <Text style={styles.appTitle}>Laundry App</Text>
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
                onPress: () => navigation.navigate('LaundryManagerHistory'),
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
          <Text style={styles.userName}>Hi Laundry Manager {user?.name}</Text>
        </View>
      </View>

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
          {/* Quick Actions */}
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>Laundry Operations</Text>
            <View style={styles.actionsGrid}>
              <TouchableOpacity
                style={styles.actionCard}
                onPress={() => navigation.navigate('LaundryRequestList')}>
                <View style={[styles.actionIconContainer, { backgroundColor: 'rgba(0, 188, 212, 0.1)' }]}>
                  <Ionicons name="shirt-outline" size={24} color="#00BCD4" />
                </View>
                <Text style={styles.actionText}>Laundry</Text>
                <Text style={styles.actionSubtext}>Requests</Text>
              </TouchableOpacity>

              <TouchableOpacity
                style={styles.actionCard}
                onPress={() => navigation.navigate('Reports')}>
                <View style={[styles.actionIconContainer, { backgroundColor: 'rgba(0, 150, 136, 0.1)' }]}>
                  <Ionicons name="bar-chart-outline" size={24} color="#009688" />
                </View>
                <Text style={styles.actionText}>Reports</Text>
                <Text style={styles.actionSubtext}>Analytics</Text>
              </TouchableOpacity>
            </View>
          </View>

          {/* Statistics */}
          {stats && (
            <View style={styles.section}>
              <Text style={styles.sectionTitle}>Laundry Status Overview</Text>
              <View style={styles.statsGrid}>
                <StatCard
                  title="Pending Requests"
                  value={stats.pending_laundry_requests?.toString() || '0'}
                  icon="time-outline"
                  color="#FF9800"
                />
                <StatCard
                  title="In Progress"
                  value={stats.in_progress_laundry_requests?.toString() || '0'}
                  icon="refresh-outline"
                  color="#2196F3"
                />
                <StatCard
                  title="Completed Today"
                  value={stats.completed_today_laundry?.toString() || '0'}
                  icon="checkmark-circle-outline"
                  color="#4CAF50"
                />
                <StatCard
                  title="Ready for Pickup"
                  value={stats.ready_for_pickup_laundry?.toString() || '0'}
                  icon="cube-outline"
                  color="#9C27B0"
                />
              </View>
            </View>
          )}

          {/* Recent Requests */}
          {recentRequests.length > 0 && (
            <View style={styles.section}>
              <Text style={styles.sectionTitle}>Recent Laundry Requests</Text>
              {recentRequests.map((request) => (
                <TouchableOpacity
                  key={request.id}
                  style={styles.requestCard}
                  onPress={() => navigation.navigate('LaundryRequestDetail', { requestId: request.id })}>
                  <View style={styles.requestHeader}>
                    <View style={styles.requestInfo}>
                      <Text style={styles.requestStudent}>
                        {request.student_name}
                        {request.room_no && ` - Room ${request.room_no}`}
                      </Text>
                      <Text style={styles.requestDetails}>
                        {request.student_uid} • {request.hostel_name || 'N/A'}
                      </Text>
                    </View>
                    <View style={styles.statusBadge}>
                      <Text style={styles.statusText}>{request.status.toUpperCase()}</Text>
                    </View>
                  </View>
                  {request.service_type && (
                    <Text style={styles.requestItems}>Service: {request.service_type}</Text>
                  )}
                  {request.bag_count && (
                    <Text style={styles.requestItems}>
                      {request.bag_count} bag{request.bag_count !== 1 ? 's' : ''}
                    </Text>
                  )}
                  <View style={styles.requestFooter}>
                    <Text style={styles.requestTime}>
                      {formatDistanceToNow(new Date(request.requested_at), { addSuffix: true })}
                    </Text>
                    <Ionicons name="chevron-forward" size={20} color={colors.textMuted} />
                  </View>
                </TouchableOpacity>
              ))}
              <TouchableOpacity
                style={styles.viewAllButton}
                onPress={() => navigation.navigate('LaundryRequestList')}>
                <Text style={styles.viewAllText}>View All Requests</Text>
                <Ionicons name="arrow-forward" size={16} color={colors.primary} />
              </TouchableOpacity>
            </View>
          )}

          {/* Quick Stats */}
          {stats && (
            <View style={styles.section}>
              <Text style={styles.sectionTitle}>Weekly Summary</Text>
              <View style={styles.quickStats}>
                <View style={styles.quickStatItem}>
                  <Text style={styles.quickStatNumber}>
                    {stats.total_laundry_requests || 0}
                  </Text>
                  <Text style={styles.quickStatLabel}>Total Requests</Text>
                </View>
                <View style={styles.quickStatItem}>
                  <Text style={styles.quickStatNumber}>
                    {stats.completed_today_laundry || 0}
                  </Text>
                  <Text style={styles.quickStatLabel}>Completed</Text>
                </View>
                <View style={styles.quickStatItem}>
                  <Text style={styles.quickStatNumber}>
                    {stats.completion_rate_laundry ? `${stats.completion_rate_laundry}%` : '0%'}
                  </Text>
                  <Text style={styles.quickStatLabel}>Completion Rate</Text>
                </View>
              </View>
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
  requestCard: {
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
  requestHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  requestStudent: {
    fontSize: 16,
    fontWeight: '600',
    color: '#333',
  },
  priorityBadge: {
    backgroundColor: '#f44336',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
  },
  priorityText: {
    color: '#fff',
    fontSize: 10,
    fontWeight: '600',
  },
  requestItems: {
    fontSize: 14,
    color: '#333',
    marginBottom: 4,
  },
  requestDetails: {
    fontSize: 12,
    color: '#666',
    marginBottom: 8,
  },
  requestFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  requestTime: {
    fontSize: 12,
    color: '#999',
  },
  requestStatus: {
    fontSize: 12,
    color: '#2196F3',
    fontWeight: '600',
  },
  machineCard: {
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
  machineHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  machineName: {
    fontSize: 16,
    fontWeight: '600',
    color: '#333',
  },
  machineStatusBadge: {
    backgroundColor: '#2196F3',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
  },
  machineStatusText: {
    color: '#fff',
    fontSize: 10,
    fontWeight: '600',
  },
  machineDetails: {
    fontSize: 12,
    color: '#666',
    marginBottom: 8,
  },
  machineProgress: {
    height: 6,
    backgroundColor: '#f0f0f0',
    borderRadius: 3,
  },
  machineProgressFill: {
    height: '100%',
    backgroundColor: '#4CAF50',
    borderRadius: 3,
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
  requestInfo: {
    flex: 1,
    marginRight: 12,
  },
  statusBadge: {
    backgroundColor: colors.primary + '20',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
  },
  statusText: {
    fontSize: 10,
    fontWeight: '600',
    color: colors.primary,
  },
  viewAllButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 16,
    marginTop: 8,
    gap: 8,
  },
  viewAllText: {
    color: colors.primary,
    fontSize: 16,
    fontWeight: '600',
  },
});
