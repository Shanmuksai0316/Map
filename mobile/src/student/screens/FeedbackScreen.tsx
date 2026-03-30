import React, { useState } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  ScrollView,
  TextInput,
  Alert,
  ActivityIndicator,
} from 'react-native';
import { GradientButton } from '../../shared/components/GradientButton';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { theme } from '../../shared/theme/theme';
import { apiService } from '../../shared/services/api.service';
import { APP_CONFIG } from '../../shared/config/app.config';

export const FeedbackScreen = ({ navigation }: any) => {
  const insets = useSafeAreaInsets();
  const [rating, setRating] = useState<number>(0);
  const [description, setDescription] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const HEADER_ROW_HEIGHT = 52;
  const HEADER_PADDING_TOP = Math.max(insets.top, 10);
  const HEADER_PADDING_BOTTOM = 6;

  const handleSubmit = async () => {
    if (!rating) {
      Alert.alert('Error', 'Please provide a star rating');
      return;
    }

    // Details are now optional

    setSubmitting(true);
    try {
      // Submit overall feedback (no department selection)
      await apiService.post(APP_CONFIG.ENDPOINTS.STUDENT_FEEDBACK, {
        department: 'overall',
        rating,
        details: description.trim() || null, // Optional
      });

      Alert.alert(
        'Thank You!',
        'Your feedback has been submitted successfully. We appreciate your input!',
        [{ text: 'OK', onPress: () => navigation.goBack() }]
      );
    } catch (error) {
      Alert.alert('Error', 'Failed to submit feedback. Please try again.');
    } finally {
      setSubmitting(false);
    }
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
            onPress={() => (navigation?.canGoBack?.() ? navigation.goBack() : navigation.navigate('Home'))}
            accessibilityLabel="Go back">
            <Ionicons name="arrow-back" size={24} color={theme.colors.primary} />
          </TouchableOpacity>
          <Text style={styles.headerTitle}>Feedback</Text>
          <View style={styles.headerSpacer} />
        </View>
      </View>
      <ScrollView
        style={styles.content}
        contentContainerStyle={styles.contentContainer}
        keyboardShouldPersistTaps="handled">
        <Text style={styles.subtitle}>
          Help us improve by rating your overall hostel experience
        </Text>

        {/* Overall Rating with Star Selection */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Overall rating *</Text>
          <View style={styles.starContainer}>
            {[1, 2, 3, 4, 5].map((star) => (
              <TouchableOpacity
                key={star}
                onPress={() => setRating(star)}
                disabled={submitting}>
                <Ionicons
                  name={rating >= star ? 'star' : 'star-outline'}
                  size={32}
                  color={rating >= star ? '#FFD700' : theme.colors.border}
                />
              </TouchableOpacity>
            ))}
          </View>
        </View>

        {/* Description Input - Now Optional */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Details (Optional)</Text>
          <TextInput
            style={styles.descriptionInput}
            placeholder="Any additional comments or suggestions..."
            value={description}
            onChangeText={setDescription}
            multiline
            numberOfLines={4}
            textAlignVertical="top"
            maxLength={1000}
            editable={!submitting}
          />
          <Text style={styles.charCount}>
            {description.length}/1000 characters
          </Text>
        </View>

        {/* Submit Button */}
        <GradientButton
          style={[styles.submitButton, submitting && styles.submitButtonDisabled]}
          onPress={handleSubmit}
          disabled={submitting}>
          {submitting ? (
            <ActivityIndicator color={theme.colors.white} />
          ) : (
            <>
              <Ionicons name="send-outline" size={20} color={theme.colors.white} />
              <Text style={styles.submitButtonText}>Submit Feedback</Text>
            </>
          )}
        </GradientButton>
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
  headerTitle: {
    flex: 1,
    textAlign: 'center',
    fontSize: theme.fontSize.lg,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.primary,
  },
  headerSpacer: {
    width: 32,
  },
  content: {
    flex: 1,
  },
  contentContainer: {
    padding: 20,
    paddingBottom: 40,
  },
  subtitle: {
    fontSize: 16,
    color: theme.colors.textSecondary,
    textAlign: 'center',
    marginBottom: 24,
  },
  section: {
    marginBottom: 24,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.text,
    marginBottom: 12,
  },
  departmentList: {
    gap: 12,
  },
  departmentCard: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.borderRadius.lg,
    padding: 16,
    borderWidth: 2,
    borderColor: theme.colors.border,
    ...theme.shadows.small,
  },
  departmentCardSelected: {
    borderColor: theme.colors.primary,
    backgroundColor: `${theme.colors.primary}05`,
  },
  departmentHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 12,
  },
  departmentIcon: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: `${theme.colors.primary}15`,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
  },
  departmentIconSelected: {
    backgroundColor: theme.colors.primary,
  },
  departmentLabel: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.text,
    flex: 1,
  },
  departmentLabelSelected: {
    color: theme.colors.primary,
  },
  starContainer: {
    flexDirection: 'row',
    justifyContent: 'center',
    gap: 8,
  },
  descriptionInput: {
    backgroundColor: theme.colors.card,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.borderRadius.md,
    padding: 16,
    fontSize: 16,
    color: theme.colors.text,
    minHeight: 120,
  },
  charCount: {
    fontSize: 12,
    color: theme.colors.textMuted,
    textAlign: 'right',
    marginTop: 4,
  },
  submitButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 16,
    borderRadius: theme.borderRadius.lg,
    backgroundColor: '#D79F24',
    marginTop: theme.spacing.lg,
    marginBottom: theme.spacing.xl,
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
  infoContainer: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 8,
    padding: 16,
    backgroundColor: theme.colors.surface,
    borderRadius: theme.borderRadius.md,
  },
  infoText: {
    flex: 1,
    fontSize: 14,
    color: theme.colors.textSecondary,
    lineHeight: 20,
  },
});
