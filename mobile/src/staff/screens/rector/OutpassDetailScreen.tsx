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
import type { OutPass } from '../../../shared/types';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface Props {
  navigation: any;
  route: {
    params: {
      outpass: OutPass;
    };
  };
}

const getStatusColor = (status: string): string => {
  const colors: Record<string, string> = {
    pending: theme.colors.warning,
    approved: theme.colors.success,
    rejected: theme.colors.error,
    completed: theme.colors.textSecondary,
    emergency_exit: theme.colors.error,
  };
  return colors[status.toLowerCase()] || theme.colors.textSecondary;
};

export const OutpassDetailScreen: React.FC<Props> = ({ navigation, route }) => {
  const { outpass } = route.params;
  const [rejectionReason, setRejectionReason] = useState('');
  const [showRejectForm, setShowRejectForm] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleApprove = async () => {
    Alert.alert(
      'Approve Outpass',
      `Are you sure you want to approve this outpass for ${outpass.student_name}?`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Approve',
          style: 'default',
          onPress: async () => {
            setIsSubmitting(true);
            try {
              await apiService.put(`/mobile/rector/outpasses/${outpass.id}/approve`);
              Alert.alert('Success', 'Outpass approved successfully', [
                { text: 'OK', onPress: () => navigation.goBack() },
              ]);
            } catch {
              Alert.alert('Error', 'Failed to approve outpass');
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
      await apiService.put(`/mobile/rector/outpasses/${outpass.id}/reject`, {
        rejection_reason: rejectionReason.trim(),
      });
      Alert.alert('Success', 'Outpass rejected', [
        { text: 'OK', onPress: () => navigation.goBack() },
      ]);
    } catch {
      Alert.alert('Error', 'Failed to reject outpass');
    } finally {
      setIsSubmitting(false);
    }
  };

  const formatDateTime = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const statusColor = getStatusColor(outpass.status);
  const isPending = outpass.status === 'pending';

  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Outpass Details" />
      <View style={styles.header}>
        <View style={[styles.headerStatusBadge, { backgroundColor: statusColor }]}>
          <Text style={styles.headerStatusText}>{outpass.status.toUpperCase()}</Text>
        </View>
      </View>

      <ScrollView style={styles.content} showsVerticalScrollIndicator={false}>
        {/* Student Info Card */}
        <View style={styles.studentCard}>
          <View style={styles.avatarContainer}>
            <Text style={styles.avatarText}>
              {outpass.student_name
                ?.split(' ')
                .map((n) => n[0])
                .join('')
                .substring(0, 2)}
            </Text>
          </View>
          <View style={styles.studentInfo}>
            <Text style={styles.studentName}>{outpass.student_name}</Text>
            <View style={styles.studentDetails}>
              {outpass.hostel && (
                <View style={styles.detailRow}>
                  <Icon name="office-building" size={14} color={theme.colors.textSecondary} />
                  <Text style={styles.detailText}>{outpass.hostel}</Text>
                </View>
              )}
              {outpass.room && (
                <View style={styles.detailRow}>
                  <Icon name="door" size={14} color={theme.colors.textSecondary} />
                  <Text style={styles.detailText}>Room {outpass.room}</Text>
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
              <Text style={styles.detailValue}>{outpass.reason}</Text>
            </View>

            <View style={styles.divider} />

            <View style={styles.detailItem}>
              <Text style={styles.detailLabel}>Departure</Text>
              <Text style={styles.detailValue}>{formatDateTime(outpass.requested_at)}</Text>
            </View>

            <View style={styles.divider} />

            <View style={styles.detailItem}>
              <Text style={styles.detailLabel}>Expected Return</Text>
              <Text style={styles.detailValue}>{formatDateTime(outpass.valid_until)}</Text>
            </View>

            {outpass.overnight && (
              <>
                <View style={styles.divider} />
                <View style={styles.detailItem}>
                  <Text style={styles.detailLabel}>Type</Text>
                  <View style={styles.overnightBadge}>
                    <Icon name="weather-night" size={14} color={theme.colors.primaryLight} />
                    <Text style={styles.overnightText}>Overnight Stay</Text>
                  </View>
                </View>
              </>
            )}
          </View>
        </View>

        {/* Time Verification (if approved) */}
        {outpass.status === 'approved' && (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>Time Verification</Text>
            <View style={styles.timeCard}>
              <View style={styles.timeRow}>
                <View style={styles.timeItem}>
                  <Icon name="exit-run" size={20} color={theme.colors.error} />
                  <Text style={styles.timeLabel}>Check-out</Text>
                  <Text style={styles.timeValue}>
                    {outpass.actual_out_time
                      ? formatDateTime(outpass.actual_out_time)
                      : 'Not recorded'}
                  </Text>
                </View>
                <View style={styles.timeDivider} />
                <View style={styles.timeItem}>
                  <Icon name="home-import-outline" size={20} color={theme.colors.success} />
                  <Text style={styles.timeLabel}>Check-in</Text>
                  <Text style={styles.timeValue}>
                    {outpass.actual_in_time
                      ? formatDateTime(outpass.actual_in_time)
                      : 'Not recorded'}
                  </Text>
                </View>
              </View>
            </View>
          </View>
        )}

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
  avatarText: {
    color: theme.colors.white,
    fontSize: 20,
    fontWeight: '700',
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
    backgroundColor: theme.colors.white,
    borderRadius: 16,
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
  overnightBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    alignSelf: 'flex-start',
    backgroundColor: theme.colors.background,
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 8,
    marginTop: 4,
  },
  overnightText: {
    fontSize: 13,
    fontWeight: '500',
    color: theme.colors.primaryLight,
    marginLeft: 6,
  },
  timeCard: {
    backgroundColor: theme.colors.white,
    borderRadius: 16,
    padding: 16,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  timeRow: {
    flexDirection: 'row',
  },
  timeItem: {
    flex: 1,
    alignItems: 'center',
  },
  timeDivider: {
    width: 1,
    backgroundColor: theme.colors.border,
    marginHorizontal: 16,
  },
  timeLabel: {
    fontSize: 12,
    color: theme.colors.textSecondary,
    marginTop: 8,
  },
  timeValue: {
    fontSize: 14,
    fontWeight: '500',
    color: theme.colors.text,
    marginTop: 4,
    textAlign: 'center',
  },
  rejectionInput: {
    backgroundColor: theme.colors.white,
    borderRadius: 12,
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
    backgroundColor: theme.colors.white,
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
    borderRadius: 12,
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
    borderRadius: 12,
  },
  rejectButton: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: theme.colors.error,
    paddingVertical: 14,
    borderRadius: 12,
  },
  cancelButton: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 14,
    borderRadius: 12,
    backgroundColor: theme.colors.divider,
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

export default OutpassDetailScreen;
