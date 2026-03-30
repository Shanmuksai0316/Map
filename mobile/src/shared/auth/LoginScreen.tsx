import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  StyleSheet,
  Alert,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  Image,
} from 'react-native';
import { useAuthStore } from '../store/auth.store';
import { APP_CONFIG, isStudentApp } from '../config/app.config';
import { enableSecureScreen, disableSecureScreen } from '../utils/secure-screen.util';
import { theme } from '../theme/theme';
import { RadialGradientButton } from '../components/RadialGradientButton';

// Test credentials for development (bypass OTP)
const TEST_CREDENTIALS = {
  student: { phone: '9999999999', otp: '123456' },
  staff: { phone: '8888888888', otp: '123456' },
};

const MAX_PHONE_LENGTH = 15; // E.164 allows up to 15 digits (plus optional +)
const DEV_STUDENT_SCREENSHOT_PHONE = '9886179767';
const DEV_STAFF_SCREENSHOT_PHONES = new Set([
  '7200658181', // RM Supervisor
  '7676000129', // Sports Manager
  '9739143498', // Guard
  '8555903456', // HK Supervisor
  '9538678739', // Laundry Manager
  '9739557963', // Warden
  '7975452363', // Campus Manager
  '9663275871', // Rector
]);

export const LoginScreen = () => {
  const [phone, setPhone] = useState('');
  const [otp, setOTP] = useState('');
  const [otpSent, setOtpSent] = useState(false);
  const [isDevAutoVerifying, setIsDevAutoVerifying] = useState(false);

  const { sendOTP, verifyOTP, isLoading, error } = useAuthStore();
  const isDebugBuild = APP_CONFIG.IS_DEBUG_BUILD;

  // Dev-only shortcut for iOS simulator screenshot flow when SEND_OTP is unavailable.
  useEffect(() => {
    if (
      __DEV__ &&
      isStudentApp() &&
      phone === DEV_STUDENT_SCREENSHOT_PHONE &&
      !otpSent &&
      !isLoading
    ) {
      console.log('[LoginScreen] Student dev bypass: auto-showing OTP input');
      setOtpSent(true);
    }
  }, [phone, otpSent, isLoading]);

  // Dev-only shortcut for staff screenshot flow when SEND_OTP tap is flaky on simulator.
  useEffect(() => {
    if (
      __DEV__ &&
      !isStudentApp() &&
      DEV_STAFF_SCREENSHOT_PHONES.has(phone) &&
      !otpSent &&
      !isLoading
    ) {
      console.log('[LoginScreen] Staff dev bypass: auto-showing OTP input');
      setOtpSent(true);
    }
  }, [phone, otpSent, isLoading]);

  // Dev-only shortcut for iOS simulator screenshot flow when verify button interaction is flaky.
  useEffect(() => {
    if (
      !__DEV__ ||
      !isStudentApp() ||
      phone !== DEV_STUDENT_SCREENSHOT_PHONE ||
      !otpSent ||
      otp !== TEST_CREDENTIALS.student.otp ||
      isDevAutoVerifying ||
      isLoading
    ) {
      return;
    }

    setIsDevAutoVerifying(true);
    verifyOTP(phone, otp)
      .catch((verifyError: any) => {
        console.error('[LoginScreen] Student dev auto-verify failed:', verifyError?.message);
      })
      .finally(() => {
        setIsDevAutoVerifying(false);
      });
  }, [phone, otpSent, otp, isDevAutoVerifying, isLoading, verifyOTP]);

  // Enable screenshot blocking when OTP input is visible (skip in debug builds for QA screenshots)
  useEffect(() => {
    if (otpSent && !isDebugBuild) {
      enableSecureScreen();
      return () => {
        disableSecureScreen();
      };
    }
    disableSecureScreen();
    return () => {
      disableSecureScreen();
    };
  }, [otpSent, isDebugBuild]);

  const handleSendOTP = async () => {
    if (!phone || phone.length < 10) {
      Alert.alert('Error', 'Please enter a valid 10-digit phone number');
      return;
    }

    // Dev-only bypass for student screenshots/testing when backend OTP delivery is unavailable.
    if (
      __DEV__ &&
      isStudentApp() &&
      (phone === TEST_CREDENTIALS.student.phone || phone === '9886179767')
    ) {
      console.log('[LoginScreen] Student dev bypass: skipping sendOTP API call');
      setOtpSent(true);
      setOTP(TEST_CREDENTIALS.student.otp);
      Alert.alert('Test Mode', `Use OTP ${TEST_CREDENTIALS.student.otp}`);
      return;
    }

    // Staff app: skip real SEND_OTP and use backend bypass OTP (123456).
    if (!isStudentApp()) {
      const authStore = useAuthStore.getState();
      if (!authStore.selectedTenant) {
        try {
          await authStore.autoDetectTenant(phone);
        } catch (err: any) {
          console.error('[LoginScreen] Failed to auto-detect tenant:', err.message);
          Alert.alert('Institution not detected', err?.message || 'Unable to detect your institution. Please try again.');
          return;
        }
      }
      setOtpSent(true);
      Alert.alert('Success', 'OTP sent successfully');
      return;
    }

    // Student app: call real send-OTP (SMS). Backend also accepts bypass OTP 123456 when verifying.

    try {
      const response = await sendOTP(phone);

      // In practice, some backends may omit the `success` flag and only return a 200 + message.
      // Treat any successful HTTP response as success unless `success === false` explicitly.
      const isSuccess = response && response.success !== false;

      if (isSuccess) {
        setOtpSent(true);
        if (response?.otp) {
          setOTP(response.otp);
        }
        Alert.alert(
          response?.otp ? 'Use code below' : 'Success',
          response?.message || 'OTP sent successfully'
        );
      } else {
        Alert.alert('Error', response?.message || 'Failed to send OTP. Please try again.');
      }
    } catch (sendError: any) {
      Alert.alert('Error', sendError.message);
    }
  };

  const handleVerifyOTP = async () => {
    if (!otp || otp.length !== 6) {
      Alert.alert('Error', 'Please enter a valid 6-digit OTP');
      return;
    }

    try {
      await verifyOTP(phone, otp);
      // Navigation will be handled by the root navigator
    } catch (verifyError: any) {
      Alert.alert('Error', verifyError.message);
    }
  };

  const handleResendOTP = () => {
    setOTP('');
    setOtpSent(false);
  };

  const student = isStudentApp();
  // Unified light mode: clean white background for both apps
  const containerBg = theme.colors.white;
  const subtitleColor = theme.colors.primary;
  const labelColor = theme.colors.text;
  const helperColor = theme.colors.textSecondary;
  const footerColor = theme.colors.textMuted;
  const linkColor = theme.colors.primary;
  const inputBg = theme.colors.white;
  const inputBorder = theme.colors.border;
  const inputText = theme.colors.text;
  const inputPlaceholder = theme.colors.textMuted;
  const inputDisabledBg = theme.colors.surfaceMuted;

  return (
    <KeyboardAvoidingView
      style={[styles.container, { backgroundColor: containerBg }]}
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}>
      <ScrollView contentContainerStyle={styles.scrollContent}>
        <View style={styles.content}>
          {/* Header */}
          <View style={styles.header}>
            <View style={styles.logoContainer}>
              <Image
                source={require('../assets/map-logo.png')}
                style={styles.logoImage}
                resizeMode="contain"
              />
            </View>
            <Text
              style={[
                student ? styles.subtitleStudent : styles.subtitleKarta,
                { color: subtitleColor },
              ]}>
              {student ? 'VIDYARTHI' : 'Karta'}
            </Text>
            <Text style={[styles.tagline, { color: subtitleColor }]}>
              {student
                ? 'Smart app for smarter students.'
                : 'Smart Control for Smarter Hostels.'}
            </Text>
          </View>

          {/* Form */}
          <View style={styles.form}>
            <View style={styles.inputContainer}>
              <Text style={[styles.label, { color: labelColor }]}>Enter Your Number</Text>
              <TextInput
                style={[
                  styles.input,
                  {
                    backgroundColor: inputBg,
                    borderColor: inputBorder,
                    color: inputText,
                  },
                  otpSent && { backgroundColor: inputDisabledBg },
                ]}
                placeholder="Enter Your Number"
                placeholderTextColor={inputPlaceholder}
                keyboardType="phone-pad"
                maxLength={MAX_PHONE_LENGTH}
                value={phone}
                onChangeText={(value) => {
                  const sanitized = value.replace(/[^0-9+]/g, '');
                  setPhone(sanitized);
                }}
                editable={!otpSent && !isLoading}
                testID="phone-input"
              />
              {!otpSent && (
                <Text style={[styles.helperText, { color: helperColor }]}>
                  We'll send a 6-digit OTP to this number
                </Text>
              )}
            </View>

            {otpSent && (
              <View style={styles.inputContainer}>
                <Text style={[styles.label, { color: labelColor }]}>Verification Code</Text>
                <TextInput
                  style={[
                    styles.input,
                    {
                      backgroundColor: inputBg,
                      borderColor: inputBorder,
                      color: inputText,
                    },
                  ]}
                  placeholder="Enter 6-digit code"
                  placeholderTextColor={inputPlaceholder}
                  keyboardType="number-pad"
                  maxLength={6}
                  value={otp}
                  onChangeText={setOTP}
                  editable={!isLoading}
                  autoFocus
                  testID="otp-input"
                />
                <Text style={[styles.helperText, { color: helperColor }]}>
                  Check your SMS for the verification code
                </Text>
              </View>
            )}

            {error && (
              <View style={styles.errorContainer}>
                <Text style={styles.errorText}>{error}</Text>
              </View>
            )}

            <RadialGradientButton
              label={otpSent ? 'Verify & Login' : 'Get OTP'}
              onPress={otpSent ? handleVerifyOTP : handleSendOTP}
              disabled={(otpSent && otp.length !== 6) || isLoading}
              loading={isLoading}
              indicatorColor={theme.colors.primary}
              testID={otpSent ? 'verify-otp-button' : 'send-otp-button'}
            />
            {otpSent && (
              <TouchableOpacity
                style={styles.linkButton}
                onPress={handleResendOTP}
                disabled={isLoading}>
                <Text style={[styles.linkText, { color: linkColor }]}>Resend OTP</Text>
              </TouchableOpacity>
            )}
          </View>

          <View style={styles.footer}>
            <Text style={[styles.footerText, { color: footerColor }]}>
              Version {APP_CONFIG.APP_VERSION}
            </Text>
          </View>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  scrollContent: {
    flexGrow: 1,
  },
  content: {
    flex: 1,
    padding: 24,
    justifyContent: 'center',
  },
  header: {
    alignItems: 'center',
    paddingTop: 32,
    marginBottom: 48,
  },
  logoContainer: {
    width: 120,
    height: 120,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 24,
  },
  logoImage: {
    width: '100%',
    height: '100%',
  },
  subtitle: {
    fontSize: 22,
    fontWeight: '700',
    letterSpacing: 1,
    marginBottom: 4,
  },
  subtitleStudent: {
    fontFamily: 'EthnocentricRg',
    fontSize: theme.fontSize.xxl,
    letterSpacing: 1,
    marginBottom: 4,
  },
  subtitleKarta: {
    fontFamily: 'EthnocentricRg',
    fontSize: 28,
    letterSpacing: 1,
    marginBottom: 4,
  },
  tagline: {
    fontSize: 14,
    textAlign: 'center',
    marginBottom: 24,
  },
  form: {
    marginBottom: 32,
  },
  inputContainer: {
    marginBottom: 20,
  },
  label: {
    fontSize: 16,
    fontWeight: '600',
    marginBottom: 8,
  },
  helperText: {
    fontSize: 12,
    marginTop: 4,
  },
  input: {
    borderWidth: 1,
    borderRadius: 8,
    padding: 16,
    fontSize: 16,
  },
  linkButton: {
    padding: 16,
    alignItems: 'center',
  },
  linkText: {
    fontSize: 16,
    fontWeight: '600',
  },
  errorContainer: {
    backgroundColor: '#ffebee',
    padding: 12,
    borderRadius: 8,
    marginBottom: 16,
  },
  errorText: {
    color: '#c62828',
    fontSize: 14,
  },
  footer: {
    alignItems: 'center',
    marginTop: 32,
  },
  footerText: {
    fontSize: 14,
  },
});
