/**
 * Root Navigator - Main navigation orchestrator
 * Uses conditional imports to only load student OR staff navigator
 * This prevents loading 75+ screens at once, which was causing crashes
 */

import React, { useEffect, useState } from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { useAuthStore, type AuthState } from '../store/auth.store';
import { StorageService } from '../services/storage.service';
import { LoginScreen } from '../auth/LoginScreen';
import { TenantSelectionScreen } from '../auth/TenantSelectionScreen';
import { StudentSplashScreen } from '../auth/StudentSplashScreen';
import { StaffSplashScreen } from '../auth/StaffSplashScreen';
import { SplashScreen } from '../components/SplashScreen';
import { isStudentApp } from '../config/app.config';
import { UnsupportedRoleScreen } from '../auth/UnsupportedRoleScreen';
import { pushNotificationService } from '../services/push-notification.service';
import { rootNavigationRef } from './navigation-ref';

const Stack = createNativeStackNavigator();

// Conditionally import navigators based on app type
// This prevents loading all screens at once
// Using require() inside the component to ensure it's truly lazy

export const RootNavigator = () => {
  // #region agent log
  fetch('http://127.0.0.1:7242/ingest/26316810-a694-48b7-8f83-116907028f19', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Debug-Session-Id': '0a1483',
    },
    body: JSON.stringify({
      sessionId: '0a1483',
      runId: 'pre-fix',
      hypothesisId: 'H_root_mount',
      location: 'RootNavigator.tsx:27',
      message: 'RootNavigator mounted before useAuthStore call',
      data: {},
      timestamp: Date.now(),
    }),
  }).catch(() => {});
  // #endregion
  const fallbackAuthState: AuthState = {
    user: null,
    token: null,
    isAuthenticated: false,
    isLoading: false,
    error: null,
    selectedTenant: null,
    roleValidation: {
      mismatch: false,
      expected: isStudentApp() ? 'student' : 'staff',
      actual: null,
    },
    featureFlags: null,
    sendOTP: async () => ({ success: false, message: 'Unavailable' }),
    verifyOTP: async () => {},
    logout: async () => {},
    loadStoredAuth: () => {},
    clearError: () => {},
    validateRole: () => true,
    validateRoleScoping: () => ({ valid: true }),
    clearRoleValidation: () => {},
    loadFeatureFlags: async () => {},
    isFeatureEnabled: () => false,
    setSelectedTenant: () => {},
    setDefaultTestTenant: () => null,
    clearSelectedTenant: () => {},
    autoDetectTenant: async () => {
      throw new Error('Tenant detection unavailable');
    },
  };

  const authStoreHook = typeof useAuthStore === 'function' ? useAuthStore : () => fallbackAuthState;

  const {
    isAuthenticated,
    loadStoredAuth,
    selectedTenant,
    roleValidation,
    clearRoleValidation,
    logout,
    setSelectedTenant,
  } = authStoreHook();

  const [isInitializing, setIsInitializing] = useState(true);
  const [AppNavigator, setAppNavigator] = useState<any>(null);

  useEffect(() => {
    let cleanupFn: (() => void) | undefined;
    let timeoutId: NodeJS.Timeout | undefined;

    const initializeApp = async () => {
      // Set timeout to prevent infinite loading (10 seconds)
      timeoutId = setTimeout(() => {
        console.warn('[RootNavigator] Initialization timeout - forcing completion after 10 seconds');
        setIsInitializing(false);
      }, 10000);

      try {
        loadStoredAuth();

        // Load the appropriate navigator based on app type
        // Use require() inside useEffect to ensure true lazy loading
        let Navigator;
        if (isStudentApp()) {
          Navigator = require('./student-navigator').StudentNavigator;
        } else {
          Navigator = require('./staff-navigator').StaffNavigator;
        }
        setAppNavigator(() => Navigator);
      } catch (error) {
        console.error('[RootNavigator] App initialization error:', error);
        // Even on error, set isInitializing to false to prevent infinite loading
        setIsInitializing(false);
      } finally {
        // Clear timeout if initialization completes normally
        if (timeoutId) {
          clearTimeout(timeoutId);
        }
        setIsInitializing(false);
      }
    };

    initializeApp();

    return () => {
      if (timeoutId) {
        clearTimeout(timeoutId);
      }
      if (cleanupFn) {
        cleanupFn();
      }
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    if (roleValidation.mismatch) {
      StorageService.clear();
    }
  }, [roleValidation.mismatch]);

  useEffect(() => {
    if (isAuthenticated && selectedTenant) {
      pushNotificationService.start().catch((err) => {
        console.warn('[RootNavigator] Failed to start push notifications', err);
      });
    }
  }, [isAuthenticated, selectedTenant]);

  // Show splash screen while initializing
  if (isInitializing) {
    return <SplashScreen />;
  }

  if (roleValidation.mismatch) {
    return (
      <UnsupportedRoleScreen
        userRole={roleValidation.actual ?? 'unknown'}
        onLogout={async () => {
          clearRoleValidation();
          await logout();
        }}
      />
    );
  }

  // Show tenant selection if not authenticated or no tenant selected
  if (!isAuthenticated || !selectedTenant) {
    const isStudent = isStudentApp();
    // Register both Login and TenantSelection so navigate('Login') from splash always works
    const initialRoute =
      isStudent
        ? 'StudentSplash'
        : isAuthenticated
          ? 'TenantSelection'
          : 'StaffSplash';
    return (
      <NavigationContainer ref={rootNavigationRef}>
        <Stack.Navigator
          initialRouteName={initialRoute}
          screenOptions={{
            headerShown: false,
          }}>
          {isStudent && (
            <Stack.Screen name="StudentSplash" component={StudentSplashScreen} />
          )}
          {!isStudent && (
            <Stack.Screen name="StaffSplash" component={StaffSplashScreen} />
          )}
          <Stack.Screen name="Login" component={LoginScreen} />
          <Stack.Screen name="TenantSelection">
            {() => (
              <TenantSelectionScreen
                onTenantSelected={(tenant) => {
                  setSelectedTenant(tenant);
                }}
              />
            )}
          </Stack.Screen>
        </Stack.Navigator>
      </NavigationContainer>
    );
  }

  // Render the appropriate app navigator
  if (!AppNavigator) {
    console.warn('[RootNavigator] AppNavigator not loaded yet, showing splash screen');
    return <SplashScreen />;
  }

  return (
    <NavigationContainer ref={rootNavigationRef}>
      <AppNavigator />
    </NavigationContainer>
  );
};
