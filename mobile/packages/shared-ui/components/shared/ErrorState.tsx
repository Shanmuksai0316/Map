import React from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { colors } from '../../theme/colors';
import { errorHandler, ErrorDetails } from '../../utils/errorHandler';

interface ErrorStateProps {
  error: ErrorDetails | Error | any;
  onRetry?: () => void;
  title?: string;
  message?: string;
}

export const ErrorState: React.FC<ErrorStateProps> = ({
  error,
  onRetry,
  title,
  message,
}) => {
  const errorDetails = errorHandler.getErrorDetails(error);
  const displayTitle = title || 'Something went wrong';
  const displayMessage = message || errorDetails.message;

  return (
    <View style={styles.container}>
      <View style={styles.content}>
        <Ionicons
          name={errorDetails.isNetworkError ? 'cloud-offline-outline' : 'alert-circle-outline'}
          size={64}
          color={colors.error}
          style={styles.icon}
        />
        <Text style={styles.title}>{displayTitle}</Text>
        <Text style={styles.message}>{displayMessage}</Text>
        {onRetry && (
          <TouchableOpacity style={styles.retryButton} onPress={onRetry}>
            <Ionicons name="refresh-outline" size={20} color={colors.white} />
            <Text style={styles.retryButtonText}>Try Again</Text>
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
    padding: 32,
    backgroundColor: colors.background,
  },
  content: {
    alignItems: 'center',
    maxWidth: 300,
  },
  icon: {
    marginBottom: 16,
  },
  title: {
    fontSize: 20,
    fontWeight: 'bold',
    color: colors.text,
    marginBottom: 8,
    textAlign: 'center',
  },
  message: {
    fontSize: 16,
    color: colors.textSecondary,
    textAlign: 'center',
    marginBottom: 24,
    lineHeight: 22,
  },
  retryButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.primary,
    paddingHorizontal: 24,
    paddingVertical: 12,
    borderRadius: 8,
    gap: 8,
  },
  retryButtonText: {
    color: colors.white,
    fontSize: 16,
    fontWeight: '600',
  },
});

