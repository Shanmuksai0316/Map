/**
 * OfflineIndicator Component
 * 
 * Shows a banner when the app is offline or has pending offline actions
 */

import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity } from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useNetworkStatus } from '../../hooks/useNetworkStatus';
import { useOfflineQueue } from '../../hooks/useOfflineQueue';
import { colors } from '../../theme/colors';

interface OfflineIndicatorProps {
  onSyncPress?: () => void;
}

export const OfflineIndicator: React.FC<OfflineIndicatorProps> = ({ onSyncPress }) => {
  const { isOnline } = useNetworkStatus();
  const { queueCount, isSyncing, sync } = useOfflineQueue();

  const handleSyncPress = async () => {
    if (onSyncPress) {
      onSyncPress();
    } else {
      await sync();
    }
  };

  // Don't show if online and no pending actions
  if (isOnline && queueCount === 0) {
    return null;
  }

  return (
    <View style={[
      styles.container,
      !isOnline ? styles.offlineContainer : styles.syncContainer
    ]}>
      <View style={styles.content}>
        <Ionicons
          name={!isOnline ? 'cloud-offline-outline' : 'cloud-upload-outline'}
          size={20}
          color={colors.white}
          style={styles.icon}
        />
        <View style={styles.textContainer}>
          <Text style={styles.text}>
            {!isOnline ? 'You are offline' : `${queueCount} action${queueCount > 1 ? 's' : ''} pending`}
          </Text>
          {!isOnline && queueCount > 0 && (
            <Text style={styles.subText}>
              {queueCount} action{queueCount > 1 ? 's' : ''} queued for sync
            </Text>
          )}
        </View>
      </View>

      {isOnline && queueCount > 0 && (
        <TouchableOpacity
          style={styles.syncButton}
          onPress={handleSyncPress}
          disabled={isSyncing}
        >
          <Ionicons
            name={isSyncing ? 'sync-outline' : 'cloud-upload-outline'}
            size={16}
            color={colors.white}
            style={isSyncing ? styles.spinning : undefined}
          />
          <Text style={styles.syncButtonText}>
            {isSyncing ? 'Syncing...' : 'Sync Now'}
          </Text>
        </TouchableOpacity>
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderRadius: 8,
    marginHorizontal: 16,
    marginBottom: 12,
  },
  offlineContainer: {
    backgroundColor: colors.error,
  },
  syncContainer: {
    backgroundColor: colors.warning,
  },
  content: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  icon: {
    marginRight: 12,
  },
  textContainer: {
    flex: 1,
  },
  text: {
    color: colors.white,
    fontSize: 14,
    fontWeight: '600',
  },
  subText: {
    color: colors.white,
    fontSize: 12,
    marginTop: 2,
    opacity: 0.9,
  },
  syncButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: 'rgba(255, 255, 255, 0.2)',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 4,
  },
  syncButtonText: {
    color: colors.white,
    fontSize: 12,
    fontWeight: '600',
    marginLeft: 4,
  },
  spinning: {
    // Animation would be added via Animated API if needed
  },
});

