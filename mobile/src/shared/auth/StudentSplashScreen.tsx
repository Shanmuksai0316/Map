/**
 * Student app splash screen – shown on app open and after logout.
 * Uses high-res PNG for sharp quality; "Get Started" navigates to Login.
 */

import React from 'react';
import { View, Text, StyleSheet, Image } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { SvgXml } from 'react-native-svg';
import { RadialGradientButton } from '../components/RadialGradientButton';
import { theme } from '../theme/theme';
import { mapLogoColorSvgXml } from '../assets/map-logo-color';

type AuthStackParamList = {
  StudentSplash: undefined;
  Login: undefined;
};

export const StudentSplashScreen = () => {
  const navigation = useNavigation<NativeStackNavigationProp<AuthStackParamList, 'StudentSplash'>>();

  const handleGetStarted = () => {
    navigation.navigate('Login');
  };

  return (
    <View style={styles.container}>
      <View style={styles.topSection}>
        <SvgXml xml={mapLogoColorSvgXml} width={96} height={96} />
        <Text style={styles.appName}>VIDYARTHI</Text>
        <Text style={styles.tagline}>Your Home Away From Home</Text>
      </View>

      <View style={styles.illustrationSection}>
        <Image
          source={require('../assets/student-splash.png')}
          style={styles.illustrationImage}
          resizeMode="contain"
          accessibilityLabel="Student splash illustration"
        />
      </View>

      <View style={styles.bottomSection}>
        <RadialGradientButton
          label="Get Started →"
          onPress={handleGetStarted}
          accessibilityRole="button"
          accessibilityLabel="Get Started"
        />
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.white,
  },
  topSection: {
    alignItems: 'center',
    paddingTop: theme.spacing.xxl,
    paddingHorizontal: theme.spacing.xl,
  },
  appName: {
    marginTop: theme.spacing.md,
    fontFamily: 'EthnocentricRg',
    fontSize: theme.fontSize.xxxl,
    color: theme.colors.primary,
    letterSpacing: 1,
  },
  tagline: {
    marginTop: theme.spacing.sm,
    fontSize: theme.fontSize.md,
    color: '#D79F24',
    textAlign: 'center',
  },
  illustrationSection: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: theme.spacing.xl,
  },
  illustrationImage: {
    width: '100%',
    maxWidth: 420,
    height: 300,
  },
  bottomSection: {
    paddingHorizontal: theme.spacing.xl,
    paddingBottom: theme.spacing.xxl + 24,
    paddingTop: theme.spacing.lg,
    alignItems: 'center',
  },
});
