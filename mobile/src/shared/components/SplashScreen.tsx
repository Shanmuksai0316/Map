import React from 'react';
import { View, Text, StyleSheet, ActivityIndicator, Image } from 'react-native';
import { theme } from '../theme/theme';
import { isStudentApp } from '../config/app.config';

export const SplashScreen = () => {
  return (
    <View style={styles.container}>
      <View style={styles.content}>
        <View style={styles.logoContainer}>
          <Image
            source={require('../assets/map-logo.png')}
            style={styles.logo}
            resizeMode="contain"
          />
        </View>
        <Text style={styles.appName}>MAP HMS</Text>
        <Text style={styles.appSubtitle}>{isStudentApp() ? 'Student App' : 'Staff App'}</Text>
        <ActivityIndicator size="large" color={theme.colors.primary} style={styles.loadingIndicator} />
        <Text style={styles.loadingText}>Preparing your experience…</Text>
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
    justifyContent: 'center',
    alignItems: 'center',
  },
  content: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: theme.spacing.lg,
  },
  logoContainer: {
    width: 140,
    height: 140,
    borderRadius: theme.borderRadius.full,
    backgroundColor: theme.colors.surface,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: theme.spacing.lg,
    ...theme.shadows.medium,
  },
  logo: {
    width: 110,
    height: 110,
  },
  appName: {
    fontSize: theme.fontSize.xxxl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.primary,
    marginBottom: theme.spacing.xs,
  },
  appSubtitle: {
    fontSize: theme.fontSize.lg,
    color: theme.colors.textSecondary,
    marginBottom: theme.spacing.lg,
  },
  loadingIndicator: {
    marginBottom: theme.spacing.sm,
  },
  loadingText: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textMuted,
  },
});

