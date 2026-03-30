import React, { useState } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  TextInput,
  Alert,
  KeyboardAvoidingView,
  Platform,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { apiService } from '../../../shared/services/api.service';
import { theme } from '../../../shared/theme/theme';
import type { Leave } from '../../../shared/types';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface Props {
  navigation: any;
  route: {
    params: {
      leave: Leave;
    };
  };
}

const getStatusColor = (status: string): string => {
  const colors: Record<string, string> = {
    pending: theme.colors.warning,
    approved: theme.colors.success,
    rejected: theme.colors.error,
  };
  return colors[status.toLowerCase()] || theme.colors.textSecondary;
};

export const RectorLeaveDetailScreen: React.FC<Props> = ({ navigation, route }) => {
  const { leave } = route.params;
  const [rejectionReason, setRejectionReason] = useState('');
  const [showRejectForm, setShowRejectForm] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleApprove = async () => {
    Alert.alert(
      'Approve Leave',
      `Are you sure you want to approve this leave request?`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Approve',
          style: 'default',
          onPress: async () => {
            setIsSubmitting(true);
            try {
              await apiService.put(`/mobile/rector/leaves/${leave.id}/approve`);
              Alert.alert('Success', 'Leave request approved successfully', [
                { text: 'OK', onPress: () => navigation.goBack() },
              ]);
            } catch (error) {
              Alert.alert('Error', 'Failed to approve leave request');
            } finally {
              setIsSubmitting(false);
            }
          },
        },
      ]
    );
  };

  const handleReject = async () => {
    if (!rejectionReason.trim()) {
      Alert.alert('Error', 'Please provide a reason for rejection');
      return;
    }

    setIsSubmitting(true);
    try {
      await apiService.put(`/mobile/rector/leaves/${leave.id}/reject`, {
        rejection_reason: rejectionReason.trim(),
      });
      Alert.alert('Success', 'Leave request rejected', [
        { text: 'OK', onPress: () => navigation.goBack() },
      ]);
    } catch (error) {
      Alert.alert('Error', 'Failed to reject leave request');
    } finally {
      setIsSubmitting(false);
    }
  };

  const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    });
  };

  const calculateDuration = (from: string, to: string) => {
    const fromDate = new Date(from);
    const toDate = new Date(to);
    const days = Math.ceil((toDate.getTime() - fromDate.getTime()) / (1000 * 60 * 60 * 24)) + 1;
    return `${days} day${days > 1 ? 's' : ''}`;
  };

  const statusColor = getStatusColor(leave.status);
  const isPending = leave.status === 'pending';

  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Leave Request Details" />
      <View style={styles.header}>
        <View style={[styles.headerStatusBadge, { backgroundColor: statusColor }]}>
          <Text style={styles.headerStatusText}>{leave.status.toUpperCase()}</Text>
        </View>
      </View>

      <ScrollView style={styles.content} showsVerticalScrollIndicator={false}>
        {/* Student Info Card */}
        <View style={styles.studentCard}>
          <View style={styles.avatarContainer}>
            <Icon name="account" size={28} color={theme.colors.white} />
          </View>
          <View style={styles.studentInfo}>
            <Text style={styles.studentName}>{leave.student_name || 'Student'}</Text>
            <View style={styles.studentDetails}>
              {leave.hostel_name && (
                <View style={styles.detailRow}>
                  <Icon name="office-building" size={14} color={theme.colors.textSecondary} />
                  <Text style={styles.detailText}>{leave.hostel_name}</Text>
                </View>
              )}
              {leave.room_number && (
                <View style={styles.detailRow}>
                  <Icon name="door" size={14} color={theme.colors.textSecondary} />
                  <Text style={styles.detailText}>Room {leave.room_number}</Text>
                </View>
              )}
            </View>
          </View>
        </View>

        {/* Request Details */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Request Details</Text>

          <View style={styles.detailCard}>
            <View style={styles.detailItem}>
              <Text style={styles.detailLabel}>Reason</Text>
              <Text style={styles.detailValue}>{leave.reason_for_leave}</Text>
            </View>

            <View style={styles.divider} />

            <View style={styles.detailItem}>
              <Text style={styles.detailLabel}>From Date</Text>
              <Text style={styles.detailValue}>{formatDate(leave.from_date)}</Text>
            </View>

            <View style={styles.divider} />

            <View style={styles.detailItem}>
              <Text style={styles.detailLabel}>To Date</Text>
              <Text style={styles.detailValue}>{formatDate(leave.to_date)}</Text>
            </View>

            <View style={styles.divider} />

            <View style={styles.detailItem}>
              <Text style={styles.detailLabel}>Duration</Text>
              <View style={styles.durationBadge}>
                <Icon name="clock-outline" size={14} color={theme.colors.warning} />
                <Text style={styles.durationText}>
                  {calculateDuration(leave.from_date, leave.to_date)}
                </Text>
              </View>
            </View>

            {leave.description && (
              <>
                <View style={styles.divider} />
                <View style={styles.detailItem}>
                  <Text style={styles.detailLabel}>Description</Text>
                  <Text style={styles.detailValue}>{leave.description}</Text>
                </View>
              </>
            )}

            {leave.submitted_at && (
              <>
                <View style={styles.divider} />
                <View style={styles.detailItem}>
                  <Text style={styles.detailLabel}>Submitted Date & Time</Text>
                  <Text style={styles.detailValue}>
                    {new Date(leave.submitted_at).toLocaleString('en-US', {
                      month: 'short',
                      day: 'numeric',
                      year: 'numeric',
                      hour: '2-digit',
                      minute: '2-digit',
                    })}
                  </Text>
                </View>
              </>
            )}
          </View>
        </View>

        {/* Rejection Form */}
        {isPending && showRejectForm && (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>Rejection Reason</Text>
            <TextInput
              style={styles.rejectionInput}
              value={rejectionReason}
              onChangeText={setRejectionReason}
              placeholder="Enter reason for rejection..."
              placeholderTextColor={theme.colors.textMuted}
              multiline
              numberOfLines={4}
              textAlignVertical="top"
            />
          </View>
        )}

        <View style={styles.bottomPadding} />
      </ScrollView>

      {/* Action Buttons */}
      {isPending && (
        <View style={styles.actionContainer}>
          {showRejectForm ? (
            <>
              <GradientButton
                style={styles.cancelButton}
                onPress={() => {
                  setShowRejectForm(false);
                  setRejectionReason('');
                }}
              >
                <Text style={styles.cancelButtonText}>Cancel</Text>
              </GradientButton>
              <GradientButton
                style={[styles.rejectButton, isSubmitting && styles.buttonDisabled]}
                onPress={handleReject}
                disabled={isSubmitting}
              >
                <Icon name="close" size={20} color={theme.colors.white} />
                <Text style={styles.buttonText}>
                  {isSubmitting ? 'Rejecting...' : 'Confirm Reject'}
                </Text>
              </GradientButton>
            </>
          ) : (
            <>
              <GradientButton
                style={styles.rejectOutlineButton}
                onPress={() => setShowRejectForm(true)}
              >
                <Icon name="close" size={20} color={theme.colors.error} />
                <Text style={styles.rejectOutlineText}>Reject</Text>
              </GradientButton>
              <GradientButton
                style={[styles.approveButton, isSubmitting && styles.buttonDisabled]}
                onPress={handleApprove}
                disabled={isSubmitting}
              >
                <Icon name="check" size={20} color={theme.colors.white} />
                <Text style={styles.buttonText}>
                  {isSubmitting ? 'Approving...' : 'Approve'}
                </Text>
              </GradientButton>
            </>
          )}
        </View>
      )}
    </KeyboardAvoidingView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  header: {
    backgroundColor: theme.colors.white,
    paddingBottom: 16,
    paddingHorizontal: 20,
    flexDirection: 'row',
    alignItems: 'center',
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
  },
  headerTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: theme.colors.primary,
    flex: 1,
  },
  headerStatusBadge: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: theme.borderRadius.sm,
  },
  headerStatusText: {
    color: theme.colors.white,
    fontSize: 11,
    fontWeight: '700',
  },
  content: {
    flex: 1,
    padding: 16,
  },
  studentCard: {
    flexDirection: 'row',
    backgroundColor: theme.colors.card,
    padding: 16,
    borderRadius: theme.borderRadius.lg,
    marginBottom: 16,
    ...theme.shadows.small,
  },
  avatarContainer: {
    width: 56,
    height: 56,
    borderRadius: 28,
    backgroundColor: theme.colors.primary,
    justifyContent: 'center',
    alignItems: 'center',
  },
  studentInfo: {
    flex: 1,
    marginLeft: 14,
  },
  studentName: {
    fontSize: 18,
    fontWeight: '700',
    color: theme.colors.text,
    marginBottom: 4,
  },
  studentDetails: {
    flexDirection: 'row',
    gap: 16,
  },
  detailRow: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  detailText: {
    fontSize: 13,
    color: theme.colors.textSecondary,
    marginLeft: 4,
  },
  section: {
    marginBottom: 16,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.text,
    marginBottom: 12,
  },
  detailCard: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.borderRadius.lg,
    padding: 16,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  detailItem: {
    paddingVertical: 12,
  },
  detailLabel: {
    fontSize: 12,
    fontWeight: '600',
    color: theme.colors.textSecondary,
    textTransform: 'uppercase',
    marginBottom: 4,
  },
  detailValue: {
    fontSize: 15,
    color: theme.colors.text,
    lineHeight: 22,
  },
  divider: {
    height: 1,
    backgroundColor: theme.colors.border,
  },
  durationBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    alignSelf: 'flex-start',
    backgroundColor: theme.colors.warningLight,
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: theme.borderRadius.xs,
    marginTop: 4,
  },
  durationText: {
    fontSize: 13,
    fontWeight: '500',
    color: theme.colors.warning,
    marginLeft: 6,
  },
  rejectionInput: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.borderRadius.md,
    padding: 16,
    fontSize: 15,
    color: theme.colors.text,
    borderWidth: 1,
    borderColor: theme.colors.border,
    minHeight: 120,
  },
  bottomPadding: {
    height: 100,
  },
  actionContainer: {
    flexDirection: 'row',
    padding: 16,
    paddingBottom: 34,
    backgroundColor: theme.colors.card,
    borderTopWidth: 1,
    borderTopColor: theme.colors.border,
    gap: 12,
  },
  rejectOutlineButton: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 14,
    borderRadius: theme.borderRadius.sm,
    borderWidth: 2,
    borderColor: theme.colors.error,
  },
  rejectOutlineText: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.error,
    marginLeft: 8,
  },
  approveButton: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: theme.colors.success,
    paddingVertical: 14,
    borderRadius: theme.borderRadius.sm,
  },
  rejectButton: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: theme.colors.error,
    paddingVertical: 14,
    borderRadius: theme.borderRadius.sm,
  },
  cancelButton: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 14,
    borderRadius: theme.borderRadius.sm,
    backgroundColor: theme.colors.surfaceMuted,
  },
  cancelButtonText: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.textSecondary,
  },
  buttonText: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.white,
    marginLeft: 8,
  },
  buttonDisabled: {
    opacity: 0.6,
  },
});

export default RectorLeaveDetailScreen;
