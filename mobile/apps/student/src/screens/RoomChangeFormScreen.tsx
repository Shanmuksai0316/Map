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
import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import Ionicons from 'react-native-vector-icons/Ionicons';
import DateTimePicker from '@react-native-community/datetimepicker';
import { useAuthStore } from '../../store/auth.store';
import { apiService } from '../../services/api.service';
import { APP_CONFIG } from '../../config/app.config';
import { theme } from '../../theme/theme';
import { errorHandler } from '../../utils/errorHandler';
import { hapticService } from '../../services/haptic.service';
import { sanitizeText } from '../../utils/validation';
import { roomChangeSchema, type RoomChangeFormData } from '../../validation/schemas/room-change.schema';

export const RoomChangeFormScreen = ({ navigation, route }: any) => {
  const { user } = useAuthStore();
  const [showDatePicker, setShowDatePicker] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  const sharingOptions = [
    { value: 'single', label: 'Single' },
    { value: 'double', label: 'Double' },
    { value: 'triple', label: 'Triple' },
    { value: 'quad', label: 'Quad' },
  ];

  const {
    control,
    handleSubmit,
    formState: { errors },
    setValue,
    watch,
  } = useForm<RoomChangeFormData>({
    resolver: zodResolver(roomChangeSchema),
    defaultValues: {
      description: '',
      preferred_room_number: '',
      preferred_floor: '',
      sharing_preference: undefined,
      date_required: undefined,
    },
  });

  const dateRequired = watch('date_required');

  const onSubmit = async (data: RoomChangeFormData) => {
    setSubmitting(true);
    try {
      await apiService.post(APP_CONFIG.ENDPOINTS.ROOM_CHANGES, {
        description: sanitizeText(data.description),
        preferred_room_number: data.preferred_room_number ? sanitizeText(data.preferred_room_number) : null,
        preferred_floor: data.preferred_floor ? sanitizeText(data.preferred_floor) : null,
        sharing_preference: data.sharing_preference || null,
        date_required: data.date_required ? data.date_required.toISOString().split('T')[0] : null,
      });

      hapticService.onSuccess();
      Alert.alert('Success', 'Room change request submitted successfully', [
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
    return user?.student_uid ? `Room: ${user.student_uid}` : 'Room: N/A';
  };

  return (
    <View style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity
          style={styles.closeButton}
          onPress={() => navigation.goBack()}>
          <Ionicons name="close" size={24} color={theme.colors.white} />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Room Change Request</Text>
        <View style={{ width: 24 }} />
      </View>

      <ScrollView style={styles.content}>
        {/* Name and Room Number (default) */}
        <View style={styles.section}>
          <Text style={styles.label}>Name and Room Number</Text>
          <Text style={styles.defaultValue}>
            {user?.name || 'Student'} - {getRoomNumber()}
          </Text>
        </View>

        {/* Description */}
        <View style={styles.section}>
          <Text style={styles.label}>Description *</Text>
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
            placeholder="Enter reason for room change"
                  value={value}
                  onChangeText={onChange}
                  onBlur={onBlur}
            multiline
            numberOfLines={4}
            textAlignVertical="top"
                  accessibilityLabel="Description"
                  accessibilityHint="Enter reason for room change"
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

        {/* Preferred Room Number (Optional) */}
        <View style={styles.section}>
          <Text style={styles.label}>Preferred Room Number (Optional)</Text>
          <Controller
            control={control}
            name="preferred_room_number"
            render={({ field: { onChange, onBlur, value } }) => (
          <TextInput
            style={styles.textInput}
            placeholder="Enter preferred room number"
                value={value || ''}
                onChangeText={onChange}
                onBlur={onBlur}
                accessibilityLabel="Preferred room number"
                accessibilityHint="Enter preferred room number (optional)"
              />
            )}
          />
        </View>

        {/* Preferred Floor (Optional) */}
        <View style={styles.section}>
          <Text style={styles.label}>Preferred Floor (Optional)</Text>
          <Controller
            control={control}
            name="preferred_floor"
            render={({ field: { onChange, onBlur, value } }) => (
          <TextInput
            style={styles.textInput}
            placeholder="Enter preferred floor"
                value={value || ''}
                onChangeText={onChange}
                onBlur={onBlur}
                accessibilityLabel="Preferred floor"
                accessibilityHint="Enter preferred floor (optional)"
              />
            )}
          />
        </View>

        {/* Sharing Preference (Optional) */}
        <View style={styles.section}>
          <Text style={styles.label}>Sharing Preference (Optional)</Text>
          <Controller
            control={control}
            name="sharing_preference"
            render={({ field: { onChange, value } }) => (
          <View style={styles.optionsContainer}>
            {sharingOptions.map((option) => (
              <TouchableOpacity
                key={option.value}
                style={[
                  styles.optionButton,
                      value === option.value && styles.optionButtonSelected,
                ]}
                    onPress={() => onChange(option.value as any)}>
                <Text
                  style={[
                    styles.optionText,
                        value === option.value && styles.optionTextSelected,
                  ]}>
                  {option.label}
                </Text>
              </TouchableOpacity>
            ))}
          </View>
            )}
          />
        </View>

        {/* Date Required (Optional) */}
        <View style={styles.section}>
          <Text style={styles.label}>Date Required (Optional)</Text>
          <Controller
            control={control}
            name="date_required"
            render={({ field: { value } }) => (
              <>
          <TouchableOpacity
                  style={[
                    styles.dateButton,
                    errors.date_required && styles.dateButtonError,
                  ]}
                  onPress={() => setShowDatePicker(true)}
                  accessibilityRole="button"
                  accessibilityLabel={value ? `Date required: ${value.toLocaleDateString()}` : 'Select date required'}
                  accessibilityHint="Double tap to select date">
            <Text style={styles.dateButtonText}>
                    {value ? value.toLocaleDateString() : 'Select date'}
            </Text>
            <Ionicons name="calendar-outline" size={20} color={theme.colors.primary} />
          </TouchableOpacity>
          {showDatePicker && (
            <DateTimePicker
                    value={value || new Date()}
              mode="date"
              display={Platform.OS === 'ios' ? 'spinner' : 'default'}
              onChange={(event, selectedDate) => {
                setShowDatePicker(Platform.OS === 'ios');
                if (selectedDate) {
                        setValue('date_required', selectedDate, { shouldValidate: true });
                }
              }}
              minimumDate={new Date()}
            />
          )}
                {errors.date_required && (
                  <Text style={styles.errorText}>
                    {errors.date_required.message}
                  </Text>
                )}
              </>
            )}
          />
        </View>

        {/* Actions */}
        <View style={styles.actions}>
          <TouchableOpacity
            style={styles.closeButtonBottom}
            onPress={() => navigation.goBack()}>
            <Text style={styles.closeButtonText}>Close</Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={[styles.submitButton, submitting && styles.submitButtonDisabled]}
            onPress={handleSubmit(onSubmit)}
            disabled={submitting}>
            <Text style={styles.submitButtonText}>
              {submitting ? 'Submitting...' : 'Submit Request'}
            </Text>
          </TouchableOpacity>
        </View>
      </ScrollView>
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
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: theme.spacing.lg,
    paddingTop: theme.spacing.xl * 2,
  },
  closeButton: {
    padding: theme.spacing.xs,
  },
  headerTitle: {
    color: theme.colors.white,
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
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
  },
  textArea: {
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.borderRadius.md,
    padding: theme.spacing.md,
    fontSize: theme.fontSize.md,
    color: theme.colors.text,
    backgroundColor: theme.colors.white,
    minHeight: 100,
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
  optionsContainer: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: theme.spacing.sm,
  },
  optionButton: {
    paddingHorizontal: theme.spacing.md,
    paddingVertical: theme.spacing.sm,
    borderRadius: theme.borderRadius.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.white,
  },
  optionButtonSelected: {
    backgroundColor: theme.colors.primary,
    borderColor: theme.colors.primary,
  },
  optionText: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
    fontWeight: theme.fontWeight.medium,
  },
  optionTextSelected: {
    color: theme.colors.white,
    fontWeight: theme.fontWeight.semibold,
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
    backgroundColor: theme.colors.primary,
    padding: theme.spacing.md,
    borderRadius: theme.borderRadius.md,
    alignItems: 'center',
  },
  submitButtonDisabled: {
    opacity: 0.6,
  },
  submitButtonText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
  },
  textAreaError: {
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

