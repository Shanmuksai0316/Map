import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { theme } from '../theme/theme';

interface StatItem {
  label: string;
  value: number | string;
  icon?: string;
  color?: string;
}

interface OverviewCardProps {
  title: string;
  stats: StatItem[];
  icon?: string;
  iconColor?: string;
}

export const OverviewCard: React.FC<OverviewCardProps> = ({
  title,
  stats,
  icon,
  iconColor = theme.colors.primary,
}) => {
  return (
    <View style={styles.container}>
      <View style={styles.header}>
        {icon && (
          <View style={[styles.iconContainer, { backgroundColor: `${iconColor}15` }]}>
            <Ionicons name={icon} size={24} color={iconColor} />
          </View>
        )}
        <Text style={styles.title}>{title}</Text>
      </View>
      <View style={styles.statsContainer}>
        {stats.map((stat, index) => (
          <View key={index} style={styles.statItem}>
            {stat.icon && (
              <Ionicons 
                name={stat.icon} 
                size={18} 
                color={stat.color || theme.colors.primary} 
                style={styles.statIcon}
              />
            )}
            <Text style={[styles.statValue, stat.color && { color: stat.color }]}>
              {stat.value}
            </Text>
            <Text style={styles.statLabel}>{stat.label}</Text>
          </View>
        ))}
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.borderRadius.lg,
    padding: theme.spacing.lg,
    marginBottom: theme.spacing.lg,
    ...theme.shadows.small,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: theme.spacing.md,
  },
  iconContainer: {
    width: 40,
    height: 40,
    borderRadius: 20,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: theme.spacing.sm,
  },
  title: {
    fontSize: theme.fontSize.lg,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
  },
  statsContainer: {
    flexDirection: 'row',
    justifyContent: 'space-around',
    paddingTop: theme.spacing.sm,
  },
  statItem: {
    alignItems: 'center',
    flex: 1,
  },
  statIcon: {
    marginBottom: theme.spacing.xs,
  },
  statValue: {
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.primary,
    marginBottom: 2,
  },
  statLabel: {
    fontSize: theme.fontSize.xs,
    color: theme.colors.textSecondary,
    fontWeight: theme.fontWeight.medium,
    textTransform: 'uppercase',
    textAlign: 'center',
  },
});

