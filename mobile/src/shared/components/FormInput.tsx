import React, { useState } from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  StyleSheet,
  TextInputProps,
  ViewStyle,
  TextStyle,
} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { theme } from '../theme/theme';

interface FormInputProps extends TextInputProps {
  label: string;
  error?: string;
  required?: boolean;
  leftIcon?: string;
  rightIcon?: string;
  onRightIconPress?: () => void;
  containerStyle?: ViewStyle;
  labelStyle?: TextStyle;
  inputStyle?: TextStyle;
  variant?: 'default' | 'floating' | 'outlined';
  size?: 'small' | 'medium' | 'large';
}

export const FormInput: React.FC<FormInputProps> = ({
  label,
  error,
  required = false,
  leftIcon,
  rightIcon,
  onRightIconPress,
  containerStyle,
  labelStyle,
  inputStyle,
  variant = 'outlined',
  size = 'medium',
  value,
  placeholder,
  ...props
}) => {
  const [isFocused, setIsFocused] = useState(false);

  const sizeConfig = {
    small: {
      height: 40,
      fontSize: theme.fontSize.sm,
      paddingHorizontal: theme.spacing.sm,
      paddingVertical: theme.spacing.xs,
    },
    medium: {
      height: 48,
      fontSize: theme.fontSize.md,
      paddingHorizontal: theme.spacing.md,
      paddingVertical: theme.spacing.sm,
    },
    large: {
      height: 56,
      fontSize: theme.fontSize.lg,
      paddingHorizontal: theme.spacing.lg,
      paddingVertical: theme.spacing.md,
    },
  };

  const currentSize = sizeConfig[size];

  const getBorderColor = () => {
    if (error) return theme.colors.error;
    if (isFocused) return theme.colors.primary;
    return theme.colors.border;
  };

  const getBackgroundColor = () => {
    if (variant === 'floating') return 'transparent';
    return theme.colors.white;
  };

  const getLabelPosition = () => {
    if (variant === 'floating') {
      return isFocused || (value && value.length > 0) ? 'floating' : 'normal';
    }
    return 'normal';
  };

  const labelPosition = getLabelPosition();

  const renderLeftIcon = () => {
    if (!leftIcon) return null;

    return (
      <View style={styles.leftIconContainer}>
        <Ionicons
          name={leftIcon}
          size={20}
          color={isFocused ? theme.colors.primary : theme.colors.textMuted}
        />
      </View>
    );
  };

  const renderRightIcon = () => {
    if (!rightIcon) return null;

    return (
      <TouchableOpacity
        style={styles.rightIconContainer}
        onPress={onRightIconPress}
        disabled={!onRightIconPress}>
        <Ionicons
          name={rightIcon}
          size={20}
          color={isFocused ? theme.colors.primary : theme.colors.textMuted}
        />
      </TouchableOpacity>
    );
  };

  return (
    <View style={[styles.container, containerStyle]}>
      {/* Label */}
      {variant !== 'floating' && (
        <Text style={[styles.label, labelStyle]}>
          {label}
          {required && <Text style={styles.required}> *</Text>}
        </Text>
      )}

      {/* Floating Label */}
      {variant === 'floating' && (
        <Text
          style={[
            styles.floatingLabel,
            labelStyle,
            {
              color: labelPosition === 'floating'
                ? theme.colors.primary
                : theme.colors.textMuted,
              fontSize: labelPosition === 'floating'
                ? theme.fontSize.sm
                : theme.fontSize.md,
              top: labelPosition === 'floating' ? -8 : 16,
            },
          ]}>
          {label}
          {required && <Text style={styles.required}> *</Text>}
        </Text>
      )}

      {/* Input Container */}
      <View
        style={[
          styles.inputContainer,
          {
            borderColor: getBorderColor(),
            backgroundColor: getBackgroundColor(),
            height: currentSize.height,
            borderRadius: variant === 'outlined' ? theme.borderRadius.md : 0,
            borderWidth: variant === 'outlined' ? 1 : 0,
          },
        ]}>

        {renderLeftIcon()}

        <TextInput
          style={[
            styles.input,
            currentSize,
            inputStyle,
            {
              color: theme.colors.text,
              fontSize: currentSize.fontSize,
              paddingHorizontal: leftIcon
                ? currentSize.paddingHorizontal + 32
                : currentSize.paddingHorizontal,
              paddingVertical: currentSize.paddingVertical,
            },
          ]}
          value={value}
          placeholder={variant === 'floating' ? undefined : placeholder}
          placeholderTextColor={theme.colors.textMuted}
          onFocus={(e) => {
            setIsFocused(true);
            props.onFocus?.(e);
          }}
          onBlur={(e) => {
            setIsFocused(false);
            props.onBlur?.(e);
          }}
          {...props}
        />

        {renderRightIcon()}
      </View>

      {/* Error Text */}
      {error && <Text style={styles.errorText}>{error}</Text>}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    marginBottom: theme.spacing.lg,
  },
  label: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
    marginBottom: theme.spacing.sm,
  },
  floatingLabel: {
    position: 'absolute',
    left: 16,
    backgroundColor: theme.colors.white,
    paddingHorizontal: theme.spacing.xs,
    zIndex: 1,
  },
  required: {
    color: theme.colors.error,
  },
  inputContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    position: 'relative',
  },
  input: {
    flex: 1,
    fontWeight: theme.fontWeight.regular,
  },
  leftIconContainer: {
    position: 'absolute',
    left: 12,
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 1,
  },
  rightIconContainer: {
    position: 'absolute',
    right: 12,
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 1,
  },
  errorText: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.error,
    marginTop: theme.spacing.xs,
  },
});
