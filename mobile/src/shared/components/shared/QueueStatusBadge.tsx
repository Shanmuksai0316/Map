/**
 * QueueStatusBadge Component
 * 
 * Shows the count of pending offline actions as a badge
 */

import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { useOfflineQueue } from '../../hooks/useOfflineQueue';
import { colors } from '../../theme/colors';

interface QueueStatusBadgeProps {
  style?: any;
}

export const QueueStatusBadge: React.FC<QueueStatusBadgeProps> = ({ style }) => {
  const { queueCount } = useOfflineQueue();

  if (queueCount === 0) {
    return null;
  }

  return (
    <View style={[styles.container, style]}>
      <Text style={styles.text}>{queueCount > 99 ? '99+' : queueCount}</Text>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    backgroundColor: colors.error,
    borderRadius: 12,
    minWidth: 24,
    height: 24,
    paddingHorizontal: 6,
    justifyContent: 'center',
    alignItems: 'center',
  },
  text: {
    color: colors.white,
    fontSize: 12,
    fontWeight: 'bold',
  },
});

