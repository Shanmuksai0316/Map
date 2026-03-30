import React, { useState } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  TextInput,
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
import { roomChangeSchema, type RoomChangeFormData } from '../../shared/validation/schemas/room-change.schema';

export const RoomChangeFormScreen = ({ navigation, route }: any) => {
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
  } = useForm<RoomChangeFormData>({
    resolver: zodResolver(roomChangeSchema),
    defaultValues: {
      description: '',
    },
  });

  // Check if user has exceeded the limit of 2 room change requests per month
  const checkMonthlyRequestLimit = async (): Promise<boolean> => {
    try {
      const response = await apiService.get<{ data: any[] }>(APP_CONFIG.ENDPOINTS.ROOM_CHANGES);
      const requests = response.data || [];
      
      // Get date 30 days ago
      const thirtyDaysAgo = new Date();
      thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
      
      // Count requests in last 30 days
      const recentRequests = requests.filter((req: any) => {
        const reqDate = new Date(req.created_at);
        return reqDate >= thirtyDaysAgo;
      });
      
      return recentRequests.length >= 2;
    } catch (err) {
      console.error('Error checking room change request limit:', err);
      return false; // Allow submission if check fails
    }
  };

  const onSubmit = async (data: RoomChangeFormData) => {
    setSubmitting(true);
    try {
      // Check monthly limit first
      const hasExceededLimit = await checkMonthlyRequestLimit();
      if (hasExceededLimit) {
        hapticService.onError();
        Alert.alert('Limit Reached', 'You can only submit 2 room change requests per month');
        setSubmitting(false);
        return;
      }

      await apiService.post(APP_CONFIG.ENDPOINTS.ROOM_CHANGES, {
        description: sanitizeText(data.description),
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
          <Text style={styles.headerTitle}>Room Change Request</Text>
          <View style={styles.headerSpacer} />
        </View>
      </View>

      <ScrollView style={styles.content}>
        {/* Reason for Room Change */}
        <View style={styles.section}>
          <Text style={styles.label}>Reason *</Text>
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

        {/* Actions */}
        <View style={styles.actions}>
          <GradientButton
            style={[styles.submitButtonFull, submitting && styles.submitButtonDisabled]}
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
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#D79F24',
    padding: 16,
    borderRadius: theme.borderRadius.lg,
    ...theme.shadows.medium,
  },
  submitButtonFull: {
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
