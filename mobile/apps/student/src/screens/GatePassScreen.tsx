import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  Alert,
  RefreshControl,
  Modal,
  Switch,
} from 'react-native';
import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useAuthStore } from '../../store/auth.store';
import { apiService } from '../../services/api.service';
import { GatePass } from '../../types';
import { APP_CONFIG } from '../../config/app.config';
import { format, formatDistanceToNow } from 'date-fns';
import { StudentQRCode, StatusBadge, FormInput, CustomDatePicker, Card, CardContent, GatePassSkeleton, EmptyState } from '../../components';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { theme } from '../../theme/theme';
import { hapticService } from '../../services/haptic.service';
import { errorHandler } from '../../utils/errorHandler';
import { z } from 'zod';
import { v4 as uuidv4 } from 'uuid';

// Local schema matching the new API contract
const gatePassSchema = z.object({
  reason: z.enum(['normal', 'leave', 'sick'], {
    required_error: 'Please select a reason',
  }),
  overnight: z.boolean().default(false),
  valid_until: z.date().optional(),
  note: z.string().max(500, 'Note must not exceed 500 characters').optional(),
});

type GatePassFormData = z.infer<typeof gatePassSchema>;

const GATE_PASS_REASONS = [
  { value: 'normal', label: 'Normal Outing', icon: 'walk-outline' },
  { value: 'leave', label: 'Leave', icon: 'home-outline' },
  { value: 'sick', label: 'Medical', icon: 'medical-outline' },
] as const;

export const GatePassScreen = ({ navigation }: any) => {
  const { user } = useAuthStore();
  const [gatePasses, setGatePasses] = useState<GatePass[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [showQRModal, setShowQRModal] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  
  const {
    control,
    handleSubmit,
    formState: { errors },
    reset,
    watch,
  } = useForm<GatePassFormData>({
    resolver: zodResolver(gatePassSchema),
    defaultValues: {
      reason: 'normal',
      overnight: false,
      note: '',
    },
  });

  const selectedReason = watch('reason');

  const fetchGatePasses = async () => {
    try {
      const response = await apiService.get<{ data: GatePass[] }>(
        APP_CONFIG.ENDPOINTS.GATE_PASSES
      );
      setGatePasses(response.data || []);
    } catch (error) {
      const err: any = error;
      console.error('Error fetching gate passes:', {
        message: err?.message,
        status: err?.response?.status,
        url: err?.config?.baseURL ? `${err.config.baseURL}${err.config?.url || ''}` : err?.config?.url,
        response: err?.response?.data,
      });
      Alert.alert('Error', 'Failed to fetch gate passes');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchGatePasses();
  }, []);

  const onRefresh = () => {
    setRefreshing(true);
    fetchGatePasses();
  };

  const onSubmit = async (data: GatePassFormData) => {
    setSubmitting(true);
    try {
      const idempotencyKey = uuidv4();
      
      await apiService.post(APP_CONFIG.ENDPOINTS.GATE_PASSES, {
        reason: data.reason,
        overnight: data.overnight,
        valid_until: data.valid_until?.toISOString(),
        note: data.note?.trim() || null,
      }, {
        headers: {
          'Idempotency-Key': idempotencyKey,
        },
      });

      hapticService.onSuccess();
      Alert.alert('Success', 'Gate pass request submitted successfully');
      setShowCreateModal(false);
      reset({
        reason: 'normal',
        overnight: false,
        note: '',
      });
      fetchGatePasses();
    } catch (err) {
      hapticService.onError();
      const errorDetails = errorHandler.handleError(err);
      Alert.alert('Error', errorDetails.message);
    } finally {
      setSubmitting(false);
    }
  };

  const handleCancel = async (passId: string) => {
    Alert.alert(
      'Cancel Request',
      'Are you sure you want to cancel this gate pass request?',
      [
        { text: 'No', style: 'cancel' },
        {
          text: 'Yes, Cancel',
          style: 'destructive',
          onPress: async () => {
            try {
              await apiService.post(`${APP_CONFIG.ENDPOINTS.GATE_PASSES}/${passId}/cancel`, {});
              hapticService.onSuccess();
              Alert.alert('Cancelled', 'Gate pass request has been cancelled');
              fetchGatePasses();
            } catch (err) {
              hapticService.onError();
              const errorDetails = errorHandler.handleError(err);
              Alert.alert('Error', errorDetails.message);
            }
          },
        },
      ]
    );
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'pending': return theme.colors.warning;
      case 'approved': return theme.colors.success;
      case 'declined': return theme.colors.error;
      case 'cancelled': return theme.colors.textMuted;
      case 'expired': return theme.colors.textMuted;
      default: return theme.colors.textSecondary;
    }
  };

  const getReasonIcon = (reason: string) => {
    switch (reason) {
      case 'normal': return 'walk-outline';
      case 'leave': return 'home-outline';
      case 'sick': return 'medical-outline';
      default: return 'ticket-outline';
    }
  };

  return (
    <View style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity
          style={styles.backButton}
          onPress={() => {
            hapticService.onButtonPress();
            navigation.goBack();
          }}
          accessibilityRole="button"
          accessibilityLabel="Back"
          accessibilityHint="Double tap to go back">
          <Ionicons name="arrow-back" size={20} color={theme.colors.white} />
          <Text style={styles.backButtonText}>Back</Text>
        </TouchableOpacity>
        <Text style={styles.headerTitle} accessibilityRole="header">Gate Pass</Text>
        <View style={styles.headerActions}>
          <TouchableOpacity
            style={styles.iconButton}
            onPress={() => {
              hapticService.onButtonPress();
              setShowQRModal(true);
            }}
            accessibilityRole="button"
            accessibilityLabel="Show QR Code">
            <Ionicons name="qr-code-outline" size={18} color={theme.colors.white} />
            <Text style={styles.iconButtonText}>QR</Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={styles.createButton}
            onPress={() => {
              hapticService.onButtonPress();
              setShowCreateModal(true);
            }}
            accessibilityRole="button"
            accessibilityLabel="Create new gate pass">
            <Ionicons name="add" size={18} color={theme.colors.primary} />
            <Text style={styles.createButtonText}>New</Text>
          </TouchableOpacity>
        </View>
      </View>

      {/* Gate Passes List */}
      <ScrollView
        style={styles.content}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>
        {loading ? (
          <GatePassSkeleton count={3} />
        ) : gatePasses.length === 0 ? (
          <EmptyState
            variant="no-data"
            title="No Gate Passes"
            subtitle="You haven't requested any gate passes yet"
            actionLabel="Create First Gate Pass"
            actionIcon="add"
            onActionPress={() => setShowCreateModal(true)}
          />
        ) : (
          gatePasses.map((pass) => (
            <Card key={pass.id} style={styles.passCard} variant="default">
              <CardContent>
                {/* Header Row */}
                <View style={styles.passHeader}>
                  <View style={styles.reasonBadge}>
                    <Ionicons 
                      name={getReasonIcon(pass.reason)} 
                      size={16} 
                      color={theme.colors.primary} 
                    />
                    <Text style={styles.reasonText}>
                      {pass.reason_label || pass.reason}
                    </Text>
                  </View>
                  <StatusBadge
                    status={pass.status}
                    size="small"
                    variant="filled"
                  />
                </View>

                {/* Details */}
                <View style={styles.passDetails}>
                  {pass.overnight && (
                    <View style={styles.detailRow}>
                      <Ionicons name="moon-outline" size={16} color={theme.colors.warning} />
                      <Text style={styles.overnightLabel}>Overnight Stay</Text>
                    </View>
                  )}

                  <View style={styles.detailRow}>
                    <Ionicons name="time-outline" size={16} color={theme.colors.textSecondary} />
                    <Text style={styles.detailLabel}>Requested:</Text>
                    <Text style={styles.detailValue}>
                      {pass.requested_at 
                        ? format(new Date(pass.requested_at), 'MMM dd, yyyy HH:mm')
                        : 'N/A'}
                    </Text>
                  </View>

                  <View style={styles.detailRow}>
                    <Ionicons name="hourglass-outline" size={16} color={theme.colors.textSecondary} />
                    <Text style={styles.detailLabel}>Valid Until:</Text>
                    <Text style={styles.detailValue}>
                      {pass.valid_until 
                        ? format(new Date(pass.valid_until), 'MMM dd, yyyy HH:mm')
                        : 'N/A'}
                    </Text>
                  </View>

                  {pass.decided_at && (
                    <View style={styles.detailRow}>
                      <Ionicons 
                        name={pass.status === 'approved' ? 'checkmark-circle-outline' : 'close-circle-outline'} 
                        size={16} 
                        color={pass.status === 'approved' ? theme.colors.success : theme.colors.error} 
                      />
                      <Text style={styles.detailLabel}>Decided:</Text>
                      <Text style={styles.detailValue}>
                        {format(new Date(pass.decided_at), 'MMM dd, yyyy HH:mm')}
                      </Text>
                    </View>
                  )}

                  {pass.note && (
                    <View style={styles.noteContainer}>
                      <Ionicons name="document-text-outline" size={16} color={theme.colors.textMuted} />
                      <Text style={styles.noteText}>{pass.note}</Text>
                    </View>
                  )}

                  <View style={styles.detailRow}>
                    <Ionicons name="calendar-outline" size={16} color={theme.colors.textMuted} />
                    <Text style={styles.createdAt}>
                      Created {formatDistanceToNow(new Date(pass.created_at), { addSuffix: true })}
                    </Text>
                  </View>
                </View>

                {/* Cancel Button for pending requests */}
                {pass.status === 'pending' && (
                  <TouchableOpacity
                    style={styles.cancelButton}
                    onPress={() => handleCancel(pass.id)}
                    accessibilityRole="button"
                    accessibilityLabel="Cancel request">
                    <Ionicons name="close-circle-outline" size={18} color={theme.colors.error} />
                    <Text style={styles.cancelButtonText}>Cancel Request</Text>
                  </TouchableOpacity>
                )}
              </CardContent>
            </Card>
          ))
        )}
      </ScrollView>

      {/* QR Code Modal */}
      <Modal
        visible={showQRModal}
        animationType="slide"
        presentationStyle="pageSheet">
        <ScrollView style={styles.modalContainer}>
          <View style={styles.modalHeader}>
            <Text style={styles.modalTitle}>Your Gate Pass QR</Text>
            <TouchableOpacity onPress={() => {
              hapticService.onButtonPress();
              setShowQRModal(false);
            }}>
              <Ionicons name="close" size={24} color={theme.colors.textSecondary} />
            </TouchableOpacity>
          </View>
          <StudentQRCode onClose={() => setShowQRModal(false)} />
        </ScrollView>
      </Modal>

      {/* Create Gate Pass Modal */}
      <Modal
        visible={showCreateModal}
        animationType="slide"
        presentationStyle="pageSheet">
        <View style={styles.modalContainer}>
          <View style={styles.modalHeader}>
            <Text style={styles.modalTitle}>Request Gate Pass</Text>
            <TouchableOpacity onPress={() => {
              hapticService.onButtonPress();
              setShowCreateModal(false);
            }}>
              <Ionicons name="close" size={24} color={theme.colors.textSecondary} />
            </TouchableOpacity>
          </View>

          <ScrollView style={styles.modalContent}>
            {/* Reason Selection */}
            <View style={styles.inputGroup}>
              <Text style={styles.inputLabel}>Reason *</Text>
              <Controller
                control={control}
                name="reason"
                render={({ field: { onChange, value } }) => (
                  <View style={styles.reasonOptions}>
                    {GATE_PASS_REASONS.map((option) => (
                      <TouchableOpacity
                        key={option.value}
                        style={[
                          styles.reasonOption,
                          value === option.value && styles.reasonOptionSelected,
                        ]}
                        onPress={() => {
                          hapticService.onButtonPress();
                          onChange(option.value);
                        }}>
                        <Ionicons 
                          name={option.icon} 
                          size={24} 
                          color={value === option.value ? theme.colors.white : theme.colors.primary} 
                        />
                        <Text style={[
                          styles.reasonOptionText,
                          value === option.value && styles.reasonOptionTextSelected,
                        ]}>
                          {option.label}
                        </Text>
                      </TouchableOpacity>
                    ))}
                  </View>
                )}
              />
              {errors.reason && (
                <Text style={styles.errorText}>{errors.reason.message}</Text>
              )}
            </View>

            {/* Overnight Toggle */}
            <View style={styles.inputGroup}>
              <View style={styles.switchRow}>
                <View style={styles.switchLabel}>
                  <Ionicons name="moon-outline" size={20} color={theme.colors.textSecondary} />
                  <Text style={styles.inputLabel}>Overnight Stay</Text>
                </View>
                <Controller
                  control={control}
                  name="overnight"
                  render={({ field: { onChange, value } }) => (
                    <Switch
                      value={value}
                      onValueChange={onChange}
                      trackColor={{ false: theme.colors.border, true: theme.colors.primaryLight }}
                      thumbColor={value ? theme.colors.primary : theme.colors.textMuted}
                    />
                  )}
                />
              </View>
              <Text style={styles.helperText}>
                Enable if you need to stay outside overnight
              </Text>
            </View>

            {/* Valid Until (optional) */}
            <Controller
              control={control}
              name="valid_until"
              render={({ field: { onChange, value } }) => (
                <CustomDatePicker
                  label="Valid Until (optional)"
                  value={value}
                  onChange={onChange}
                  placeholder="Auto: 8 hours from now"
                  mode="datetime"
                  error={errors.valid_until?.message}
                  minimumDate={new Date()}
                />
              )}
            />

            {/* Note */}
            <Controller
              control={control}
              name="note"
              render={({ field: { onChange, onBlur, value } }) => (
                <FormInput
                  label="Note (optional)"
                  value={value}
                  onChangeText={onChange}
                  onBlur={onBlur}
                  placeholder="Any additional details..."
                  multiline
                  numberOfLines={3}
                  variant="outlined"
                  error={errors.note?.message}
                />
              )}
            />

            <TouchableOpacity
              style={[styles.submitButton, submitting && styles.submitButtonDisabled]}
              onPress={handleSubmit(onSubmit)}
              disabled={submitting}>
              {submitting ? (
                <Text style={styles.submitButtonText}>Submitting...</Text>
              ) : (
                <Text style={styles.submitButtonText}>Submit Request</Text>
              )}
            </TouchableOpacity>
          </ScrollView>
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
    padding: theme.spacing.lg,
    paddingTop: theme.spacing.xl * 2,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  backButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.xs,
    padding: theme.spacing.sm,
  },
  backButtonText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
  },
  headerTitle: {
    color: theme.colors.white,
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
  },
  headerActions: {
    flexDirection: 'row',
    gap: theme.spacing.sm,
  },
  iconButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.xs,
    backgroundColor: 'rgba(255,255,255,0.2)',
    paddingHorizontal: theme.spacing.md,
    paddingVertical: theme.spacing.sm,
    borderRadius: theme.borderRadius.md,
  },
  iconButtonText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.sm,
    fontWeight: theme.fontWeight.semibold,
  },
  createButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.xs,
    backgroundColor: theme.colors.white,
    paddingHorizontal: theme.spacing.lg,
    paddingVertical: theme.spacing.sm,
    borderRadius: theme.borderRadius.md,
  },
  createButtonText: {
    color: theme.colors.primary,
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
  },
  content: {
    flex: 1,
    padding: theme.spacing.md,
  },
  passCard: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.borderRadius.lg,
    padding: theme.spacing.md,
    marginBottom: theme.spacing.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    ...theme.shadows.small,
  },
  passHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: theme.spacing.sm,
  },
  reasonBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.xs,
    backgroundColor: theme.colors.primaryLight,
    paddingHorizontal: theme.spacing.sm,
    paddingVertical: theme.spacing.xs,
    borderRadius: theme.borderRadius.sm,
  },
  reasonText: {
    fontSize: theme.fontSize.sm,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.primary,
  },
  passDetails: {
    gap: theme.spacing.xs,
  },
  detailRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: theme.spacing.xs,
  },
  detailLabel: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
    fontWeight: theme.fontWeight.medium,
    marginLeft: theme.spacing.xs,
    minWidth: 80,
  },
  detailValue: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.text,
    flex: 1,
    marginLeft: theme.spacing.xs,
  },
  overnightLabel: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.warning,
    fontWeight: theme.fontWeight.medium,
    marginLeft: theme.spacing.xs,
  },
  noteContainer: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    marginTop: theme.spacing.xs,
    padding: theme.spacing.sm,
    backgroundColor: theme.colors.background,
    borderRadius: theme.borderRadius.sm,
  },
  noteText: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
    marginLeft: theme.spacing.xs,
    flex: 1,
  },
  createdAt: {
    fontSize: theme.fontSize.xs,
    color: theme.colors.textMuted,
    marginLeft: theme.spacing.xs,
    fontStyle: 'italic',
  },
  cancelButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: theme.spacing.xs,
    marginTop: theme.spacing.md,
    paddingVertical: theme.spacing.sm,
    borderTopWidth: 1,
    borderTopColor: theme.colors.border,
  },
  cancelButtonText: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.error,
    fontWeight: theme.fontWeight.medium,
  },
  modalContainer: {
    flex: 1,
    backgroundColor: theme.colors.card,
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: theme.spacing.lg,
    paddingTop: theme.spacing.xl * 2,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.divider,
  },
  modalTitle: {
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
  },
  modalContent: {
    flex: 1,
    padding: theme.spacing.lg,
  },
  inputGroup: {
    marginBottom: theme.spacing.lg,
  },
  inputLabel: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
    marginBottom: theme.spacing.sm,
  },
  reasonOptions: {
    flexDirection: 'row',
    gap: theme.spacing.sm,
  },
  reasonOption: {
    flex: 1,
    alignItems: 'center',
    padding: theme.spacing.md,
    borderWidth: 2,
    borderColor: theme.colors.border,
    borderRadius: theme.borderRadius.md,
    backgroundColor: theme.colors.white,
  },
  reasonOptionSelected: {
    borderColor: theme.colors.primary,
    backgroundColor: theme.colors.primary,
  },
  reasonOptionText: {
    fontSize: theme.fontSize.sm,
    fontWeight: theme.fontWeight.medium,
    color: theme.colors.text,
    marginTop: theme.spacing.xs,
  },
  reasonOptionTextSelected: {
    color: theme.colors.white,
  },
  switchRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  switchLabel: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.sm,
  },
  helperText: {
    fontSize: theme.fontSize.xs,
    color: theme.colors.textMuted,
    marginTop: theme.spacing.xs,
  },
  errorText: {
    fontSize: theme.fontSize.xs,
    color: theme.colors.error,
    marginTop: theme.spacing.xs,
  },
  submitButton: {
    backgroundColor: theme.colors.primary,
    padding: theme.spacing.md,
    borderRadius: theme.borderRadius.md,
    alignItems: 'center',
    marginTop: theme.spacing.lg,
    marginBottom: theme.spacing.xl,
  },
  submitButtonDisabled: {
    backgroundColor: theme.colors.textMuted,
  },
  submitButtonText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
  },
});
