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

interface ChecklistStatus {
  id: number;
  status: 'pending' | 'submitted' | 'approved' | 'sent_back';
  completed_tasks: number;
  total_tasks: number;
}

interface Request {
  id: number;
  title?: string;
  issue?: string;
  description?: string;
  student_name: string;
  room_number?: string;
  status: 'open' | 'pending' | 'in_progress' | 'resolved' | 'closed' | 'completed';
  created_at: string;
}

export const HKSupervisorDashboard = ({ navigation }: any) => {
  const { user, logout } = useAuthStore();
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [checklistStatus, setChecklistStatus] = useState<ChecklistStatus | null>(null);
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [requests, setRequests] = useState<Request[]>([]);
  const [ticketCounts, setTicketCounts] = useState({
    open: 0,
    in_progress: 0,
    completed: 0,
  });

  const fetchDashboardData = async () => {
    try {
      const [dashboardResponse, checklistResponse, requestsResponse] = await Promise.all([
        apiService.get<{ data: DashboardStats }>(APP_CONFIG.ENDPOINTS.DASHBOARD),
        apiService.get<{ data: any }>(`${APP_CONFIG.ENDPOINTS.ADMIN_CHECKLISTS}/today`).catch(() => null),
        apiService.get<{ data: Request[] }>(`${APP_CONFIG.ENDPOINTS.SUPERVISOR_TICKETS}?category=housekeeping`).catch(() => ({ data: [] })),
      ]);
      setStats(dashboardResponse.data);
      
      if (checklistResponse?.data) {
        setChecklistStatus({
          id: checklistResponse.data.id,
          status: checklistResponse.data.status,
          completed_tasks: checklistResponse.data.completed_tasks || 0,
          total_tasks: checklistResponse.data.total_tasks || 0,
        });
      } else {
        setChecklistStatus({
          id: 1,
          status: 'pending',
          completed_tasks: 0,
          total_tasks: 8,
        });
      }

      // Process requests
      const requestsData = requestsResponse.data || [];
      setRequests(requestsData.slice(0, 5));
      
      const counts = {
        open: requestsData.filter((r: Request) => r.status === 'open' || r.status === 'pending').length,
        in_progress: requestsData.filter((r: Request) => r.status === 'in_progress').length,
        completed: requestsData.filter((r: Request) => r.status === 'resolved' || r.status === 'closed' || r.status === 'completed').length,
      };
      setTicketCounts(counts);
    } catch (error) {
      console.error('Dashboard fetch error:', error);
      setStats({
        total_students: 150,
        present_today: 142,
        absent_today: 8,
        active_gate_passes: 12,
        pending_complaints: 5,
      });
      setChecklistStatus({
        id: 1,
        status: 'pending',
        completed_tasks: 0,
        total_tasks: 8,
      });
      setRequests([]);
      setTicketCounts({ open: 0, in_progress: 0, completed: 0 });
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

  const StatCard = ({ title, value, icon, color = theme.colors.success }: any) => (
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
            <Text style={styles.appTitle}>Housekeeping App</Text>
          </View>
          <KebabMenu
            options={[
              {
                label: 'Profile',
                icon: 'person-outline',
                onPress: () => navigation.navigate('Profile'),
              },
              {
                label: 'Announcements',
                icon: 'megaphone-outline',
                onPress: () => navigation.navigate('Announcements'),
              },
              {
                label: 'Notifications',
                icon: 'notifications-outline',
                onPress: () => navigation.navigate('Notifications'),
              },
            ]}
          />
        </View>
        <View style={styles.greetingContainer}>
          <Text style={styles.greeting}>{getGreeting()},</Text>
          <Text style={styles.userName}>Hi Repair and Management {user?.name}</Text>
        </View>
      </View>

      {/* Today's Raised Tickets */}
      <View style={styles.section}>
        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>Today's Raised Tickets</Text>
        </View>
        <View style={styles.ticketStatsGrid}>
          <View style={styles.ticketStatCard}>
            <Text style={styles.ticketStatValue}>{ticketCounts.open}</Text>
            <Text style={styles.ticketStatLabel}>Open Request</Text>
          </View>
          <View style={styles.ticketStatCard}>
            <Text style={styles.ticketStatValue}>{ticketCounts.in_progress}</Text>
            <Text style={styles.ticketStatLabel}>In Progress</Text>
          </View>
          <View style={styles.ticketStatCard}>
            <Text style={styles.ticketStatValue}>{ticketCounts.completed}</Text>
            <Text style={styles.ticketStatLabel}>Completed</Text>
          </View>
        </View>
      </View>

      {/* Recent Requests */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Recent Requests</Text>
        {requests.length > 0 ? (
          requests.map((request) => (
            <TouchableOpacity
              key={request.id}
              style={styles.recentRequestCard}
              onPress={() => navigation.navigate('HKSupervisorRequests')}>
              <View style={styles.recentRequestHeader}>
                <View style={styles.recentRequestInfo}>
                  <Text style={styles.recentRequestIssue} numberOfLines={1}>
                    {request.title || request.issue || 'No Title'}
                  </Text>
                  <Text style={styles.recentRequestStudent}>
                    {request.student_name} • Room {request.room_number || 'N/A'}
                  </Text>
                </View>
                <View style={[
                  styles.recentRequestStatusBadge,
                  { backgroundColor: request.status === 'open' || request.status === 'pending' ? '#FF9800' : 
                                      request.status === 'in_progress' ? '#2196F3' : '#4CAF50' }
                ]}>
                  <Text style={styles.recentRequestStatusText}>
                    {request.status === 'open' || request.status === 'pending' ? 'OPEN' : 
                     request.status === 'in_progress' ? 'IN PROGRESS' : 'COMPLETED'}
                  </Text>
                </View>
              </View>
              <View style={styles.recentRequestFooter}>
                <Text style={styles.recentRequestTime}>
                  {formatDistanceToNow(new Date(request.created_at), { addSuffix: true })}
                </Text>
                <TouchableOpacity
                  style={styles.viewButton}
                  onPress={() => navigation.navigate('HKSupervisorRequests')}>
                  <Text style={styles.viewButtonText}>View</Text>
                </TouchableOpacity>
              </View>
            </TouchableOpacity>
          ))
        ) : (
          <View style={styles.emptyState}>
            <Text style={styles.emptyText}>No recent requests</Text>
          </View>
        )}
      </View>

      {/* Quick Actions */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Housekeeping Operations</Text>
        <View style={styles.actionsGrid}>
          <TouchableOpacity
            style={styles.actionCard}
            onPress={() => navigation.navigate('Requests')}>
            <View style={[styles.actionIconContainer, { backgroundColor: 'rgba(76, 175, 80, 0.1)' }]}>
              <Ionicons name="broom-outline" size={24} color={theme.colors.success} />
            </View>
            <Text style={styles.actionText}>Housekeeping</Text>
            <Text style={styles.actionSubtext}>Requests</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.actionCard}
            onPress={() => navigation.navigate('Checklists')}>
            <View style={[styles.actionIconContainer, { backgroundColor: 'rgba(76, 175, 80, 0.1)' }]}>
              <Ionicons name="clipboard-outline" size={24} color={theme.colors.success} />
            </View>
            <Text style={styles.actionText}>Today's Checklist</Text>
            <Text style={styles.actionSubtext}>Daily Tasks</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.actionCard}
            onPress={() => navigation.navigate('TicketCreate')}>
            <View style={[styles.actionIconContainer, { backgroundColor: 'rgba(0, 150, 136, 0.1)' }]}>
              <Ionicons name="add-circle-outline" size={24} color="#009688" />
            </View>
            <Text style={styles.actionText}>New Ticket</Text>
            <Text style={styles.actionSubtext}>Report Issue</Text>
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

      {/* Today's Checklist Status */}
      {checklistStatus && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Today's Checklist</Text>
          <TouchableOpacity
            style={styles.checklistCard}
            onPress={() => navigation.navigate('ChecklistDetail')}
          >
            <View style={styles.checklistHeader}>
              <View style={styles.checklistInfo}>
                <Text style={styles.checklistTitle}>Daily Housekeeping Checklist</Text>
                <Text style={styles.checklistStatusText}>
                  {checklistStatus.status === 'pending' ? 'Pending' :
                   checklistStatus.status === 'submitted' ? 'Submitted' :
                   checklistStatus.status === 'approved' ? 'Approved' :
                   'Needs Review'}
                </Text>
              </View>
              <Ionicons
                name={checklistStatus.status === 'approved' ? 'checkmark-circle' : 'clipboard-outline'}
                size={32}
                color={
                  checklistStatus.status === 'approved' ? theme.colors.success :
                  checklistStatus.status === 'submitted' ? theme.colors.info :
                  checklistStatus.status === 'sent_back' ? theme.colors.warning :
                  theme.colors.textSecondary
                }
              />
            </View>
            <View style={styles.checklistProgress}>
              <View style={styles.progressBar}>
                <View
                  style={[
                    styles.progressFill,
                    {
                      width: `${Math.round((checklistStatus.completed_tasks / checklistStatus.total_tasks) * 100)}%`,
                    },
                  ]}
                />
              </View>
              <Text style={styles.progressText}>
                {checklistStatus.completed_tasks} of {checklistStatus.total_tasks} tasks completed
              </Text>
            </View>
            {checklistStatus.status === 'pending' && (
              <TouchableOpacity
                style={styles.startButton}
                onPress={() => navigation.navigate('ChecklistDetail')}
              >
                <Text style={styles.startButtonText}>Start Checklist</Text>
              </TouchableOpacity>
            )}
          </TouchableOpacity>
        </View>
      )}

      {/* Statistics */}
      {stats && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Today's Overview</Text>
          <View style={styles.statsGrid}>
            <StatCard
              title="Cleaning Requests"
              value="12"
              icon="broom-outline"
              color="#4CAF50"
            />
            <StatCard
              title="Completed"
              value="8"
              icon="checkmark-circle-outline"
              color="#2196F3"
            />
            <StatCard
              title="Pending"
              value="4"
              icon="time-outline"
              color="#FF9800"
            />
            <StatCard
              title="Staff On Duty"
              value="6"
              icon="people-outline"
              color="#9C27B0"
            />
          </View>
        </View>
      )}


      {/* Quick Stats */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Weekly Summary</Text>
        <View style={styles.quickStats}>
          <View style={styles.quickStatItem}>
            <Text style={styles.quickStatNumber}>85</Text>
            <Text style={styles.quickStatLabel}>Total Requests</Text>
          </View>
          <View style={styles.quickStatItem}>
            <Text style={styles.quickStatNumber}>78</Text>
            <Text style={styles.quickStatLabel}>Completed</Text>
          </View>
          <View style={styles.quickStatItem}>
            <Text style={styles.quickStatNumber}>92%</Text>
            <Text style={styles.quickStatLabel}>Completion Rate</Text>
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
    marginBottom: 16,
  },
  ticketStatsGrid: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 12,
  },
  ticketStatCard: {
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
  ticketStatValue: {
    fontSize: 24,
    fontWeight: 'bold',
    color: colors.primary,
    marginBottom: 4,
  },
  ticketStatLabel: {
    fontSize: 12,
    color: colors.textMuted,
    textAlign: 'center',
  },
  recentRequestCard: {
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
  recentRequestHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 12,
  },
  recentRequestInfo: {
    flex: 1,
    marginRight: 12,
  },
  recentRequestIssue: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: 4,
  },
  recentRequestStudent: {
    fontSize: 14,
    color: colors.textMuted,
  },
  recentRequestStatusBadge: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
  },
  recentRequestStatusText: {
    color: colors.surface,
    fontSize: 10,
    fontWeight: '600',
  },
  recentRequestFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  recentRequestTime: {
    fontSize: 12,
    color: colors.textMuted,
  },
  viewButton: {
    backgroundColor: colors.primary,
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 8,
  },
  viewButtonText: {
    color: colors.surface,
    fontSize: 14,
    fontWeight: '600',
  },
  emptyState: {
    backgroundColor: colors.surface,
    padding: 40,
    borderRadius: 12,
    alignItems: 'center',
  },
  emptyText: {
    fontSize: 14,
    color: colors.textMuted,
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
  requestRoom: {
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
  requestType: {
    fontSize: 14,
    color: '#333',
    marginBottom: 4,
  },
  requestDetails: {
    fontSize: 14,
    color: '#666',
    marginBottom: 8,
  },
  requestTime: {
    fontSize: 12,
    color: '#999',
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
  checklistCard: {
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
  checklistHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  checklistInfo: {
    flex: 1,
  },
  checklistTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: '#333',
    marginBottom: 4,
  },
  checklistStatusText: {
    fontSize: 14,
    color: '#666',
  },
  checklistProgress: {
    marginBottom: 12,
  },
  progressBar: {
    height: 8,
    backgroundColor: '#E0E0E0',
    borderRadius: 4,
    marginBottom: 8,
  },
  progressFill: {
    height: '100%',
    backgroundColor: '#4CAF50',
    borderRadius: 4,
  },
  progressText: {
    fontSize: 12,
    color: '#666',
    textAlign: 'center',
  },
  startButton: {
    backgroundColor: '#4CAF50',
    paddingVertical: 12,
    paddingHorizontal: 24,
    borderRadius: 8,
    alignItems: 'center',
  },
  startButtonText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '600',
  },
});
