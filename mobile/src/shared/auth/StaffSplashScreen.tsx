/**
 * StaffSplashScreen
 *
 * Splash / welcome screen shown when the Staff (Kartha) app is first opened
 * or after the user logs out. It is a single non-scrollable screen with an
 * olive-green background, the Kartha logo, "Karta" branding text, a tagline,
 * a central illustration, and a "Get Started" button that navigates to the
 * Login screen.
 *
 * Required assets:
 *   - karta-logo.png        (shared/assets/) -- top logo
 *   - staff-splash-illustration.png (shared/assets/) -- central illustration
 *
 * Font dependency:
 *   - Custom font EthnocentricRg is required for the "Karta" heading.
 *     Must be linked in both Android (assets/fonts/) and iOS (Info.plist).
 *
 * Navigation:
 *   - Pressing "Get Started" calls navigation.navigate('Login').
 */

import React, { useEffect } from 'react';
import { View, Text, StyleSheet, Image } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { SvgXml } from 'react-native-svg';
import { theme } from '../theme/theme';
import { RadialGradientButton } from '../components/RadialGradientButton';
import { mapLogoColorSvgXml } from '../assets/map-logo-color';

const STAFF_SPLASH = {
  background: '#FFFFFF',
  yellow: '#D79F24',
  green: '#2F4F2F',
};

type AuthStackParamList = {
  StaffSplash: undefined;
  Login: undefined;
};

export const StaffSplashScreen = () => {
  const navigation = useNavigation<NativeStackNavigationProp<AuthStackParamList, 'StaffSplash'>>();

  const handleGetStarted = () => {
    navigation.navigate('Login');
  };

  useEffect(() => {
    // #region agent log
    fetch('http://127.0.0.1:7242/ingest/26316810-a694-48b7-8f83-116907028f19', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Debug-Session-Id': '65d170',
      },
      body: JSON.stringify({
        sessionId: '65d170',
        runId: 'pre-fix',
        hypothesisId: 'H1',
        location: 'StaffSplashScreen.tsx:48',
        message: 'StaffSplashScreen mounted',
        data: {
          variant: 'staff',
        },
        timestamp: Date.now(),
      }),
    }).catch(() => {});
    // #endregion
  }, []);

  return (
    <View style={styles.container}>
      {/* Top: logo same as student header, then Karta with same font style as header */}
      <View style={styles.topSection}>
        <SvgXml xml={mapLogoColorSvgXml} width={96} height={96} />
        <View style={styles.karthaHeadingWrapper}>
          <View style={styles.karthaAccent} />
          <Text style={styles.karthaText}>Karta</Text>
        </View>
        <Text style={styles.tagline}>Smart control for Smarter hostels.</Text>
      </View>

      {/* Central illustration - uses provided asset */}
      <View style={styles.centralSection}>
        <Image
          source={require('../assets/staff-splash-provided.png')}
          style={styles.centralImage}
          resizeMode="contain"
          accessibilityLabel="Staff splash illustration"
        />
      </View>

      <View style={styles.bottomSection}>
        <RadialGradientButton
          label="Get Started →"
          onPress={handleGetStarted}
        />
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: STAFF_SPLASH.background,
  },
  topSection: {
    alignItems: 'center',
    paddingTop: theme.spacing.xxxl,
    paddingHorizontal: theme.spacing.xl,
  },
  karthaHeadingWrapper: {
    alignItems: 'center',
    marginTop: theme.spacing.md,
  },
  karthaAccent: {
    width: 40,
    height: 2,
    borderRadius: 1,
    backgroundColor: STAFF_SPLASH.yellow,
    marginBottom: theme.spacing.xs,
  },
  karthaText: {
    fontFamily: 'EthnocentricRg',
    fontSize: theme.fontSize.xxxl,
    color: STAFF_SPLASH.green,
    letterSpacing: 1,
  },
  tagline: {
    fontSize: theme.fontSize.md,
    color: STAFF_SPLASH.yellow,
    textAlign: 'center',
    marginTop: theme.spacing.sm,
  },
  centralSection: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: theme.spacing.xl,
    marginTop: theme.spacing.lg,
    marginBottom: theme.spacing.lg,
  },
  centralImage: {
    width: '100%',
    maxWidth: 420,
    height: 340,
  },
  bottomSection: {
    paddingHorizontal: theme.spacing.xl,
    paddingBottom: theme.spacing.xxl,
    paddingTop: theme.spacing.lg,
    alignItems: 'center',
  },
});
