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
import { GradientButton } from '../../shared/components/GradientButton';
import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useAuthStore } from '../../shared/store/auth.store';
import { apiService } from '../../shared/services/api.service';
import { APP_CONFIG } from '../../shared/config/app.config';
import { format, formatDistanceToNow } from 'date-fns';
import { StudentQRCode, StatusBadge, FormInput, CustomDatePicker, Card, CardContent, GatePassSkeleton, EmptyState, OverviewCard } from '../../shared/components';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { theme } from '../../shared/theme/theme';
import { hapticService } from '../../shared/services/haptic.service';
import { sanitizeText } from '../../shared/utils/validation';
import { errorHandler } from '../../shared/utils/errorHandler';
import { gatePassSchema, GATE_PASS_REASONS, type GatePassFormData } from '../../shared/validation/schemas/gate-pass.schema';

interface GatePassItem {
  id: string;
  reason: string;
  reason_label: string;
  overnight: boolean;
  status: string;
  status_label: string;
  status_color?: string;
  hostel?: string;
  requested_at: string;
  valid_until?: string;
  decided_at?: string;
  note?: string;
  created_at: string;
  updated_at: string;
}

export const GatePassScreen = ({ navigation }: any) => {
  const insets = useSafeAreaInsets();
  const { user } = useAuthStore();
  const [gatePasses, setGatePasses] = useState<GatePassItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [showQRModal, setShowQRModal] = useState(false);
  const [qrLoading, setQrLoading] = useState(false);
  const [qrError, setQrError] = useState<string | null>(null);
  const [qrPass, setQrPass] = useState<{
    outPassId: string;
    uniqueId: string;
    validUntil: string;
    backupCode: string | null;
  } | null>(null);
  
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
  const isOvernight = watch('overnight');

  const fetchGatePasses = async () => {
    try {
      const response = await apiService.get<{ data: GatePassItem[] }>(
        APP_CONFIG.ENDPOINTS.GATE_PASSES
      );
      setGatePasses(response.data || []);
    } catch (error) {
      // Log server payload to diagnose 500s
      const err: any = error;
      console.error('Error fetching gate passes:', {
        message: err?.message,
        status: err?.response?.status,
        url: err?.config?.baseURL ? `${err.config.baseURL}${err.config?.url || ''}` : err?.config?.url,
        response: err?.response?.data,
      });
      // Don't show alert on fetch errors, just set empty
      setGatePasses([]);
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
    try {
      await apiService.post(APP_CONFIG.ENDPOINTS.GATE_PASSES, {
        reason: data.reason,
        overnight: data.overnight || false,
        valid_until: data.valid_until?.toISOString(),
        note: data.note ? sanitizeText(data.note) : null,
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
    }
  };

  const openQrModal = async () => {
    setShowQRModal(true);
    setQrError(null);
    setQrPass(null);
    setQrLoading(true);

    try {
      const approved = gatePasses
        .filter((p) => p.status === 'approved')
        .sort((a, b) => new Date(b.requested_at).getTime() - new Date(a.requested_at).getTime())[0];

      if (!approved) {
        setQrError('No approved gate pass found.');
        return;
      }

      const response = await apiService.get<{
        data: {
          id: string;
          unique_id: string;
          valid_until?: string;
          backup_code?: string | null;
          is_expired?: boolean;
        };
      }>(`${APP_CONFIG.ENDPOINTS.GATE_PASSES}/${approved.id}`);

      const details = response.data;

      if (!details?.valid_until) {
        setQrError('Gate pass has no expiry time set yet. Please try again in a moment.');
        return;
      }

      setQrPass({
        outPassId: details.id,
        uniqueId: details.unique_id || `OP-${details.id}`,
        validUntil: details.valid_until,
        backupCode: details.backup_code ?? null,
      });
    } catch (err: any) {
      const errorDetails = errorHandler.handleError(err);
      setQrError(errorDetails.message || 'Failed to load gate pass details.');
    } finally {
      setQrLoading(false);
    }
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'approved':
        return theme.colors.success;
      case 'pending':
        return theme.colors.warning;
      case 'rejected':
      case 'cancelled':
        return theme.colors.error;
      case 'used':
        return theme.colors.info;
      default:
        return theme.colors.textMuted;
    }
  };

  const HEADER_ROW_HEIGHT = 52;
  const HEADER_PADDING_TOP = Math.max(insets.top, 10);
  const HEADER_PADDING_BOTTOM = 6;

  return (
    <View style={styles.container}>
      {/* Header */}
      <View
        style={[
          styles.header,
          {
            paddingTop: HEADER_PADDING_TOP,
            paddingBottom: HEADER_PADDING_BOTTOM,
            minHeight: HEADER_PADDING_TOP + HEADER_ROW_HEIGHT + HEADER_PADDING_BOTTOM,
          },
        ]}>
        <View style={[styles.headerRow, { height: HEADER_ROW_HEIGHT }]}>
          <TouchableOpacity
            style={styles.backButton}
            onPress={() => {
              hapticService.onButtonPress();
              if (navigation?.canGoBack?.()) {
                navigation.goBack();
              } else {
                navigation.navigate('Home');
              }
            }}
            accessibilityRole="button"
            accessibilityLabel="Back"
            accessibilityHint="Double tap to go back">
            <Ionicons name="arrow-back" size={24} color={theme.colors.primary} accessibilityRole="image" />
          </TouchableOpacity>
          <Text style={styles.headerTitle} accessibilityRole="header">Gate Pass</Text>
          <View style={styles.headerActions}>
            <TouchableOpacity
              style={styles.iconButton}
              onPress={() => {
                hapticService.onButtonPress();
                openQrModal();
              }}
              accessibilityRole="button"
              accessibilityLabel="Show QR Code"
              accessibilityHint="Double tap to view your QR code">
              <Ionicons name="qr-code-outline" size={18} color={theme.colors.white} accessibilityRole="image" />
              <Text style={styles.iconButtonText}>QR</Text>
            </TouchableOpacity>
            <GradientButton
              style={styles.createButton}
              onPress={() => {
                hapticService.onButtonPress();
                setShowCreateModal(true);
              }}
              accessibilityRole="button"
              accessibilityLabel="Create new gate pass"
              accessibilityHint="Double tap to create a new gate pass request">
                <Ionicons name="add" size={18} color={theme.colors.primary} accessibilityRole="image" />
                <Text style={styles.createButtonText}>New</Text>
              </GradientButton>
          </View>
        </View>
      </View>

      {/* Gate Passes List */}
      <ScrollView
        style={styles.content}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>
        {/* Overview Card */}
        {!loading && gatePasses.length > 0 && (
          <OverviewCard
            title="Gate Pass Overview"
            icon="exit-outline"
            stats={[
              { 
                label: 'Total', 
                value: gatePasses.length, 
                icon: 'document-text-outline',
                color: theme.colors.primary 
              },
              { 
                label: 'Pending', 
                value: gatePasses.filter(p => p.status === 'pending').length, 
                icon: 'time-outline',
                color: theme.colors.warning 
              },
              { 
                label: 'Approved', 
                value: gatePasses.filter(p => p.status === 'approved').length, 
                icon: 'checkmark-circle-outline',
                color: theme.colors.success 
              },
            ]}
          />
        )}
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
                {/* Title/Reason */}
                <View style={styles.passHeader}>
                  <View style={styles.reasonContainer}>
                    <Text style={styles.passTitle}>{pass.reason_label || pass.reason}</Text>
                    {pass.overnight && (
                      <View style={styles.overnightBadge}>
                        <Ionicons name="moon-outline" size={12} color={theme.colors.white} />
                        <Text style={styles.overnightText}>Overnight</Text>
                      </View>
                    )}
                  </View>
                  <StatusBadge
                    status={pass.status}
                    size="small"
                    variant="filled"
                  />
                </View>

                <View style={styles.passDetails}>
                  {pass.hostel && (
                    <View style={styles.detailRow}>
                      <Ionicons name="home-outline" size={16} color={theme.colors.textSecondary} />
                      <Text style={styles.detailLabel}>Hostel:</Text>
                      <Text style={styles.detailValue}>{pass.hostel}</Text>
                    </View>
                  )}

                  <View style={styles.detailRow}>
                    <Ionicons name="calendar-outline" size={16} color={theme.colors.textSecondary} />
                    <Text style={styles.detailLabel}>Requested:</Text>
                    <Text style={styles.detailValue}>
                      {pass.requested_at ? format(new Date(pass.requested_at), 'MMM dd, yyyy HH:mm') : 'N/A'}
                    </Text>
                  </View>

                  {pass.valid_until && (
                    <View style={styles.detailRow}>
                      <Ionicons name="time-outline" size={16} color={theme.colors.textSecondary} />
                      <Text style={styles.detailLabel}>Valid Until:</Text>
                      <Text style={styles.detailValue}>
                        {format(new Date(pass.valid_until), 'MMM dd, yyyy HH:mm')}
                      </Text>
                    </View>
                  )}

                  {pass.decided_at && (
                    <View style={styles.detailRow}>
                      <Ionicons name="checkmark-done-outline" size={16} color={getStatusColor(pass.status)} />
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
                    <Text style={styles.createdAt}>
                      {formatDistanceToNow(new Date(pass.created_at), { addSuffix: true })}
                    </Text>
                  </View>
                </View>
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
              setQrPass(null);
              setQrError(null);
            }}>
              <Ionicons name="arrow-back" size={24} color={theme.colors.textSecondary} />
            </TouchableOpacity>
          </View>
          {qrLoading ? (
            <View style={{ padding: 20 }}>
              <Text style={{ textAlign: 'center', color: theme.colors.textSecondary }}>
                Loading gate pass...
              </Text>
            </View>
          ) : qrError ? (
            <View style={{ padding: 20 }}>
              <Text style={{ textAlign: 'center', color: theme.colors.error }}>
                {qrError}
              </Text>
            </View>
          ) : qrPass ? (
            <StudentQRCode
              onClose={() => setShowQRModal(false)}
              outPassId={qrPass.outPassId}
              uniqueId={qrPass.uniqueId}
              validUntil={qrPass.validUntil}
              backupCode={qrPass.backupCode}
            />
          ) : null}
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
              <Ionicons name="arrow-back" size={24} color={theme.colors.textSecondary} />
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
                    {GATE_PASS_REASONS.map((reason) => (
                      <TouchableOpacity
                        key={reason.value}
                        style={[
                          styles.reasonOption,
                          value === reason.value && styles.reasonOptionSelected,
                        ]}
                        onPress={() => {
                          hapticService.onButtonPress();
                          onChange(reason.value);
                        }}>
                        <Ionicons
                          name={
                            reason.value === 'normal' ? 'walk-outline' :
                            reason.value === 'leave' ? 'airplane-outline' :
                            'medical-outline'
                          }
                          size={24}
                          color={value === reason.value ? theme.colors.white : theme.colors.primary}
                        />
                        <Text style={[
                          styles.reasonOptionText,
                          value === reason.value && styles.reasonOptionTextSelected,
                        ]}>
                          {reason.label}
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
                  <Ionicons name="moon-outline" size={20} color={theme.colors.primary} />
                  <Text style={styles.inputLabel}>Overnight Stay</Text>
                </View>
                <Controller
                  control={control}
                  name="overnight"
                  render={({ field: { onChange, value } }) => (
                    <Switch
                      value={value}
                      onValueChange={(val) => {
                        hapticService.onButtonPress();
                        onChange(val);
                      }}
                      trackColor={{ false: theme.colors.border, true: theme.colors.primary }}
                      thumbColor={value ? theme.colors.white : theme.colors.surface}
                    />
                  )}
                />
              </View>
              {isOvernight && (
                <Text style={styles.helperText}>
                  You're requesting permission to stay out overnight
                </Text>
              )}
            </View>

            {/* Valid Until (Optional) */}
            <Controller
              control={control}
              name="valid_until"
              render={({ field: { onChange, value } }) => (
                <CustomDatePicker
                  label="Valid Until (Optional)"
                  value={value}
                  onChange={onChange}
                  placeholder="Select when pass expires"
                  mode="datetime"
                  minimumDate={new Date()}
                />
              )}
            />

            {/* Note (Optional) */}
            <Controller
              control={control}
              name="note"
              render={({ field: { onChange, onBlur, value } }) => (
                <FormInput
                  label="Additional Notes (Optional)"
                  value={value || ''}
                  onChangeText={onChange}
                  onBlur={onBlur}
                  placeholder="Any additional information..."
                  multiline
                  numberOfLines={3}
                  variant="outlined"
                  error={errors.note?.message}
                />
              )}
            />

            <GradientButton
              style={styles.submitButton}
              onPress={handleSubmit(onSubmit)}>
              <Text style={styles.submitButtonText}>Submit Request</Text>
            </GradientButton>

            <View style={styles.infoBox}>
              <Ionicons name="information-circle-outline" size={20} color={theme.colors.info} />
              <Text style={styles.infoText}>
                Your request will be reviewed by the hostel authorities. You'll be notified once it's approved or rejected.
              </Text>
            </View>
          </ScrollView>
        </View>
      </Modal>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.white,
  },
  header: {
    backgroundColor: theme.colors.white,
    paddingHorizontal: theme.spacing.lg,
    ...theme.shadows.medium,
  },
  headerRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  backButton: {
    padding: theme.spacing.xs,
  },
  headerTitle: {
    color: theme.colors.primary,
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
    alignItems: 'flex-start',
    marginBottom: theme.spacing.sm,
  },
  reasonContainer: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    flexWrap: 'wrap',
    gap: theme.spacing.sm,
  },
  passTitle: {
    fontSize: theme.fontSize.lg,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
  },
  overnightBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: theme.colors.primary,
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: theme.borderRadius.sm,
  },
  overnightText: {
    fontSize: theme.fontSize.xs,
    color: theme.colors.white,
    fontWeight: theme.fontWeight.medium,
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
  noteContainer: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: theme.spacing.xs,
    marginTop: theme.spacing.xs,
    padding: theme.spacing.sm,
    backgroundColor: theme.colors.surface,
    borderRadius: theme.borderRadius.sm,
  },
  noteText: {
    flex: 1,
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
    fontStyle: 'italic',
  },
  createdAt: {
    fontSize: theme.fontSize.xs,
    color: theme.colors.textMuted,
    fontStyle: 'italic',
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
    justifyContent: 'center',
    padding: theme.spacing.md,
    borderWidth: 2,
    borderColor: theme.colors.primary,
    borderRadius: theme.borderRadius.md,
    backgroundColor: theme.colors.white,
    gap: theme.spacing.xs,
  },
  reasonOptionSelected: {
    backgroundColor: theme.colors.primary,
    borderColor: theme.colors.primary,
  },
  reasonOptionText: {
    fontSize: theme.fontSize.sm,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.primary,
    textAlign: 'center',
  },
  reasonOptionTextSelected: {
    color: theme.colors.white,
  },
  switchRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: theme.spacing.sm,
  },
  switchLabel: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.sm,
  },
  helperText: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.info,
    marginTop: theme.spacing.xs,
  },
  errorText: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.error,
    marginTop: theme.spacing.xs,
  },
  submitButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#D79F24',
    padding: 16,
    borderRadius: theme.borderRadius.lg,
    marginTop: theme.spacing.lg,
    ...theme.shadows.medium,
  },
  submitButtonText: {
    color: theme.colors.primary,
    fontSize: 16,
    fontWeight: '600',
  },
  infoBox: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: theme.spacing.sm,
    padding: theme.spacing.md,
    backgroundColor: `${theme.colors.info}15`,
    borderRadius: theme.borderRadius.md,
    marginTop: theme.spacing.lg,
    marginBottom: theme.spacing.xl,
  },
  infoText: {
    flex: 1,
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
    lineHeight: 20,
  },
});
