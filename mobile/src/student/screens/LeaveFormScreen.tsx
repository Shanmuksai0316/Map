import React, { useState } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  TextInput,
  Alert,
  Platform,
} from 'react-native';
import { GradientButton } from '../../shared/components/GradientButton';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import Ionicons from 'react-native-vector-icons/Ionicons';
import DateTimePicker from '@react-native-community/datetimepicker';
import { useAuthStore } from '../../shared/store/auth.store';
import { apiService } from '../../shared/services/api.service';
import { APP_CONFIG } from '../../shared/config/app.config';
import { theme } from '../../shared/theme/theme';
import { errorHandler } from '../../shared/utils/errorHandler';
import { hapticService } from '../../shared/services/haptic.service';
import { sanitizeText, sanitizePhone } from '../../shared/utils/validation';
import { leaveSchema, type LeaveFormData } from '../../shared/validation/schemas/leave.schema';

export const LeaveFormScreen = ({ navigation, route }: any) => {
  const insets = useSafeAreaInsets();
  const { user } = useAuthStore();
  const [showFromDatePicker, setShowFromDatePicker] = useState(false);
  const [showToDatePicker, setShowToDatePicker] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  const HEADER_ROW_HEIGHT = 52;
  const HEADER_PADDING_TOP = Math.max(insets.top, 10);
  const HEADER_PADDING_BOTTOM = 6;

  const {
    control,
    handleSubmit,
    formState: { errors },
    setValue,
    watch,
  } = useForm<LeaveFormData>({
    resolver: zodResolver(leaveSchema),
    defaultValues: {
      reason_for_leave: '',
      from_date: new Date(),
      to_date: new Date(),
      emergency_contact: '',
    },
  });

  const fromDate = watch('from_date');
  const toDate = watch('to_date');

  const onSubmit = async (data: LeaveFormData) => {
    setSubmitting(true);
    try {
      await apiService.post(APP_CONFIG.ENDPOINTS.LEAVES, {
        title: sanitizeText(data.reason_for_leave),
        reason_for_leave: sanitizeText(data.reason_for_leave),
        from_date: data.from_date.toISOString().split('T')[0],
        to_date: data.to_date.toISOString().split('T')[0],
        emergency_contact: data.emergency_contact ? sanitizePhone(data.emergency_contact) : null,
      });

      hapticService.onSuccess();
      Alert.alert('Success', 'Leave request submitted successfully', [
        { text: 'OK', onPress: () => navigation.goBack() },
      ]);
    } catch (err: any) {
      hapticService.onError();
      const errorDetails = errorHandler.handleError(err);
      Alert.alert('Error', errorDetails.message);
    } finally {
      setSubmitting(false);
    }
  };

  const getRoomNumber = () => {
    // Get room number from 'user profile'
    return user?.student_uid ? `Room: ${user.student_uid}` : 'Room: N/A';
  };

  return (
    <View style={styles.container}>
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
            onPress={() => navigation.goBack()}
            accessibilityLabel="Go back">
            <Ionicons name="arrow-back" size={24} color={theme.colors.primary} />
          </TouchableOpacity>
          <Text style={styles.headerTitle}>Leave Request</Text>
          <View style={styles.headerSpacer} />
        </View>
      </View>

      <ScrollView style={styles.content}>
        {/* Reason for Leave */}
        <View style={styles.section}>
          <Text style={styles.label}>Reason for Leave *</Text>
          <Controller
            control={control}
            name="reason_for_leave"
            render={({ field: { onChange, onBlur, value } }) => (
              <>
          <TextInput
                  style={[
                    styles.textInput,
                    errors.reason_for_leave && styles.textInputError,
                  ]}
            placeholder="Enter reason for leave"
                  value={value}
                  onChangeText={onChange}
                  onBlur={onBlur}
            multiline
            numberOfLines={3}
            textAlignVertical="top"
            accessibilityLabel="Reason for leave"
            accessibilityHint="Enter the reason for your leave request"
            accessibilityRole="text"
                />
                {errors.reason_for_leave && (
                  <Text style={styles.errorText}>
                    {errors.reason_for_leave.message}
                  </Text>
                )}
              </>
            )}
          />
        </View>

        {/* From Date */}
        <View style={styles.section}>
          <Text style={styles.label}>From Date *</Text>
          <Controller
            control={control}
            name="from_date"
            render={({ field: { value } }) => (
              <>
          <TouchableOpacity
                  style={[
                    styles.dateButton,
                    errors.from_date && styles.dateButtonError,
                  ]}
            onPress={() => setShowFromDatePicker(true)}
            accessibilityRole="button"
                  accessibilityLabel={`From date: ${value.toLocaleDateString()}`}
            accessibilityHint="Double tap to select from 'date'">
            <Text style={styles.dateButtonText}>
                    {value.toLocaleDateString()}
            </Text>
            <Ionicons name="calendar-outline" size={20} color={theme.colors.primary} accessibilityRole="image" />
          </TouchableOpacity>
          {showFromDatePicker && (
            <DateTimePicker
                    value={value}
              mode="date"
              display={Platform.OS === 'ios' ? 'spinner' : 'default'}
              onChange={(event, selectedDate) => {
                setShowFromDatePicker(Platform.OS === 'ios');
                if (selectedDate) {
                        setValue('from_date', selectedDate, { shouldValidate: true });
                }
              }}
              minimumDate={new Date()}
            />
          )}
                {errors.from_date && (
                  <Text style={styles.errorText}>
                    {errors.from_date.message}
                  </Text>
                )}
              </>
            )}
          />
        </View>

        {/* To Date */}
        <View style={styles.section}>
          <Text style={styles.label}>To Date *</Text>
          <Controller
            control={control}
            name="to_date"
            render={({ field: { value } }) => (
              <>
          <TouchableOpacity
                  style={[
                    styles.dateButton,
                    errors.to_date && styles.dateButtonError,
                  ]}
            onPress={() => setShowToDatePicker(true)}
            accessibilityRole="button"
                  accessibilityLabel={`To date: ${value.toLocaleDateString()}`}
            accessibilityHint="Double tap to select to date">
            <Text style={styles.dateButtonText}>
                    {value.toLocaleDateString()}
            </Text>
            <Ionicons name="calendar-outline" size={20} color={theme.colors.primary} accessibilityRole="image" />
          </TouchableOpacity>
          {showToDatePicker && (
            <DateTimePicker
                    value={value}
              mode="date"
              display={Platform.OS === 'ios' ? 'spinner' : 'default'}
              onChange={(event, selectedDate) => {
                setShowToDatePicker(Platform.OS === 'ios');
                if (selectedDate) {
                        setValue('to_date', selectedDate, { shouldValidate: true });
                }
              }}
                    minimumDate={fromDate}
            />
          )}
                {errors.to_date && (
                  <Text style={styles.errorText}>
                    {errors.to_date.message}
                  </Text>
                )}
              </>
            )}
          />
        </View>

        {/* Emergency Contact (Optional) */}
        <View style={styles.section}>
          <Text style={styles.label}>Emergency Contact (Optional)</Text>
          <Controller
            control={control}
            name="emergency_contact"
            render={({ field: { onChange, onBlur, value } }) => (
              <>
          <TextInput
                  style={[
                    styles.textInput,
                    errors.emergency_contact && styles.textInputError,
                  ]}
            placeholder="Enter emergency contact number"
                  value={value || ''}
                  onChangeText={onChange}
                  onBlur={onBlur}
            keyboardType="phone-pad"
            accessibilityLabel="Emergency contact"
            accessibilityHint="Enter emergency contact phone number (optional)"
            accessibilityRole="text"
                />
                {errors.emergency_contact && (
                  <Text style={styles.errorText}>
                    {errors.emergency_contact.message}
                  </Text>
                )}
              </>
            )}
          />
        </View>

        {/* Actions */}
        <View style={styles.actions}>
          <GradientButton
            style={styles.closeButtonBottom}
            onPress={() => navigation.goBack()}
            accessibilityRole="button"
            accessibilityLabel="Close"
            accessibilityHint="Double tap to cancel and go back">
            <Text style={styles.closeButtonText}>Close</Text>
          </GradientButton>
          <GradientButton
            style={[styles.submitButton, submitting && styles.submitButtonDisabled]}
            onPress={handleSubmit(onSubmit)}
            disabled={submitting}
            accessibilityRole="button"
            accessibilityLabel={submitting ? 'Submitting request' : 'Submit leave request'}
            accessibilityHint="Double tap to submit your leave request"
            accessibilityState={{ disabled: submitting }}>
            <Text style={styles.submitButtonText}>
              {submitting ? 'Submitting...' : 'Submit Request'}
            </Text>
          </GradientButton>
        </View>
      </ScrollView>
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
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  backButton: {
    padding: theme.spacing.xs,
  },
  headerSpacer: {
    width: 32,
  },
  headerTitle: {
    color: theme.colors.primary,
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
    textAlign: 'center',
    flex: 1,
  },
  content: {
    flex: 1,
    padding: theme.spacing.lg,
  },
  section: {
    marginBottom: theme.spacing.lg,
  },
  label: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
    marginBottom: theme.spacing.sm,
  },
  defaultValue: {
    fontSize: theme.fontSize.md,
    color: theme.colors.textSecondary,
    padding: theme.spacing.md,
    backgroundColor: theme.colors.surface,
    borderRadius: theme.borderRadius.md,
  },
  textInput: {
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.borderRadius.md,
    padding: theme.spacing.md,
    fontSize: theme.fontSize.md,
    color: theme.colors.text,
    backgroundColor: theme.colors.white,
    minHeight: 80,
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
  actions: {
    flexDirection: 'row',
    gap: theme.spacing.md,
    marginTop: theme.spacing.lg,
    marginBottom: theme.spacing.xl,
  },
  closeButtonBottom: {
    flex: 1,
    padding: theme.spacing.md,
    borderRadius: theme.borderRadius.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.surface,
    alignItems: 'center',
  },
  closeButtonText: {
    fontSize: theme.fontSize.md,
    color: theme.colors.text,
    fontWeight: theme.fontWeight.semibold,
  },
  submitButton: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
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
  textInputError: {
    borderColor: theme.colors.error,
  },
  dateButtonError: {
    borderColor: theme.colors.error,
  },
  errorText: {
    color: theme.colors.error,
    fontSize: theme.fontSize.xs,
    marginTop: theme.spacing.xs,
  },
});
