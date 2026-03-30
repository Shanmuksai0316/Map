import React from 'react';
import { StyleSheet, Text, TextStyle } from 'react-native';
import { RadialGradientButton } from './RadialGradientButton';
import { theme } from '../theme/theme';

type GradientButtonProps = React.ComponentProps<typeof RadialGradientButton> & {
  contentColor?: string;
  labelStyle?: TextStyle;
  activeOpacity?: number;
};

const tintChild = (child: React.ReactNode, color: string): React.ReactNode => {
  if (!React.isValidElement(child)) {
    return child;
  }

  const nextProps: Record<string, any> = {};
  const childStyle = child.props.style;
  const childHasColorProp = Object.prototype.hasOwnProperty.call(child.props, 'color');

  if (child.type === Text) {
    nextProps.style = [childStyle, { color }];
  } else if (childStyle) {
    nextProps.style = [childStyle, { color }];
  }

  if (childHasColorProp) {
    nextProps.color = color;
  }

  if (child.props.children) {
    nextProps.children = React.Children.map(child.props.children, nested =>
      tintChild(nested, color),
    );
  }

  return React.cloneElement(child, nextProps);
};

export const GradientButton: React.FC<GradientButtonProps> = ({
  children,
  label,
  labelStyle,
  contentColor = theme.colors.primary,
  activeOpacity,
  style,
  ...rest
}) => {
  const tintedChildren = children
    ? React.Children.map(children, child => tintChild(child, contentColor))
    : undefined;

  const flattenedStyle = StyleSheet.flatten(style);
  const hasCustomSizing =
    Boolean(flattenedStyle?.padding) ||
    Boolean(flattenedStyle?.paddingHorizontal) ||
    Boolean(flattenedStyle?.paddingVertical) ||
    Boolean(flattenedStyle?.width) ||
    Boolean(flattenedStyle?.minWidth);
  const mergedStyle = hasCustomSizing ? [style, { minWidth: 0 }] : style;

  return (
    <RadialGradientButton
      label={label}
      labelStyle={StyleSheet.flatten([labelStyle, { color: contentColor }])}
      style={mergedStyle}
      {...rest}
    >
      {tintedChildren}
    </RadialGradientButton>
  );
};
