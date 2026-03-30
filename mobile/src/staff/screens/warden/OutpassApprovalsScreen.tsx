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

interface Outpass {
  id: number;
  student_name: string;
  student_id: string;
  room_number: string;
  pass_type: string;
  reason: string;
  valid_from: string;
  valid_until: string;
  status: string;
  created_at: string;
  contact_number?: string;
  destination?: string;
}

interface Props {
  navigation: any;
}

export const OutpassApprovalsScreen: React.FC<Props> = ({ navigation }) => {
  const [outpasses, setOutpasses] = useState<Outpass[]>([]);
  const [_isLoading, setIsLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [_error, setError] = useState<string | null>(null);
  const [selectedOutpass, setSelectedOutpass] = useState<Outpass | null>(null);
  const [modalVisible, setModalVisible] = useState(false);
  const [actionType, setActionType] = useState<'approve' | 'reject'>('approve');
  const [rejectReason, setRejectReason] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const fetchOutpasses = useCallback(async () => {
    try {
      setError(null);
      const response = await apiService.get<any>('/warden/outpasses/pending');
      // apiService returns data directly, but backend may wrap it in { data: ... }
      setOutpasses(response?.data || response || []);
    } catch (err) {
      console.error('Failed to fetch outpasses:', err);
      setError('Failed to load outpass requests. Pull down to refresh.');
      setOutpasses([]);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchOutpasses();
  }, [fetchOutpasses]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await fetchOutpasses();
    setRefreshing(false);
  }, [fetchOutpasses]);

  const handleAction = (outpass: Outpass, action: 'approve' | 'reject') => {
    setSelectedOutpass(outpass);
    setActionType(action);
    setRejectReason('');
    setModalVisible(true);
  };

  const confirmAction = async () => {
    if (!selectedOutpass) return;
    if (actionType === 'reject' && !rejectReason.trim()) {
      Alert.alert('Error', 'Please provide a reason for rejection');
      return;
    }

    setIsSubmitting(true);
    try {
      await apiService.post(`/outpasses/${selectedOutpass.id}/${actionType}`, {
        rejection_reason: actionType === 'reject' ? rejectReason : undefined,
      });

      Alert.alert(
        'Success',
        `Outpass ${actionType === 'approve' ? 'approved' : 'rejected'} successfully`
      );
      setModalVisible(false);
      fetchOutpasses();
    } catch {
      Alert.alert('Error', `Failed to ${actionType} outpass`);
    } finally {
      setIsSubmitting(false);
    }
  };

  const formatDateTime = (dateString: string) => {
    const date = new Date(dateString);
    return `${date.toLocaleDateString([], { day: 'numeric', month: 'short' })} • ${date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
  };

  const getPassTypeLabel = (type: string) => {
    switch (type) {
      case 'day_pass':
        return 'Day Pass';
      case 'night_out':
        return 'Night Out';
      case 'weekend':
        return 'Weekend';
      case 'emergency':
        return 'Emergency';
      default:
        return type;
    }
  };

  const getPassTypeColor = (type: string) => {
    switch (type) {
      case 'day_pass':
        return theme.colors.primary;
      case 'night_out':
        return theme.colors.primary;
      case 'weekend':
        return theme.colors.success;
      case 'emergency':
        return theme.colors.error;
      default:
        return theme.colors.textSecondary;
    }
  };

  const renderOutpass = ({ item }: { item: Outpass }) => (
    <View style={styles.outpassCard}>
      <Text style={styles.outpassIdLabel}>Outpass #{item.id}</Text>
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
            styles.passTypeBadge,
            { backgroundColor: getPassTypeColor(item.pass_type) + '20' },
          ]}
        >
          <Text
            style={[
              styles.passTypeText,
              { color: getPassTypeColor(item.pass_type) },
            ]}
          >
            {getPassTypeLabel(item.pass_type)}
          </Text>
        </View>
      </View>

      <View style={styles.reasonSection}>
        <Text style={styles.reasonLabel}>Reason</Text>
        <Text style={styles.reasonText}>{item.reason}</Text>
      </View>

      {item.destination && (
        <View style={styles.infoRow}>
          <Icon name="map-marker-outline" size={16} color={theme.colors.textSecondary} />
          <Text style={styles.infoText}>{item.destination}</Text>
        </View>
      )}

      <View style={styles.timeSection}>
        <View style={styles.timeBlock}>
          <Text style={styles.timeLabel}>From</Text>
          <Text style={styles.timeValue}>{formatDateTime(item.valid_from)}</Text>
        </View>
        <Icon name="arrow-right" size={16} color={theme.colors.textMuted} />
        <View style={styles.timeBlock}>
          <Text style={styles.timeLabel}>Until</Text>
          <Text style={styles.timeValue}>{formatDateTime(item.valid_until)}</Text>
        </View>
      </View>

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
      <StaffScreenHeader
        onBack={() => navigation.goBack()}
        onNotificationsPress={() => navigation.navigate('Notifications')}
        rightSlot={
          <View style={styles.headerBadge}>
            <Text style={styles.headerBadgeText}>{outpasses.length}</Text>
          </View>
        }  title="Outpass Approvals" />

      <FlatList
        data={outpasses}
        renderItem={renderOutpass}
        keyExtractor={item => item.id.toString()}
        contentContainerStyle={styles.listContent}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
        ListEmptyComponent={
          <View style={styles.emptyState}>
            <Icon name="check-circle-outline" size={64} color={theme.colors.success} />
            <Text style={styles.emptyText}>No pending outpasses</Text>
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
                {actionType === 'approve' ? 'Approve Outpass' : 'Reject Outpass'}
              </Text>
            </View>

            {selectedOutpass && (
              <View style={styles.modalBody}>
                <Text style={styles.modalStudentName}>
                  {selectedOutpass.student_name}
                </Text>
                <Text style={styles.modalStudentMeta}>
                  {getPassTypeLabel(selectedOutpass.pass_type)} •{' '}
                  {selectedOutpass.room_number}
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
    display: 'none',
  },
  headerBadge: {
    backgroundColor: theme.colors.accentMuted,
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderRadius: 12,
  },
  headerBadgeText: {
    color: theme.colors.primary,
    fontSize: 14,
    fontWeight: '600',
  },
  listContent: {
    padding: 16,
  },
  outpassCard: {
    backgroundColor: theme.colors.white,
    borderRadius: 16,
    padding: 16,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  outpassIdLabel: {
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
  passTypeBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 8,
  },
  passTypeText: {
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
  timeSection: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 12,
    borderTopWidth: 1,
    borderBottomWidth: 1,
    borderColor: theme.colors.divider,
    marginBottom: 12,
  },
  timeBlock: {
    flex: 1,
  },
  timeLabel: {
    fontSize: 11,
    color: theme.colors.textMuted,
    textTransform: 'uppercase',
    marginBottom: 4,
  },
  timeValue: {
    fontSize: 13,
    fontWeight: '500',
    color: theme.colors.text,
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

export default OutpassApprovalsScreen;
