import React from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { theme } from '../theme/theme';

interface ScreenHeaderProps {
  title: string;
  subtitle?: string;
  onBack?: () => void;
  backLabel?: string;
  actions?: React.ReactNode;
  backgroundColor?: string;
  textColor?: string;
  showBackButton?: boolean;
}

export const ScreenHeader: React.FC<ScreenHeaderProps> = ({
  title,
  subtitle,
  onBack,
  backLabel = 'Back',
  actions,
  backgroundColor = theme.colors.primary,
  textColor = theme.colors.white,
  showBackButton = true,
}) => {
  return (
    <View style={[styles.container, { backgroundColor }]}>
      <View style={styles.content}>
        {showBackButton && onBack && (
          <TouchableOpacity
            style={styles.backButton}
            onPress={onBack}
            accessibilityLabel={`Go back, ${backLabel}`}>
            <Ionicons name="arrow-back" size={20} color={textColor} />
            <Text style={[styles.backButtonText, { color: textColor }]}>
              {backLabel}
            </Text>
          </TouchableOpacity>
        )}

        <View style={styles.titleContainer}>
          <Text style={[styles.title, { color: textColor }]}>{title}</Text>
          {subtitle && (
            <Text style={[styles.subtitle, { color: textColor }]}>
              {subtitle}
            </Text>
          )}
        </View>

        {actions && <View style={styles.actions}>{actions}</View>}
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    paddingTop: theme.spacing.xl * 2,
    paddingBottom: theme.spacing.lg,
  },
  content: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: theme.spacing.lg,
  },
  backButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.xs,
    padding: theme.spacing.sm,
    marginRight: theme.spacing.md,
  },
  backButtonText: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
  },
  titleContainer: {
    flex: 1,
    justifyContent: 'center',
  },
  title: {
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
    textAlign: 'center',
  },
  subtitle: {
    fontSize: theme.fontSize.sm,
    fontWeight: theme.fontWeight.regular,
    textAlign: 'center',
    marginTop: theme.spacing.xs,
    opacity: 0.9,
  },
  actions: {
    marginLeft: theme.spacing.md,
  },
});
