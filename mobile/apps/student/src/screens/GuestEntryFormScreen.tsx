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
import { useForm, Controller, useFieldArray } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import Ionicons from 'react-native-vector-icons/Ionicons';
import DateTimePicker from '@react-native-community/datetimepicker';
import { useAuthStore } from '../../store/auth.store';
import { apiService } from '../../services/api.service';
import { APP_CONFIG } from '../../config/app.config';
import { theme } from '../../theme/theme';
import { errorHandler } from '../../utils/errorHandler';
import { hapticService } from '../../services/haptic.service';
import { sanitizeText, sanitizePhone, sanitizeIDNumber } from '../../utils/validation';
import { guestEntrySchema, type GuestEntryFormData } from '../../validation/schemas/guest-entry.schema';

export const GuestEntryFormScreen = ({ navigation, route }: any) => {
  const { user } = useAuthStore();
  const [showDatePicker, setShowDatePicker] = useState(false);
  const [showCheckInTimePicker, setShowCheckInTimePicker] = useState(false);
  const [showCheckOutTimePicker, setShowCheckOutTimePicker] = useState(false);
  const [submitting, setSubmitting] = useState(false);

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
        { name: '', phone: '', relationship: '', id_type: 'aadhar_card', id_number: '' },
      ],
      primary_contact_mobile: '',
      visit_date: new Date(),
      check_in_time: new Date(),
      check_out_time: new Date(),
      purpose_to_visit: '',
    },
  });

  const { fields, append, remove } = useFieldArray({
    control,
    name: 'guests',
  });

  const visitDate = watch('visit_date');
  const checkInTime = watch('check_in_time');
  const checkOutTime = watch('check_out_time');

  const addGuest = () => {
    if (fields.length >= 4) {
      Alert.alert('Limit Reached', 'Maximum 4 guests allowed');
      return;
    }
    append({ name: '', phone: '', relationship: '', id_type: 'aadhar_card', id_number: '' });
  };

  const removeGuest = (index: number) => {
    if (fields.length > 1) {
      remove(index);
    }
  };

  const onSubmit = async (data: GuestEntryFormData) => {
    setSubmitting(true);
    try {
      await apiService.post(APP_CONFIG.ENDPOINTS.GUEST_ENTRIES, {
        guests: data.guests.map(guest => ({
          ...guest,
          name: sanitizeText(guest.name),
          id_number: sanitizeIDNumber(guest.id_number),
          phone: guest.phone ? sanitizePhone(guest.phone) : '',
        })),
        primary_contact_mobile: sanitizePhone(data.primary_contact_mobile),
        visit_date: data.visit_date.toISOString().split('T')[0],
        check_in_time: `${data.check_in_time.getHours().toString().padStart(2, '0')}:${data.check_in_time.getMinutes().toString().padStart(2, '0')}`,
        check_out_time: `${data.check_out_time.getHours().toString().padStart(2, '0')}:${data.check_out_time.getMinutes().toString().padStart(2, '0')}`,
        purpose_to_visit: sanitizeText(data.purpose_to_visit),
      });

      hapticService.onSuccess();
      Alert.alert('Success', 'Guest entry request submitted successfully', [
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
        <Text style={styles.headerTitle}>Guest Entry Request</Text>
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

        {/* Guests (up to 4) */}
        <View style={styles.section}>
          <View style={styles.guestHeader}>
            <Text style={styles.label}>Guest Name * (up to 4 guests)</Text>
            {fields.length < 4 && (
              <TouchableOpacity style={styles.addGuestButton} onPress={addGuest}>
                <Ionicons name="add" size={20} color={theme.colors.primary} />
                <Text style={styles.addGuestText}>Add Guest</Text>
              </TouchableOpacity>
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
              <TextInput
                style={styles.textInput}
                placeholder="Phone (Optional)"
                    value={value || ''}
                    onChangeText={onChange}
                    onBlur={onBlur}
                keyboardType="phone-pad"
                    accessibilityLabel={`Guest ${index + 1} phone`}
                  />
                )}
              />

              <View style={styles.dropdownContainer}>
                <Text style={styles.dropdownLabel}>Guest ID Proof Type *</Text>
                <Controller
                  control={control}
                  name={`guests.${index}.id_type`}
                  render={({ field: { onChange, value } }) => (
                    <>
                <ScrollView horizontal showsHorizontalScrollIndicator={false}>
                  {idTypes.map((type) => (
                    <TouchableOpacity
                      key={type.value}
                      style={[
                        styles.dropdownOption,
                              value === type.value && styles.dropdownOptionSelected,
                      ]}
                            onPress={() => onChange(type.value as any)}>
                      <Text
                        style={[
                          styles.dropdownOptionText,
                                value === type.value && styles.dropdownOptionTextSelected,
                        ]}>
                        {type.label}
                      </Text>
                    </TouchableOpacity>
                  ))}
                </ScrollView>
                      {errors.guests?.[index]?.id_type && (
                        <Text style={styles.errorText}>
                          {errors.guests[index]?.id_type?.message}
                        </Text>
                      )}
                    </>
                  )}
                />
              </View>

              <Controller
                control={control}
                name={`guests.${index}.id_number`}
                render={({ field: { onChange, onBlur, value } }) => (
                  <>
              <TextInput
                      style={[
                        styles.textInput,
                        errors.guests?.[index]?.id_number && styles.textInputError,
                      ]}
                placeholder="Guest ID Number *"
                      value={value}
                      onChangeText={onChange}
                      onBlur={onBlur}
                      accessibilityLabel={`Guest ${index + 1} ID number`}
                    />
                    {errors.guests?.[index]?.id_number && (
                      <Text style={styles.errorText}>
                        {errors.guests[index]?.id_number?.message}
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

        {/* Primary Contact Mobile Number */}
        <View style={styles.section}>
          <Text style={styles.label}>Primary Contact Mobile Number *</Text>
          <Controller
            control={control}
            name="primary_contact_mobile"
            render={({ field: { onChange, onBlur, value } }) => (
              <>
          <TextInput
                  style={[
                    styles.textInput,
                    errors.primary_contact_mobile && styles.textInputError,
                  ]}
            placeholder="Enter primary contact mobile number"
                  value={value}
                  onChangeText={onChange}
                  onBlur={onBlur}
            keyboardType="phone-pad"
                  accessibilityLabel="Primary contact mobile number"
                />
                {errors.primary_contact_mobile && (
                  <Text style={styles.errorText}>
                    {errors.primary_contact_mobile.message}
                  </Text>
                )}
              </>
            )}
          />
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

        {/* Check-in Time */}
        <View style={styles.section}>
          <Text style={styles.label}>Check-in Time *</Text>
          <Controller
            control={control}
            name="check_in_time"
            render={({ field: { value } }) => (
              <>
          <TouchableOpacity
                  style={[
                    styles.dateButton,
                    errors.check_in_time && styles.dateButtonError,
                  ]}
                  onPress={() => setShowCheckInTimePicker(true)}
                  accessibilityRole="button"
                  accessibilityLabel={`Check-in time: ${value.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`}>
            <Text style={styles.dateButtonText}>
                    {value.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
            </Text>
            <Ionicons name="time-outline" size={20} color={theme.colors.primary} />
          </TouchableOpacity>
          {showCheckInTimePicker && (
            <DateTimePicker
                    value={value}
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
                {errors.check_in_time && (
                  <Text style={styles.errorText}>
                    {errors.check_in_time.message}
                  </Text>
                )}
              </>
            )}
          />
        </View>

        {/* Check-out Time */}
        <View style={styles.section}>
          <Text style={styles.label}>Check-out Time *</Text>
          <Controller
            control={control}
            name="check_out_time"
            render={({ field: { value } }) => (
              <>
          <TouchableOpacity
                  style={[
                    styles.dateButton,
                    errors.check_out_time && styles.dateButtonError,
                  ]}
                  onPress={() => setShowCheckOutTimePicker(true)}
                  accessibilityRole="button"
                  accessibilityLabel={`Check-out time: ${value.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`}>
            <Text style={styles.dateButtonText}>
                    {value.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
            </Text>
            <Ionicons name="time-outline" size={20} color={theme.colors.primary} />
          </TouchableOpacity>
          {showCheckOutTimePicker && (
            <DateTimePicker
                    value={value}
              mode="time"
              display={Platform.OS === 'ios' ? 'spinner' : 'default'}
              onChange={(event, selectedTime) => {
                setShowCheckOutTimePicker(Platform.OS === 'ios');
                if (selectedTime) {
                        setValue('check_out_time', selectedTime, { shouldValidate: true });
                }
              }}
            />
          )}
                {errors.check_out_time && (
                  <Text style={styles.errorText}>
                    {errors.check_out_time.message}
                  </Text>
                )}
              </>
            )}
          />
        </View>

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
              {submitting ? 'Submitting...' : 'Submit'}
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
    marginBottom: theme.spacing.sm,
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

