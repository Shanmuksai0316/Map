import React from 'react';
import { View, Text, StyleSheet, ViewStyle } from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { RadialGradientButton } from './RadialGradientButton';
import { theme } from '../theme/theme';
import { hapticService } from '../services/haptic.service';

export type EmptyStateVariant =
  | 'no-data'
  | 'no-results'
  | 'no-notifications'
  | 'no-messages'
  | 'no-favorites'
  | 'no-history'
  | 'error'
  | 'offline'
  | 'custom';

interface EmptyStateProps {
  variant?: EmptyStateVariant;
  title: string;
  subtitle?: string;
  icon?: string;
  iconSize?: number;
  iconColor?: string;
  actionLabel?: string;
  onActionPress?: () => void;
  actionIcon?: string;
  secondaryActionLabel?: string;
  onSecondaryActionPress?: () => void;
  style?: ViewStyle;
  showCard?: boolean;
}

const variantConfig: Record<EmptyStateVariant, {
  defaultIcon: string;
  defaultIconColor: string;
  defaultTitle: string;
}> = {
  'no-data': {
    defaultIcon: 'document-text-outline',
    defaultIconColor: theme.colors.textSecondary,
    defaultTitle: 'No Data',
  },
  'no-results': {
    defaultIcon: 'search-outline',
    defaultIconColor: theme.colors.textSecondary,
    defaultTitle: 'No Results',
  },
  'no-notifications': {
    defaultIcon: 'notifications-off-outline',
    defaultIconColor: theme.colors.textSecondary,
    defaultTitle: 'No Notifications',
  },
  'no-messages': {
    defaultIcon: 'chatbubble-ellipses-outline',
    defaultIconColor: theme.colors.textSecondary,
    defaultTitle: 'No Messages',
  },
  'no-favorites': {
    defaultIcon: 'heart-outline',
    defaultIconColor: theme.colors.textSecondary,
    defaultTitle: 'No Favorites',
  },
  'no-history': {
    defaultIcon: 'time-outline',
    defaultIconColor: theme.colors.textSecondary,
    defaultTitle: 'No History',
  },
  'error': {
    defaultIcon: 'alert-circle-outline',
    defaultIconColor: theme.colors.error,
    defaultTitle: 'Something went wrong',
  },
  'offline': {
    defaultIcon: 'cloud-offline-outline',
    defaultIconColor: theme.colors.warning,
    defaultTitle: 'No Internet Connection',
  },
  'custom': {
    defaultIcon: 'ellipse-outline',
    defaultIconColor: theme.colors.textSecondary,
    defaultTitle: '',
  },
};

export const EmptyState: React.FC<EmptyStateProps> = ({
  variant = 'custom',
  title,
  subtitle,
  icon,
  iconSize = 64,
  iconColor,
  actionLabel,
  onActionPress,
  actionIcon = 'add',
  secondaryActionLabel,
  onSecondaryActionPress,
  style,
  showCard = true,
}) => {
  const config = variantConfig[variant];
  const displayIcon = icon || config.defaultIcon;
  const displayIconColor = iconColor || config.defaultIconColor;

  const renderContent = () => (
    <View style={styles.content}>
      <Ionicons
        name={displayIcon}
        size={iconSize}
        color={displayIconColor}
        style={styles.icon}
      />

      <Text style={styles.title}>{title}</Text>

      {subtitle && (
        <Text style={styles.subtitle}>{subtitle}</Text>
      )}

      {/* Primary Action */}
      {actionLabel && onActionPress && (
        <RadialGradientButton
          style={styles.primaryButton}
          contentStyle={styles.primaryButtonContent}
          onPress={() => {
            hapticService.onButtonPress();
            onActionPress();
          }}>
          <Ionicons name={actionIcon} size={18} color={theme.colors.primary} />
          <Text style={styles.primaryButtonText}>{actionLabel}</Text>
        </RadialGradientButton>
      )}

      {/* Secondary Action */}
      {secondaryActionLabel && onSecondaryActionPress && (
        <RadialGradientButton
          style={styles.secondaryButton}
          contentStyle={styles.secondaryButtonContent}
          onPress={() => {
            hapticService.onButtonPress();
            onSecondaryActionPress();
          }}>
          <Text style={styles.secondaryButtonText}>{secondaryActionLabel}</Text>
        </RadialGradientButton>
      )}
    </View>
  );

  if (showCard) {
    return (
      <View style={[styles.container, style]}>
        {renderContent()}
      </View>
    );
  }

  return renderContent();
};

const styles = StyleSheet.create({
  container: {
    margin: theme.spacing.md,
    borderRadius: theme.borderRadius.lg,
    backgroundColor: theme.colors.card,
    ...theme.shadows.medium,
  },
  content: {
    alignItems: 'center',
    paddingVertical: theme.spacing.xxl,
    paddingHorizontal: theme.spacing.lg,
  },
  icon: {
    marginBottom: theme.spacing.lg,
  },
  title: {
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
    textAlign: 'center',
    marginBottom: theme.spacing.sm,
  },
  subtitle: {
    fontSize: theme.fontSize.md,
    color: theme.colors.textSecondary,
    textAlign: 'center',
    lineHeight: 20,
    marginBottom: theme.spacing.lg,
  },
  primaryButton: {
    marginBottom: theme.spacing.sm,
  },
  primaryButtonContent: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.xs,
    minHeight: 44,
    paddingHorizontal: theme.spacing.xl,
  },
  primaryButtonText: {
    color: theme.colors.primary,
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
  },
  secondaryButton: {
    marginTop: theme.spacing.xs,
  },
  secondaryButtonContent: {
    minHeight: 40,
    paddingHorizontal: theme.spacing.lg,
  },
  secondaryButtonText: {
    color: theme.colors.primary,
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.medium,
  },
});
