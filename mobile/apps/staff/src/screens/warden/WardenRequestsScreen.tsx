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
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useAuthStore } from '../../store/auth.store';
import { apiService } from '../../services/api.service';
import { Request } from '../../types';
import { APP_CONFIG } from '../../config/app.config';
import { colors } from '../../theme/colors';
import { format } from 'date-fns';

export const WardenRequestsScreen = ({ navigation }: any) => {
  const { user } = useAuthStore();
  const [requests, setRequests] = useState<Request[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState<'all' | 'housekeeping' | 'repair_maintenance' | 'leave' | 'outpass' | 'guest_entry'>('all');

  const fetchRequests = async () => {
    try {
      const response = await apiService.get<{ data: Request[] }>(APP_CONFIG.ENDPOINTS.WARDEN_REQUESTS);
      setRequests(response.data);
    } catch (error) {
      console.error('Requests fetch error:', error);
      // Mock data for demo
      setRequests([
        {
          id: 1,
          type: 'housekeeping',
          title: 'Room Cleaning Required',
          description: 'Room 101 needs immediate cleaning - mess created during maintenance',
          status: 'pending',
          priority: 'high',
          student_id: 1,
          student_name: 'John Doe',
          hostel_name: 'Hostel A',
          created_by: 'John Doe',
          tenant_id: 'tenant_1',
          created_at: '2025-10-15T08:30:00Z',
          updated_at: '2025-10-15T08:30:00Z',
        },
        {
          id: 2,
          type: 'repair_maintenance',
          title: 'Broken Window in Room 205',
          description: 'Window glass is cracked and needs replacement urgently',
          status: 'in_progress',
          priority: 'high',
          student_id: 2,
          student_name: 'Jane Smith',
          hostel_name: 'Hostel A',
          created_by: 'Jane Smith',
          assigned_to: 'Maintenance Team',
          tenant_id: 'tenant_1',
          created_at: '2025-10-15T09:15:00Z',
          updated_at: '2025-10-15T11:00:00Z',
        },
        {
          id: 3,
          type: 'outpass',
          title: 'Medical Emergency - Need to go home',
          description: 'Family member hospitalized, need emergency gate pass',
          status: 'completed',
          priority: 'high',
          student_id: 3,
          student_name: 'Mike Johnson',
          hostel_name: 'Hostel A',
          created_by: 'Mike Johnson',
          tenant_id: 'tenant_1',
          created_at: '2025-10-15T07:00:00Z',
          updated_at: '2025-10-15T07:30:00Z',
          resolved_at: '2025-10-15T07:30:00Z',
        },
        {
          id: 4,
          type: 'guest_entry',
          title: 'Parents visiting for weekend',
          description: 'Parents will visit this weekend, need guest entry approval',
          status: 'pending',
          priority: 'medium',
          student_id: 4,
          student_name: 'Sarah Wilson',
          hostel_name: 'Hostel A',
          created_by: 'Sarah Wilson',
          tenant_id: 'tenant_1',
          created_at: '2025-10-15T10:00:00Z',
          updated_at: '2025-10-15T10:00:00Z',
        },
        {
          id: 5,
          type: 'repair_maintenance',
          title: 'Leaky faucet in bathroom',
          description: 'Bathroom faucet is leaking continuously, needs repair',
          status: 'completed',
          priority: 'medium',
          student_id: 5,
          student_name: 'David Brown',
          hostel_name: 'Hostel B',
          created_by: 'David Brown',
          assigned_to: 'Maintenance Team',
          tenant_id: 'tenant_1',
          created_at: '2025-10-14T14:00:00Z',
          updated_at: '2025-10-15T09:00:00Z',
          resolved_at: '2025-10-15T09:00:00Z',
        },
      ]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchRequests();
  }, []);

  const onRefresh = () => {
    setRefreshing(true);
    fetchRequests();
  };

  const handleStatusChange = async (requestId: number, newStatus: 'pending' | 'in_progress' | 'completed' | 'cancelled') => {
    Alert.alert(
      'Update Request Status',
      `Change status to ${newStatus}?`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Update',
          onPress: async () => {
            try {
              await apiService.put(`${APP_CONFIG.ENDPOINTS.WARDEN_REQUESTS}/${requestId}/status`, {
                status: newStatus,
                updated_by: user?.name,
              });

              setRequests(prev =>
                prev.map(request =>
                  request.id === requestId
                    ? { ...request, status: newStatus, updated_at: new Date().toISOString() }
                    : request
                )
              );

              Alert.alert('Success', 'Request status updated successfully');
            } catch (error) {
              Alert.alert('Error', 'Failed to update request status');
            }
          },
        },
      ]
    );
  };

  const filteredRequests = requests.filter(request => {
    if (filter === 'all') return true;
    return request.type === filter;
  });

  const getTypeIcon = (type: string) => {
    switch (type) {
      case 'housekeeping': return 'broom-outline';
      case 'repair_maintenance': return 'construct-outline';
      case 'outpass': return 'log-out-outline';
      case 'guest_entry': return 'people-outline';
      default: return 'document-text-outline';
    }
  };

  const getPriorityColor = (priority: string) => {
    switch (priority) {
      case 'high': return colors.error;
      case 'medium': return colors.warning;
      default: return colors.success;
    }
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'completed': return colors.success;
      case 'in_progress': return colors.warning;
      case 'cancelled': return colors.error;
      default: return colors.textMuted;
    }
  };

  const FilterButton = ({ status, label, count }: { status: string; label: string; count: number }) => (
    <TouchableOpacity
      style={[styles.filterButton, filter === status && styles.filterButtonActive]}
      onPress={() => setFilter(status as any)}>
      <Text style={[styles.filterButtonText, filter === status && styles.filterButtonTextActive]}>
        {label} ({count})
      </Text>
    </TouchableOpacity>
  );

  const RequestCard = ({ request }: { request: Request }) => (
    <TouchableOpacity
      style={styles.requestCard}
      onPress={() => navigation.navigate('WardenRequestDetail', { requestId: request.id })}>
      <View style={styles.cardHeader}>
        <View style={styles.typeContainer}>
          <Ionicons name={getTypeIcon(request.type)} size={20} color={colors.primary} style={styles.typeIcon} />
          <Text style={styles.typeText}>{request.type.replace('_', ' ').toUpperCase()}</Text>
        </View>
        <View style={[styles.statusBadge, { backgroundColor: getStatusColor(request.status) }]}>
          <Text style={styles.statusText}>{request.status.toUpperCase()}</Text>
        </View>
      </View>

      <Text style={styles.title}>{request.title}</Text>
      <Text style={styles.description} numberOfLines={2}>{request.description}</Text>

      <View style={styles.priorityBadge}>
        <Text style={[styles.priorityText, { color: getPriorityColor(request.priority) }]}>
          {request.priority.toUpperCase()} PRIORITY
        </Text>
      </View>

      <Text style={styles.studentInfo}>
        {request.student_name} • {request.hostel_name}
      </Text>

      <Text style={styles.timestamp}>
        {format(new Date(request.created_at), 'MMM dd, HH:mm')}
      </Text>

      {request.assigned_to && (
        <Text style={styles.assignedTo}>Assigned to: {request.assigned_to}</Text>
      )}

      {request.status !== 'completed' && request.status !== 'cancelled' && (
        <View style={styles.actions}>
          {request.status === 'pending' && (
            <TouchableOpacity
              style={[styles.actionButton, styles.startButton]}
              onPress={() => handleStatusChange(request.id, 'in_progress')}>
              <Text style={styles.startButtonText}>Start</Text>
            </TouchableOpacity>
          )}
          <TouchableOpacity
            style={[styles.actionButton, styles.resolveButton]}
            onPress={() => handleStatusChange(request.id, 'completed')}>
            <Text style={styles.resolveButtonText}>Resolve</Text>
          </TouchableOpacity>
        </View>
      )}
    </TouchableOpacity>
  );

  const getFilterCount = (filterType: string) => {
    if (filterType === 'all') return requests.length;
    return requests.filter(r => r.type === filterType).length;
  };

  return (
    <View style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <View>
          <Text style={styles.greeting}>Requests</Text>
          <Text style={styles.subGreeting}>Manage all student requests</Text>
        </View>
      </View>

      {/* Filters */}
      <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.filters}>
        <FilterButton status="all" label="All" count={getFilterCount('all')} />
        <FilterButton status="housekeeping" label="Housekeeping" count={getFilterCount('housekeeping')} />
        <FilterButton status="repair_maintenance" label="Repair & Maintenance" count={getFilterCount('repair_maintenance')} />
        <FilterButton status="leave" label="Leave" count={getFilterCount('leave')} />
        <FilterButton status="outpass" label="Out Pass" count={getFilterCount('outpass')} />
        <FilterButton status="guest_entry" label="Guest Entry" count={getFilterCount('guest_entry')} />
      </ScrollView>

      {/* Requests List */}
      <ScrollView
        style={styles.requestsList}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>
        {filteredRequests.map((request) => (
          <RequestCard key={request.id} request={request} />
        ))}
      </ScrollView>
    </View>
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
  greeting: {
    color: colors.surface,
    fontSize: 24,
    fontWeight: 'bold',
  },
  subGreeting: {
    color: colors.surface,
    fontSize: 14,
    opacity: 0.8,
    marginTop: 4,
  },
  filters: {
    backgroundColor: colors.surface,
    paddingVertical: 16,
    paddingHorizontal: 16,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  filterButton: {
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: colors.surfaceMuted,
    marginRight: 8,
  },
  filterButtonActive: {
    backgroundColor: colors.primary,
  },
  filterButtonText: {
    fontSize: 12,
    color: colors.textMuted,
    fontWeight: '500',
  },
  filterButtonTextActive: {
    color: colors.surface,
    fontWeight: '600',
  },
  requestsList: {
    flex: 1,
  },
  requestCard: {
    backgroundColor: colors.surface,
    padding: 16,
    marginHorizontal: 20,
    marginVertical: 6,
    borderRadius: 12,
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  typeContainer: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  typeIcon: {
    fontSize: 16,
    marginRight: 8,
  },
  typeText: {
    fontSize: 12,
    fontWeight: '600',
    color: colors.primary,
  },
  statusBadge: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
  },
  statusText: {
    color: colors.surface,
    fontSize: 10,
    fontWeight: '600',
  },
  title: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: 8,
  },
  description: {
    fontSize: 14,
    color: colors.textMuted,
    marginBottom: 8,
  },
  priorityBadge: {
    marginBottom: 8,
  },
  priorityText: {
    fontSize: 12,
    fontWeight: '600',
  },
  studentInfo: {
    fontSize: 12,
    color: colors.textMuted,
    marginBottom: 4,
  },
  timestamp: {
    fontSize: 12,
    color: colors.textMuted,
    marginBottom: 8,
  },
  assignedTo: {
    fontSize: 12,
    color: colors.textMuted,
    marginBottom: 12,
  },
  actions: {
    flexDirection: 'row',
    gap: 12,
  },
  actionButton: {
    flex: 1,
    paddingVertical: 10,
    borderRadius: 8,
    alignItems: 'center',
  },
  startButton: {
    backgroundColor: colors.warning,
  },
  resolveButton: {
    backgroundColor: colors.success,
  },
  startButtonText: {
    color: colors.surface,
    fontSize: 14,
    fontWeight: '600',
  },
  resolveButtonText: {
    color: colors.surface,
    fontSize: 14,
    fontWeight: '600',
  },
});
