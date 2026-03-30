/**
 * WardenRequestDetailScreen
 * 
 * Shows detailed view of a request with type-specific information
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
  ActivityIndicator,
  Image,
} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useAuthStore } from '../../store/auth.store';
import { apiService } from '../../services/api.service';
import { Request, Leave } from '../../types';
import { APP_CONFIG } from '../../config/app.config';
import { colors } from '../../theme/colors';
import { format, differenceInHours, differenceInMinutes } from 'date-fns';

interface RequestDetail extends Request {
  room_number?: string;
  attachment?: string;
  approved_by?: string;
  from_date?: string;
  to_date?: string;
  from_time?: string;
  to_time?: string;
  reason?: string;
}

export const WardenRequestDetailScreen = ({ navigation, route }: any) => {
  const { user } = useAuthStore();
  const { requestId } = route.params;
  const [request, setRequest] = useState<RequestDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const fetchRequest = async () => {
    try {
      const response = await apiService.get<{ data: RequestDetail }>(
        `${APP_CONFIG.ENDPOINTS.WARDEN_REQUESTS}/${requestId}`
      );
      setRequest(response.data);
    } catch (error) {
      console.error('Request fetch error:', error);
      // Mock data for demo
      const mockRequest: RequestDetail = {
        id: requestId,
        type: 'housekeeping',
        title: 'Room Cleaning Required',
        description: 'Room 101 needs immediate cleaning - mess created during maintenance',
        status: 'pending',
        priority: 'high',
        student_id: 1,
        student_name: 'John Doe',
        room_number: '101',
        hostel_name: 'Hostel A',
        created_by: 'John Doe',
        tenant_id: 'tenant_1',
        created_at: '2025-10-15T08:30:00Z',
        updated_at: '2025-10-15T08:30:00Z',
        attachment: undefined,
      };
      setRequest(mockRequest);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    if (requestId) {
      fetchRequest();
    } else {
      Alert.alert('Error', 'Invalid request ID');
      navigation.goBack();
    }
  }, [requestId]);

  const onRefresh = () => {
    setRefreshing(true);
    fetchRequest();
  };

  const calculateTimeElapsed = (createdAt: string) => {
    const now = new Date();
    const created = new Date(createdAt);
    const hours = differenceInHours(now, created);
    const minutes = differenceInMinutes(now, created) % 60;

    if (hours > 0) {
      return `${hours} hour${hours > 1 ? 's' : ''} ${minutes > 0 ? `${minutes} minute${minutes > 1 ? 's' : ''}` : ''}`;
    }
    return `${minutes} minute${minutes > 1 ? 's' : ''}`;
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'completed': return colors.success;
      case 'in_progress': return colors.warning;
      case 'cancelled': return colors.error;
      default: return colors.textMuted;
    }
  };

  const renderHousekeepingDetail = () => {
    if (!request) return null;

    return (
      <View style={styles.detailContainer}>
        <View style={styles.section}>
          <Text style={styles.label}>Request Type</Text>
          <Text style={styles.value}>
            {request.type === 'housekeeping' ? 'Housekeeping' : 'Repair & Maintenance'}
          </Text>
        </View>

        <View style={styles.section}>
          <Text style={styles.label}>Student Name & Room</Text>
          <Text style={styles.value}>
            {request.student_name} • Room {request.room_number || 'N/A'}
          </Text>
        </View>

        <View style={styles.section}>
          <Text style={styles.label}>Submitted Date and Time</Text>
          <Text style={styles.value}>
            {format(new Date(request.created_at), 'MMM dd, yyyy HH:mm')}
          </Text>
        </View>

        <View style={styles.section}>
          <Text style={styles.label}>Time Elapsed</Text>
          <Text style={styles.value}>{calculateTimeElapsed(request.created_at)}</Text>
        </View>

        <View style={styles.section}>
          <Text style={styles.label}>Request Description</Text>
          <Text style={styles.value}>{request.description}</Text>
        </View>

        {request.attachment && (
          <View style={styles.section}>
            <Text style={styles.label}>Attachment</Text>
            <Image source={{ uri: request.attachment }} style={styles.attachmentImage} />
          </View>
        )}
      </View>
    );
  };

  const renderLeaveDetail = () => {
    if (!request) return null;

    return (
      <View style={styles.detailContainer}>
        <View style={styles.section}>
          <Text style={styles.label}>Request Type</Text>
          <Text style={styles.value}>
            {request.type === 'leave' ? 'Leave' : 'Out Pass'}
          </Text>
        </View>

        <View style={styles.section}>
          <Text style={styles.label}>Student Name & Room</Text>
          <Text style={styles.value}>
            {request.student_name} • Room {request.room_number || 'N/A'}
          </Text>
        </View>

        <View style={styles.section}>
          <Text style={styles.label}>Approved by</Text>
          <Text style={styles.value}>{request.approved_by || 'Rector Name'}</Text>
        </View>

        {request.type === 'leave' && request.from_date && request.to_date && (
          <View style={styles.section}>
            <Text style={styles.label}>From Date - To Date</Text>
            <Text style={styles.value}>
              {format(new Date(request.from_date), 'MMM dd, yyyy')} -{' '}
              {format(new Date(request.to_date), 'MMM dd, yyyy')}
            </Text>
          </View>
        )}

        {request.type === 'outpass' && request.from_time && request.to_time && (
          <View style={styles.section}>
            <Text style={styles.label}>From Time - To Time</Text>
            <Text style={styles.value}>
              {request.from_time} - {request.to_time}
            </Text>
          </View>
        )}

        <View style={styles.section}>
          <Text style={styles.label}>Status</Text>
          <View style={[styles.statusBadge, { backgroundColor: getStatusColor(request.status) }]}>
            <Text style={styles.statusText}>{request.status.toUpperCase()}</Text>
          </View>
        </View>

        {request.reason && (
          <View style={styles.section}>
            <Text style={styles.label}>Reason for Request</Text>
            <Text style={styles.value}>{request.reason}</Text>
          </View>
        )}
      </View>
    );
  };

  if (loading || !request) {
    return (
      <View style={styles.container}>
        <View style={styles.header}>
          <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
            <Ionicons name="arrow-back" size={24} color={colors.surface} />
          </TouchableOpacity>
          <Text style={styles.headerTitle}>Request Details</Text>
          <View style={styles.headerSpacer} />
        </View>
        <View style={styles.loaderContainer}>
          <ActivityIndicator size="large" color={colors.primary} />
        </View>
      </View>
    );
  }

  const isHousekeepingOrRepair = request.type === 'housekeeping' || request.type === 'repair_maintenance';
  const isLeaveOrOutpass = request.type === 'leave' || request.type === 'outpass';

  return (
    <View style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
          <Ionicons name="arrow-back" size={24} color={colors.surface} />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Request Details</Text>
        <View style={styles.headerSpacer} />
      </View>

      {/* Content */}
      <ScrollView
        style={styles.content}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>
        {isHousekeepingOrRepair && renderHousekeepingDetail()}
        {isLeaveOrOutpass && renderLeaveDetail()}
        {!isHousekeepingOrRepair && !isLeaveOrOutpass && renderHousekeepingDetail()}
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
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  backButton: {
    padding: 8,
  },
  headerTitle: {
    color: colors.surface,
    fontSize: 20,
    fontWeight: 'bold',
    flex: 1,
    textAlign: 'center',
  },
  headerSpacer: {
    width: 40,
  },
  loaderContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  content: {
    flex: 1,
  },
  detailContainer: {
    padding: 20,
  },
  section: {
    marginBottom: 20,
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textMuted,
    marginBottom: 8,
  },
  value: {
    fontSize: 16,
    color: colors.textPrimary,
    lineHeight: 24,
  },
  statusBadge: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 8,
    alignSelf: 'flex-start',
  },
  statusText: {
    color: colors.surface,
    fontSize: 12,
    fontWeight: '600',
  },
  attachmentImage: {
    width: '100%',
    height: 200,
    borderRadius: 8,
    resizeMode: 'cover',
    marginTop: 8,
  },
});

