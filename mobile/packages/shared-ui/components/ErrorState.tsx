import React from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { theme } from '../theme/theme';

interface ErrorStateProps {
  error?: string | Error | null;
  title?: string;
  message?: string;
  onRetry?: () => void;
  retryLabel?: string;
}

export const ErrorState: React.FC<ErrorStateProps> = ({
  error,
  title = 'Something went wrong',
  message,
  onRetry,
  retryLabel = 'Try Again',
}) => {
  const displayMessage = message || 
    (error instanceof Error ? error.message : error) || 
    'An unexpected error occurred. Please try again.';

  return (
    <View style={styles.container}>
      <View style={styles.content}>
        <Ionicons
          name="alert-circle-outline"
          size={64}
          color={theme.colors.error}
          style={styles.icon}
        />
        <Text style={styles.title}>{title}</Text>
        <Text style={styles.message}>{displayMessage}</Text>
        {onRetry && (
          <TouchableOpacity
            style={styles.retryButton}
            onPress={onRetry}
            accessibilityLabel={retryLabel}
            accessibilityRole="button">
            <Ionicons
              name="refresh-outline"
              size={20}
              color={theme.colors.white}
              style={styles.retryIcon}
            />
            <Text style={styles.retryButtonText}>{retryLabel}</Text>
          </TouchableOpacity>
        )}
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: theme.spacing.lg,
    backgroundColor: theme.colors.background,
  },
  content: {
    alignItems: 'center',
    maxWidth: 300,
  },
  icon: {
    marginBottom: theme.spacing.md,
  },
  title: {
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
    marginBottom: theme.spacing.sm,
    textAlign: 'center',
  },
  message: {
    fontSize: theme.fontSize.md,
    color: theme.colors.textSecondary,
    textAlign: 'center',
    marginBottom: theme.spacing.lg,
    lineHeight: theme.lineHeight.relaxed * theme.fontSize.md,
  },
  retryButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: theme.colors.primary,
    paddingHorizontal: theme.spacing.lg,
    paddingVertical: theme.spacing.md,
    borderRadius: theme.borderRadius.md,
    minHeight: 44, // Accessibility requirement
    ...theme.shadows.small,
  },
  retryIcon: {
    marginRight: theme.spacing.xs,
  },
  retryButtonText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
  },
});

