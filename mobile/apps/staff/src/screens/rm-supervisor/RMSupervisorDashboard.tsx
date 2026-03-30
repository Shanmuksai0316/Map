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
import { DashboardStats, Request } from '../../types';
import { APP_CONFIG } from '../../config/app.config';
import { format } from 'date-fns';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { theme } from '../../theme/theme';
import { colors } from '../../theme/colors';
import { KebabMenu } from '../../components/shared/KebabMenu';
import { getGreeting } from '../../utils/greeting.util';
import { hapticService } from '../../services/haptic.service';

interface ChecklistStatus {
  id: number;
  status: 'pending' | 'submitted' | 'approved' | 'sent_back';
  completed_tasks: number;
  total_tasks: number;
}

export const RMSupervisorDashboard = ({ navigation }: any) => {
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
        apiService.get<{ data: Request[] }>(`${APP_CONFIG.ENDPOINTS.SUPERVISOR_TICKETS}?category=repair_maintenance`).catch(() => ({ data: [] })),
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
        // Mock checklist status
        setChecklistStatus({
          id: 1,
          status: 'pending',
          completed_tasks: 0,
          total_tasks: 8,
        });
      }

      // Fetch and process requests
      const requestsData = requestsResponse.data || [];
      setRequests(requestsData.slice(0, 5)); // Recent 5 requests
      
      // Calculate ticket counts by status
      const counts = {
        open: requestsData.filter((r: Request) => r.status === 'open' || r.status === 'pending').length,
        in_progress: requestsData.filter((r: Request) => r.status === 'in_progress').length,
        completed: requestsData.filter((r: Request) => r.status === 'resolved' || r.status === 'closed' || r.status === 'completed').length,
      };
      setTicketCounts(counts);
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
      setChecklistStatus({
        id: 1,
        status: 'pending',
        completed_tasks: 0,
        total_tasks: 8,
      });
      
      // Mock requests data
      const mockRequests: Request[] = [
        {
          id: 1,
          type: 'repair_maintenance',
          title: 'Broken Window in Room 205',
          description: 'Window glass is cracked',
          status: 'open',
          priority: 'high',
          student_id: 1,
          student_name: 'John Doe',
          room_number: '205',
          hostel_name: 'Hostel A',
          created_by: 'John Doe',
          tenant_id: 'tenant_1',
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString(),
        },
        {
          id: 2,
          type: 'repair_maintenance',
          title: 'Leaky Faucet',
          description: 'Bathroom faucet leaking',
          status: 'in_progress',
          priority: 'medium',
          student_id: 2,
          student_name: 'Jane Smith',
          room_number: '101',
          hostel_name: 'Hostel A',
          created_by: 'Jane Smith',
          tenant_id: 'tenant_1',
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString(),
        },
      ];
      setRequests(mockRequests);
      setTicketCounts({
        open: 1,
        in_progress: 1,
        completed: 0,
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

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'completed': return colors.success;
      case 'in_progress': return colors.warning;
      case 'cancelled': return colors.error;
      default: return colors.textMuted;
    }
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
            <Text style={styles.appTitle}>RM Supervisor App</Text>
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
          <Text style={styles.userName}>RM Supervisor {user?.name}</Text>
        </View>
      </View>

      {/* Today's Raised Tickets */}
      <View style={styles.section}>
        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>Today's Raised Tickets</Text>
          <View style={styles.ticketCountBadge}>
            <Text style={styles.ticketCountText}>
              {ticketCounts.open + ticketCounts.in_progress + ticketCounts.completed}
            </Text>
          </View>
        </View>
        <View style={styles.ticketStatsGrid}>
          <View style={styles.ticketStatCard}>
            <Text style={styles.ticketStatValue}>{ticketCounts.open}</Text>
            <Text style={styles.ticketStatLabel}>Open</Text>
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
      {requests.length > 0 && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Recent Requests</Text>
          {requests.map((request) => (
            <TouchableOpacity
              key={request.id}
              style={styles.recentRequestCard}
              onPress={() => {
                hapticService.onButtonPress();
                navigation.navigate('RMSupervisorRequests');
              }}>
              <View style={styles.recentRequestHeader}>
                <View style={styles.recentRequestInfo}>
                  <Text style={styles.recentRequestIssue} numberOfLines={1}>
                    {request.title}
                  </Text>
                  <Text style={styles.recentRequestStudent}>
                    {request.student_name} • Room {request.room_number || 'N/A'}
                  </Text>
                </View>
                <View style={[styles.recentRequestStatusBadge, { backgroundColor: getStatusColor(request.status) }]}>
                  <Text style={styles.recentRequestStatusText}>
                    {request.status === 'open' || request.status === 'pending' ? 'OPEN' : 
                     request.status === 'in_progress' ? 'IN PROGRESS' : 
                     request.status === 'resolved' || request.status === 'closed' || request.status === 'completed' ? 'COMPLETED' : 'OPEN'}
                  </Text>
                </View>
              </View>
              <View style={styles.recentRequestFooter}>
                <Text style={styles.recentRequestTime}>
                  {format(new Date(request.created_at), 'HH:mm')}
                </Text>
                <TouchableOpacity
                  style={styles.viewButton}
                  onPress={() => {
                    hapticService.onButtonPress();
                    navigation.navigate('RMSupervisorRequests');
                  }}>
                  <Text style={styles.viewButtonText}>View</Text>
                </TouchableOpacity>
              </View>
            </TouchableOpacity>
          ))}
        </View>
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
  ticketCountBadge: {
    backgroundColor: colors.primary,
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 16,
  },
  ticketCountText: {
    color: colors.surface,
    fontSize: 14,
    fontWeight: '600',
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
  allocationCard: {
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
  allocationHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  allocationRoom: {
    fontSize: 16,
    fontWeight: '600',
    color: '#333',
  },
  statusBadge: {
    backgroundColor: '#4CAF50',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
  },
  statusText: {
    color: '#fff',
    fontSize: 10,
    fontWeight: '600',
  },
  allocationStudent: {
    fontSize: 14,
    color: '#333',
    marginBottom: 4,
  },
  allocationDetails: {
    fontSize: 12,
    color: '#666',
    marginBottom: 8,
  },
  allocationTime: {
    fontSize: 12,
    color: '#999',
  },
  availabilityCard: {
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
  availabilityHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  availabilityTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: '#333',
  },
  availabilityCount: {
    fontSize: 14,
    color: '#666',
  },
  availabilityBar: {
    height: 8,
    backgroundColor: '#f0f0f0',
    borderRadius: 4,
    marginBottom: 8,
  },
  availabilityFill: {
    height: '100%',
    backgroundColor: '#4CAF50',
    borderRadius: 4,
  },
  availabilityText: {
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
