/**
 * Lazy Screen Wrapper Component
 * Provides loading state for lazy-loaded screens
 */
import React, { Suspense } from 'react';
import { View, ActivityIndicator, StyleSheet } from 'react-native';
import { theme } from '../theme/theme';

interface LazyScreenWrapperProps {
  children: React.ReactNode;
}

export const LazyScreenWrapper: React.FC<LazyScreenWrapperProps> = ({ children }) => {
  return (
    <Suspense
      fallback={
        <View style={styles.container}>
          <ActivityIndicator size="large" color={theme.colors.primary} />
        </View>
      }>
      {children}
    </Suspense>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: theme.colors.background,
  },
});

