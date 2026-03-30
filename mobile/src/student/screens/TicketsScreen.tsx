import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  Modal,
  TextInput,
  Alert,
} from 'react-native';
import { GradientButton } from '../../shared/components/GradientButton';
import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useAuthStore } from '../../shared/store/auth.store';
import { apiService } from '../../shared/services/api.service';
import { Ticket } from '../../types';
import { APP_CONFIG } from '../../shared/config/app.config';
import { format } from 'date-fns';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { theme } from '../../shared/theme/theme';
import { errorHandler } from '../../shared/utils/errorHandler';
import { ErrorState, LoadingState } from '../../shared/components';
import { PhotoUpload } from '../components/PhotoUpload';
import { sanitizeText } from '../../shared/utils/validation';
import { hapticService } from '../../shared/services/haptic.service';
import { ticketSchema, type TicketFormData } from '../../shared/validation/schemas/ticket.schema';

export const TicketsScreen = ({ navigation, route }: any) => {
  const insets = useSafeAreaInsets();
  const { user } = useAuthStore();
  const categoryFilter = route?.params?.category; // 'housekeeping' or 'repair_maintenance'
  const [tickets, setTickets] = useState<Ticket[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [selectedHKOption, setSelectedHKOption] = useState<string>('');
  const [showOthersModal, setShowOthersModal] = useState(false);
  const [othersText, setOthersText] = useState('');
  const [selectedRepairSubcategory, setSelectedRepairSubcategory] = useState<string>('');
  const [repairOtherText, setRepairOtherText] = useState('');

  const HOUSEKEEPING_OPTIONS = [
    'Room cleaning request',
    'Washroom cleaning request',
    'Common area cleaning request',
    'Others',
  ];

  const REPAIR_SUBCATEGORIES = [
    'Light',
    'Fan',
    'Switch board',
    'Geyser',
    'RO water purifier',
    'Plumbing',
    'Door',
    'Window',
    'Lift',
    'AC',
    'Furniture',
    'Infrastructure',
    'Other',
  ];

  const {
    control,
    handleSubmit,
    setValue,
    formState: { errors },
    reset,
  } = useForm<TicketFormData>({
    resolver: zodResolver(ticketSchema),
    defaultValues: {
      request_type: categoryFilter || '',
    issue: '',
    description: '',
      photos: [],
    },
  });

  const requestTypes = [
    { value: 'repair_maintenance', label: 'Repair Maintenance' },
    { value: 'housekeeping', label: 'House Keeping' },
  ];

  const fetchTickets = async () => {
    try {
      setError(null);
      setLoading(true);
      // Build API URL with category filter if provided
      let apiUrl = APP_CONFIG.ENDPOINTS.TICKETS;
      if (categoryFilter) {
        // Map repair_maintenance to maintenance for API
        const apiCategory = categoryFilter === 'repair_maintenance' ? 'maintenance' : categoryFilter;
        apiUrl += `?category=${apiCategory}`;
      }
      
      const response = await apiService.get<{ data: Ticket[] }>(apiUrl);
      setTickets(response.data || []);
    } catch (err) {
      const errorDetails = errorHandler.handleError(err);
      console.error('[Tickets] Fetch error:', err);
      setError(errorDetails.message);
      setTickets([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchTickets();
  }, []);

  useEffect(() => {
    if (categoryFilter === 'housekeeping') {
      const v = selectedHKOption === 'Others' ? (othersText || 'Others') : selectedHKOption;
      setValue('issue', v);
    } else if (categoryFilter === 'repair_maintenance') {
      const v = selectedRepairSubcategory === 'Other' ? (repairOtherText || 'Other') : selectedRepairSubcategory;
      setValue('issue', v);
    }
  }, [categoryFilter, selectedHKOption, othersText, selectedRepairSubcategory, repairOtherText, setValue]);

  const onRefresh = () => {
    setRefreshing(true);
    fetchTickets();
  };

  const onSubmit = async (data: TicketFormData) => {
    if (submitting) return; // Prevent double submission
    
    // Validate housekeeping option selection
    if (categoryFilter === 'housekeeping' && !selectedHKOption) {
      Alert.alert('Validation Error', 'Please select a service option');
      return;
    }
    
    if (categoryFilter === 'housekeeping' && selectedHKOption === 'Others' && !othersText.trim()) {
      Alert.alert('Validation Error', 'Please describe the service needed for Others');
      return;
    }

    // Validate repair subcategory
    if (categoryFilter === 'repair_maintenance' && !selectedRepairSubcategory) {
      Alert.alert('Validation Error', 'Please select a repair category');
      return;
    }
    if (categoryFilter === 'repair_maintenance' && selectedRepairSubcategory === 'Other' && !repairOtherText.trim()) {
      Alert.alert('Validation Error', 'Please describe the repair needed for Other');
      return;
    }


    const hostelId = user?.hostel_id ?? null;
    if (!hostelId) {
      try {
        const profileRes = await apiService.get<{ data?: { student?: { hostel_id?: number }; hostel_id?: number } }>(APP_CONFIG.ENDPOINTS.PROFILE);
        const resolvedHostelId = profileRes?.data?.student?.hostel_id ?? profileRes?.data?.hostel_id ?? null;
        if (!resolvedHostelId) {
          Alert.alert('Error', 'Your hostel is not set. Please contact your administrator.');
          return;
        }
        await submitTicket(data, resolvedHostelId);
      } catch (e) {
        const errDetails = errorHandler.handleError(e);
        Alert.alert('Error', errDetails.message);
      } finally {
        setSubmitting(false);
      }
      return;
    }

    try {
      setSubmitting(true);
      await submitTicket(data, hostelId);
    } catch (err) {
      console.error('Ticket submission error:', err);
      hapticService.onError();
      const errorDetails = errorHandler.handleError(err);
      Alert.alert('Error', errorDetails.message);
    } finally {
      setSubmitting(false);
    }
  };

  const submitTicket = async (data: TicketFormData, hostelId: number) => {
    setSubmitting(true);
    const apiCategory = categoryFilter === 'repair_maintenance' ? 'maintenance' : 'housekeeping';

    let title: string;
    if (categoryFilter === 'housekeeping') {
      title = selectedHKOption === 'Others' ? othersText : selectedHKOption;
    } else {
      title = selectedRepairSubcategory === 'Other' ? repairOtherText : selectedRepairSubcategory;
      if (data.description?.trim()) {
        title = title + ' - ' + (data.description.length > 40 ? data.description.substring(0, 40) + '...' : data.description);
      }
    }

    await apiService.post(APP_CONFIG.ENDPOINTS.TICKETS, {
      title: sanitizeText(title),
      issue: sanitizeText(title),
      description: sanitizeText(data.description!),
      request_type: categoryFilter || 'housekeeping',
      category: apiCategory,
      photos: data.photos || [],
    });

    hapticService.onSuccess();
    Alert.alert('Success', 'Request created successfully');
    setShowCreateModal(false);
    setSelectedHKOption('');
    setOthersText('');
    setSelectedRepairSubcategory('');
    setRepairOtherText('');
    reset({
      request_type: categoryFilter || '',
      issue: '',
      description: '',
      photos: [],
    });
    fetchTickets();
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'done':
        return theme.colors.success;
      case 'pending':
        return theme.colors.warning;
      case 'in_progress':
        return theme.colors.warning;
      case 'resolved':
        return theme.colors.success;
      case 'closed':
        return theme.colors.textMuted;
      default:
        return theme.colors.textMuted;
    }
  };

  const getStatusLabel = (status: string) => {
    switch (status) {
      case 'done':
        return 'DONE';
      case 'pending':
        return 'PENDING';
      case 'in_progress':
        return 'IN PROGRESS';
      case 'resolved':
        return 'RESOLVED';
      case 'closed':
        return 'CLOSED';
      default:
        return status.toUpperCase();
    }
  };

  const calculateTimeElapsed = (createdAt: string) => {
    const now = new Date();
    const created = new Date(createdAt);
    const diffInMs = now.getTime() - created.getTime();
    const diffInHours = Math.floor(diffInMs / (1000 * 60 * 60));
    const diffInDays = Math.floor(diffInHours / 24);
    
    if (diffInDays > 0) {
      return `${diffInDays} day${diffInDays > 1 ? 's' : ''}`;
    } else if (diffInHours > 0) {
      return `${diffInHours} hour${diffInHours > 1 ? 's' : ''}`;
    } else {
      const diffInMinutes = Math.floor(diffInMs / (1000 * 60));
      return `${diffInMinutes} minute${diffInMinutes > 1 ? 's' : ''}`;
    }
  };

  // Match NotificationsScreen header height and spacing
  const HEADER_ROW_HEIGHT = 52;
  const HEADER_PADDING_TOP = Math.max(insets.top, 10);
  const HEADER_PADDING_BOTTOM = 6;

  return (
    <View style={styles.container}>
      {/* Header - same height/spacing as Notifications screen, no "Back" text */}
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
            onPress={() =>
              navigation?.canGoBack?.() ? navigation.goBack() : navigation.navigate('Home')
            }
            accessibilityLabel="Go back">
            <Ionicons name="arrow-back" size={24} color={theme.colors.primary} />
          </TouchableOpacity>
          <Text style={styles.headerTitle}>
            {categoryFilter === 'housekeeping'
              ? 'House Keeping'
              : categoryFilter === 'repair_maintenance'
              ? 'Repair & Maintenance'
              : 'Tickets'}
          </Text>
          <GradientButton style={styles.createButton} onPress={() => setShowCreateModal(true)}>
            <Ionicons name="add" size={18} color={theme.colors.primary} />
            <Text style={styles.createButtonText}>New</Text>
          </GradientButton>
        </View>
      </View>

      {/* Tickets List */}
      <ScrollView
        style={styles.content}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>
        {loading ? (
          <LoadingState message="Loading tickets..." />
        ) : error ? (
          <ErrorState error={error} onRetry={fetchTickets} />
        ) : tickets.length === 0 ? (
          <View style={styles.emptyState}>
            <Ionicons
              name="clipboard-outline"
              size={64}
              color={theme.colors.textSecondary}
              style={styles.emptyIcon}
            />
            <Text style={styles.emptyTitle}>No Requests</Text>
            <Text style={styles.emptySubtitle}>
              You haven't created any requests yet
            </Text>
            <GradientButton
              style={styles.emptyButton}
              onPress={() => setShowCreateModal(true)}>
              <Text style={styles.emptyButtonText}>Create First Request</Text>
            </GradientButton>
          </View>
        ) : (
          tickets.map((ticket) => (
            <TouchableOpacity
              key={ticket.id}
              style={styles.ticketCard}
              onPress={() => navigation.navigate('TicketDetail', { ticketId: ticket.id })}>
              {/* Student Name and Room */}
              <View style={styles.ticketStudentInfo}>
                <Text style={styles.ticketStudentName}>{ticket.student_name || user?.name}</Text>
                {ticket.student_room && (
                  <Text style={styles.ticketRoom}>Room: {ticket.student_room}</Text>
                )}
              </View>

              {/* Title and Status (right side) */}
              <View style={styles.ticketHeaderRow}>
                <Text style={styles.ticketTitle} numberOfLines={2}>{ticket.title}</Text>
                <View
                  style={[
                    styles.statusBadge,
                    { backgroundColor: getStatusColor(ticket.status) },
                  ]}>
                  <Text style={styles.statusText}>
                    {getStatusLabel(ticket.status)}
                  </Text>
                </View>
              </View>

              {/* Default Department */}
              {ticket.department && (
                <View style={styles.ticketDepartment}>
                  <Text style={styles.ticketDepartmentLabel}>Department:</Text>
                  <Text style={styles.ticketDepartmentValue}>{ticket.department}</Text>
                </View>
              )}

              {/* Ticket Raised Time */}
              <View style={styles.ticketTimeRow}>
                <Ionicons name="time-outline" size={14} color={theme.colors.textSecondary} />
                <Text style={styles.ticketTimeText}>
                  Raised: {format(new Date(ticket.created_at), 'MMM dd, yyyy HH:mm')}
                  {ticket.time_elapsed && ` (${ticket.time_elapsed} ago)`}
                </Text>
              </View>
            </TouchableOpacity>
          ))
        )}
      </ScrollView>

      {/* Create Ticket Modal */}
      <Modal
        visible={showCreateModal}
        animationType="slide"
        presentationStyle="pageSheet">
        <View style={styles.modalContainer}>
          <View style={styles.modalHeader}>
            <TouchableOpacity onPress={() => setShowCreateModal(false)}>
              <Ionicons name="arrow-back" size={24} color={theme.colors.text} />
            </TouchableOpacity>
            <Text style={styles.modalTitle}>Create Request</Text>
            <View style={{ width: 24 }} />
          </View>

          <ScrollView style={styles.modalContent}>
            {/* Request type is auto-generated from selected tab (categoryFilter) */}
            {categoryFilter && (
              <View style={styles.requestTypeInfo}>
                <Ionicons 
                  name={categoryFilter === 'housekeeping' ? 'home-outline' : 'construct-outline'} 
                  size={20} 
                  color={theme.colors.primary} 
                />
                <Text style={styles.requestTypeInfoText}>
                  {categoryFilter === 'housekeeping' ? 'House Keeping' : 'Repair & Maintenance'} Request
                </Text>
              </View>
            )}

            {/* Housekeeping Options */}
            {categoryFilter === 'housekeeping' && (
              <View style={styles.inputGroup}>
                <Text style={styles.inputLabel}>Select Service *</Text>
                <View style={styles.radioGroup}>
                  {HOUSEKEEPING_OPTIONS.map((option) => (
                    <TouchableOpacity
                      key={option}
                      style={[
                        styles.radioOption,
                        selectedHKOption === option && styles.radioOptionSelected,
                      ]}
                      onPress={() => {
                        hapticService.onButtonPress();
                        setSelectedHKOption(option);
                        if (option === 'Others') {
                          setShowOthersModal(true);
                        }
                      }}>
                      <View style={styles.radioCircle}>
                        {selectedHKOption === option && <View style={styles.radioCircleInner} />}
                      </View>
                      <Text style={[
                        styles.radioText,
                        selectedHKOption === option && styles.radioTextSelected,
                      ]}>
                        {option}
                      </Text>
                    </TouchableOpacity>
                  ))}
                </View>
                {selectedHKOption === 'Others' && othersText && (
                  <View style={styles.othersPreview}>
                    <Text style={styles.othersPreviewText}>{othersText}</Text>
                  </View>
                )}
              </View>
            )}

            {/* Repair Subcategories */}
            {categoryFilter === 'repair_maintenance' && (
              <View style={styles.inputGroup}>
                <Text style={styles.inputLabel}>Repair Categories *</Text>
                <View style={styles.radioGroup}>
                  {REPAIR_SUBCATEGORIES.map((option) => (
                    <TouchableOpacity
                      key={option}
                      style={[
                        styles.radioOption,
                        selectedRepairSubcategory === option && styles.radioOptionSelected,
                      ]}
                      onPress={() => {
                        hapticService.onButtonPress();
                        setSelectedRepairSubcategory(option);
                        if (option === 'Other') {
                          setRepairOtherText('');
                        }
                      }}>
                      <View style={styles.radioCircle}>
                        {selectedRepairSubcategory === option && <View style={styles.radioCircleInner} />}
                      </View>
                      <Text style={[
                        styles.radioText,
                        selectedRepairSubcategory === option && styles.radioTextSelected,
                      ]}>
                        {option}
                      </Text>
                    </TouchableOpacity>
                  ))}
                </View>
                {selectedRepairSubcategory === 'Other' && (
                  <View style={[styles.inputGroup, { marginTop: theme.spacing.sm }]}>
                    <Text style={styles.inputLabel}>Describe the repair needed *</Text>
                    <TextInput
                      style={[styles.textArea, { marginTop: 4 }]}
                      placeholder="Describe the repair..."
                      value={repairOtherText}
                      onChangeText={setRepairOtherText}
                      multiline
                      numberOfLines={2}
                      textAlignVertical="top"
                    />
                  </View>
                )}
              </View>
            )}

            <View style={styles.inputGroup}>
              <Text style={styles.inputLabel}>Description (Optional)</Text>
              <Controller
                control={control}
                name="description"
                render={({ field: { onChange, onBlur, value } }) => (
                  <>
              <TextInput
                      style={[
                        styles.textArea,
                        errors.description && styles.textAreaError,
                      ]}
                placeholder="Provide detailed information..."
                      value={value}
                      onChangeText={onChange}
                      onBlur={onBlur}
                multiline
                numberOfLines={6}
                textAlignVertical="top"
                    />
                    {errors.description && (
                      <Text style={styles.errorText}>
                        {errors.description.message}
                      </Text>
                    )}
                  </>
                )}
              />
            </View>

            <View style={styles.inputGroup}>
              <Controller
                control={control}
                name="photos"
                render={({ field: { onChange, value } }) => (
                  <PhotoUpload
                    photos={value || []}
                    onPhotosChange={onChange}
                    maxPhotos={3}
                  />
                )}
              />
            </View>

            <View style={styles.modalActions}>
            <TouchableOpacity
              style={styles.backButtonModal}
              onPress={() => {
                setShowCreateModal(false);
                setSelectedHKOption('');
                setOthersText('');
                setSelectedRepairSubcategory('');
                setRepairOtherText('');
              }}>
              <Text style={styles.backButtonModalText}>Back</Text>
            </TouchableOpacity>
              <GradientButton
                style={[styles.submitButton, submitting && styles.submitButtonDisabled]}
                onPress={handleSubmit(onSubmit)}
                disabled={submitting}>
                <Text style={styles.submitButtonText}>
                  {submitting ? 'Submitting...' : 'Submit'}
                </Text>
              </GradientButton>
            </View>
          </ScrollView>
        </View>
      </Modal>

      {/* Others Description Modal */}
      <Modal
        visible={showOthersModal}
        animationType="slide"
        presentationStyle="pageSheet"
        transparent={false}>
        <View style={styles.modalContainer}>
          <View style={styles.modalHeader}>
            <TouchableOpacity onPress={() => {
              setShowOthersModal(false);
              setSelectedHKOption('');
              setOthersText('');
            }}>
              <Ionicons name="arrow-back" size={24} color={theme.colors.text} />
            </TouchableOpacity>
            <Text style={styles.modalTitle}>Describe Service</Text>
            <View style={{ width: 24 }} />
          </View>
          <View style={styles.modalContent}>
            <Text style={styles.inputLabel}>Please describe the service you need *</Text>
            <TextInput
              style={[styles.textArea, { marginTop: theme.spacing.sm }]}
              placeholder="Describe the housekeeping service..."
              value={othersText}
              onChangeText={setOthersText}
              multiline
              numberOfLines={4}
              textAlignVertical="top"
              autoFocus
            />
            <GradientButton
              style={[styles.submitButton, { marginTop: theme.spacing.lg }]}
              onPress={() => {
                if (othersText.trim()) {
                  setShowOthersModal(false);
                } else {
                  Alert.alert('Required', 'Please describe the service');
                }
              }}>
              <Text style={styles.submitButtonText}>Save</Text>
            </GradientButton>
          </View>
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
    flexDirection: 'row',
    alignItems: 'flex-end',
    ...theme.shadows.medium,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    width: '100%',
  },
  backButton: {
    padding: 8,
    marginLeft: -8,
  },
  headerTitle: {
    color: theme.colors.primary,
    fontSize: 20,
    fontWeight: 'bold',
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
  content: {
    flex: 1,
    padding: theme.spacing.md,
  },
  emptyState: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: theme.spacing.xxl,
  },
  emptyIcon: {
    marginBottom: theme.spacing.md,
  },
  emptyTitle: {
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
    marginBottom: theme.spacing.sm,
  },
  emptySubtitle: {
    fontSize: theme.fontSize.md,
    color: theme.colors.textSecondary,
    textAlign: 'center',
    marginBottom: theme.spacing.lg,
  },
  emptyButton: {
    backgroundColor: theme.colors.primary,
    paddingHorizontal: theme.spacing.xl,
    paddingVertical: theme.spacing.md,
    borderRadius: theme.borderRadius.md,
  },
  emptyButtonText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
  },
  ticketCard: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.borderRadius.lg,
    padding: theme.spacing.md,
    marginBottom: theme.spacing.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    ...theme.shadows.small,
  },
  ticketStudentInfo: {
    marginBottom: theme.spacing.sm,
  },
  ticketStudentName: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
    marginBottom: theme.spacing.xs,
  },
  ticketRoom: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
  },
  ticketHeaderRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: theme.spacing.sm,
    gap: theme.spacing.sm,
  },
  ticketTitle: {
    fontSize: theme.fontSize.lg,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
    flex: 1,
  },
  ticketDepartment: {
    flexDirection: 'row',
    marginBottom: theme.spacing.xs,
    gap: theme.spacing.xs,
  },
  ticketDepartmentLabel: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textMuted,
    fontWeight: theme.fontWeight.medium,
  },
  ticketDepartmentValue: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
  },
  ticketTimeRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.xs,
    marginTop: theme.spacing.xs,
  },
  ticketTimeText: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
  },
  ticketBadges: {
    flexDirection: 'row',
    gap: theme.spacing.xs,
  },
  priorityBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: theme.spacing.sm,
    paddingVertical: theme.spacing.xs,
    borderRadius: theme.borderRadius.xl,
  },
  priorityIcon: {
    marginRight: theme.spacing.xs,
  },
  priorityText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.xs,
    fontWeight: theme.fontWeight.semibold,
  },
  statusBadge: {
    paddingHorizontal: theme.spacing.sm,
    paddingVertical: theme.spacing.xs,
    borderRadius: theme.borderRadius.xl,
    alignSelf: 'flex-start',
  },
  statusText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.xs,
    fontWeight: theme.fontWeight.semibold,
  },
  ticketDescription: {
    fontSize: theme.fontSize.md,
    color: theme.colors.textSecondary,
    lineHeight: 20,
    marginBottom: theme.spacing.sm,
  },
  ticketDetails: {
    gap: theme.spacing.xs,
  },
  ticketDetailRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  ticketDetailLabel: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textMuted,
    fontWeight: theme.fontWeight.medium,
  },
  ticketDetailValue: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
    flex: 1,
    textAlign: 'right',
    marginLeft: theme.spacing.md,
  },
  assignedContainer: {
    marginTop: theme.spacing.sm,
    paddingTop: theme.spacing.sm,
    borderTopWidth: 1,
    borderTopColor: theme.colors.divider,
  },
  assignedText: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.primary,
    fontWeight: theme.fontWeight.semibold,
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
  requestTypeInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.sm,
    backgroundColor: `${theme.colors.primary}10`,
    padding: theme.spacing.md,
    borderRadius: theme.borderRadius.md,
    marginBottom: theme.spacing.lg,
    borderLeftWidth: 4,
    borderLeftColor: theme.colors.primary,
  },
  requestTypeInfoText: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.primary,
  },
  inputLabel: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
    marginBottom: theme.spacing.sm,
  },
  textInput: {
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.borderRadius.md,
    padding: theme.spacing.md,
    fontSize: theme.fontSize.md,
    color: theme.colors.text,
    backgroundColor: theme.colors.white,
  },
  requestTypeContainer: {
    flexDirection: 'row',
    gap: theme.spacing.md,
  },
  requestTypeOption: {
    flex: 1,
    paddingHorizontal: theme.spacing.md,
    paddingVertical: theme.spacing.md,
    borderRadius: theme.borderRadius.md,
    borderWidth: 2,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.surface,
    alignItems: 'center',
  },
  requestTypeSelected: {
    backgroundColor: theme.colors.primary,
    borderColor: theme.colors.primary,
  },
  requestTypeOptionText: {
    fontSize: theme.fontSize.md,
    color: theme.colors.textSecondary,
    fontWeight: theme.fontWeight.medium,
  },
  requestTypeSelectedText: {
    color: theme.colors.white,
    fontWeight: theme.fontWeight.semibold,
  },
  uploadButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    padding: theme.spacing.md,
    borderRadius: theme.borderRadius.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderStyle: 'dashed',
    backgroundColor: theme.colors.surface,
    gap: theme.spacing.sm,
  },
  uploadButtonText: {
    fontSize: theme.fontSize.md,
    color: theme.colors.primary,
    fontWeight: theme.fontWeight.medium,
  },
  modalActions: {
    flexDirection: 'row',
    gap: theme.spacing.md,
    marginTop: theme.spacing.lg,
    marginBottom: theme.spacing.xl,
  },
  backButtonModal: {
    flex: 1,
    padding: theme.spacing.md,
    borderRadius: theme.borderRadius.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.surface,
    alignItems: 'center',
  },
  backButtonModalText: {
    fontSize: theme.fontSize.md,
    color: theme.colors.text,
    fontWeight: theme.fontWeight.semibold,
  },
  priorityGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: theme.spacing.sm,
  },
  priorityOption: {
    paddingHorizontal: theme.spacing.md,
    paddingVertical: theme.spacing.sm,
    borderRadius: theme.borderRadius.md,
    borderWidth: 2,
    backgroundColor: theme.colors.white,
  },
  priorityOptionText: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
    fontWeight: theme.fontWeight.medium,
  },
  priorityOptionSelectedText: {
    color: theme.colors.white,
    fontWeight: theme.fontWeight.semibold,
  },
  textInputError: {
    borderColor: theme.colors.error,
  },
  textAreaError: {
    borderColor: theme.colors.error,
  },
  errorText: {
    color: theme.colors.error,
    fontSize: theme.fontSize.xs,
    marginTop: theme.spacing.xs,
  },
  textArea: {
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.borderRadius.md,
    padding: theme.spacing.md,
    fontSize: theme.fontSize.md,
    color: theme.colors.text,
    backgroundColor: theme.colors.white,
    minHeight: 120,
  },
  submitButton: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#D79F24',
    padding: 16,
    borderRadius: theme.borderRadius.lg,
    ...theme.shadows.medium,
  },
  submitButtonDisabled: {
    opacity: 0.6,
  },
  submitButtonText: {
    color: theme.colors.primary,
    fontSize: 16,
    fontWeight: '600',
  },
  radioGroup: {
    gap: theme.spacing.sm,
  },
  radioOption: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: theme.spacing.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.borderRadius.md,
    backgroundColor: theme.colors.white,
    gap: theme.spacing.md,
  },
  radioOptionSelected: {
    backgroundColor: `${theme.colors.primary}10`,
    borderColor: theme.colors.primary,
    borderWidth: 2,
  },
  radioCircle: {
    width: 20,
    height: 20,
    borderRadius: 10,
    borderWidth: 2,
    borderColor: theme.colors.border,
    alignItems: 'center',
    justifyContent: 'center',
  },
  radioCircleInner: {
    width: 10,
    height: 10,
    borderRadius: 5,
    backgroundColor: theme.colors.primary,
  },
  radioText: {
    fontSize: theme.fontSize.md,
    color: theme.colors.text,
    fontWeight: theme.fontWeight.medium,
  },
  radioTextSelected: {
    color: theme.colors.primary,
    fontWeight: theme.fontWeight.semibold,
  },
  othersPreview: {
    marginTop: theme.spacing.sm,
    padding: theme.spacing.md,
    backgroundColor: theme.colors.surface,
    borderRadius: theme.borderRadius.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  othersPreviewText: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.text,
  },
});
