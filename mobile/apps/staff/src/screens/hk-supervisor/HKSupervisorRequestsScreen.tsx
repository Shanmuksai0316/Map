import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  Alert,
  Modal,
  Image,
} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useAuthStore } from '../../store/auth.store';
import { apiService } from '../../services/api.service';
import { APP_CONFIG } from '../../config/app.config';
import { colors } from '../../theme/colors';
import { format, formatDistanceToNow } from 'date-fns';
import { SLACountdownBadge } from '../../components/SLACountdownBadge';

interface Request {
  id: number;
  title?: string;
  issue?: string;
  description?: string;
  student_name: string;
  room_number?: string;
  status: 'open' | 'pending' | 'in_progress' | 'resolved' | 'closed' | 'completed';
  images?: string[];
  created_at: string;
  updated_at: string;
}

export const HKSupervisorRequestsScreen = ({ navigation }: any) => {
  const { user } = useAuthStore();
  const [requests, setRequests] = useState<Request[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [selectedRequest, setSelectedRequest] = useState<Request | null>(null);
  const [showDetailModal, setShowDetailModal] = useState(false);
  const [showStatusModal, setShowStatusModal] = useState(false);
  const [selectedStatus, setSelectedStatus] = useState<string>('');

  const fetchRequests = async () => {
    try {
      const response = await apiService.get<{ data: Request[] }>(
        `${APP_CONFIG.ENDPOINTS.SUPERVISOR_TICKETS}?category=housekeeping`
      );
      setRequests(response.data);
    } catch (error) {
      console.error('Requests fetch error:', error);
      // Mock data
      setRequests([
        {
          id: 1,
          title: 'Bathroom Cleaning Required',
          issue: 'Bathroom Cleaning Required',
          description: 'Bathroom tiles need thorough cleaning and sanitization',
          status: 'open',
          student_name: 'John Doe',
          room_number: '101',
          images: [],
          created_at: new Date(Date.now() - 15 * 60 * 1000).toISOString(),
          updated_at: new Date().toISOString(),
        },
        {
          id: 2,
          title: 'Room Deep Cleaning',
          issue: 'Room Deep Cleaning',
          description: 'Weekly room cleaning and bed linen change',
          status: 'in_progress',
          student_name: 'Jane Smith',
          room_number: '205',
          images: [],
          created_at: new Date(Date.now() - 45 * 60 * 1000).toISOString(),
          updated_at: new Date().toISOString(),
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

  const handleViewDetails = (request: Request) => {
    setSelectedRequest(request);
    setShowDetailModal(true);
  };

  const handleUpdateStatus = (request: Request) => {
    setSelectedRequest(request);
    setSelectedStatus(request.status);
    setShowStatusModal(true);
  };

  const getNextStatus = (currentStatus: string): string => {
    if (currentStatus === 'open' || currentStatus === 'pending') return 'in_progress';
    if (currentStatus === 'in_progress') return 'resolved';
    return currentStatus;
  };

  const handleConfirmStatusUpdate = async () => {
    if (!selectedRequest) return;

    const newStatus = getNextStatus(selectedRequest.status);

    try {
      await apiService.post(`${APP_CONFIG.ENDPOINTS.SUPERVISOR_TICKETS}/${selectedRequest.id}/status`, {
        status: newStatus,
      });
      
      Alert.alert('Success', 'Status updated successfully');
      setShowStatusModal(false);
      fetchRequests();
    } catch (error) {
      console.error('Status update error:', error);
      Alert.alert('Error', 'Failed to update status');
    }
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'open':
      case 'pending':
        return '#FF9800';
      case 'in_progress':
        return '#2196F3';
      case 'completed':
      case 'resolved':
      case 'closed':
        return '#4CAF50';
      default:
        return colors.textMuted;
    }
  };

  return (
    <View style={styles.container}>
      <ScrollView
        style={styles.scrollView}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>
        <View style={styles.header}>
          <Text style={styles.headerTitle}>Requests</Text>
        </View>

        <View style={styles.content}>
          {requests.map((request) => (
            <View key={request.id} style={styles.requestCard}>
              <View style={styles.requestHeader}>
                <View style={styles.requestInfo}>
                  <View style={styles.requestTitleRow}>
                    <Text style={styles.requestIssue} numberOfLines={1}>
                      {request.title || request.issue || 'No Title'}
                    </Text>
                    <SLACountdownBadge
                      createdAt={request.created_at}
                      status={request.status}
                      category="housekeeping"
                      size="small"
                    />
                  </View>
                  <Text style={styles.requestStudent}>
                    {request.student_name} • Room {request.room_number || 'N/A'}
                  </Text>
                  <Text style={styles.requestTime}>
                    {formatDistanceToNow(new Date(request.created_at), { addSuffix: true })}
                  </Text>
                </View>
                <View
                  style={[
                    styles.statusBadge,
                    { backgroundColor: getStatusColor(request.status) },
                  ]}>
                  <Text style={styles.statusText}>
                    {request.status === 'open' || request.status === 'pending'
                      ? 'OPEN'
                      : request.status === 'in_progress'
                      ? 'IN PROGRESS'
                      : 'COMPLETED'}
                  </Text>
                </View>
              </View>
              <TouchableOpacity
                style={styles.viewButton}
                onPress={() => handleViewDetails(request)}>
                <Text style={styles.viewButtonText}>View Details</Text>
              </TouchableOpacity>
            </View>
          ))}
        </View>
      </ScrollView>

      {/* Detail Modal */}
      <Modal
        visible={showDetailModal}
        animationType="slide"
        transparent={false}
        onRequestClose={() => setShowDetailModal(false)}>
        <View style={styles.modalContainer}>
          <View style={styles.modalHeader}>
            <Text style={styles.modalTitle}>Request Details</Text>
            <TouchableOpacity
              onPress={() => setShowDetailModal(false)}
              style={styles.closeButton}>
              <Ionicons name="close" size={24} color={colors.textPrimary} />
            </TouchableOpacity>
          </View>

          <ScrollView style={styles.modalContent}>
            {selectedRequest && (
              <>
                <View style={styles.detailSection}>
                  <Text style={styles.detailLabel}>Issue</Text>
                  <Text style={styles.detailValue}>
                    {selectedRequest.title || selectedRequest.issue}
                  </Text>
                </View>

                <View style={styles.detailSection}>
                  <Text style={styles.detailLabel}>Status</Text>
                  <View
                    style={[
                      styles.statusBadge,
                      { backgroundColor: getStatusColor(selectedRequest.status) },
                    ]}>
                    <Text style={styles.statusText}>
                      {selectedRequest.status === 'open' ||
                      selectedRequest.status === 'pending'
                        ? 'OPEN'
                        : selectedRequest.status === 'in_progress'
                        ? 'IN PROGRESS'
                        : 'COMPLETED'}
                    </Text>
                  </View>
                </View>

                <View style={styles.detailSection}>
                  <Text style={styles.detailLabel}>Description</Text>
                  <Text style={styles.detailValue}>
                    {selectedRequest.description || 'No description provided'}
                  </Text>
                </View>

                {selectedRequest.images && selectedRequest.images.length > 0 && (
                  <View style={styles.detailSection}>
                    <Text style={styles.detailLabel}>Images</Text>
                    <ScrollView horizontal>
                      {selectedRequest.images.map((image, index) => (
                        <Image
                          key={index}
                          source={{ uri: image }}
                          style={styles.detailImage}
                        />
                      ))}
                    </ScrollView>
                  </View>
                )}

                <View style={styles.detailSection}>
                  <Text style={styles.detailLabel}>Student Details</Text>
                  <Text style={styles.detailValue}>
                    {selectedRequest.student_name}
                  </Text>
                  <Text style={styles.detailValue}>
                    Room {selectedRequest.room_number || 'N/A'}
                  </Text>
                </View>

                <TouchableOpacity
                  style={styles.updateStatusButton}
                  onPress={() => {
                    setShowDetailModal(false);
                    handleUpdateStatus(selectedRequest);
                  }}>
                  <Text style={styles.updateStatusButtonText}>Update Status</Text>
                </TouchableOpacity>
              </>
            )}
          </ScrollView>
        </View>
      </Modal>

      {/* Status Update Modal */}
      <Modal
        visible={showStatusModal}
        animationType="slide"
        transparent={true}
        onRequestClose={() => setShowStatusModal(false)}>
        <View style={styles.statusModalOverlay}>
          <View style={styles.statusModalContent}>
            <Text style={styles.statusModalTitle}>Update Status</Text>
            <Text style={styles.statusModalSubtitle}>
              Track request status: Open → In Progress → Resolved
            </Text>

            <View style={styles.statusOptions}>
              <TouchableOpacity
                style={[
                  styles.statusOption,
                  selectedStatus === 'open' && styles.statusOptionActive,
                ]}
                onPress={() => setSelectedStatus('open')}>
                <Text
                  style={[
                    styles.statusOptionText,
                    selectedStatus === 'open' && styles.statusOptionTextActive,
                  ]}>
                  Open
                </Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[
                  styles.statusOption,
                  selectedStatus === 'in_progress' && styles.statusOptionActive,
                ]}
                onPress={() => setSelectedStatus('in_progress')}>
                <Text
                  style={[
                    styles.statusOptionText,
                    selectedStatus === 'in_progress' && styles.statusOptionTextActive,
                  ]}>
                  In Progress
                </Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[
                  styles.statusOption,
                  selectedStatus === 'resolved' && styles.statusOptionActive,
                ]}
                onPress={() => setSelectedStatus('resolved')}>
                <Text
                  style={[
                    styles.statusOptionText,
                    selectedStatus === 'resolved' && styles.statusOptionTextActive,
                  ]}>
                  Resolved
                </Text>
              </TouchableOpacity>
            </View>

            <View style={styles.statusModalActions}>
              <TouchableOpacity
                style={styles.cancelButton}
                onPress={() => setShowStatusModal(false)}>
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
  scrollView: {
    flex: 1,
  },
  header: {
    backgroundColor: colors.primary,
    padding: 20,
    paddingTop: 60,
  },
  headerTitle: {
    color: colors.surface,
    fontSize: 24,
    fontWeight: 'bold',
  },
  content: {
    padding: 20,
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
    alignItems: 'flex-start',
    marginBottom: 12,
  },
  requestInfo: {
    flex: 1,
    marginRight: 12,
  },
  requestTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 4,
    gap: 8,
  },
  requestIssue: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.textPrimary,
    flex: 1,
  },
  requestStudent: {
    fontSize: 14,
    color: colors.textMuted,
    marginBottom: 4,
  },
  requestTime: {
    fontSize: 12,
    color: colors.textMuted,
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
  viewButton: {
    backgroundColor: colors.primary,
    paddingVertical: 10,
    paddingHorizontal: 16,
    borderRadius: 8,
    alignItems: 'center',
  },
  viewButtonText: {
    color: colors.surface,
    fontSize: 14,
    fontWeight: '600',
  },
  modalContainer: {
    flex: 1,
    backgroundColor: colors.background,
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 20,
    paddingTop: 60,
    backgroundColor: colors.primary,
  },
  modalTitle: {
    color: colors.surface,
    fontSize: 20,
    fontWeight: 'bold',
  },
  closeButton: {
    padding: 8,
  },
  modalContent: {
    flex: 1,
    padding: 20,
  },
  detailSection: {
    marginBottom: 20,
  },
  detailLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textMuted,
    marginBottom: 8,
  },
  detailValue: {
    fontSize: 16,
    color: colors.textPrimary,
  },
  detailImage: {
    width: 200,
    height: 200,
    borderRadius: 8,
    marginRight: 12,
  },
  updateStatusButton: {
    backgroundColor: colors.primary,
    paddingVertical: 14,
    borderRadius: 8,
    alignItems: 'center',
    marginTop: 20,
  },
  updateStatusButtonText: {
    color: colors.surface,
    fontSize: 16,
    fontWeight: '600',
  },
  statusModalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  statusModalContent: {
    backgroundColor: colors.surface,
    borderRadius: 16,
    padding: 24,
    width: '90%',
    maxWidth: 400,
  },
  statusModalTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: colors.textPrimary,
    marginBottom: 8,
  },
  statusModalSubtitle: {
    fontSize: 14,
    color: colors.textMuted,
    marginBottom: 24,
  },
  statusOptions: {
    marginBottom: 24,
  },
  statusOption: {
    padding: 16,
    borderRadius: 8,
    marginBottom: 12,
    borderWidth: 2,
    borderColor: colors.border,
  },
  statusOptionActive: {
    borderColor: colors.primary,
    backgroundColor: 'rgba(255, 107, 53, 0.1)',
  },
  statusOptionText: {
    fontSize: 16,
    color: colors.textPrimary,
  },
  statusOptionTextActive: {
    color: colors.primary,
    fontWeight: '600',
  },
  statusModalActions: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 12,
  },
  cancelButton: {
    flex: 1,
    paddingVertical: 12,
    borderRadius: 8,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: colors.border,
  },
  cancelButtonText: {
    fontSize: 16,
    color: colors.textPrimary,
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
    fontSize: 16,
    color: colors.surface,
    fontWeight: '600',
  },
});
