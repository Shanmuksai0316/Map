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
import { SLACountdownBadge } from '../../components/SLACountdownBadge';

export const RMSupervisorRequestsScreen = ({ navigation }: any) => {
  const { user } = useAuthStore();
  const [requests, setRequests] = useState<Request[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);

  const fetchRequests = async () => {
    try {
      // Fetch only maintenance (repair & maintenance) requests for RM Supervisor
      const response = await apiService.get<{ data: Request[] }>(
        `${APP_CONFIG.ENDPOINTS.SUPERVISOR_TICKETS}?category=repair_maintenance`
      );
      setRequests(response.data);
    } catch (error) {
      console.error('Requests fetch error:', error);
      // Mock data for demo
      setRequests([
        {
          id: 1,
          type: 'repair_maintenance',
          title: 'Broken Window in Room 205',
          description: 'Window glass is cracked and needs replacement urgently',
          status: 'open',
          priority: 'high',
          student_id: 1,
          student_name: 'John Doe',
          room_number: '205',
          hostel_name: 'Hostel A',
          created_by: 'John Doe',
          tenant_id: 'tenant_1',
          created_at: '2025-10-15T08:30:00Z',
          updated_at: '2025-10-15T08:30:00Z',
        },
        {
          id: 2,
          type: 'repair_maintenance',
          title: 'Leaky Faucet in Bathroom',
          description: 'Bathroom faucet is leaking continuously, needs repair',
          status: 'in_progress',
          priority: 'medium',
          student_id: 2,
          student_name: 'Jane Smith',
          room_number: '101',
          hostel_name: 'Hostel A',
          created_by: 'Jane Smith',
          assigned_to: 'Maintenance Team',
          tenant_id: 'tenant_1',
          created_at: '2025-10-15T09:15:00Z',
          updated_at: '2025-10-15T11:00:00Z',
        },
        {
          id: 3,
          type: 'repair_maintenance',
          title: 'Door Lock Not Working',
          description: 'Room door lock is stuck and cannot be opened',
          status: 'completed',
          priority: 'high',
          student_id: 3,
          student_name: 'Mike Johnson',
          room_number: '305',
          hostel_name: 'Hostel B',
          created_by: 'Mike Johnson',
          tenant_id: 'tenant_1',
          created_at: '2025-10-14T14:00:00Z',
          updated_at: '2025-10-15T10:00:00Z',
          resolved_at: '2025-10-15T10:00:00Z',
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

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'completed': return colors.success;
      case 'in_progress': return colors.warning;
      case 'cancelled': return colors.error;
      default: return colors.textMuted;
    }
  };

  const RequestCard = ({ request }: { request: Request }) => (
    <TouchableOpacity
      style={styles.requestCard}
      onPress={() => navigation.navigate('RMSupervisorRequestDetail', { requestId: request.id })}>
      <View style={styles.cardHeader}>
        <View style={styles.typeContainer}>
          <Ionicons name="construct-outline" size={20} color={colors.primary} style={styles.typeIcon} />
          <Text style={styles.typeText}>REPAIR & MAINTENANCE</Text>
        </View>
        <View style={[styles.statusBadge, { backgroundColor: getStatusColor(request.status) }]}>
          <Text style={styles.statusText}>
            {request.status === 'open' || request.status === 'pending' ? 'OPEN' :
             request.status === 'in_progress' ? 'IN PROGRESS' : 
             request.status === 'resolved' || request.status === 'closed' || request.status === 'completed' ? 'COMPLETED' : 'OPEN'}
          </Text>
        </View>
      </View>

      <View style={styles.titleRow}>
        <Text style={styles.title}>{request.title}</Text>
        <SLACountdownBadge
          createdAt={request.created_at}
          status={request.status}
          category="maintenance"
          size="small"
        />
      </View>
      <Text style={styles.description} numberOfLines={2}>{request.description}</Text>

      <Text style={styles.studentInfo}>
        {request.student_name} • Room {request.room_number || 'N/A'}
      </Text>

      <Text style={styles.timestamp}>
        {format(new Date(request.created_at), 'MMM dd, HH:mm')}
      </Text>

      <TouchableOpacity
        style={styles.viewDetailsButton}
        onPress={() => navigation.navigate('RMSupervisorRequestDetail', { requestId: request.id })}>
        <Text style={styles.viewDetailsText}>View Details</Text>
        <Ionicons name="chevron-forward" size={16} color={colors.primary} />
      </TouchableOpacity>
    </TouchableOpacity>
  );

  return (
    <View style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <View>
          <Text style={styles.greeting}>Requests</Text>
          <Text style={styles.subGreeting}>Manage all repair & maintenance requests</Text>
        </View>
      </View>

      {/* Requests List */}
      <ScrollView
        style={styles.requestsList}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>
        {requests.map((request) => (
          <RequestCard key={request.id} request={request} />
        ))}
        {requests.length === 0 && !loading && (
          <View style={styles.emptyContainer}>
            <Ionicons name="construct-outline" size={64} color={colors.textMuted} />
            <Text style={styles.emptyText}>No requests found</Text>
          </View>
        )}
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
  titleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 8,
    gap: 8,
  },
  title: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.textPrimary,
    flex: 1,
  },
  description: {
    fontSize: 14,
    color: colors.textMuted,
    marginBottom: 8,
  },
  studentInfo: {
    fontSize: 12,
    color: colors.textMuted,
    marginBottom: 4,
  },
  timestamp: {
    fontSize: 12,
    color: colors.textMuted,
    marginBottom: 12,
  },
  viewDetailsButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'flex-end',
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  viewDetailsText: {
    color: colors.primary,
    fontSize: 14,
    fontWeight: '600',
    marginRight: 4,
  },
  emptyContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: 60,
  },
  emptyText: {
    fontSize: 16,
    color: colors.textMuted,
    marginTop: 16,
  },
});
