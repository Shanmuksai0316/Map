import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { theme } from '../theme/theme';

interface BadgeIndicatorProps {
  count: number;
  maxCount?: number;
  size?: 'small' | 'medium' | 'large';
  variant?: 'primary' | 'secondary' | 'success' | 'warning' | 'error';
  style?: any;
}

export const BadgeIndicator: React.FC<BadgeIndicatorProps> = ({
  count,
  maxCount = 99,
  size = 'small',
  variant = 'primary',
  style,
}) => {
  if (count <= 0) return null;

  const displayCount = count > maxCount ? `${maxCount}+` : count.toString();

  const sizeConfig = {
    small: {
      width: 16,
      height: 16,
      fontSize: 10,
      paddingHorizontal: 4,
    },
    medium: {
      width: 20,
      height: 20,
      fontSize: 12,
      paddingHorizontal: 6,
    },
    large: {
      width: 24,
      height: 24,
      fontSize: 14,
      paddingHorizontal: 8,
    },
  };

  const variantConfig = {
    primary: theme.colors.primary,
    secondary: theme.colors.textSecondary,
    success: theme.colors.success,
    warning: theme.colors.warning,
    error: theme.colors.error,
  };

  const currentSize = sizeConfig[size];
  const backgroundColor = variantConfig[variant];

  return (
    <View style={[styles.container, style]}>
      <View
        style={[
          styles.badge,
          {
            width: currentSize.width,
            height: currentSize.height,
            borderRadius: currentSize.width / 2,
            backgroundColor,
          },
        ]}>
        <Text
          style={[
            styles.text,
            {
              fontSize: currentSize.fontSize,
              color: theme.colors.white,
            },
          ]}>
          {displayCount}
        </Text>
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    position: 'absolute',
    top: -6,
    right: -6,
    zIndex: 1,
  },
  badge: {
    justifyContent: 'center',
    alignItems: 'center',
    minWidth: 16,
    borderWidth: 2,
    borderColor: theme.colors.white,
  },
  text: {
    fontWeight: theme.fontWeight.bold,
    textAlign: 'center',
  },
});
