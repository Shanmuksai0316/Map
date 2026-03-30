import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  FlatList,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  Modal,
  TextInput,
  Alert,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { theme } from '../../../shared/theme/theme';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface Leave {
  id: number;
  student_name: string;
  student_id: string;
  room_number: string;
  leave_type: string;
  reason: string;
  from_date: string;
  to_date: string;
  status: string;
  created_at: string;
  emergency_contact?: string;
  address?: string;
  total_days?: number;
}

interface Props {
  navigation: any;
}

export const LeaveApprovalsScreen: React.FC<Props> = ({ navigation }) => {
  const [leaves, setLeaves] = useState<Leave[]>([]);
  const [_isLoading, setIsLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [selectedLeave, setSelectedLeave] = useState<Leave | null>(null);
  const [modalVisible, setModalVisible] = useState(false);
  const [actionType, setActionType] = useState<'approve' | 'reject'>('approve');
  const [rejectReason, setRejectReason] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const fetchLeaves = useCallback(async () => {
    try {
      const response = await apiService.get<any>('/warden/leaves/pending');
      // apiService returns data directly, but backend may wrap it in { data: ... }
      setLeaves(response?.data || response || []);
    } catch (error) {
      console.error('Failed to fetch leaves:', error);
      // Show empty state - no mock data in production
      setLeaves([]);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchLeaves();
  }, [fetchLeaves]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await fetchLeaves();
    setRefreshing(false);
  }, [fetchLeaves]);

  const handleAction = (leave: Leave, action: 'approve' | 'reject') => {
    setSelectedLeave(leave);
    setActionType(action);
    setRejectReason('');
    setModalVisible(true);
  };

  const confirmAction = async () => {
    if (!selectedLeave) return;
    if (actionType === 'reject' && !rejectReason.trim()) {
      Alert.alert('Error', 'Please provide a reason for rejection');
      return;
    }

    setIsSubmitting(true);
    try {
      await apiService.post(`/leaves/${selectedLeave.id}/${actionType}`, {
        rejection_reason: actionType === 'reject' ? rejectReason : undefined,
      });

      Alert.alert(
        'Success',
        `Leave ${actionType === 'approve' ? 'approved' : 'rejected'} successfully`
      );
      setModalVisible(false);
      fetchLeaves();
    } catch {
      Alert.alert('Error', `Failed to ${actionType} leave`);
    } finally {
      setIsSubmitting(false);
    }
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString([], {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
    });
  };

  const getLeaveTypeLabel = (type: string) => {
    switch (type) {
      case 'home_leave':
        return 'Home Leave';
      case 'medical_leave':
        return 'Medical Leave';
      case 'emergency_leave':
        return 'Emergency';
      case 'casual_leave':
        return 'Casual Leave';
      default:
        return type;
    }
  };

  const getLeaveTypeColor = (type: string) => {
    switch (type) {
      case 'home_leave':
        return theme.colors.primary;
      case 'medical_leave':
        return theme.colors.error;
      case 'emergency_leave':
        return theme.colors.warning;
      case 'casual_leave':
        return theme.colors.success;
      default:
        return theme.colors.textSecondary;
    }
  };

  const renderLeave = ({ item }: { item: Leave }) => (
    <View style={styles.leaveCard}>
      <Text style={styles.leaveIdLabel}>Leave #{item.id}</Text>
      <View style={styles.cardHeader}>
        <View style={styles.studentInfo}>
          <View style={styles.avatar}>
            <Text style={styles.avatarText}>
              {item.student_name.charAt(0).toUpperCase()}
            </Text>
          </View>
          <View style={styles.studentDetails}>
            <Text style={styles.studentName}>{item.student_name}</Text>
            <Text style={styles.studentMeta}>
              {item.student_id} • Room {item.room_number}
            </Text>
          </View>
        </View>
        <View
          style={[
            styles.leaveTypeBadge,
            { backgroundColor: getLeaveTypeColor(item.leave_type) + '20' },
          ]}
        >
          <Text
            style={[
              styles.leaveTypeText,
              { color: getLeaveTypeColor(item.leave_type) },
            ]}
          >
            {getLeaveTypeLabel(item.leave_type)}
          </Text>
        </View>
      </View>

      <View style={styles.reasonSection}>
        <Text style={styles.reasonLabel}>Reason</Text>
        <Text style={styles.reasonText}>{item.reason}</Text>
      </View>

      <View style={styles.dateSection}>
        <View style={styles.dateBlock}>
          <Icon name="calendar-start" size={18} color={theme.colors.textSecondary} />
          <View style={styles.dateInfo}>
            <Text style={styles.dateLabel}>From</Text>
            <Text style={styles.dateValue}>{formatDate(item.from_date)}</Text>
          </View>
        </View>
        <View style={styles.dateDivider}>
          <Icon name="arrow-right" size={16} color={theme.colors.textMuted} />
        </View>
        <View style={styles.dateBlock}>
          <Icon name="calendar-end" size={18} color={theme.colors.textSecondary} />
          <View style={styles.dateInfo}>
            <Text style={styles.dateLabel}>To</Text>
            <Text style={styles.dateValue}>{formatDate(item.to_date)}</Text>
          </View>
        </View>
        <View style={styles.durationBadge}>
          <Text style={styles.durationText}>{item.total_days || 1} days</Text>
        </View>
      </View>

      {item.address && (
        <View style={styles.infoRow}>
          <Icon name="map-marker-outline" size={16} color={theme.colors.textSecondary} />
          <Text style={styles.infoText}>{item.address}</Text>
        </View>
      )}

      <View style={styles.actionButtons}>
        <GradientButton
          style={[styles.actionButton, styles.rejectButton]}
          onPress={() => handleAction(item, 'reject')}
        >
          <Icon name="close" size={18} color={theme.colors.error} />
          <Text style={[styles.actionButtonText, { color: theme.colors.error }]}>
            Reject
          </Text>
        </GradientButton>
        <GradientButton
          style={[styles.actionButton, styles.approveButton]}
          onPress={() => handleAction(item, 'approve')}
        >
          <Icon name="check" size={18} color={theme.colors.white} />
          <Text style={styles.actionButtonTextWhite}>Approve</Text>
        </GradientButton>
      </View>
    </View>
  );

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Leave Approvals" />
      <FlatList
        data={leaves}
        renderItem={renderLeave}
        keyExtractor={item => item.id.toString()}
        contentContainerStyle={styles.listContent}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
        ListEmptyComponent={
          <View style={styles.emptyState}>
            <Icon name="check-circle-outline" size={64} color={theme.colors.success} />
            <Text style={styles.emptyText}>No pending leaves</Text>
            <Text style={styles.emptySubtext}>
              All caught up! New requests will appear here.
            </Text>
          </View>
        }
      />

      {/* Action Modal */}
      <Modal
        visible={modalVisible}
        transparent
        animationType="fade"
        onRequestClose={() => setModalVisible(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <View style={styles.modalHeader}>
              <Icon
                name={actionType === 'approve' ? 'check-circle' : 'close-circle'}
                size={40}
                color={actionType === 'approve' ? theme.colors.success : theme.colors.error}
              />
              <Text style={styles.modalTitle}>
                {actionType === 'approve' ? 'Approve Leave' : 'Reject Leave'}
              </Text>
            </View>

            {selectedLeave && (
              <View style={styles.modalBody}>
                <Text style={styles.modalStudentName}>
                  {selectedLeave.student_name}
                </Text>
                <Text style={styles.modalStudentMeta}>
                  {getLeaveTypeLabel(selectedLeave.leave_type)} •{' '}
                  {selectedLeave.total_days || 1} days
                </Text>

                {actionType === 'reject' && (
                  <View style={styles.rejectReasonContainer}>
                    <Text style={styles.rejectReasonLabel}>
                      Rejection Reason *
                    </Text>
                    <TextInput
                      style={styles.rejectReasonInput}
                      value={rejectReason}
                      onChangeText={setRejectReason}
                      placeholder="Enter reason for rejection..."
                      placeholderTextColor={theme.colors.textMuted}
                      multiline
                      numberOfLines={3}
                      textAlignVertical="top"
                    />
                  </View>
                )}
              </View>
            )}

            <View style={styles.modalActions}>
              <GradientButton
                style={styles.modalCancelButton}
                onPress={() => setModalVisible(false)}
                disabled={isSubmitting}
              >
                <Text style={styles.modalCancelText}>Cancel</Text>
              </GradientButton>
              <GradientButton
                style={[
                  styles.modalConfirmButton,
                  {
                    backgroundColor:
                      actionType === 'approve' ? theme.colors.success : theme.colors.error,
                  },
                  isSubmitting && styles.buttonDisabled,
                ]}
                onPress={confirmAction}
                disabled={isSubmitting}
              >
                <Text style={styles.modalConfirmText}>
                  {isSubmitting
                    ? 'Processing...'
                    : actionType === 'approve'
                    ? 'Approve'
                    : 'Reject'}
                </Text>
              </GradientButton>
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
    backgroundColor: theme.colors.background,
  },
  header: {
    backgroundColor: theme.colors.primary,
    paddingBottom: 20,
    paddingHorizontal: 20,
    flexDirection: 'row',
    alignItems: 'center',
  },
  headerTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: theme.colors.white,
    flex: 1,
  },
  headerBadge: {
    backgroundColor: 'rgba(255,255,255,0.2)',
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderRadius: 12,
  },
  headerBadgeText: {
    color: theme.colors.white,
    fontSize: 14,
    fontWeight: '600',
  },
  listContent: {
    padding: 16,
  },
  leaveCard: {
    backgroundColor: theme.colors.white,
    borderRadius: 16,
    padding: 16,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  leaveIdLabel: {
    fontSize: 12,
    fontWeight: '600',
    color: theme.colors.textSecondary,
    marginBottom: 8,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 12,
  },
  studentInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  avatar: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: theme.colors.primary,
    justifyContent: 'center',
    alignItems: 'center',
  },
  avatarText: {
    fontSize: 18,
    fontWeight: '700',
    color: theme.colors.white,
  },
  studentDetails: {
    marginLeft: 12,
    flex: 1,
  },
  studentName: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.text,
  },
  studentMeta: {
    fontSize: 13,
    color: theme.colors.textSecondary,
    marginTop: 2,
  },
  leaveTypeBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 8,
  },
  leaveTypeText: {
    fontSize: 12,
    fontWeight: '600',
  },
  reasonSection: {
    backgroundColor: theme.colors.background,
    padding: 12,
    borderRadius: 8,
    marginBottom: 12,
  },
  reasonLabel: {
    fontSize: 11,
    color: theme.colors.textSecondary,
    textTransform: 'uppercase',
    marginBottom: 4,
  },
  reasonText: {
    fontSize: 14,
    color: theme.colors.text,
    lineHeight: 20,
  },
  dateSection: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 12,
    borderTopWidth: 1,
    borderBottomWidth: 1,
    borderColor: theme.colors.divider,
    marginBottom: 12,
  },
  dateBlock: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  dateInfo: {
    marginLeft: 8,
  },
  dateLabel: {
    fontSize: 11,
    color: theme.colors.textMuted,
    textTransform: 'uppercase',
  },
  dateValue: {
    fontSize: 13,
    fontWeight: '500',
    color: theme.colors.text,
    marginTop: 2,
  },
  dateDivider: {
    paddingHorizontal: 8,
  },
  durationBadge: {
    backgroundColor: theme.colors.divider,
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 8,
  },
  durationText: {
    fontSize: 12,
    fontWeight: '600',
    color: theme.colors.textSecondary,
  },
  infoRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 12,
  },
  infoText: {
    fontSize: 13,
    color: theme.colors.textSecondary,
    marginLeft: 8,
  },
  actionButtons: {
    flexDirection: 'row',
    gap: 12,
  },
  actionButton: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 12,
    borderRadius: 10,
  },
  rejectButton: {
    backgroundColor: theme.colors.errorLight,
  },
  approveButton: {
    backgroundColor: theme.colors.success,
  },
  actionButtonText: {
    fontSize: 14,
    fontWeight: '600',
    marginLeft: 6,
  },
  actionButtonTextWhite: {
    fontSize: 14,
    fontWeight: '600',
    marginLeft: 6,
    color: theme.colors.white,
  },
  emptyState: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 60,
  },
  emptyText: {
    fontSize: 18,
    fontWeight: '600',
    color: theme.colors.text,
    marginTop: 16,
  },
  emptySubtext: {
    fontSize: 14,
    color: theme.colors.textSecondary,
    marginTop: 4,
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  modalContent: {
    backgroundColor: theme.colors.white,
    borderRadius: 20,
    padding: 24,
    width: '85%',
    maxWidth: 400,
  },
  modalHeader: {
    alignItems: 'center',
    marginBottom: 20,
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: theme.colors.text,
    marginTop: 12,
  },
  modalBody: {
    alignItems: 'center',
  },
  modalStudentName: {
    fontSize: 18,
    fontWeight: '600',
    color: theme.colors.text,
  },
  modalStudentMeta: {
    fontSize: 14,
    color: theme.colors.textSecondary,
    marginTop: 4,
  },
  rejectReasonContainer: {
    width: '100%',
    marginTop: 20,
  },
  rejectReasonLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: theme.colors.text,
    marginBottom: 8,
  },
  rejectReasonInput: {
    backgroundColor: theme.colors.background,
    borderRadius: 12,
    padding: 14,
    fontSize: 15,
    color: theme.colors.text,
    borderWidth: 1,
    borderColor: theme.colors.border,
    minHeight: 80,
  },
  modalActions: {
    flexDirection: 'row',
    marginTop: 24,
    gap: 12,
  },
  modalCancelButton: {
    flex: 1,
    paddingVertical: 14,
    borderRadius: 10,
    backgroundColor: theme.colors.divider,
    alignItems: 'center',
  },
  modalCancelText: {
    fontSize: 15,
    fontWeight: '600',
    color: theme.colors.textSecondary,
  },
  modalConfirmButton: {
    flex: 1,
    paddingVertical: 14,
    borderRadius: 10,
    alignItems: 'center',
  },
  modalConfirmText: {
    fontSize: 15,
    fontWeight: '600',
    color: theme.colors.white,
  },
  buttonDisabled: {
    opacity: 0.6,
  },
});

export default LeaveApprovalsScreen;
