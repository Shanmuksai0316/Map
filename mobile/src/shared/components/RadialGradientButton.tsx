import React from 'react';
import {
  ActivityIndicator,
  Pressable,
  PressableProps,
  StyleSheet,
  Text,
  TextStyle,
  View,
  ViewStyle,
} from 'react-native';
import { Svg, Defs, RadialGradient, Rect, Stop } from 'react-native-svg';
import { theme } from '../theme/theme';

export const RADIAL_GRADIENT_COLORS = ['#F6C32E', '#F0B90B', '#D99E00'] as const;
export const RADIAL_GRADIENT_ICON_COLOR = RADIAL_GRADIENT_COLORS[1];

interface RadialGradientButtonProps extends PressableProps {
  label?: string;
  labelStyle?: TextStyle;
  contentStyle?: ViewStyle;
  loading?: boolean;
  indicatorColor?: string;
  children?: React.ReactNode;
}

export const RadialGradientButton: React.FC<RadialGradientButtonProps> = ({
  label,
  labelStyle,
  style,
  contentStyle,
  disabled,
  loading = false,
  indicatorColor,
  children,
  ...rest
}) => {
  const resolvedLabel = label ?? '';

  return (
    <Pressable
      style={({ pressed }) => [
        styles.button,
        style,
        pressed && styles.pressed,
        disabled && styles.disabled,
      ]}
      disabled={disabled}
      {...rest}>
      <View style={[StyleSheet.absoluteFill, styles.gradientContainer]}>
        <Svg style={StyleSheet.absoluteFill}>
          <Defs>
            <RadialGradient
              id="staffButtonGradient"
              cx="30%"
              cy="30%"
              rx="70%"
              ry="70%">
              <Stop offset="0%" stopColor={RADIAL_GRADIENT_COLORS[0]} />
              <Stop offset="50%" stopColor={RADIAL_GRADIENT_COLORS[1]} />
              <Stop offset="100%" stopColor={RADIAL_GRADIENT_COLORS[2]} />
            </RadialGradient>
          </Defs>
          <Rect width="100%" height="100%" fill="url(#staffButtonGradient)" />
        </Svg>
      </View>
      <View style={[styles.content, contentStyle]}>
        {loading ? (
          <ActivityIndicator
            color={indicatorColor ?? theme.colors.primary}
            size="small"
          />
        ) : children ? (
          children
        ) : (
          <Text style={[styles.label, labelStyle]}>{resolvedLabel}</Text>
        )}
      </View>
    </Pressable>
  );
};

const styles = StyleSheet.create({
  button: {
    borderRadius: theme.borderRadius.lg,
    overflow: 'hidden',
    minWidth: 200,
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: theme.spacing.md,
    paddingHorizontal: theme.spacing.xxl,
    ...theme.shadows.medium,
  },
  gradientContainer: {
    opacity: 0.95,
  },
  content: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: theme.spacing.sm,
  },
  label: {
    color: theme.colors.primary,
    fontSize: theme.fontSize.lg,
    fontWeight: theme.fontWeight.semibold,
  },
  pressed: {
    transform: [{ scale: 0.98 }],
  },
  disabled: {
    opacity: 0.6,
  },
});
