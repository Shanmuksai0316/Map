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
import { GradientButton } from '../../shared/components/GradientButton';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useAuthStore } from '../../shared/store/auth.store';
import { apiService } from '../../shared/services/api.service';
import { APP_CONFIG } from '../../shared/config/app.config';
import { theme } from '../../shared/theme/theme';
import { errorHandler } from '../../shared/utils/errorHandler';
import { hapticService } from '../../shared/services/haptic.service';
import { sanitizeText } from '../../shared/utils/validation';
import { sickLeaveSchema, type SickLeaveFormData } from '../../shared/validation/schemas/sick-leave.schema';

export const SickLeaveFormScreen = ({ navigation, route }: any) => {
  const insets = useSafeAreaInsets();
  const { user } = useAuthStore();
  const [submitting, setSubmitting] = useState(false);

  const HEADER_ROW_HEIGHT = 52;
  const HEADER_PADDING_TOP = Math.max(insets.top, 10);
  const HEADER_PADDING_BOTTOM = 6;

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
          <Text style={styles.headerTitle}>Sick Leave Request</Text>
          <View style={styles.headerSpacer} />
        </View>
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
          <GradientButton
            style={styles.closeButtonBottom}
            onPress={() => navigation.goBack()}>
            <Text style={styles.closeButtonText}>Close</Text>
          </GradientButton>
          <GradientButton
            style={[styles.submitButton, submitting && styles.submitButtonDisabled]}
            onPress={handleSubmit(onSubmit)}
            disabled={submitting}>
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
  errorText: {
    color: theme.colors.error,
    fontSize: theme.fontSize.xs,
    marginTop: theme.spacing.xs,
  },
});
