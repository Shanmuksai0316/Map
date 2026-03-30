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
  Platform,
} from 'react-native';
import { GradientButton } from '../../shared/components/GradientButton';
import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useAuthStore } from '../../shared/store/auth.store';
import { apiService } from '../../shared/services/api.service';
import { APP_CONFIG } from '../../shared/config/app.config';
import { format, formatDistanceToNow } from 'date-fns';
import DateTimePicker from '@react-native-community/datetimepicker';
import { StudentQRCode, StatusBadge, Card, CardContent, EmptyState } from '../../shared/components';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { theme } from '../../shared/theme/theme';
import { hapticService } from '../../shared/services/haptic.service';
import { sanitizeText } from '../../shared/utils/validation';
import { errorHandler } from '../../shared/utils/errorHandler';
import { z } from 'zod';

// Updated schema without overnight and notes
const outpassSchema = z.object({
  reason: z.enum(['normal', 'medical', 'urgent']),
  required_date: z.date(),
});

type OutpassFormData = z.infer<typeof outpassSchema>;

const OUTPASS_REASONS = [
  { value: 'normal', label: 'Normal' },
  { value: 'medical', label: 'Medical' },
  { value: 'urgent', label: 'Urgent' },
];

interface OutpassItem {
  id: string;
  reason: string;
  reason_label: string;
  status: string;
  status_label: string;
  status_color?: string;
  hostel?: string;
  requested_at: string;
  required_date?: string;
  decided_at?: string;
  created_at: string;
  updated_at: string;
  unique_id?: string;
  backup_code?: string;
}

type TabType = 'approved' | 'pending' | 'rejected' | 'completed';

export const OutpassScreen = ({ navigation }: any) => {
  const insets = useSafeAreaInsets();
  const { user } = useAuthStore();
  const [outpasses, setOutpasses] = useState<OutpassItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [showQRModal, setShowQRModal] = useState(false);
  const [selectedTab, setSelectedTab] = useState<TabType>('pending');
  const [selectedOutpass, setSelectedOutpass] = useState<OutpassItem | null>(null);
  const [qrLoading, setQrLoading] = useState(false);
  const [qrError, setQrError] = useState<string | null>(null);
  const [showDatePicker, setShowDatePicker] = useState(false);
  
  const {
    control,
    handleSubmit,
    formState: { errors },
    reset,
    watch,
    setValue,
  } = useForm<OutpassFormData>({
    resolver: zodResolver(outpassSchema),
    defaultValues: {
      reason: 'normal',
      required_date: new Date(),
    },
  });

  const requiredDate = watch('required_date');

  const fetchOutpasses = async () => {
    try {
      const response = await apiService.get<{ data: OutpassItem[] }>(
        APP_CONFIG.ENDPOINTS.GATE_PASSES
      );
      setOutpasses(response.data || []);
    } catch (error) {
      const err: any = error;
      console.error('Error fetching outpasses:', {
        message: err?.message,
        status: err?.response?.status,
        response: err?.response?.data,
      });
      setOutpasses([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchOutpasses();
  }, []);

  const onRefresh = () => {
    setRefreshing(true);
    fetchOutpasses();
  };

  const onSubmit = async (data: OutpassFormData) => {
    try {
      await apiService.post(APP_CONFIG.ENDPOINTS.GATE_PASSES, {
        reason: data.reason,
        required_date: data.required_date.toISOString().split('T')[0],
      });

      hapticService.onSuccess();
      Alert.alert('Success', 'Outpass request submitted successfully');
      setShowCreateModal(false);
      reset({
        reason: 'normal',
        required_date: new Date(),
      });
      fetchOutpasses();
    } catch (err) {
      hapticService.onError();
      const errorDetails = errorHandler.handleError(err);
      Alert.alert('Error', errorDetails.message);
    }
  };

  const openQrForOutpass = async (outpass: OutpassItem) => {
    setSelectedOutpass(outpass);
    setShowQRModal(true);
    setQrError(null);
    setQrLoading(true);

    try {
      const response = await apiService.get<{
        data: {
          id: string;
          unique_id: string;
          valid_until?: string;
          backup_code?: string | null;
        };
      }>(`${APP_CONFIG.ENDPOINTS.GATE_PASSES}/${outpass.id}`);

      const details = response.data;
      setSelectedOutpass({
        ...outpass,
        unique_id: details.unique_id,
        backup_code: details.backup_code || undefined,
      });
    } catch (err: any) {
      const errorDetails = errorHandler.handleError(err);
      setQrError(errorDetails.message || 'Failed to load outpass details.');
    } finally {
      setQrLoading(false);
    }
  };

  const getFilteredOutpasses = () => {
    return outpasses.filter(pass => {
      if (selectedTab === 'completed') {
        return pass.status === 'used' || pass.status === 'completed';
      }
      return pass.status === selectedTab;
    });
  };

  const filteredOutpasses = getFilteredOutpasses();

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
      case 'completed':
        return theme.colors.info;
      default:
        return theme.colors.textMuted;
    }
  };

  const tabs: { key: TabType; label: string }[] = [
    { key: 'approved', label: 'Approved' },
    { key: 'pending', label: 'Pending' },
    { key: 'rejected', label: 'Rejected' },
    { key: 'completed', label: 'Completed' },
  ];

  const HEADER_ROW_HEIGHT = 52;
  const HEADER_PADDING_TOP = Math.max(insets.top, 10);
  const HEADER_PADDING_BOTTOM = 6;

  return (
    <View style={styles.container}>
      {/* Header - compact height, same feel as home screen */}
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
            accessibilityLabel="Back">
            <Ionicons name="arrow-back" size={24} color={theme.colors.primary} />
          </TouchableOpacity>
          <Text style={styles.headerTitle}>Outpass</Text>
          <GradientButton
            style={styles.createButton}
            onPress={() => {
              hapticService.onButtonPress();
              setShowCreateModal(true);
            }}
            accessibilityRole="button"
            accessibilityLabel="Create new outpass">
            <Ionicons name="add" size={18} color={theme.colors.primary} />
            <Text style={styles.createButtonText}>New</Text>
          </GradientButton>
        </View>
      </View>

      {/* Tabs */}
      <View style={styles.tabsContainer}>
        <ScrollView horizontal showsHorizontalScrollIndicator={false}>
          {tabs.map(tab => (
            <TouchableOpacity
              key={tab.key}
              style={[styles.tab, selectedTab === tab.key && styles.tabActive]}
              onPress={() => {
                hapticService.onButtonPress();
                setSelectedTab(tab.key);
              }}>
              <Text style={[styles.tabText, selectedTab === tab.key && styles.tabTextActive]}>
                {tab.label}
              </Text>
            </TouchableOpacity>
          ))}
        </ScrollView>
      </View>

      {/* Outpass List */}
      <ScrollView
        style={styles.content}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>
        {loading ? (
          <View style={styles.loadingContainer}>
            <Text>Loading...</Text>
          </View>
        ) : filteredOutpasses.length === 0 ? (
          <EmptyState
            variant="no-data"
            title="No Outpasses"
            subtitle={`You don't have any ${selectedTab} outpasses`}
          />
        ) : (
          filteredOutpasses.map((pass) => (
            <TouchableOpacity
              key={pass.id}
              onPress={() => {
                if (pass.status === 'approved') {
                  openQrForOutpass(pass);
                }
              }}
              style={styles.passCard}>
              <Card variant="default">
                <CardContent>
                  {/* Title/Reason */}
                  <View style={styles.passHeader}>
                    <Text style={styles.passTitle}>{pass.reason_label || pass.reason}</Text>
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

                    {pass.required_date && (
                      <View style={styles.detailRow}>
                        <Ionicons name="calendar-outline" size={16} color={theme.colors.textSecondary} />
                        <Text style={styles.detailLabel}>Required Date:</Text>
                        <Text style={styles.detailValue}>
                          {format(new Date(pass.required_date), 'MMM dd, yyyy')}
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

                    {pass.status === 'approved' && (
                      <View style={styles.tapHint}>
                        <Ionicons name="qr-code-outline" size={16} color={theme.colors.primary} />
                        <Text style={styles.tapHintText}>Tap to view QR code</Text>
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
            </TouchableOpacity>
          ))
        )}
      </ScrollView>

      {/* QR Code Modal */}
      <Modal
        visible={showQRModal}
        animationType="slide"
        presentationStyle="pageSheet">
        <View style={styles.modalContainer}>
          <View style={styles.modalHeader}>
            <Text style={styles.modalTitle}>Your Outpass QR</Text>
            <TouchableOpacity onPress={() => {
              hapticService.onButtonPress();
              setShowQRModal(false);
              setSelectedOutpass(null);
              setQrError(null);
            }}>
              <Ionicons name="arrow-back" size={24} color={theme.colors.textSecondary} />
            </TouchableOpacity>
          </View>
          <ScrollView style={styles.modalContent}>
            {qrLoading ? (
              <View style={{ padding: 20 }}>
                <Text style={{ textAlign: 'center', color: theme.colors.textSecondary }}>
                  Loading outpass details...
                </Text>
              </View>
            ) : qrError ? (
              <View style={{ padding: 20 }}>
                <Text style={{ textAlign: 'center', color: theme.colors.error }}>
                  {qrError}
                </Text>
              </View>
            ) : selectedOutpass ? (
              <>
                <StudentQRCode
                  onClose={() => setShowQRModal(false)}
                  outPassId={selectedOutpass.id}
                  uniqueId={selectedOutpass.unique_id || `OP-${selectedOutpass.id}`}
                  validUntil={selectedOutpass.required_date || new Date().toISOString()}
                  backupCode={selectedOutpass.backup_code || null}
                />
                <View style={styles.qrDetails}>
                  <Text style={styles.qrDetailsTitle}>Outpass Details</Text>
                  <Text style={styles.qrDetailsText}>Reason: {selectedOutpass.reason_label}</Text>
                  {selectedOutpass.required_date && (
                    <Text style={styles.qrDetailsText}>
                      Required Date: {format(new Date(selectedOutpass.required_date), 'MMM dd, yyyy')}
                    </Text>
                  )}
                </View>
              </>
            ) : null}
          </ScrollView>
        </View>
      </Modal>

      {/* Create Outpass Modal */}
      <Modal
        visible={showCreateModal}
        animationType="slide"
        presentationStyle="pageSheet">
        <View style={styles.modalContainer}>
          <View style={styles.modalHeader}>
            <Text style={styles.modalTitle}>Request Outpass</Text>
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
                    {OUTPASS_REASONS.map((reason) => (
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
                              reason.value === 'urgent' ? 'flash-outline' :
                              'medical-outline'
                            }
                            size={24}
                            color={value === reason.value ? theme.colors.white : '#D79F24'}
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

            {/* Required Date */}
            <View style={styles.inputGroup}>
              <Text style={styles.inputLabel}>Required Date *</Text>
              <Controller
                control={control}
                name="required_date"
                render={({ field: { value } }) => (
                  <>
                    <TouchableOpacity
                      style={[
                        styles.dateButton,
                        errors.required_date && styles.dateButtonError,
                      ]}
                      onPress={() => setShowDatePicker(true)}>
                      <Text style={styles.dateButtonText}>
                        {value.toLocaleDateString()}
                      </Text>
                      <Ionicons name="calendar-outline" size={20} color={theme.colors.primary} />
                    </TouchableOpacity>
                    {showDatePicker && (
                      <DateTimePicker
                        value={value}
                        mode="date"
                        display={Platform.OS === 'ios' ? 'spinner' : 'default'}
                        onChange={(event, selectedDate) => {
                          setShowDatePicker(Platform.OS === 'ios');
                          if (selectedDate) {
                            setValue('required_date', selectedDate, { shouldValidate: true });
                          }
                        }}
                        minimumDate={new Date()}
                      />
                    )}
                    {errors.required_date && (
                      <Text style={styles.errorText}>{errors.required_date.message}</Text>
                    )}
                  </>
                )}
              />
            </View>

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
    paddingHorizontal: 20,
    ...theme.shadows.medium,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  backButton: {
    padding: theme.spacing.xs,
  },
  headerTitle: {
    color: theme.colors.primary,
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
    flex: 1,
    textAlign: 'center',
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
  tabsContainer: {
    backgroundColor: theme.colors.white,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
  },
  tab: {
    paddingHorizontal: theme.spacing.lg,
    paddingVertical: theme.spacing.md,
    borderBottomWidth: 2,
    borderBottomColor: 'transparent',
  },
  tabActive: {
    borderBottomColor: theme.colors.primary,
  },
  tabText: {
    fontSize: theme.fontSize.md,
    color: theme.colors.textSecondary,
    fontWeight: theme.fontWeight.medium,
  },
  tabTextActive: {
    color: theme.colors.primary,
    fontWeight: theme.fontWeight.bold,
  },
  content: {
    flex: 1,
    padding: theme.spacing.md,
  },
  loadingContainer: {
    padding: theme.spacing.xl,
    alignItems: 'center',
  },
  passCard: {
    marginBottom: theme.spacing.md,
  },
  passHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: theme.spacing.sm,
  },
  passTitle: {
    fontSize: theme.fontSize.lg,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
    flex: 1,
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
    minWidth: 100,
  },
  detailValue: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.text,
    flex: 1,
    marginLeft: theme.spacing.xs,
  },
  tapHint: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.xs,
    marginTop: theme.spacing.sm,
    padding: theme.spacing.sm,
    backgroundColor: `${theme.colors.primary}15`,
    borderRadius: theme.borderRadius.sm,
  },
  tapHintText: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.primary,
    fontWeight: theme.fontWeight.medium,
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
    borderColor: '#D79F24',
    borderRadius: theme.borderRadius.md,
    backgroundColor: theme.colors.white,
    gap: theme.spacing.xs,
  },
  reasonOptionSelected: {
    backgroundColor: '#D79F24',
    borderColor: '#D79F24',
  },
  reasonOptionText: {
    fontSize: theme.fontSize.sm,
    fontWeight: theme.fontWeight.semibold,
    color: '#D79F24',
    textAlign: 'center',
  },
  reasonOptionTextSelected: {
    color: theme.colors.white,
  },
  dateButton: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.borderRadius.md,
    padding: theme.spacing.md,
    backgroundColor: theme.colors.white,
  },
  dateButtonText: {
    fontSize: theme.fontSize.md,
    color: theme.colors.text,
  },
  dateButtonError: {
    borderColor: theme.colors.error,
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
  qrDetails: {
    marginTop: theme.spacing.lg,
    padding: theme.spacing.md,
    backgroundColor: theme.colors.surface,
    borderRadius: theme.borderRadius.md,
  },
  qrDetailsTitle: {
    fontSize: theme.fontSize.lg,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
    marginBottom: theme.spacing.sm,
  },
  qrDetailsText: {
    fontSize: theme.fontSize.md,
    color: theme.colors.text,
    marginBottom: theme.spacing.xs,
  },
});
