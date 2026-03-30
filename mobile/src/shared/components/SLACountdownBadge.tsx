import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet } from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { theme } from '../theme/theme';
import { calculateSLATimeRemaining, SLATimeRemaining, getSLAConfig } from '../utils/sla.util';

interface SLACountdownBadgeProps {
  createdAt: string;
  status: string;
  category: string;
  showIcon?: boolean;
  size?: 'small' | 'medium' | 'large';
}

export const SLACountdownBadge: React.FC<SLACountdownBadgeProps> = ({
  createdAt,
  status,
  category,
  showIcon = true,
  size = 'medium',
}) => {
  const [slaTime, setSlaTime] = useState<SLATimeRemaining | null>(null);

  useEffect(() => {
    const slaConfig = getSLAConfig(category);
    const calculated = calculateSLATimeRemaining(createdAt, status, slaConfig.hours);
    setSlaTime(calculated);

    // Update every minute
    const interval = setInterval(() => {
      const updated = calculateSLATimeRemaining(createdAt, status, slaConfig.hours);
      setSlaTime(updated);
    }, 60000); // Update every minute

    return () => clearInterval(interval);
  }, [createdAt, status, category]);

  if (!slaTime) {
    return null;
  }

  const isBreached = slaTime.isBreached;
  const isWarning = slaTime.percentageRemaining <= 25 && !isBreached;

  const badgeStyle = [
    styles.badge,
    size === 'small' && styles.badgeSmall,
    size === 'large' && styles.badgeLarge,
    isBreached && styles.badgeBreached,
    isWarning && styles.badgeWarning,
  ];

  const textStyle = [
    styles.text,
    size === 'small' && styles.textSmall,
    size === 'large' && styles.textLarge,
    isBreached && styles.textBreached,
  ];

  return (
    <View style={badgeStyle}>
      {showIcon && (
        <Ionicons
          name={isBreached ? 'alert-circle' : isWarning ? 'time-outline' : 'checkmark-circle-outline'}
          size={size === 'small' ? 12 : size === 'large' ? 18 : 14}
          color={isBreached ? theme.colors.error : isWarning ? theme.colors.warning : theme.colors.success}
          style={styles.icon}
        />
      )}
      <Text style={textStyle}>{slaTime.formattedTime}</Text>
    </View>
  );
};

const styles = StyleSheet.create({
  badge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 12,
    backgroundColor: theme.colors.success + '20',
    borderWidth: 1,
    borderColor: theme.colors.success,
  },
  badgeSmall: {
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 8,
  },
  badgeLarge: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 16,
  },
  badgeWarning: {
    backgroundColor: theme.colors.warning + '20',
    borderColor: theme.colors.warning,
  },
  badgeBreached: {
    backgroundColor: theme.colors.error + '20',
    borderColor: theme.colors.error,
  },
  icon: {
    marginRight: 4,
  },
  text: {
    fontSize: 12,
    fontWeight: '600',
    color: theme.colors.success,
  },
  textSmall: {
    fontSize: 10,
  },
  textLarge: {
    fontSize: 14,
  },
  textBreached: {
    color: theme.colors.error,
  },
});

