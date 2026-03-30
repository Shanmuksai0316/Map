import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  ActivityIndicator,
  Alert,
  TextInput,
  Modal,
  Platform,
} from 'react-native';
import { useAuthStore } from '../../../shared/store/auth.store';
import { apiService } from '../../../shared/services/api.service';
import { APP_CONFIG } from '../../../shared/config/app.config';
import { format } from 'date-fns';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { colors } from '../../../shared/theme/colors';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';
import { GradientButton } from '../../../shared/components/GradientButton';

interface LaundryRequest {
  id: number;
  status: {
    value: string;
    label: string;
    color: string;
    is_active: boolean;
    is_in_progress: boolean;
    is_completed: boolean;
  };
  service_type: {
    value: string;
    label: string;
    description: string;
  };
  bag_count: number;
  weight_kg?: number;
  pickup_code?: string;
  student?: {
    name: string;
    student_uid: string;
    hostel_name?: string;
    room_no?: string;
  };
  hostel?: {
    name: string;
  };
  requested_at?: string;
  ready_at?: string;
  completed_at?: string;
  estimated_completion_at?: string;
  actual_completion_at?: string;
  special_instructions?: string;
  collection_notes?: string;
  delivery_notes?: string;
  manual_verify_notes?: string;
  price?: {
    total: number;
    currency: string;
  };
  payment?: {
    status: string;
    amount?: number;
    requires_payment: boolean;
  };
  verification?: {
    requires_manual_verify: boolean;
  };
  overdue?: {
    is_overdue: boolean;
    days_overdue?: number;
  };
  status_history?: Array<{
    status: string;
    timestamp: string;
    notes?: string;
  }>;
}

export const LaundryRequestDetailScreen = ({ navigation, route }: any) => {
  const { user } = useAuthStore();
  const { requestId } = route.params || {};
  const [request, setRequest] = useState<LaundryRequest | null>(null);
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [updatingStatus, setUpdatingStatus] = useState(false);
  const [notes, setNotes] = useState('');
  const [verifyCodeModalVisible, setVerifyCodeModalVisible] = useState(false);
  const [pickupCodeInput, setPickupCodeInput] = useState('');
  const [changeStatusModalVisible, setChangeStatusModalVisible] = useState(false);
  const [changeStatusSelected, setChangeStatusSelected] = useState<string>('');
  const [changeStatusNotes, setChangeStatusNotes] = useState('');

  const fetchRequest = async () => {
    try {
      const response = await apiService.get<{ data: LaundryRequest }>(
        `${APP_CONFIG.ENDPOINTS.LAUNDRY_REQUESTS}/${requestId}`
      );
      setRequest(response.data);
    } catch (error) {
      console.error('Failed to fetch laundry request:', error);
      Alert.alert('Error', 'Failed to load request details');
      navigation.goBack();
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

  const handleCollect = async () => {
    Alert.prompt(
      'Collection Notes',
      'Add any notes about the collection:',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Collect',
          onPress: async (collectionNotes) => {
            try {
              setUpdatingStatus(true);
              await apiService.post(
                `${APP_CONFIG.ENDPOINTS.LAUNDRY_REQUESTS}/${requestId}/collect`,
                { collection_notes: collectionNotes || '' }
              );
              Alert.alert('Success', 'Request collected successfully');
              fetchRequest();
            } catch (error: any) {
              Alert.alert('Error', error.response?.data?.detail || 'Failed to collect request');
            } finally {
              setUpdatingStatus(false);
            }
          },
        },
      ],
      'plain-text',
      notes
    );
  };

  const handleMarkReady = async () => {
    Alert.prompt(
      'Ready for Delivery',
      'Add any notes about readiness:',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Mark Ready',
          onPress: async (readyNotes) => {
            try {
              setUpdatingStatus(true);
              await apiService.post(
                `${APP_CONFIG.ENDPOINTS.LAUNDRY_REQUESTS}/${requestId}/ready-for-pickup`,
                { notes: readyNotes || '' }
              );
              Alert.alert('Success', 'Request marked as ready');
              fetchRequest();
            } catch (error: any) {
              Alert.alert('Error', error.response?.data?.detail || 'Failed to mark as ready');
            } finally {
              setUpdatingStatus(false);
            }
          },
        },
      ],
      'plain-text',
      notes
    );
  };

  const openVerifyPickupModal = () => {
    setPickupCodeInput('');
    setVerifyCodeModalVisible(true);
  };

  const submitVerifyPickup = async (code?: string) => {
    const trimmed = (code ?? pickupCodeInput).trim();
    if (trimmed.length !== 4) {
      Alert.alert('Invalid Code', 'Please enter the 4-digit pickup code.');
      return;
    }
    try {
      setUpdatingStatus(true);
      setVerifyCodeModalVisible(false);
      await apiService.post(
        `${APP_CONFIG.ENDPOINTS.LAUNDRY_REQUESTS}/${requestId}/verify-code`,
        { pickup_code: trimmed }
      );
      Alert.alert('Success', 'Pickup verified. Request marked as completed.');
      fetchRequest();
    } catch (error: any) {
      const msg = error.response?.data?.detail || 'Failed to verify code. Check the code and try again.';
      Alert.alert('Error', msg);
    } finally {
      setUpdatingStatus(false);
    }
  };

  const handleVerifyPickup = () => {
    if (Platform.OS === 'ios') {
      Alert.prompt(
        'Verify Pickup',
        'Enter the 4-digit code provided by the student to complete delivery:',
        [
          { text: 'Cancel', style: 'cancel' },
          {
            text: 'Verify & Complete',
            onPress: (code) => {
              if (code != null) submitVerifyPickup(code);
            },
          },
        ],
        'plain-text',
        '',
        'number-pad'
      );
    } else {
      openVerifyPickupModal();
    }
  };

  const handleManualVerify = async () => {
    Alert.prompt(
      'Manual Verification',
      'Add verification notes:',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Verify',
          onPress: async (verifyNotes) => {
            try {
              setUpdatingStatus(true);
              await apiService.post(
                `${APP_CONFIG.ENDPOINTS.LAUNDRY_REQUESTS}/${requestId}/manual-verify`,
                { verify_notes: verifyNotes || '' }
              );
              Alert.alert('Success', 'Request manually verified');
              fetchRequest();
            } catch (error: any) {
              Alert.alert('Error', error.response?.data?.detail || 'Failed to verify request');
            } finally {
              setUpdatingStatus(false);
            }
          },
        },
      ],
      'plain-text',
      notes
    );
  };

  const getCurrentStatusValue = (): string => {
    if (!request) return '';
    const s = request.status;
    return typeof s === 'string' ? s : (s?.value ?? '');
  };

  /** Allowed next statuses for "Change status" (excluding ready; use Mark Ready for that). */
  const getChangeStatusOptions = (): { value: string; label: string }[] => {
    const current = getCurrentStatusValue();
    const map: Record<string, { value: string; label: string }[]> = {
      pending: [{ value: 'scheduled', label: 'Scheduled' }],
      scheduled: [{ value: 'collected', label: 'Collected' }],
      collected: [{ value: 'washing', label: 'Washing' }],
      washing: [{ value: 'drying', label: 'Drying' }],
    };
    return map[current] ?? [];
  };

  const openChangeStatusModal = () => {
    const opts = getChangeStatusOptions();
    setChangeStatusSelected(opts[0]?.value ?? '');
    setChangeStatusNotes('');
    setChangeStatusModalVisible(true);
  };

  const submitChangeStatus = async () => {
    if (!changeStatusSelected) return;
    try {
      setUpdatingStatus(true);
      setChangeStatusModalVisible(false);
      await apiService.patch(
        `${APP_CONFIG.ENDPOINTS.LAUNDRY_REQUESTS}/${requestId}/status`,
        { status: changeStatusSelected, notes: changeStatusNotes.trim() || undefined }
      );
      Alert.alert('Success', 'Status updated.');
      fetchRequest();
    } catch (error: any) {
      Alert.alert('Error', error.response?.data?.detail || 'Failed to update status.');
    } finally {
      setUpdatingStatus(false);
    }
  };

  const getAllowedActions = (): string[] => {
    if (!request) return [];
    const currentStatus = getCurrentStatusValue();
    const allowedActions: string[] = [];

    switch (currentStatus) {
      case 'pending':
      case 'scheduled':
      case 'collected':
      case 'washing':
      case 'drying':
        if (getChangeStatusOptions().length > 0) allowedActions.push('change_status');
        if (currentStatus === 'drying') {
          allowedActions.push('mark_ready');
        }
        break;
      case 'ready':
        allowedActions.push('verify_pickup');
        if (request.verification?.requires_manual_verify) {
          allowedActions.push('manual_verify');
        }
        break;
    }

    return allowedActions;
  };

  if (loading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color={colors.primary} />
        <Text style={styles.loadingText}>Loading request details...</Text>
      </View>
    );
  }

  if (!request) {
    return (
      <View style={styles.errorContainer}>
        <Ionicons name="alert-circle-outline" size={48} color={colors.error} />
        <Text style={styles.errorText}>Request not found</Text>
        <GradientButton style={styles.backButton} onPress={() => navigation.goBack()}>
          <Text style={styles.backButtonText}>Go Back</Text>
        </GradientButton>
      </View>
    );
  }

  const allowedActions = getAllowedActions();
  const statusValue = getCurrentStatusValue();
  const statusDisplay = typeof request.status === 'object' && request.status !== null
    ? { label: (request.status as { label?: string }).label ?? statusValue, color: (request.status as { color?: string }).color ?? getStatusColor(statusValue) }
    : { label: statusValue.charAt(0).toUpperCase() + statusValue.slice(1).replace('_', ' '), color: getStatusColor(statusValue) };

  return (
    <ScrollView
      style={styles.container}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
      }>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Request Details" />

      {/* Status Badge */}
      <View style={styles.statusSection}>
        <View style={[styles.statusBadge, { backgroundColor: statusDisplay.color + '20' }]}>
          <Ionicons
            name={getStatusIcon(statusValue)}
            size={24}
            color={statusDisplay.color}
          />
          <Text style={[styles.statusText, { color: statusDisplay.color }]}>
            {statusDisplay.label}
          </Text>
        </View>
        {(request.status?.value === 'ready' || (request as any).status === 'ready') && request.pickup_code && (
          <View style={styles.pickupCodeBadge}>
            <Text style={styles.pickupCodeLabel}>Pickup code</Text>
            <Text style={styles.pickupCodeValue}>{request.pickup_code}</Text>
          </View>
        )}
        {request.overdue?.is_overdue && (
          <View style={styles.overdueBadge}>
            <Ionicons name="alert-circle" size={16} color={colors.error} />
            <Text style={styles.overdueText}>
              {request.overdue.days_overdue} day{request.overdue.days_overdue !== 1 ? 's' : ''} overdue
            </Text>
          </View>
        )}
      </View>

      {/* Student Information */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Student Information</Text>
        <View style={styles.infoCard}>
          <View style={styles.infoRow}>
            <Ionicons name="person-outline" size={20} color={colors.primary} />
            <Text style={styles.infoLabel}>Name:</Text>
            <Text style={styles.infoValue}>{request.student?.name || 'Unknown'}</Text>
          </View>
          <View style={styles.infoRow}>
            <Ionicons name="id-card-outline" size={20} color={colors.primary} />
            <Text style={styles.infoLabel}>Student UID:</Text>
            <Text style={styles.infoValue}>{request.student?.student_uid || 'N/A'}</Text>
          </View>
          <View style={styles.infoRow}>
            <Ionicons name="home-outline" size={20} color={colors.primary} />
            <Text style={styles.infoLabel}>Hostel:</Text>
            <Text style={styles.infoValue}>
              {request.student?.hostel_name || request.hostel?.name || 'N/A'}
            </Text>
          </View>
          {request.student?.room_no && (
            <View style={styles.infoRow}>
              <Ionicons name="bed-outline" size={20} color={colors.primary} />
              <Text style={styles.infoLabel}>Room:</Text>
              <Text style={styles.infoValue}>Room {request.student.room_no}</Text>
            </View>
          )}
        </View>
      </View>

      {/* Request Details */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Request Details</Text>
        <View style={styles.infoCard}>
          <View style={styles.infoRow}>
            <Ionicons name="shirt-outline" size={20} color={colors.primary} />
            <Text style={styles.infoLabel}>Service Type:</Text>
            <Text style={styles.infoValue}>{request.service_type?.label || 'Unknown'}</Text>
          </View>
          <View style={styles.infoRow}>
            <Ionicons name="cube-outline" size={20} color={colors.primary} />
            <Text style={styles.infoLabel}>Bags:</Text>
            <Text style={styles.infoValue}>
              {request.bag_count} bag{request.bag_count !== 1 ? 's' : ''}
            </Text>
          </View>
          {request.weight_kg && (
            <View style={styles.infoRow}>
              <Ionicons name="scale-outline" size={20} color={colors.primary} />
              <Text style={styles.infoLabel}>Weight:</Text>
              <Text style={styles.infoValue}>{request.weight_kg} kg</Text>
            </View>
          )}
          {request.price && (
            <View style={styles.infoRow}>
              <Ionicons name="cash-outline" size={20} color={colors.primary} />
              <Text style={styles.infoLabel}>Price:</Text>
              <Text style={styles.infoValue}>
                ₹{request.price.total.toFixed(2)} {request.price.currency}
              </Text>
            </View>
          )}
          {request.requested_at && (
            <View style={styles.infoRow}>
              <Ionicons name="calendar-outline" size={20} color={colors.primary} />
              <Text style={styles.infoLabel}>Requested:</Text>
              <Text style={styles.infoValue}>
                {format(new Date(request.requested_at), 'MMM dd, yyyy HH:mm')}
              </Text>
            </View>
          )}
          {request.estimated_completion_at && (
            <View style={styles.infoRow}>
              <Ionicons name="time-outline" size={20} color={colors.primary} />
              <Text style={styles.infoLabel}>Est. Completion:</Text>
              <Text style={styles.infoValue}>
                {format(new Date(request.estimated_completion_at), 'MMM dd, yyyy HH:mm')}
              </Text>
            </View>
          )}
        </View>
      </View>

      {/* Notes Section */}
      {request.special_instructions && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Special Instructions</Text>
          <View style={styles.notesCard}>
            <Text style={styles.notesText}>{request.special_instructions}</Text>
          </View>
        </View>
      )}

      {request.collection_notes && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Collection Notes</Text>
          <View style={styles.notesCard}>
            <Text style={styles.notesText}>{request.collection_notes}</Text>
          </View>
        </View>
      )}

      {request.delivery_notes && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Delivery Notes</Text>
          <View style={styles.notesCard}>
            <Text style={styles.notesText}>{request.delivery_notes}</Text>
          </View>
        </View>
      )}

      {request.manual_verify_notes && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Manual Verification Notes</Text>
          <View style={styles.notesCard}>
            <Text style={styles.notesText}>{request.manual_verify_notes}</Text>
          </View>
        </View>
      )}

      {/* Action Buttons */}
      {allowedActions.length > 0 && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Actions</Text>
          <View style={styles.actionsContainer}>
            {allowedActions.includes('change_status') && (
              <GradientButton
                style={[styles.actionButton, styles.changeStatusButton, updatingStatus && styles.buttonDisabled]}
                onPress={openChangeStatusModal}
                disabled={updatingStatus}>
                {updatingStatus ? (
                  <ActivityIndicator size="small" color={colors.white} />
                ) : (
                  <>
                    <Ionicons name="swap-horizontal-outline" size={20} color={colors.white} />
                    <Text style={styles.actionButtonText}>Change status</Text>
                  </>
                )}
              </GradientButton>
            )}
            {allowedActions.includes('collect') && (
              <GradientButton
                style={[styles.actionButton, styles.collectButton, updatingStatus && styles.buttonDisabled]}
                onPress={handleCollect}
                disabled={updatingStatus}>
                {updatingStatus ? (
                  <ActivityIndicator size="small" color={colors.white} />
                ) : (
                  <>
                    <Ionicons name="bag-outline" size={20} color={colors.white} />
                    <Text style={styles.actionButtonText}>Collect</Text>
                  </>
                )}
              </GradientButton>
            )}

            {allowedActions.includes('mark_ready') && (
              <GradientButton
                style={[styles.actionButton, styles.readyButton, updatingStatus && styles.buttonDisabled]}
                onPress={handleMarkReady}
                disabled={updatingStatus}>
                {updatingStatus ? (
                  <ActivityIndicator size="small" color={colors.white} />
                ) : (
                  <>
                    <Ionicons name="checkmark-circle-outline" size={20} color={colors.white} />
                    <Text style={styles.actionButtonText}>Mark Ready</Text>
                  </>
                )}
              </GradientButton>
            )}

            {allowedActions.includes('verify_pickup') && (
              <GradientButton
                style={[styles.actionButton, styles.deliverButton, updatingStatus && styles.buttonDisabled]}
                onPress={handleVerifyPickup}
                disabled={updatingStatus}>
                {updatingStatus ? (
                  <ActivityIndicator size="small" color={colors.white} />
                ) : (
                  <>
                    <Ionicons name="shield-checkmark-outline" size={20} color={colors.white} />
                    <Text style={styles.actionButtonText}>Verify Pickup & Complete</Text>
                  </>
                )}
              </GradientButton>
            )}

            {allowedActions.includes('manual_verify') && (
              <GradientButton
                style={[styles.actionButton, styles.verifyButton, updatingStatus && styles.buttonDisabled]}
                onPress={handleManualVerify}
                disabled={updatingStatus}>
                {updatingStatus ? (
                  <ActivityIndicator size="small" color={colors.white} />
                ) : (
                  <>
                    <Ionicons name="shield-checkmark-outline" size={20} color={colors.white} />
                    <Text style={styles.actionButtonText}>Manual Verify</Text>
                  </>
                )}
              </GradientButton>
            )}
          </View>
        </View>
      )}

      {/* Status History */}
      {request.status_history && request.status_history.length > 0 && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Status History</Text>
          <View style={styles.historyCard}>
            {request.status_history.map((entry, index) => (
              <View key={index} style={styles.historyItem}>
                <View style={styles.historyDot} />
                <View style={styles.historyContent}>
                  <Text style={styles.historyStatus}>{entry.status}</Text>
                  <Text style={styles.historyTime}>
                    {format(new Date(entry.timestamp), 'MMM dd, yyyy HH:mm')}
                  </Text>
                  {entry.notes && (
                    <Text style={styles.historyNotes}>{entry.notes}</Text>
                  )}
                </View>
              </View>
            ))}
          </View>
        </View>
      )}

      {/* Change status modal */}
      <Modal
        visible={changeStatusModalVisible}
        transparent
        animationType="fade"
        onRequestClose={() => setChangeStatusModalVisible(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <Text style={styles.modalTitle}>Change status</Text>
            <Text style={styles.modalSubtitle}>
              Update to the next step. Current: In progress.
            </Text>
            <View style={styles.statusOptionsRow}>
              {getChangeStatusOptions().map((opt) => (
                <TouchableOpacity
                  key={opt.value}
                  style={[
                    styles.statusOptionChip,
                    changeStatusSelected === opt.value && styles.statusOptionChipActive,
                  ]}
                  onPress={() => setChangeStatusSelected(opt.value)}
                >
                  <Text
                    style={[
                      styles.statusOptionChipText,
                      changeStatusSelected === opt.value && styles.statusOptionChipTextActive,
                    ]}
                  >
                    {opt.label}
                  </Text>
                </TouchableOpacity>
              ))}
            </View>
            <TextInput
              style={styles.modalNotesInput}
              value={changeStatusNotes}
              onChangeText={setChangeStatusNotes}
              placeholder="Notes (optional)"
              placeholderTextColor={colors.textMuted}
              multiline
              numberOfLines={2}
            />
            <View style={styles.modalButtons}>
              <GradientButton
                style={[styles.modalButton, styles.modalButtonCancel]}
                onPress={() => setChangeStatusModalVisible(false)}
              >
                <Text style={styles.modalButtonCancelText}>Cancel</Text>
              </GradientButton>
              <GradientButton
                style={[styles.modalButton, styles.modalButtonSubmit]}
                onPress={submitChangeStatus}
                disabled={updatingStatus}
              >
                {updatingStatus ? (
                  <ActivityIndicator size="small" color={colors.white} />
                ) : (
                  <Text style={styles.modalButtonSubmitText}>Update status</Text>
                )}
              </GradientButton>
            </View>
          </View>
        </View>
      </Modal>

      {/* Verify pickup code modal (Android) */}
      <Modal
        visible={verifyCodeModalVisible}
        transparent
        animationType="fade"
        onRequestClose={() => setVerifyCodeModalVisible(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <Text style={styles.modalTitle}>Verify Pickup</Text>
            <Text style={styles.modalSubtitle}>
              Enter the 4-digit code provided by the student to complete delivery.
            </Text>
            <TextInput
              style={styles.modalInput}
              value={pickupCodeInput}
              onChangeText={(t) => setPickupCodeInput(t.replace(/\D/g, '').slice(0, 4))}
              placeholder="0000"
              placeholderTextColor={colors.textMuted}
              keyboardType="number-pad"
              maxLength={4}
              autoFocus
            />
            <View style={styles.modalButtons}>
              <GradientButton
                style={[styles.modalButton, styles.modalButtonCancel]}
                onPress={() => setVerifyCodeModalVisible(false)}
              >
                <Text style={styles.modalButtonCancelText}>Cancel</Text>
              </GradientButton>
              <GradientButton
                style={[styles.modalButton, styles.modalButtonSubmit]}
                onPress={() => submitVerifyPickup()}
                disabled={updatingStatus}
              >
                {updatingStatus ? (
                  <ActivityIndicator size="small" color={colors.white} />
                ) : (
                  <Text style={styles.modalButtonSubmitText}>Verify & Complete</Text>
                )}
              </GradientButton>
            </View>
          </View>
        </View>
      </Modal>
    </ScrollView>
  );
};

const getStatusColor = (statusValue: string): string => {
  switch (statusValue) {
    case 'pending': return colors.warning;
    case 'scheduled': return '#3B82F6';
    case 'collected': return '#8B5CF6';
    case 'washing': return '#06B6D4';
    case 'drying': return '#6366F1';
    case 'ready': return colors.success;
    case 'delivered':
    case 'completed': return colors.success;
    case 'cancelled':
    case 'lost':
    case 'damaged': return colors.error;
    default: return colors.textSecondary;
  }
};

const getStatusIcon = (statusValue: string) => {
  switch (statusValue) {
    case 'pending':
      return 'time-outline';
    case 'scheduled':
      return 'calendar-outline';
    case 'collected':
      return 'bag-outline';
    case 'washing':
      return 'water-outline';
    case 'drying':
      return 'cloud-outline';
    case 'ready':
      return 'checkmark-circle-outline';
    case 'delivered':
      return 'checkmark-done-outline';
    case 'completed':
      return 'flag-outline';
    case 'cancelled':
      return 'close-circle-outline';
    case 'lost':
      return 'alert-outline';
    case 'damaged':
      return 'warning-outline';
    default:
      return 'document-text-outline';
  }
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
  },
  loadingText: {
    marginTop: 16,
    fontSize: 16,
    color: colors.textSecondary,
  },
  errorContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
  },
  errorText: {
    fontSize: 18,
    fontWeight: 'bold',
    color: colors.error,
    marginTop: 16,
    marginBottom: 24,
  },
  backButton: {
    backgroundColor: colors.primary,
    paddingHorizontal: 24,
    paddingVertical: 12,
    borderRadius: 8,
  },
  backButtonText: {
    color: colors.white,
    fontSize: 16,
    fontWeight: '600',
  },
  statusSection: {
    padding: 16,
    backgroundColor: colors.white,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  statusBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: 20,
    gap: 8,
  },
  statusText: {
    fontSize: 16,
    fontWeight: '600',
  },
  pickupCodeBadge: {
    backgroundColor: colors.surfaceMuted,
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 12,
    alignItems: 'center',
  },
  pickupCodeLabel: {
    fontSize: 11,
    color: colors.textSecondary,
    marginBottom: 2,
  },
  pickupCodeValue: {
    fontSize: 20,
    fontWeight: '700',
    letterSpacing: 4,
    color: colors.text,
  },
  overdueBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.error + '20',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 16,
    gap: 6,
  },
  overdueText: {
    fontSize: 12,
    fontWeight: '600',
    color: colors.error,
  },
  section: {
    padding: 16,
    backgroundColor: colors.white,
    marginTop: 8,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: colors.text,
    marginBottom: 12,
  },
  infoCard: {
    backgroundColor: colors.background,
    borderRadius: 12,
    padding: 16,
  },
  infoRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 12,
  },
  infoLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textSecondary,
    marginLeft: 12,
    minWidth: 120,
  },
  infoValue: {
    fontSize: 14,
    color: colors.text,
    flex: 1,
  },
  notesCard: {
    backgroundColor: colors.background,
    borderRadius: 12,
    padding: 16,
  },
  notesText: {
    fontSize: 14,
    color: colors.text,
    lineHeight: 20,
  },
  actionsContainer: {
    gap: 12,
  },
  actionButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 14,
    paddingHorizontal: 20,
    borderRadius: 12,
    gap: 8,
  },
  changeStatusButton: {
    backgroundColor: colors.info,
  },
  collectButton: {
    backgroundColor: colors.info,
  },
  readyButton: {
    backgroundColor: colors.success,
  },
  deliverButton: {
    backgroundColor: colors.primary,
  },
  verifyButton: {
    backgroundColor: colors.warning,
  },
  buttonDisabled: {
    opacity: 0.6,
  },
  actionButtonText: {
    color: colors.white,
    fontSize: 16,
    fontWeight: '600',
  },
  historyCard: {
    backgroundColor: colors.background,
    borderRadius: 12,
    padding: 16,
  },
  historyItem: {
    flexDirection: 'row',
    marginBottom: 16,
  },
  historyDot: {
    width: 12,
    height: 12,
    borderRadius: 6,
    backgroundColor: colors.primary,
    marginRight: 12,
    marginTop: 4,
  },
  historyContent: {
    flex: 1,
  },
  historyStatus: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.text,
    marginBottom: 4,
  },
  historyTime: {
    fontSize: 12,
    color: colors.textSecondary,
    marginBottom: 4,
  },
  historyNotes: {
    fontSize: 14,
    color: colors.textSecondary,
    fontStyle: 'italic',
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.5)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 24,
  },
  modalContent: {
    backgroundColor: colors.surface,
    borderRadius: 16,
    padding: 24,
    width: '100%',
    maxWidth: 340,
  },
  modalTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: colors.text,
    marginBottom: 8,
  },
  modalSubtitle: {
    fontSize: 14,
    color: colors.textSecondary,
    marginBottom: 20,
  },
  modalInput: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 12,
    paddingHorizontal: 16,
    paddingVertical: 14,
    fontSize: 24,
    letterSpacing: 8,
    color: colors.text,
    marginBottom: 24,
    textAlign: 'center',
  },
  modalNotesInput: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 12,
    paddingHorizontal: 16,
    paddingVertical: 12,
    fontSize: 14,
    color: colors.text,
    marginBottom: 20,
    minHeight: 56,
    textAlignVertical: 'top',
  },
  statusOptionsRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
    marginBottom: 16,
  },
  statusOptionChip: {
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: 20,
    backgroundColor: colors.surfaceMuted,
  },
  statusOptionChipActive: {
    backgroundColor: colors.primary,
  },
  statusOptionChipText: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textSecondary,
  },
  statusOptionChipTextActive: {
    color: colors.white,
  },
  modalButtons: {
    flexDirection: 'row',
    gap: 12,
  },
  modalButton: {
    flex: 1,
    paddingVertical: 14,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
  },
  modalButtonCancel: {
    backgroundColor: colors.surfaceMuted,
  },
  modalButtonCancelText: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.textSecondary,
  },
  modalButtonSubmit: {
    backgroundColor: colors.primary,
  },
  modalButtonSubmitText: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.white,
  },
});
