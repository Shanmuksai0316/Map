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
  Modal,
} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useAuthStore } from '../../store/auth.store';
import { apiService } from '../../services/api.service';
import { Request } from '../../types';
import { APP_CONFIG } from '../../config/app.config';
import { colors } from '../../theme/colors';
import { format } from 'date-fns';

interface RequestDetail extends Request {
  room_number?: string;
  images?: string[];
}

export const RMSupervisorRequestDetailScreen = ({ navigation, route }: any) => {
  const { user } = useAuthStore();
  const { requestId } = route.params;
  const [request, setRequest] = useState<RequestDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [statusModalVisible, setStatusModalVisible] = useState(false);
  const [currentStatus, setCurrentStatus] = useState<'open' | 'in_progress' | 'resolved'>('open');

  const fetchRequest = async () => {
    try {
      const response = await apiService.get<{ data: RequestDetail }>(
        `${APP_CONFIG.ENDPOINTS.SUPERVISOR_TICKETS}/${requestId}`
      );
      setRequest(response.data);
      setCurrentStatus(
        response.data.status === 'pending' ? 'open' :
        response.data.status === 'in_progress' ? 'in_progress' :
        response.data.status === 'resolved' || response.data.status === 'closed' ? 'resolved' :
        'open'
      );
    } catch (error) {
      console.error('Request fetch error:', error);
      // Mock data for demo
      const mockRequest: RequestDetail = {
        id: requestId,
        type: 'repair_maintenance',
        title: 'Broken Window in Room 205',
        description: 'Window glass is cracked and needs replacement urgently. The crack is spreading and needs immediate attention.',
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
        images: [],
      };
      setRequest(mockRequest);
      setCurrentStatus('open');
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

  const handleUpdateStatus = () => {
    setStatusModalVisible(true);
  };

  const handleConfirmStatusUpdate = async () => {
    try {
      // Progress status: open -> in_progress -> resolved
      let newStatus: 'open' | 'in_progress' | 'resolved' = currentStatus;
      if (currentStatus === 'open') {
        newStatus = 'in_progress';
      } else if (currentStatus === 'in_progress') {
        newStatus = 'resolved';
      } else {
        // If resolved, don't change
        newStatus = 'resolved';
      }

      await apiService.post(`${APP_CONFIG.ENDPOINTS.SUPERVISOR_TICKETS}/${requestId}/status`, {
        status: newStatus,
      });

      // Update local state
      if (request) {
        setRequest({
          ...request,
          status: newStatus,
          updated_at: new Date().toISOString(),
        });
      }

      setCurrentStatus(newStatus);
      setStatusModalVisible(false);
      Alert.alert('Success', 'Request status updated successfully');
      fetchRequest();
    } catch (error) {
      console.error('Status update error:', error);
      Alert.alert('Error', 'Failed to update request status');
    }
  };

  const handleCancelStatusUpdate = () => {
    // Reset to original status
    if (request) {
      setCurrentStatus(
        request.status === 'pending' ? 'open' :
        request.status === 'in_progress' ? 'in_progress' :
        request.status === 'resolved' || request.status === 'closed' ? 'resolved' :
        'open'
      );
    }
    setStatusModalVisible(false);
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'resolved':
      case 'closed':
      case 'completed': return colors.success;
      case 'in_progress': return colors.warning;
      case 'cancelled': return colors.error;
      default: return colors.textMuted;
    }
  };

  const getStatusLabel = (status: string) => {
    switch (status) {
      case 'open': return 'OPEN';
      case 'pending': return 'OPEN';
      case 'in_progress': return 'IN PROGRESS';
      case 'resolved':
      case 'closed':
      case 'completed': return 'COMPLETED';
      default: return status.toUpperCase();
    }
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
        <View style={styles.detailContainer}>
          {/* Issue */}
          <View style={styles.section}>
            <Text style={styles.label}>Issue</Text>
            <Text style={styles.value}>{request.title}</Text>
          </View>

          {/* Status */}
          <View style={styles.section}>
            <Text style={styles.label}>Status</Text>
            <View style={[styles.statusBadge, { backgroundColor: getStatusColor(request.status) }]}>
              <Text style={styles.statusText}>{getStatusLabel(request.status)}</Text>
            </View>
          </View>

          {/* Description */}
          <View style={styles.section}>
            <Text style={styles.label}>Description</Text>
            <Text style={styles.value}>{request.description}</Text>
          </View>

          {/* Images */}
          {request.images && request.images.length > 0 && (
            <View style={styles.section}>
              <Text style={styles.label}>Images</Text>
              <ScrollView horizontal showsHorizontalScrollIndicator={false}>
                {request.images.map((imageUri, index) => (
                  <Image
                    key={index}
                    source={{ uri: imageUri }}
                    style={styles.image}
                    resizeMode="cover"
                  />
                ))}
              </ScrollView>
            </View>
          )}

          {/* Details */}
          <View style={styles.section}>
            <Text style={styles.label}>Student Details</Text>
            <Text style={styles.value}>
              {request.student_name} • Room {request.room_number || 'N/A'}
            </Text>
            {request.hostel_name && (
              <Text style={styles.value}>{request.hostel_name}</Text>
            )}
          </View>

          {/* Timestamp */}
          <View style={styles.section}>
            <Text style={styles.label}>Submitted</Text>
            <Text style={styles.value}>
              {format(new Date(request.created_at), 'MMM dd, yyyy HH:mm')}
            </Text>
          </View>
        </View>

        {/* Update Status Button */}
        <View style={styles.footer}>
          <TouchableOpacity
            style={styles.updateStatusButton}
            onPress={handleUpdateStatus}>
            <Ionicons name="refresh-outline" size={20} color={colors.surface} />
            <Text style={styles.updateStatusText}>Update Status</Text>
          </TouchableOpacity>
        </View>
      </ScrollView>

      {/* Status Update Modal */}
      <Modal
        visible={statusModalVisible}
        transparent
        animationType="slide"
        onRequestClose={handleCancelStatusUpdate}>
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <Text style={styles.modalTitle}>Update Request Status</Text>

            <View style={styles.statusDisplay}>
              <Text style={styles.statusLabel}>Current Status:</Text>
              <View style={[styles.statusBadge, { backgroundColor: getStatusColor(currentStatus) }]}>
                <Text style={styles.statusText}>{getStatusLabel(currentStatus)}</Text>
              </View>
            </View>

            <View style={styles.statusInfo}>
              <Text style={styles.statusInfoText}>
                Status progression: Open → In Progress → Resolved
              </Text>
              {(() => {
                const nextStatus = currentStatus === 'open' ? 'in_progress' :
                                 currentStatus === 'in_progress' ? 'resolved' : 'resolved';
                return (
                  <View style={styles.nextStatusDisplay}>
                    <Text style={styles.nextStatusLabel}>Next Status:</Text>
                    <View style={[styles.statusBadge, { backgroundColor: getStatusColor(nextStatus) }]}>
                      <Text style={styles.statusText}>{getStatusLabel(nextStatus)}</Text>
                    </View>
                  </View>
                );
              })()}
            </View>

            <View style={styles.modalButtons}>
              <TouchableOpacity
                style={styles.cancelButton}
                onPress={handleCancelStatusUpdate}>
                <Text style={styles.cancelButtonText}>Cancel</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={styles.confirmButton}
                onPress={handleConfirmStatusUpdate}>
                <Text style={styles.confirmButtonText}>Confirm Status Update</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>
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
  image: {
    width: 200,
    height: 200,
    borderRadius: 8,
    marginRight: 12,
    marginTop: 8,
  },
  footer: {
    padding: 20,
    paddingBottom: 40,
  },
  updateStatusButton: {
    backgroundColor: colors.primary,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 16,
    paddingHorizontal: 24,
    borderRadius: 12,
    gap: 8,
  },
  updateStatusText: {
    color: colors.surface,
    fontSize: 16,
    fontWeight: '600',
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  modalContent: {
    backgroundColor: colors.surface,
    borderRadius: 16,
    padding: 24,
    width: '90%',
    maxWidth: 400,
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: colors.textPrimary,
    marginBottom: 20,
    textAlign: 'center',
  },
  statusDisplay: {
    alignItems: 'center',
    marginBottom: 20,
  },
  statusLabel: {
    fontSize: 14,
    color: colors.textMuted,
    marginBottom: 8,
  },
  statusInfo: {
    marginBottom: 20,
  },
  statusInfoText: {
    fontSize: 12,
    color: colors.textMuted,
    textAlign: 'center',
    marginBottom: 16,
  },
  nextStatusDisplay: {
    alignItems: 'center',
    marginTop: 12,
  },
  nextStatusLabel: {
    fontSize: 14,
    color: colors.textMuted,
    marginBottom: 8,
  },
  modalButtons: {
    flexDirection: 'row',
    gap: 12,
    marginBottom: 12,
  },
  cancelButton: {
    flex: 1,
    backgroundColor: colors.surfaceMuted,
    paddingVertical: 12,
    borderRadius: 8,
    alignItems: 'center',
  },
  cancelButtonText: {
    color: colors.textPrimary,
    fontSize: 14,
    fontWeight: '600',
  },
  confirmButton: {
    flex: 1,
    backgroundColor: colors.primary,
    paddingVertical: 12,
    borderRadius: 8,
    alignItems: 'center',
  },
  confirmButtonText: {
    color: colors.surface,
    fontSize: 14,
    fontWeight: '600',
  },
  progressButton: {
    backgroundColor: colors.surfaceMuted,
    paddingVertical: 12,
    borderRadius: 8,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: colors.primary,
  },
  progressButtonText: {
    color: colors.primary,
    fontSize: 14,
    fontWeight: '600',
  },
});
