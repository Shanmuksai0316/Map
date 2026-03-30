import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { theme } from '../theme/theme';

export type StatusType =
  | 'approved'
  | 'pending'
  | 'rejected'
  | 'active'
  | 'completed'
  | 'cancelled'
  | 'success'
  | 'warning'
  | 'error'
  | 'info'
  | 'high'
  | 'medium'
  | 'low'
  | 'default';

interface StatusBadgeProps {
  status: StatusType | string;
  size?: 'small' | 'medium' | 'large';
  variant?: 'filled' | 'outlined' | 'minimal';
  showIcon?: boolean;
  customConfig?: {
    icon?: string;
    color?: string;
    background?: string;
    textColor?: string;
  };
}

const defaultStatusConfig: Record<StatusType, {
  icon: string;
  color: string;
  background: string;
  textColor: string;
}> = {
  approved: {
    icon: 'checkmark-circle',
    color: theme.colors.success,
    background: 'rgba(76, 175, 80, 0.1)',
    textColor: theme.colors.success,
  },
  pending: {
    icon: 'time-outline',
    color: theme.colors.warning,
    background: 'rgba(255, 152, 0, 0.1)',
    textColor: theme.colors.warning,
  },
  rejected: {
    icon: 'close-circle',
    color: theme.colors.error,
    background: 'rgba(244, 67, 54, 0.1)',
    textColor: theme.colors.error,
  },
  active: {
    icon: 'walk',
    color: theme.colors.info,
    background: 'rgba(33, 150, 243, 0.1)',
    textColor: theme.colors.info,
  },
  completed: {
    icon: 'flag',
    color: theme.colors.textSecondary,
    background: 'rgba(158, 158, 158, 0.1)',
    textColor: theme.colors.textSecondary,
  },
  cancelled: {
    icon: 'close-circle-outline',
    color: theme.colors.textMuted,
    background: 'rgba(158, 158, 158, 0.1)',
    textColor: theme.colors.textMuted,
  },
  success: {
    icon: 'checkmark-circle',
    color: theme.colors.success,
    background: 'rgba(76, 175, 80, 0.1)',
    textColor: theme.colors.success,
  },
  warning: {
    icon: 'alert-circle-outline',
    color: theme.colors.warning,
    background: 'rgba(255, 152, 0, 0.1)',
    textColor: theme.colors.warning,
  },
  error: {
    icon: 'close-circle',
    color: theme.colors.error,
    background: 'rgba(244, 67, 54, 0.1)',
    textColor: theme.colors.error,
  },
  info: {
    icon: 'information-circle-outline',
    color: theme.colors.info,
    background: 'rgba(33, 150, 243, 0.1)',
    textColor: theme.colors.info,
  },
  high: {
    icon: 'alert-circle',
    color: theme.colors.error,
    background: 'rgba(244, 67, 54, 0.1)',
    textColor: theme.colors.error,
  },
  medium: {
    icon: 'alert-circle-outline',
    color: theme.colors.warning,
    background: 'rgba(255, 152, 0, 0.1)',
    textColor: theme.colors.warning,
  },
  low: {
    icon: 'checkmark-circle-outline',
    color: theme.colors.success,
    background: 'rgba(76, 175, 80, 0.1)',
    textColor: theme.colors.success,
  },
  default: {
    icon: 'document-text-outline',
    color: theme.colors.textSecondary,
    background: 'rgba(0, 0, 0, 0.05)',
    textColor: theme.colors.textSecondary,
  },
};

const sizeConfig = {
  small: {
    paddingHorizontal: theme.spacing.sm,
    paddingVertical: theme.spacing.xs,
    fontSize: theme.fontSize.xs,
    iconSize: 14,
  },
  medium: {
    paddingHorizontal: theme.spacing.md,
    paddingVertical: theme.spacing.sm,
    fontSize: theme.fontSize.sm,
    iconSize: 16,
  },
  large: {
    paddingHorizontal: theme.spacing.lg,
    paddingVertical: theme.spacing.md,
    fontSize: theme.fontSize.md,
    iconSize: 18,
  },
};

export const StatusBadge: React.FC<StatusBadgeProps> = ({
  status,
  size = 'medium',
  variant = 'filled',
  showIcon = true,
  customConfig,
}) => {
  const config = customConfig || defaultStatusConfig[status as StatusType] || defaultStatusConfig.default;
  const sizeStyles = sizeConfig[size];

  const getVariantStyles = () => {
    switch (variant) {
      case 'outlined':
        return {
          backgroundColor: 'transparent',
          borderWidth: 1,
          borderColor: config.color,
        };
      case 'minimal':
        return {
          backgroundColor: 'transparent',
        };
      case 'filled':
      default:
        return {
          backgroundColor: config.background,
        };
    }
  };

  const getTextColor = () => {
    if (variant === 'outlined') {
      return config.color;
    }
    if (variant === 'minimal') {
      return config.color;
    }
    return config.textColor;
  };

  return (
    <View
      style={[
        styles.badge,
        sizeStyles,
        getVariantStyles(),
      ]}>
      {showIcon && (
        <Ionicons
          name={config.icon}
          size={sizeStyles.iconSize}
          color={config.color}
          style={styles.icon}
        />
      )}
      <Text style={[styles.text, { color: getTextColor(), fontSize: sizeStyles.fontSize }]}>
        {status.toUpperCase()}
      </Text>
    </View>
  );
};

const styles = StyleSheet.create({
  badge: {
    flexDirection: 'row',
    alignItems: 'center',
    borderRadius: theme.borderRadius.md,
    alignSelf: 'flex-start',
  },
  icon: {
    marginRight: theme.spacing.xs,
  },
  text: {
    fontWeight: theme.fontWeight.semibold,
  },
});
