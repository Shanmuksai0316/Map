import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ActivityIndicator } from 'react-native';
import { GradientButton } from './GradientButton';
import { useOfflineStore } from '../store/offline.store';

export const OfflineSyncBanner: React.FC = () => {
  const { queue, isOnline, isSyncing, syncQueue, lastSyncedAt } = useOfflineStore();

  const pendingCount = queue.filter((action) => action.status !== 'syncing').length;

  if (pendingCount === 0 && isOnline) {
    return null;
  }

  return (
    <View style={[styles.container, !isOnline && styles.containerOffline]}>
      <View style={styles.statusContainer}>
        {isSyncing ? (
          <ActivityIndicator size="small" color="#fff" style={styles.icon} />
        ) : (
          <Text style={styles.icon}>{isOnline ? '🔄' : '📶'}</Text>
        )}

        <View style={styles.textContainer}>
          <Text style={styles.title}>
            {isSyncing
              ? `Syncing ${pendingCount} action${pendingCount === 1 ? '' : 's'}...`
              : isOnline
              ? `Pending Sync: ${pendingCount}`
              : `Offline • ${pendingCount} queued`}
          </Text>

          {lastSyncedAt && (
            <Text style={styles.subtitle}>Last synced {new Date(lastSyncedAt).toLocaleTimeString()}</Text>
          )}
        </View>
      </View>

      {isOnline && !isSyncing && pendingCount > 0 && (
        <GradientButton style={styles.button} onPress={() => void syncQueue()}>
          <Text style={styles.buttonText}>Sync now</Text>
        </GradientButton>
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
    backgroundColor: '#ffc107',
  },
  containerOffline: {
    backgroundColor: '#dc3545',
  },
  statusContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  textContainer: {
    flex: 1,
  },
  icon: {
    marginRight: 12,
  },
  title: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '600',
  },
  subtitle: {
    color: 'rgba(255,255,255,0.8)',
    fontSize: 12,
    marginTop: 2,
  },
  button: {
    backgroundColor: 'rgba(255,255,255,0.2)',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 6,
    marginLeft: 12,
  },
  buttonText: {
    color: '#fff',
    fontSize: 12,
    fontWeight: '600',
    textTransform: 'uppercase',
  },
});

export default OfflineSyncBanner;

