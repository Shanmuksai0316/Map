import React, { useState } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  TextInput,
  Switch,
  Alert,
} from 'react-native';
import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useAuthStore } from '../../store/auth.store';
import { apiService } from '../../services/api.service';
import { APP_CONFIG } from '../../config/app.config';
import { theme } from '../../theme/theme';
import { errorHandler } from '../../utils/errorHandler';
import { hapticService } from '../../services/haptic.service';
import { sanitizeText } from '../../utils/validation';
import { sickLeaveSchema, type SickLeaveFormData } from '../../validation/schemas/sick-leave.schema';

export const SickLeaveFormScreen = ({ navigation, route }: any) => {
  const { user } = useAuthStore();
  const [submitting, setSubmitting] = useState(false);

  const {
    control,
    handleSubmit,
    formState: { errors },
  } = useForm<SickLeaveFormData>({
    resolver: zodResolver(sickLeaveSchema),
    defaultValues: {
    illness: '',
    illness_details: '',
    need_medical_attention: false,
    contact_parents: false,
    },
  });

  const onSubmit = async (data: SickLeaveFormData) => {
    setSubmitting(true);
    try {
      await apiService.post(APP_CONFIG.ENDPOINTS.SICK_LEAVES, {
        title: sanitizeText(data.illness),
        illness: sanitizeText(data.illness),
        illness_details: sanitizeText(data.illness_details),
        need_medical_attention: data.need_medical_attention,
        contact_parents: data.contact_parents,
      });

      hapticService.onSuccess();
      Alert.alert('Success', 'Sick leave request submitted successfully', [
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
        <Text style={styles.headerTitle}>Sick Leave Request</Text>
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

        {/* Illness */}
        <View style={styles.section}>
          <Text style={styles.label}>Illness *</Text>
          <Controller
            control={control}
            name="illness"
            render={({ field: { onChange, onBlur, value } }) => (
              <>
          <TextInput
                  style={[
                    styles.textInput,
                    errors.illness && styles.textInputError,
                  ]}
            placeholder="Enter type of illness"
                  value={value}
                  onChangeText={onChange}
                  onBlur={onBlur}
                  accessibilityLabel="Illness"
                  accessibilityHint="Enter the type of illness"
                />
                {errors.illness && (
                  <Text style={styles.errorText}>
                    {errors.illness.message}
                  </Text>
                )}
              </>
            )}
          />
        </View>

        {/* Illness Details */}
        <View style={styles.section}>
          <Text style={styles.label}>Illness Details *</Text>
          <Controller
            control={control}
            name="illness_details"
            render={({ field: { onChange, onBlur, value } }) => (
              <>
          <TextInput
                  style={[
                    styles.textArea,
                    errors.illness_details && styles.textAreaError,
                  ]}
            placeholder="Provide detailed description of your illness"
                  value={value}
                  onChangeText={onChange}
                  onBlur={onBlur}
            multiline
            numberOfLines={4}
            textAlignVertical="top"
                  accessibilityLabel="Illness details"
                  accessibilityHint="Provide detailed description of your illness"
                />
                {errors.illness_details && (
                  <Text style={styles.errorText}>
                    {errors.illness_details.message}
                  </Text>
                )}
              </>
            )}
          />
        </View>

        {/* Need Medical Attention Toggle */}
        <View style={styles.section}>
          <View style={styles.toggleContainer}>
            <View style={styles.toggleLabelContainer}>
              <Text style={styles.toggleLabel}>Need Medical Attention</Text>
              <Controller
                control={control}
                name="need_medical_attention"
                render={({ field: { onChange, value } }) => (
              <Switch
                    value={value}
                    onValueChange={onChange}
                trackColor={{
                  false: theme.colors.border,
                  true: theme.colors.primary,
                }}
                thumbColor={theme.colors.white}
                  />
                )}
              />
            </View>
            <Text style={styles.toggleContext}>
              Do you need to see a doctor?
            </Text>
          </View>
        </View>

        {/* Contact Parents Toggle */}
        <View style={styles.section}>
          <View style={styles.toggleContainer}>
            <View style={styles.toggleLabelContainer}>
              <Text style={styles.toggleLabel}>Contact Parents</Text>
              <Controller
                control={control}
                name="contact_parents"
                render={({ field: { onChange, value } }) => (
              <Switch
                    value={value}
                    onValueChange={onChange}
                trackColor={{
                  false: theme.colors.border,
                  true: theme.colors.primary,
                }}
                thumbColor={theme.colors.white}
                  />
                )}
              />
            </View>
            <Text style={styles.toggleContext}>
              Should we inform your parents?
            </Text>
          </View>
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
  toggleContainer: {
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.borderRadius.md,
    padding: theme.spacing.md,
    backgroundColor: theme.colors.white,
  },
  toggleLabelContainer: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: theme.spacing.xs,
  },
  toggleLabel: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
  },
  toggleContext: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
    fontStyle: 'italic',
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
  errorText: {
    color: theme.colors.error,
    fontSize: theme.fontSize.xs,
    marginTop: theme.spacing.xs,
  },
});

