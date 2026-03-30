import React from 'react';
import { View, StyleSheet } from 'react-native';
import { SkeletonLoader, SkeletonText } from './SkeletonLoader';
import { theme } from '../theme/theme';

interface GatePassSkeletonProps {
  count?: number;
}

export const GatePassSkeleton: React.FC<GatePassSkeletonProps> = ({ count = 1 }) => {
  const renderSkeletonItem = () => (
    <View key="gatepass-item" style={styles.item}>
      {/* Header Row */}
      <View style={styles.headerRow}>
        <SkeletonText width="60%" height={20} />
        <SkeletonText width="25%" height={20} />
      </View>

      {/* Details */}
      <View style={styles.details}>
        {/* First detail row */}
        <View style={styles.detailRow}>
          <SkeletonLoader width={16} height={16} style={styles.icon} />
          <SkeletonText width={60} height={14} style={styles.label} />
          <SkeletonText width="70%" height={14} />
        </View>

        {/* Second detail row */}
        <View style={styles.detailRow}>
          <SkeletonLoader width={16} height={16} style={styles.icon} />
          <SkeletonText width={80} height={14} style={styles.label} />
          <SkeletonText width="60%" height={14} />
        </View>

        {/* Third detail row */}
        <View style={styles.detailRow}>
          <SkeletonLoader width={16} height={16} style={styles.icon} />
          <SkeletonText width={70} height={14} style={styles.label} />
          <SkeletonText width="65%" height={14} />
        </View>

        {/* Fourth detail row */}
        <View style={styles.detailRow}>
          <SkeletonLoader width={16} height={16} style={styles.icon} />
          <SkeletonText width="55%" height={14} />
        </View>
      </View>
    </View>
  );

  return (
    <View style={styles.container}>
      {Array.from({ length: count }).map((_, index) => (
        <View key={index} style={styles.itemContainer}>
          {renderSkeletonItem()}
        </View>
      ))}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    gap: theme.spacing.md,
  },
  itemContainer: {
    marginBottom: theme.spacing.xs,
  },
  item: {
    width: '100%',
    height: 140,
    borderRadius: theme.borderRadius.lg,
    backgroundColor: theme.colors.surface,
    padding: theme.spacing.md,
    marginBottom: theme.spacing.md,
  },
  headerRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: theme.spacing.md,
    marginBottom: theme.spacing.sm,
  },
  details: {
    width: '100%',
  },
  detailRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: theme.spacing.xs,
  },
  icon: {
    borderRadius: 8,
    marginRight: theme.spacing.xs,
  },
  label: {
    marginRight: theme.spacing.xs,
  },
});
