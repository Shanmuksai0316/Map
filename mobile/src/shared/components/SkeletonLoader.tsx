import React from 'react';
import { View, StyleSheet, ViewStyle, ActivityIndicator } from 'react-native';
import { theme } from '../theme/theme';

interface SkeletonLoaderProps {
  variant?: 'card' | 'list-item' | 'form' | 'avatar' | 'text' | 'custom';
  width?: number | string;
  height?: number;
  style?: ViewStyle;
  children?: React.ReactNode;
}

export const SkeletonLoader: React.FC<SkeletonLoaderProps> = ({
  variant = 'text',
  width = '100%',
  height,
  style,
  children,
}) => {
  const getVariantConfig = () => {
    switch (variant) {
      case 'card':
        return {
          width: '100%',
          height: 120,
          borderRadius: theme.borderRadius.lg,
        };
      case 'list-item':
        return {
          width: '100%',
          height: 80,
          borderRadius: theme.borderRadius.md,
        };
      case 'form':
        return {
          width: '100%',
          height: 48,
          borderRadius: theme.borderRadius.md,
        };
      case 'avatar':
        return {
          width: 40,
          height: 40,
          borderRadius: theme.borderRadius.full,
        };
      case 'text':
        return {
          width: width === '100%' ? '100%' : (typeof width === 'number' ? width : 200),
          height: height || 16,
          borderRadius: theme.borderRadius.sm,
        };
      default:
        return {
          width: width === '100%' ? '100%' : (typeof width === 'number' ? width : 200),
          height: height || 16,
          borderRadius: theme.borderRadius.sm,
        };
    }
  };

  const config = getVariantConfig();

  // Simple skeleton implementation without gradient
  return (
    <View 
      style={[
        styles.container, 
        { 
          width: config.width, 
          height: config.height,
          borderRadius: config.borderRadius,
          backgroundColor: theme.colors.border,
        }, 
        style
      ]}>
      {children || <View style={{ flex: 1 }} />}
    </View>
  );
};

// Pre-built skeleton components for common use cases
export const SkeletonCard: React.FC<{ style?: ViewStyle }> = ({ style }) => (
  <SkeletonLoader variant="card" style={style} />
);

export const SkeletonListItem: React.FC<{ style?: ViewStyle }> = ({ style }) => (
  <SkeletonLoader variant="list-item" style={style} />
);

export const SkeletonFormInput: React.FC<{ style?: ViewStyle }> = ({ style }) => (
  <SkeletonLoader variant="form" style={style} />
);

export const SkeletonAvatar: React.FC<{ style?: ViewStyle; size?: number }> = ({
  style,
  size = 40
}) => (
  <SkeletonLoader
    variant="avatar"
    width={size}
    height={size}
    style={style}
  />
);

export const SkeletonText: React.FC<{
  width?: number | string;
  height?: number;
  style?: ViewStyle;
}> = ({ width, height, style }) => (
  <SkeletonLoader
    variant="text"
    width={width}
    height={height}
    style={style}
  />
);

// Complex skeleton for full screens
export const SkeletonList: React.FC<{
  itemCount?: number;
  style?: ViewStyle;
}> = ({ itemCount = 3, style }) => (
  <View style={[styles.listContainer, style]}>
    {Array.from({ length: itemCount }).map((_, index) => (
      <SkeletonListItem key={index} style={styles.listItem} />
    ))}
  </View>
);

export const SkeletonCardList: React.FC<{
  itemCount?: number;
  style?: ViewStyle;
}> = ({ itemCount = 3, style }) => (
  <View style={[styles.listContainer, style]}>
    {Array.from({ length: itemCount }).map((_, index) => (
      <SkeletonCard key={index} style={styles.cardItem} />
    ))}
  </View>
);

const styles = StyleSheet.create({
  container: {
    overflow: 'hidden',
  },
  listContainer: {
    gap: theme.spacing.md,
  },
  listItem: {
    marginBottom: theme.spacing.xs,
  },
  cardItem: {
    marginBottom: theme.spacing.sm,
  },
});
