/**
 * useNetworkStatus Hook
 * 
 * React hook for monitoring network connectivity
 */

import { useState, useEffect } from 'react';
import NetInfo from '@react-native-community/netinfo';

export const useNetworkStatus = () => {
  const [isConnected, setIsConnected] = useState<boolean | null>(null);
  const [connectionType, setConnectionType] = useState<string>('unknown');

  useEffect(() => {
    // Fetch initial state
    NetInfo.fetch().then(state => {
      setIsConnected(state.isConnected);
      setConnectionType(state.type);
    });

    // Subscribe to network state updates
    const unsubscribe = NetInfo.addEventListener(state => {
      setIsConnected(state.isConnected);
      setConnectionType(state.type);
    });

    return () => {
      unsubscribe();
    };
  }, []);

  return {
    isConnected,
    connectionType,
    isOnline: isConnected === true,
    isOffline: isConnected === false,
  };
};

