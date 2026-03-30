import React from 'react';
import { View, TouchableOpacity, StyleSheet, ViewStyle } from 'react-native';
import { theme } from '../theme/theme';

export type CardVariant = 'default' | 'elevated' | 'outlined' | 'interactive';

interface CardProps {
  children: React.ReactNode;
  variant?: CardVariant;
  onPress?: () => void;
  style?: ViewStyle;
  padding?: 'none' | 'small' | 'medium' | 'large';
  margin?: 'none' | 'small' | 'medium' | 'large';
  shadow?: 'none' | 'small' | 'medium' | 'large';
  borderRadius?: 'none' | 'small' | 'medium' | 'large' | 'full';
  backgroundColor?: string;
  testID?: string;
}

const variantConfig: Record<CardVariant, {
  backgroundColor: string;
  shadow: keyof typeof theme.shadows;
  borderWidth?: number;
  borderColor?: string;
}> = {
  default: {
    backgroundColor: theme.colors.card,
    shadow: 'small',
  },
  elevated: {
    backgroundColor: theme.colors.card,
    shadow: 'large',
  },
  outlined: {
    backgroundColor: theme.colors.card,
    shadow: 'none',
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  interactive: {
    backgroundColor: theme.colors.card,
    shadow: 'medium',
  },
};

const paddingConfig = {
  none: 0,
  small: theme.spacing.sm,
  medium: theme.spacing.md,
  large: theme.spacing.lg,
};

const marginConfig = {
  none: 0,
  small: theme.spacing.sm,
  medium: theme.spacing.md,
  large: theme.spacing.lg,
};

const borderRadiusConfig = {
  none: 0,
  small: theme.borderRadius.sm,
  medium: theme.borderRadius.md,
  large: theme.borderRadius.lg,
  full: theme.borderRadius.full,
};

export const Card: React.FC<CardProps> = ({
  children,
  variant = 'default',
  onPress,
  style,
  padding = 'medium',
  margin = 'none',
  shadow,
  borderRadius = 'medium',
  backgroundColor,
  testID,
}) => {
  const variantStyles = variantConfig[variant];
  const appliedShadow = shadow ? theme.shadows[shadow] : variantStyles.shadow;
  const appliedBackgroundColor = backgroundColor || variantStyles.backgroundColor;

  const cardStyle: ViewStyle = {
    backgroundColor: appliedBackgroundColor,
    padding: paddingConfig[padding],
    margin: marginConfig[margin],
    borderRadius: borderRadiusConfig[borderRadius],
    borderWidth: variantStyles.borderWidth || 0,
    borderColor: variantStyles.borderColor,
    ...appliedShadow,
  };

  if (onPress) {
    return (
      <TouchableOpacity
        style={[cardStyle, style]}
        onPress={onPress}
        activeOpacity={0.8}
        testID={testID}
        accessibilityRole="button">
        {children}
      </TouchableOpacity>
    );
  }

  return (
    <View style={[cardStyle, style]} testID={testID}>
      {children}
    </View>
  );
};

// Convenience components for common use cases
export const CardHeader: React.FC<{
  children: React.ReactNode;
  style?: ViewStyle;
}> = ({ children, style }) => (
  <View style={[styles.header, style]}>{children}</View>
);

export const CardContent: React.FC<{
  children: React.ReactNode;
  style?: ViewStyle;
}> = ({ children, style }) => (
  <View style={[styles.content, style]}>{children}</View>
);

export const CardFooter: React.FC<{
  children: React.ReactNode;
  style?: ViewStyle;
}> = ({ children, style }) => (
  <View style={[styles.footer, style]}>{children}</View>
);

export const CardActions: React.FC<{
  children: React.ReactNode;
  style?: ViewStyle;
}> = ({ children, style }) => (
  <View style={[styles.actions, style]}>{children}</View>
);

const styles = StyleSheet.create({
  header: {
    marginBottom: theme.spacing.sm,
  },
  content: {
    flex: 1,
  },
  footer: {
    marginTop: theme.spacing.sm,
    paddingTop: theme.spacing.sm,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: theme.colors.divider,
  },
  actions: {
    marginTop: theme.spacing.sm,
    flexDirection: 'row',
    justifyContent: 'flex-end',
    gap: theme.spacing.sm,
  },
});
