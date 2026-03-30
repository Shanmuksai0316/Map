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
import { useForm, Controller, useFieldArray } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import Ionicons from 'react-native-vector-icons/Ionicons';
import DateTimePicker from '@react-native-community/datetimepicker';
import { useAuthStore } from '../../shared/store/auth.store';
import { apiService } from '../../shared/services/api.service';
import { APP_CONFIG } from '../../shared/config/app.config';
import { theme } from '../../shared/theme/theme';
import { errorHandler } from '../../shared/utils/errorHandler';
import { hapticService } from '../../shared/services/haptic.service';
import { sanitizeText, sanitizePhone, sanitizeIDNumber } from '../../shared/utils/validation';
import { guestEntrySchema, type GuestEntryFormData } from '../../shared/validation/schemas/guest-entry.schema';

export const GuestEntryFormScreen = ({ navigation, route }: any) => {
  const insets = useSafeAreaInsets();
  const { user } = useAuthStore();
  const [showDatePicker, setShowDatePicker] = useState(false);
  const [showCheckInTimePicker, setShowCheckInTimePicker] = useState(false);
  // Removed: showCheckOutTimePicker - check-out time removed per user feedback
  const [submitting, setSubmitting] = useState(false);

  const HEADER_ROW_HEIGHT = 52;
  const HEADER_PADDING_TOP = Math.max(insets.top, 10);
  const HEADER_PADDING_BOTTOM = 6;

  const idTypes = [
    { value: 'aadhar_card', label: 'Aadhar Card' },
    { value: 'driving_license', label: 'Driving License' },
    { value: 'passport', label: 'Passport' },
    { value: 'voter_id', label: 'Voter ID' },
  ];

  const relationships = ['Father', 'Mother', 'Brother', 'Sister', 'Uncle', 'Aunt', 'Other'];

  const {
    control,
    handleSubmit,
    formState: { errors },
    setValue,
    watch,
  } = useForm<GuestEntryFormData>({
    resolver: zodResolver(guestEntrySchema),
    defaultValues: {
      guests: [
        { name: '', phone: '', relationship: '' },
      ],
      visit_date: new Date(),
      check_in_time: undefined,
      purpose_to_visit: '',
    },
  });

  const { fields, append, remove } = useFieldArray({
    control,
    name: 'guests',
  });

  const visitDate = watch('visit_date');
  const checkInTime = watch('check_in_time');
  // Removed: checkOutTime - check-out time removed per user feedback

  const addGuest = () => {
    if (fields.length >= 4) {
      Alert.alert('Limit Reached', 'Maximum 4 guests allowed');
      return;
    }
    append({ name: '', phone: '', relationship: '' });
  };

  const removeGuest = (index: number) => {
    if (fields.length > 1) {
      remove(index);
    }
  };

  const onSubmit = async (data: GuestEntryFormData) => {
    setSubmitting(true);
    try {
      const response = await apiService.post(APP_CONFIG.ENDPOINTS.GUEST_ENTRIES, {
        guests: data.guests.map(guest => ({
          name: sanitizeText(guest.name),
          phone: guest.phone ? sanitizePhone(guest.phone) : null,
          relationship: guest.relationship,
        })),
        visit_date: data.visit_date.toISOString().split('T')[0],
        check_in_time: data.check_in_time 
          ? `${data.check_in_time.getHours().toString().padStart(2, '0')}:${data.check_in_time.getMinutes().toString().padStart(2, '0')}`
          : null,
        purpose_to_visit: sanitizeText(data.purpose_to_visit),
      });
      
      hapticService.onSuccess();
      Alert.alert('Success', 'Guest entry request submitted successfully', [
        { text: 'OK', onPress: () => navigation.goBack() },
      ]);
    } catch (err: any) {
      hapticService.onError();
      const errorDetails = errorHandler.handleError(err);
      console.error('[GuestEntry] Submit error:', err);
      console.error('[GuestEntry] Error details:', errorDetails);
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
          <Text style={styles.headerTitle}>Guest Entry Request</Text>
          <View style={styles.headerSpacer} />
        </View>
      </View>

      <ScrollView style={styles.content} contentContainerStyle={styles.contentContainer}>
        {/* Guests (up to 4) */}
        <View style={styles.section}>
          <Text style={styles.label}>Guest Name *</Text>
          <View style={styles.guestHeader}>
            <Text style={styles.guestHint}>(up to 4 guests)</Text>
            {fields.length < 4 && (
              <GradientButton style={styles.addGuestButton} onPress={addGuest}>
                <Ionicons name="add" size={20} color={theme.colors.primary} />
                <Text style={styles.addGuestText}>Add Guest</Text>
              </GradientButton>
            )}
          </View>

          {fields.map((field, index) => (
            <View key={field.id} style={styles.guestCard}>
              <View style={styles.guestCardHeader}>
                <Text style={styles.guestNumber}>Guest {index + 1}</Text>
                {fields.length > 1 && (
                  <TouchableOpacity onPress={() => removeGuest(index)}>
                    <Ionicons name="trash-outline" size={20} color={theme.colors.error} />
                  </TouchableOpacity>
                )}
              </View>

              <Controller
                control={control}
                name={`guests.${index}.name`}
                render={({ field: { onChange, onBlur, value } }) => (
                  <>
              <TextInput
                      style={[
                        styles.textInput,
                        errors.guests?.[index]?.name && styles.textInputError,
                      ]}
                placeholder="Guest Name *"
                      value={value}
                      onChangeText={onChange}
                      onBlur={onBlur}
                      accessibilityLabel={`Guest ${index + 1} name`}
                    />
                    {errors.guests?.[index]?.name && (
                      <Text style={styles.errorText}>
                        {errors.guests[index]?.name?.message}
                      </Text>
                    )}
                  </>
                )}
              />

              <View style={styles.dropdownContainer}>
                <Text style={styles.dropdownLabel}>Relationship with Student *</Text>
                <Controller
                  control={control}
                  name={`guests.${index}.relationship`}
                  render={({ field: { onChange, value } }) => (
                    <>
                <ScrollView horizontal showsHorizontalScrollIndicator={false}>
                  {relationships.map((rel) => (
                    <TouchableOpacity
                      key={rel}
                      style={[
                        styles.dropdownOption,
                              value === rel && styles.dropdownOptionSelected,
                      ]}
                            onPress={() => onChange(rel)}>
                      <Text
                        style={[
                          styles.dropdownOptionText,
                                value === rel && styles.dropdownOptionTextSelected,
                        ]}>
                        {rel}
                      </Text>
                    </TouchableOpacity>
                  ))}
                </ScrollView>
                      {errors.guests?.[index]?.relationship && (
                        <Text style={styles.errorText}>
                          {errors.guests[index]?.relationship?.message}
                        </Text>
                      )}
                    </>
                  )}
                />
              </View>

              <Controller
                control={control}
                name={`guests.${index}.phone`}
                render={({ field: { onChange, onBlur, value } }) => (
                  <>
              <TextInput
                style={[
                  styles.textInput,
                  errors.guests?.[index]?.phone && styles.textInputError,
                ]}
                placeholder="Phone (Optional)"
                    value={value || ''}
                    onChangeText={(text) => onChange(text)}
                    onBlur={onBlur}
                keyboardType="phone-pad"
                    accessibilityLabel={`Guest ${index + 1} phone`}
                  />
                    {errors.guests?.[index]?.phone && (
                      <Text style={styles.errorText}>
                        {errors.guests[index]?.phone?.message}
                      </Text>
                    )}
                  </>
                )}
              />
            </View>
          ))}
          {errors.guests && typeof errors.guests.message === 'string' && (
            <Text style={styles.errorText}>{errors.guests.message}</Text>
          )}
        </View>

        {/* Visit Date */}
        <View style={styles.section}>
          <Text style={styles.label}>Visit Date *</Text>
          <Controller
            control={control}
            name="visit_date"
            render={({ field: { value } }) => (
              <>
          <TouchableOpacity
                  style={[
                    styles.dateButton,
                    errors.visit_date && styles.dateButtonError,
                  ]}
                  onPress={() => setShowDatePicker(true)}
                  accessibilityRole="button"
                  accessibilityLabel={`Visit date: ${value.toLocaleDateString()}`}>
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
                        setValue('visit_date', selectedDate, { shouldValidate: true });
                }
              }}
              minimumDate={new Date()}
            />
          )}
                {errors.visit_date && (
                  <Text style={styles.errorText}>
                    {errors.visit_date.message}
                  </Text>
                )}
              </>
            )}
          />
        </View>

        {/* Check-in Time - Now Optional */}
        <View style={styles.section}>
          <Text style={styles.label}>Check-in Time (Optional)</Text>
          <Controller
            control={control}
            name="check_in_time"
            render={({ field: { value } }) => (
              <>
          <TouchableOpacity
                  style={styles.dateButton}
                  onPress={() => setShowCheckInTimePicker(true)}
                  accessibilityRole="button"
                  accessibilityLabel={value ? `Check-in time: ${value.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}` : 'Select check-in time'}>
            <Text style={styles.dateButtonText}>
                    {value ? value.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : 'Select time (optional)'}
            </Text>
            <Ionicons name="time-outline" size={20} color={theme.colors.primary} />
          </TouchableOpacity>
          {showCheckInTimePicker && (
            <DateTimePicker
                    value={value || new Date()}
              mode="time"
              display={Platform.OS === 'ios' ? 'spinner' : 'default'}
              onChange={(event, selectedTime) => {
                setShowCheckInTimePicker(Platform.OS === 'ios');
                if (selectedTime) {
                        setValue('check_in_time', selectedTime, { shouldValidate: true });
                }
              }}
            />
          )}
              </>
            )}
          />
        </View>

        {/* Removed: Check-out Time per user feedback */}

        {/* Purpose to Visit */}
        <View style={styles.section}>
          <Text style={styles.label}>Purpose to Visit *</Text>
          <Controller
            control={control}
            name="purpose_to_visit"
            render={({ field: { onChange, onBlur, value } }) => (
              <>
          <TextInput
                  style={[
                    styles.textArea,
                    errors.purpose_to_visit && styles.textAreaError,
                  ]}
            placeholder="Enter purpose of visit"
                  value={value}
                  onChangeText={onChange}
                  onBlur={onBlur}
            multiline
            numberOfLines={3}
            textAlignVertical="top"
                  accessibilityLabel="Purpose to visit"
                />
                {errors.purpose_to_visit && (
                  <Text style={styles.errorText}>
                    {errors.purpose_to_visit.message}
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
            onPress={() => navigation.goBack()}>
            <Text style={styles.closeButtonText}>Close</Text>
          </GradientButton>
          <GradientButton
            style={[styles.submitButton, submitting && styles.submitButtonDisabled]}
            onPress={() => {
              if (Object.keys(errors).length > 0) {
                // Show first error to user
                const firstError = Object.values(errors)[0];
                if (firstError && typeof firstError === 'object' && 'message' in firstError) {
                  Alert.alert('Validation Error', firstError.message as string);
                } else if (errors.guests && Array.isArray(errors.guests)) {
                  const guestError = errors.guests.find((e: any) => e);
                  if (guestError) {
                    const guestErrorMsg = Object.values(guestError).find((v: any) => v?.message) as any;
                    if (guestErrorMsg?.message) {
                      Alert.alert('Validation Error', guestErrorMsg.message);
                    }
                  }
                }
              }
              handleSubmit(onSubmit)();
            }}
            disabled={submitting}>
            <Text style={styles.submitButtonText}>
              {submitting ? 'Submitting...' : 'Submit'}
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
  contentContainer: {
    paddingBottom: 100,
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
    marginBottom: theme.spacing.sm,
  },
  textArea: {
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
  guestHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: 2,
    marginBottom: theme.spacing.sm,
  },
  guestHint: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textMuted,
  },
  addGuestButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.xs,
    padding: theme.spacing.sm,
    backgroundColor: theme.colors.surface,
    borderRadius: theme.borderRadius.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  addGuestText: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.primary,
    fontWeight: theme.fontWeight.medium,
  },
  guestCard: {
    backgroundColor: theme.colors.surface,
    padding: theme.spacing.md,
    borderRadius: theme.borderRadius.md,
    marginBottom: theme.spacing.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  guestCardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: theme.spacing.sm,
  },
  guestNumber: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
  },
  dropdownContainer: {
    marginBottom: theme.spacing.sm,
  },
  dropdownLabel: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textMuted,
    marginBottom: theme.spacing.xs,
  },
  dropdownOption: {
    paddingHorizontal: theme.spacing.md,
    paddingVertical: theme.spacing.sm,
    borderRadius: theme.borderRadius.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.white,
    marginRight: theme.spacing.sm,
  },
  dropdownOptionSelected: {
    backgroundColor: theme.colors.primary,
    borderColor: theme.colors.primary,
  },
  dropdownOptionText: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
    fontWeight: theme.fontWeight.medium,
  },
  dropdownOptionTextSelected: {
    color: theme.colors.white,
    fontWeight: theme.fontWeight.semibold,
  },
  actions: {
    flexDirection: 'row',
    gap: theme.spacing.md,
    marginTop: theme.spacing.lg,
    marginBottom: theme.spacing.xxl ?? 40,
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
    marginBottom: theme.spacing.sm,
  },
});
