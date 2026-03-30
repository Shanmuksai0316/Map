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
import type { GuestEntryItem } from './GuestEntryListScreen';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface Props {
  navigation: any;
  route: {
    params: {
      entry: GuestEntryItem;
    };
  };
}

const getStatusColor = (status: string): string => {
  const colors: Record<string, string> = {
    pending: theme.colors.warning,
    approved: theme.colors.success,
    rejected: theme.colors.error,
    completed: theme.colors.textSecondary,
  };
  return colors[status.toLowerCase()] || theme.colors.textSecondary;
};

export const RectorGuestEntryDetailScreen: React.FC<Props> = ({ navigation, route }) => {
  const { entry } = route.params;
  const [rejectionReason, setRejectionReason] = useState('');
  const [showRejectForm, setShowRejectForm] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleApprove = async () => {
    Alert.alert(
      'Approve Guest Entry',
      `Are you sure you want to approve this guest entry for ${entry.student_name}?`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Approve',
          style: 'default',
          onPress: async () => {
            setIsSubmitting(true);
            try {
              await apiService.put(`/mobile/rector/guest-entries/${entry.id}/approve`);
              Alert.alert('Success', 'Guest entry approved successfully', [
                { text: 'OK', onPress: () => navigation.goBack() },
              ]);
            } catch {
              Alert.alert('Error', 'Failed to approve guest entry');
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
      await apiService.put(`/mobile/rector/guest-entries/${entry.id}/reject`, {
        rejection_reason: rejectionReason.trim(),
      });
      Alert.alert('Success', 'Guest entry rejected', [
        { text: 'OK', onPress: () => navigation.goBack() },
      ]);
    } catch {
      Alert.alert('Error', 'Failed to reject guest entry');
    } finally {
      setIsSubmitting(false);
    }
  };

  const formatDate = (dateString?: string) => {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleDateString('en-US', {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    });
  };

  const statusColor = getStatusColor(entry.status);
  const isPending = entry.status === 'pending';

  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Guest Entry Details" />
      <View style={styles.subHeader}>
        <View style={styles.subHeaderRow}>
          <View style={[styles.headerStatusBadge, { backgroundColor: statusColor }]}>
            <Text style={styles.headerStatusText}>{entry.status.toUpperCase()}</Text>
          </View>
        </View>
      </View>

      <ScrollView style={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.studentCard}>
          <View style={styles.avatarContainer}>
            <Text style={styles.avatarText}>
              {entry.student_name
                ?.split(' ')
                .map((n) => n[0])
                .join('')
                .substring(0, 2)}
            </Text>
          </View>
          <View style={styles.studentInfo}>
            <Text style={styles.studentName}>{entry.student_name}</Text>
            <View style={styles.studentDetails}>
              {entry.room_number && (
                <View style={styles.detailRow}>
                  <Icon name="door" size={14} color={theme.colors.textSecondary} />
                  <Text style={styles.detailText}>Room {entry.room_number}</Text>
                </View>
              )}
              {entry.student_id && (
                <View style={styles.detailRow}>
                  <Icon name="identifier" size={14} color={theme.colors.textSecondary} />
                  <Text style={styles.detailText}>{entry.student_id}</Text>
                </View>
              )}
            </View>
          </View>
        </View>

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Request Details</Text>
          <View style={styles.detailCard}>
            <View style={styles.detailItem}>
              <Text style={styles.detailLabel}>Request ID</Text>
              <Text style={styles.detailValue}>{entry.unique_id ?? `#${entry.id}`}</Text>
            </View>
            <View style={styles.divider} />
            <View style={styles.detailItem}>
              <Text style={styles.detailLabel}>Guest</Text>
              <Text style={styles.detailValue}>
                {entry.guest_name || '—'}
                {entry.guest_relation ? ` (${entry.guest_relation})` : ''}
              </Text>
            </View>
            <View style={styles.divider} />
            <View style={styles.detailItem}>
              <Text style={styles.detailLabel}>Visit Date</Text>
              <Text style={styles.detailValue}>{formatDate(entry.visit_date)}</Text>
            </View>
            {entry.purpose_to_visit ? (
              <>
                <View style={styles.divider} />
                <View style={styles.detailItem}>
                  <Text style={styles.detailLabel}>Purpose</Text>
                  <Text style={styles.detailValue}>{entry.purpose_to_visit}</Text>
                </View>
              </>
            ) : null}
          </View>
        </View>

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
  container: { flex: 1, backgroundColor: theme.colors.background },
  subHeader: { paddingHorizontal: 16, paddingTop: 12, paddingBottom: 8 },
  subHeaderRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  subHeaderTitle: { fontSize: 20, fontWeight: '700', color: theme.colors.textHeading, flex: 1 },
  headerStatusBadge: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: theme.borderRadius.sm,
  },
  headerStatusText: { color: theme.colors.white, fontSize: 11, fontWeight: '700' },
  content: { flex: 1, padding: 16 },
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
  avatarText: { color: theme.colors.white, fontSize: 20, fontWeight: '700' },
  studentInfo: { flex: 1, marginLeft: 14 },
  studentName: { fontSize: 18, fontWeight: '700', color: theme.colors.text, marginBottom: 4 },
  studentDetails: { flexDirection: 'row', gap: 16 },
  detailRow: { flexDirection: 'row', alignItems: 'center' },
  detailText: { fontSize: 13, color: theme.colors.textSecondary, marginLeft: 4 },
  section: { marginBottom: 16 },
  sectionTitle: { fontSize: 16, fontWeight: '600', color: theme.colors.text, marginBottom: 12 },
  detailCard: {
    backgroundColor: theme.colors.white,
    borderRadius: 16,
    padding: 16,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  detailItem: { paddingVertical: 12 },
  detailLabel: {
    fontSize: 12,
    fontWeight: '600',
    color: theme.colors.textSecondary,
    textTransform: 'uppercase',
    marginBottom: 4,
  },
  detailValue: { fontSize: 15, color: theme.colors.text, lineHeight: 22 },
  divider: { height: 1, backgroundColor: theme.colors.border },
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
  bottomPadding: { height: 100 },
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
  rejectOutlineText: { fontSize: 16, fontWeight: '600', color: theme.colors.error, marginLeft: 8 },
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
  cancelButtonText: { fontSize: 16, fontWeight: '600', color: theme.colors.textSecondary },
  buttonText: { fontSize: 16, fontWeight: '600', color: theme.colors.white, marginLeft: 8 },
  buttonDisabled: { opacity: 0.6 },
});

export default RectorGuestEntryDetailScreen;
