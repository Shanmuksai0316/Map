import React from 'react';
import { View, StyleSheet } from 'react-native';
import { SkeletonLoader, SkeletonText } from './SkeletonLoader';
import { theme } from '../theme/theme';

interface ComplaintSkeletonProps {
  count?: number;
}

export const ComplaintSkeleton: React.FC<ComplaintSkeletonProps> = ({ count = 1 }) => {
  const renderSkeletonItem = () => (
    <View key="complaint-item" style={styles.item}>
      {/* Header Row */}
      <View style={styles.headerRow}>
        {/* Category with icon */}
        <View style={styles.category}>
          <SkeletonLoader width={16} height={16} style={styles.icon} />
          <SkeletonText width="80%" height={18} />
        </View>
        {/* Status badge */}
        <SkeletonText width="25%" height={20} />
      </View>

      {/* Priority badge */}
      <View style={styles.priorityRow}>
        <SkeletonText width="20%" height={18} />
      </View>

      {/* Description */}
      <View style={styles.description}>
        <SkeletonText width="100%" height={14} style={styles.line} />
        <SkeletonText width="90%" height={14} style={styles.line} />
        <SkeletonText width="75%" height={14} style={styles.line} />
        <SkeletonText width="85%" height={14} />
      </View>

      {/* Footer */}
      <View style={styles.footer}>
        {/* Hostel info */}
        <View style={styles.footerItem}>
          <SkeletonLoader width={14} height={14} style={styles.icon} />
          <SkeletonText width="70%" height={14} />
        </View>
        {/* Date */}
        <View style={styles.footerItem}>
          <SkeletonLoader width={14} height={14} style={styles.icon} />
          <SkeletonText width="60%" height={14} />
        </View>
      </View>

      {/* Optional resolved status */}
      <View style={styles.resolvedRow}>
        <SkeletonLoader width={16} height={16} style={styles.icon} />
        <SkeletonText width="50%" height={14} />
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
    height: 160,
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
  category: {
    width: '60%',
    flexDirection: 'row',
    alignItems: 'center',
  },
  priorityRow: {
    width: '100%',
    flexDirection: 'row',
    justifyContent: 'flex-end',
    marginTop: theme.spacing.sm,
    marginBottom: theme.spacing.sm,
  },
  description: {
    width: '100%',
    marginTop: theme.spacing.sm,
  },
  line: {
    marginBottom: theme.spacing.xs,
  },
  footer: {
    width: '100%',
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: theme.spacing.md,
  },
  footerItem: {
    width: '45%',
    flexDirection: 'row',
    alignItems: 'center',
  },
  resolvedRow: {
    width: '100%',
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: theme.spacing.sm,
    marginBottom: theme.spacing.md,
  },
  icon: {
    borderRadius: 8,
    marginRight: theme.spacing.xs,
  },
});
