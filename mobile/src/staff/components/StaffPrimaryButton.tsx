import React from 'react';
import { ViewStyle, StyleProp } from 'react-native';
import { RadialGradientButton } from '../../shared/components/RadialGradientButton';
import { theme } from '../../shared/theme/theme';

export interface StaffPrimaryButtonProps {
  label: string;
  onPress: () => void;
  disabled?: boolean;
  loading?: boolean;
  style?: StyleProp<ViewStyle>;
}

export const StaffPrimaryButton: React.FC<StaffPrimaryButtonProps> = ({
  label,
  onPress,
  disabled,
  loading,
  style,
}) => {
  return (
    <RadialGradientButton
      label={label}
      onPress={onPress}
      disabled={disabled || loading}
      loading={loading}
      style={style}
      contentStyle={{
        minHeight: 48,
        paddingHorizontal: theme.spacing.xl,
      }}
    />
  );
};

