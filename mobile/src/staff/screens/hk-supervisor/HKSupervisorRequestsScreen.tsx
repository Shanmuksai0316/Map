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
import { GradientButton } from '../../../shared/components/GradientButton';
import Ionicons from 'react-native-vector-icons/Ionicons';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { useAuthStore } from '../../../shared/store/auth.store';
import { apiService } from '../../../shared/services/api.service';
import { APP_CONFIG } from '../../../shared/config/app.config';
import { theme } from '../../../shared/theme/theme';
import { format, formatDistanceToNow } from 'date-fns';
import { SLACountdownBadge } from '../../../shared/components/SLACountdownBadge';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

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

interface Props {
  navigation: any;
}

export const HKSupervisorRequestsScreen: React.FC<Props> = ({ navigation }) => {
  const { user } = useAuthStore();
  const [requests, setRequests] = useState<Request[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedRequest, setSelectedRequest] = useState<Request | null>(null);
  const [showDetailModal, setShowDetailModal] = useState(false);
  const [showStatusModal, setShowStatusModal] = useState(false);
  const [selectedStatus, setSelectedStatus] = useState<string>('');

  const fetchRequests = async () => {
    try {
      setError(null);
      // Use supervisor tickets endpoint so we only see tickets assigned to this supervisor
      const response = await apiService.get<{ data: Request[] }>(
        APP_CONFIG.ENDPOINTS.SUPERVISOR_TICKETS
      );
      const raw = (response as any)?.data?.data ?? [];
      const hkOnly = (Array.isArray(raw) ? raw : []).filter((r: any) => {
        const type = String(r?.type ?? r?.category ?? '').toLowerCase();
        return type === 'housekeeping';
      });
      setRequests(hkOnly as Request[]);
    } catch (error) {
      console.error('Requests fetch error:', error);
      setError('Failed to load requests. Pull down to refresh.');
      setRequests([]);
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
    // Backend ticket statuses: open | in_progress | resolved | closed
    // Mobile UI maps open->pending and resolved->completed.
    if (currentStatus === 'open' || currentStatus === 'pending') return 'in_progress';
    if (currentStatus === 'in_progress') return 'resolved';
    return currentStatus;
  };

  const handleConfirmStatusUpdate = async () => {
    if (!selectedRequest) return;

    const newStatus = getNextStatus(selectedRequest.status);

    try {
      // Staff/admin status change endpoint is POST /tickets/{ticket}/status
      await apiService.post(`/tickets/${selectedRequest.id}/status`, {
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
        return theme.colors.warning;
      case 'in_progress':
        return theme.colors.info;
      case 'completed':
      case 'resolved':
      case 'closed':
        return theme.colors.success;
      default:
        return theme.colors.textMuted;
    }
  };

  return (
    <View style={styles.container}>
      <StaffScreenHeader
        title="Requests"
        variant="minimal"
        onBack={() => navigation.goBack()}
        showBell={false}
      />

      <ScrollView
        style={styles.scrollView}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>
        <View style={styles.content}>
          {error && (
            <View style={styles.errorBanner}>
              <Icon name="alert-circle-outline" size={20} color={theme.colors.error} />
              <Text style={styles.errorText}>{error}</Text>
            </View>
          )}

          {requests.length === 0 && !loading && !error && (
            <View style={styles.emptyState}>
              <Text style={styles.emptyStateTitle}>No Housekeeping Requests</Text>
              <Text style={styles.emptyStateSubtitle}>
                There are no housekeeping requests assigned right now.
              </Text>
            </View>
          )}

          {requests.map((request) => (
            <View key={request.id} style={styles.requestCard}>
              <View style={styles.requestHeader}>
                <View style={styles.requestInfo}>
                  <Text style={styles.requestId}>Request #{request.id}</Text>
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
              <GradientButton
                style={styles.viewButton}
                onPress={() => handleViewDetails(request)}>
                <Text style={styles.viewButtonText}>View Details</Text>
              </GradientButton>
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
              <Ionicons name="close" size={24} color={theme.colors.text} />
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

                <GradientButton
                  style={styles.updateStatusButton}
                  onPress={() => {
                    setShowDetailModal(false);
                    handleUpdateStatus(selectedRequest);
                  }}>
                  <Text style={styles.updateStatusButtonText}>Update Status</Text>
                </GradientButton>
              </>
            )}
          </ScrollView>
        </View>
      </Modal>

      {/* Status Update Modal: only next status + timeline highlight, no cancel */}
      <Modal
        visible={showStatusModal}
        animationType="slide"
        transparent={true}
        onRequestClose={() => setShowStatusModal(false)}>
        <View style={styles.statusModalOverlay}>
          <View style={styles.statusModalContent}>
            <Text style={styles.statusModalTitle}>Update Status</Text>

            {/* Timeline: Open → In Progress → Complete (highlight current step) */}
            <View style={styles.timelineRow}>
              <View style={[styles.timelineStep, (selectedRequest?.status === 'open' || selectedRequest?.status === 'pending') && styles.timelineStepActive]}>
                <View style={[styles.timelineDot, (selectedRequest?.status === 'open' || selectedRequest?.status === 'pending') && styles.timelineDotActive]} />
                <Text style={[styles.timelineLabel, (selectedRequest?.status === 'open' || selectedRequest?.status === 'pending') && styles.timelineLabelActive]}>Open</Text>
              </View>
              <View style={styles.timelineConnector} />
              <View style={[styles.timelineStep, selectedRequest?.status === 'in_progress' && styles.timelineStepActive]}>
                <View style={[styles.timelineDot, selectedRequest?.status === 'in_progress' && styles.timelineDotActive]} />
                <Text style={[styles.timelineLabel, selectedRequest?.status === 'in_progress' && styles.timelineLabelActive]}>In Progress</Text>
              </View>
              <View style={styles.timelineConnector} />
              <View style={[styles.timelineStep, (selectedRequest?.status === 'resolved' || selectedRequest?.status === 'completed' || selectedRequest?.status === 'closed') && styles.timelineStepActive]}>
                <View style={[styles.timelineDot, (selectedRequest?.status === 'resolved' || selectedRequest?.status === 'completed' || selectedRequest?.status === 'closed') && styles.timelineDotActive]} />
                <Text style={[styles.timelineLabel, (selectedRequest?.status === 'resolved' || selectedRequest?.status === 'completed' || selectedRequest?.status === 'closed') && styles.timelineLabelActive]}>Complete</Text>
              </View>
            </View>

            {/* Show only next status: Open → In Progress, or In Progress → Complete */}
            {(selectedRequest?.status === 'open' || selectedRequest?.status === 'pending') && (
              <Text style={styles.statusModalSubtitle}>Update to: In Progress</Text>
            )}
            {selectedRequest?.status === 'in_progress' && (
              <Text style={styles.statusModalSubtitle}>Update to: Complete</Text>
            )}

            <GradientButton
              style={styles.confirmButton}
              onPress={handleConfirmStatusUpdate}>
              <Text style={styles.confirmButtonText}>Update status</Text>
            </GradientButton>
          </View>
        </View>
      </Modal>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  scrollView: {
    flex: 1,
  },
  content: {
    padding: 16,
  },
  emptyState: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 40,
  },
  emptyStateTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.textHeading,
    marginBottom: 4,
  },
  emptyStateSubtitle: {
    fontSize: 14,
    color: theme.colors.textSecondary,
    textAlign: 'center',
  },
  requestCard: {
    backgroundColor: theme.colors.white,
    padding: 16,
    borderRadius: 12,
    marginBottom: 12,
    ...theme.shadows.medium,
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
  requestId: {
    fontSize: 12,
    color: theme.colors.textSecondary,
    marginBottom: 4,
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
    color: theme.colors.text,
    flex: 1,
  },
  requestStudent: {
    fontSize: 14,
    color: theme.colors.textSecondary,
    marginBottom: 4,
  },
  requestTime: {
    fontSize: 12,
    color: theme.colors.textMuted,
  },
  statusBadge: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
  },
  statusText: {
    color: theme.colors.white,
    fontSize: 10,
    fontWeight: '600',
  },
  errorBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: theme.colors.errorLight,
    borderRadius: 8,
    padding: 12,
    marginBottom: 12,
    gap: 8,
  },
  errorText: {
    flex: 1,
    color: theme.colors.error,
    fontSize: 13,
  },
  viewButton: {
    backgroundColor: theme.colors.primary,
    paddingVertical: 10,
    paddingHorizontal: 16,
    borderRadius: 8,
    alignItems: 'center',
  },
  viewButtonText: {
    color: theme.colors.white,
    fontSize: 14,
    fontWeight: '600',
  },
  modalContainer: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 20,
    paddingTop: 60,
    backgroundColor: theme.colors.primary,
  },
  modalTitle: {
    color: theme.colors.white,
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
    color: theme.colors.textSecondary,
    marginBottom: 8,
  },
  detailValue: {
    fontSize: 16,
    color: theme.colors.text,
  },
  detailImage: {
    width: 200,
    height: 200,
    borderRadius: 8,
    marginRight: 12,
  },
  updateStatusButton: {
    backgroundColor: theme.colors.primary,
    paddingVertical: 14,
    borderRadius: 8,
    alignItems: 'center',
    marginTop: 20,
  },
  updateStatusButtonText: {
    color: theme.colors.white,
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
    backgroundColor: theme.colors.white,
    borderRadius: 16,
    padding: 24,
    width: '90%',
    maxWidth: 400,
  },
  statusModalTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: theme.colors.textHeading,
    marginBottom: 8,
  },
  statusModalSubtitle: {
    fontSize: 14,
    color: theme.colors.textSecondary,
    marginBottom: 24,
  },
  timelineRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 20,
  },
  timelineStep: {
    alignItems: 'center',
  },
  timelineStepActive: {
    opacity: 1,
  },
  timelineDot: {
    width: 12,
    height: 12,
    borderRadius: 6,
    backgroundColor: theme.colors.border,
  },
  timelineDotActive: {
    backgroundColor: theme.colors.primary,
    width: 14,
    height: 14,
    borderRadius: 7,
  },
  timelineLabel: {
    fontSize: 10,
    color: theme.colors.textMuted,
    marginTop: 4,
  },
  timelineLabelActive: {
    color: theme.colors.primary,
    fontWeight: '600',
  },
  timelineConnector: {
    width: 24,
    height: 2,
    backgroundColor: theme.colors.border,
    marginHorizontal: 4,
  },
  confirmButton: {
    alignSelf: 'stretch',
    backgroundColor: theme.colors.primary,
    paddingVertical: 12,
    borderRadius: 8,
    alignItems: 'center',
  },
  confirmButtonText: {
    fontSize: 16,
    color: theme.colors.white,
    fontWeight: '600',
  },
});
