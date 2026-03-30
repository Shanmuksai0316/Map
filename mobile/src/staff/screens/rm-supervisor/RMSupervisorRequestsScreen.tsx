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
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useAuthStore } from '../../../shared/store/auth.store';
import { apiService } from '../../../shared/services/api.service';
import { theme } from '../../../shared/theme/theme';
import { Request } from '../../../shared/types';
import { APP_CONFIG } from '../../../shared/config/app.config';
import { format, formatDistanceToNow } from 'date-fns';
import { SLACountdownBadge } from '../../../shared/components/SLACountdownBadge';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface Props {
  navigation: any;
}

export const RMSupervisorRequestsScreen: React.FC<Props> = ({ navigation }) => {
  const { user } = useAuthStore();
  const [requests, setRequests] = useState<Request[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedRequest, setSelectedRequest] = useState<Request | null>(null);
  const [showDetailModal, setShowDetailModal] = useState(false);
  const [showAcceptModal, setShowAcceptModal] = useState(false);

  const fetchRequests = async () => {
    try {
      setError(null);
      // Fetch tickets assigned to this RM Supervisor (backend filters by role)
      const response = await apiService.get<{ data: Request[] }>(
        APP_CONFIG.ENDPOINTS.SUPERVISOR_TICKETS
      );
      const raw = (response as any)?.data?.data ?? [];
      const rmOnly = (Array.isArray(raw) ? raw : []).filter((r: any) => {
        const type = String(r?.type ?? r?.category ?? '').toLowerCase();
        return type === 'repair_maintenance' || type === 'maintenance' || type === 'repair';
      });
      setRequests(rmOnly as Request[]);
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

  const getNextStatus = (currentStatus: string): string => {
    if (currentStatus === 'open' || currentStatus === 'pending') return 'in_progress';
    if (currentStatus === 'in_progress') return 'resolved';
    return currentStatus;
  };

  const handleUpdateStatus = (request: Request) => {
    setSelectedRequest(request);
    setShowAcceptModal(true);
  };

  const handleConfirmStatusUpdate = async () => {
    if (!selectedRequest) return;
    const newStatus = getNextStatus(selectedRequest.status);

    try {
      await apiService.post(`/tickets/${selectedRequest.id}/status`, {
        status: newStatus,
      });
      Alert.alert('Success', 'Status updated successfully');
      setShowAcceptModal(false);
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

  const RequestCard = ({ request }: { request: Request }) => (
    <View style={styles.requestCard}>
      <View style={styles.cardHeader}>
        <View style={styles.requestInfo}>
          <Text style={styles.requestId}>Request #{request.id}</Text>
          <View style={styles.requestTitleRow}>
            <Text style={styles.requestIssue} numberOfLines={1}>
              {request.title || request.issue || 'No Title'}
            </Text>
            <SLACountdownBadge
              createdAt={request.created_at}
              status={request.status}
              category="maintenance"
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
      
      <View style={styles.actionButtons}>
        <GradientButton
          style={styles.secondaryButton}
          onPress={() => handleViewDetails(request)}>
          <Text style={styles.secondaryButtonText}>View Details</Text>
        </GradientButton>
        {(request.status === 'pending' || request.status === 'open') && (
          <GradientButton
            style={styles.primaryButton}
            onPress={() => handleUpdateStatus(request)}>
            <Text style={styles.primaryButtonText}>Accept</Text>
          </GradientButton>
        )}
        {request.status === 'in_progress' && (
          <GradientButton
            style={styles.primaryButton}
            onPress={() => handleUpdateStatus(request)}>
            <Text style={styles.primaryButtonText}>Mark as complete</Text>
          </GradientButton>
        )}
      </View>
    </View>
  );

  return (
    <View style={styles.container}>
      <StaffScreenHeader
        title="Requests"
        variant="minimal"
        onBack={() => navigation.goBack()}
        showBell={false}
      />

      {/* Requests List */}
      <ScrollView
        style={styles.scrollView}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>
        <View style={styles.content}>
          {requests.length === 0 && !loading && !error && (
            <View style={styles.emptyState}>
              <Text style={styles.emptyStateTitle}>No Maintenance Requests</Text>
              <Text style={styles.emptyStateSubtitle}>
                There are no repair & maintenance requests assigned right now.
              </Text>
            </View>
          )}

          {requests.map((request) => (
            <RequestCard key={request.id} request={request} />
          ))}
        </View>
      </ScrollView>

      {/* View Details Modal (Full Page) */}
      <Modal
        visible={showDetailModal}
        animationType="slide"
        transparent={false}
        onRequestClose={() => setShowDetailModal(false)}
      >
        <View style={styles.modalContainer}>
          <View style={styles.modalHeader}>
            <TouchableOpacity
              onPress={() => setShowDetailModal(false)}
              style={styles.closeButton}
            >
              <Icon name="arrow-left" size={24} color={theme.colors.white} />
            </TouchableOpacity>
            <Text style={styles.modalTitle}>{selectedRequest?.title || 'Request Details'}</Text>
            <View style={styles.placeholder} />
          </View>

          <ScrollView style={styles.modalContent}>
            {selectedRequest && (
              <>
                {/* ID and Status Row */}
                <View style={styles.idStatusRow}>
                  <Text style={styles.idLabel}>ID: {selectedRequest.id}</Text>
                  <View
                    style={[
                      styles.statusBadge,
                      { backgroundColor: getStatusColor(selectedRequest.status) },
                    ]}>
                    <Text style={styles.statusText}>
                      {selectedRequest.status === 'open' || selectedRequest.status === 'pending'
                        ? 'OPEN'
                        : selectedRequest.status === 'in_progress'
                        ? 'IN PROGRESS'
                        : 'COMPLETED'}
                    </Text>
                  </View>
                </View>

                {/* Description */}
                <View style={styles.detailSection}>
                  <Text style={styles.detailLabel}>Description</Text>
                  <Text style={styles.detailValue}>
                    {selectedRequest.description || 'No description provided'}
                  </Text>
                </View>

                {/* Images */}
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

                {/* Student Details */}
                <View style={styles.detailSection}>
                  <Text style={styles.detailLabel}>Student Details</Text>
                  <Text style={styles.detailValue}>
                    {selectedRequest.student_name}
                  </Text>
                  <Text style={styles.detailValue}>
                    Room {selectedRequest.room_number || 'N/A'}
                  </Text>
                </View>

                {/* Update status: Accept (open) or Mark as complete (in progress) */}
                {(selectedRequest.status === 'pending' || selectedRequest.status === 'open') && (
                  <GradientButton
                    style={styles.acceptRequestButton}
                    onPress={() => {
                      setShowDetailModal(false);
                      handleUpdateStatus(selectedRequest);
                    }}>
                    <Text style={styles.acceptRequestButtonText}>Accept Request</Text>
                  </GradientButton>
                )}
                {selectedRequest.status === 'in_progress' && (
                  <GradientButton
                    style={styles.completeRequestButton}
                    onPress={() => {
                      setShowDetailModal(false);
                      handleUpdateStatus(selectedRequest);
                    }}>
                    <Text style={styles.acceptRequestButtonText}>Mark as complete</Text>
                  </GradientButton>
                )}
              </>
            )}
          </ScrollView>
        </View>
      </Modal>

      {/* Update Status Modal: timeline + only next status + single Update button, no Cancel */}
      <Modal
        visible={showAcceptModal}
        animationType="slide"
        transparent={true}
        onRequestClose={() => setShowAcceptModal(false)}
      >
        <View style={styles.statusModalOverlay}>
          <View style={styles.statusModalContent}>
            <Text style={styles.statusModalTitle}>Update Status</Text>

            {selectedRequest && (
              <>
                <View style={styles.timelineRow}>
                  <View style={[styles.timelineStep, (selectedRequest.status === 'open' || selectedRequest.status === 'pending') && styles.timelineStepActive]}>
                    <View style={[styles.timelineDot, (selectedRequest.status === 'open' || selectedRequest.status === 'pending') && styles.timelineDotActive]} />
                    <Text style={[styles.timelineStepLabel, (selectedRequest.status === 'open' || selectedRequest.status === 'pending') && styles.timelineStepLabelActive]}>Open</Text>
                  </View>
                  <View style={styles.timelineConnector} />
                  <View style={[styles.timelineStep, selectedRequest.status === 'in_progress' && styles.timelineStepActive]}>
                    <View style={[styles.timelineDot, selectedRequest.status === 'in_progress' && styles.timelineDotActive]} />
                    <Text style={[styles.timelineStepLabel, selectedRequest.status === 'in_progress' && styles.timelineStepLabelActive]}>In Progress</Text>
                  </View>
                  <View style={styles.timelineConnector} />
                  <View style={[styles.timelineStep, (selectedRequest.status === 'resolved' || selectedRequest.status === 'completed' || selectedRequest.status === 'closed') && styles.timelineStepActive]}>
                    <View style={[styles.timelineDot, (selectedRequest.status === 'resolved' || selectedRequest.status === 'completed' || selectedRequest.status === 'closed') && styles.timelineDotActive]} />
                    <Text style={[styles.timelineStepLabel, (selectedRequest.status === 'resolved' || selectedRequest.status === 'completed' || selectedRequest.status === 'closed') && styles.timelineStepLabelActive]}>Complete</Text>
                  </View>
                </View>

                {(selectedRequest.status === 'open' || selectedRequest.status === 'pending') && (
                  <Text style={styles.statusModalSubtitle}>Update to: In Progress</Text>
                )}
                {selectedRequest.status === 'in_progress' && (
                  <Text style={styles.statusModalSubtitle}>Update to: Complete</Text>
                )}

                <GradientButton style={styles.confirmButton} onPress={handleConfirmStatusUpdate}>
                  <Text style={styles.confirmButtonText}>Update status</Text>
                </GradientButton>
              </>
            )}
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
  requestCard: {
    backgroundColor: theme.colors.white,
    padding: 16,
    borderRadius: 12,
    marginBottom: 12,
    ...theme.shadows.medium,
  },
  cardHeader: {
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
  actionButtons: {
    flexDirection: 'row',
    gap: 12,
    marginTop: 12,
  },
  secondaryButton: {
    flex: 1,
    backgroundColor: theme.colors.surfaceMuted,
    paddingVertical: 10,
    paddingHorizontal: 16,
    borderRadius: 8,
    alignItems: 'center',
  },
  secondaryButtonText: {
    color: theme.colors.primary,
    fontSize: 14,
    fontWeight: '600',
  },
  primaryButton: {
    flex: 1,
    backgroundColor: theme.colors.accent,
    paddingVertical: 10,
    paddingHorizontal: 16,
    borderRadius: 8,
    alignItems: 'center',
  },
  primaryButtonText: {
    color: theme.colors.textOnAccent,
    fontSize: 14,
    fontWeight: '600',
  },
  completeRequestButton: {
    backgroundColor: theme.colors.success,
    paddingVertical: 16,
    borderRadius: 12,
    alignItems: 'center',
    marginTop: 20,
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
  modalContainer: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  modalHeader: {
    backgroundColor: theme.colors.primary,
    paddingTop: 60,
    paddingBottom: 20,
    paddingHorizontal: 20,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  closeButton: {
    padding: 4,
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: theme.colors.white,
  },
  modalContent: {
    flex: 1,
    padding: 20,
  },
  idStatusRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 20,
  },
  idLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: theme.colors.textSecondary,
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
    lineHeight: 24,
  },
  detailImage: {
    width: 200,
    height: 200,
    borderRadius: 8,
    marginRight: 12,
  },
  acceptRequestButton: {
    backgroundColor: theme.colors.primary,
    paddingVertical: 16,
    borderRadius: 12,
    alignItems: 'center',
    marginTop: 20,
  },
  acceptRequestButtonText: {
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
    marginBottom: 20,
    textAlign: 'center',
  },
  statusModalSubtitle: {
    fontSize: 14,
    color: theme.colors.textSecondary,
    marginBottom: 24,
    textAlign: 'center',
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
  timelineStepLabel: {
    fontSize: 10,
    color: theme.colors.textMuted,
    marginTop: 4,
  },
  timelineStepLabelActive: {
    color: theme.colors.primary,
    fontWeight: '600',
  },
  timelineConnector: {
    width: 24,
    height: 2,
    backgroundColor: theme.colors.border,
    marginHorizontal: 4,
  },
  timelineSection: {
    marginBottom: 24,
  },
  timelineLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: theme.colors.textHeading,
    marginBottom: 12,
  },
  timelineItem: {
    fontSize: 14,
    color: theme.colors.text,
    marginBottom: 8,
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
    borderColor: theme.colors.border,
  },
  cancelButtonText: {
    fontSize: 16,
    color: theme.colors.text,
    fontWeight: '600',
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
