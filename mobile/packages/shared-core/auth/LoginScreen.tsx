import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  StyleSheet,
  ActivityIndicator,
  Alert,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  Image,
} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useAuthStore } from '../../store/auth.store';
import { APP_CONFIG, isStudentApp } from '../../config/app.config';
import { enableSecureScreen, disableSecureScreen } from '../../utils/secure-screen.util';

// Test credentials for development (bypass OTP)
const TEST_CREDENTIALS = {
  student: { phone: '9999999999', otp: '123456' },
  staff: { phone: '8888888888', otp: '123456' },
};

export const LoginScreen = () => {
  const [phone, setPhone] = useState('');
  const [otp, setOTP] = useState('');
  const [otpSent, setOtpSent] = useState(false);
  
  const { sendOTP, verifyOTP, isLoading, error, clearError } = useAuthStore();

  // Enable screenshot blocking when OTP input is visible
  useEffect(() => {
    if (otpSent) {
      enableSecureScreen();
    } else {
      disableSecureScreen();
    }
    return () => {
      disableSecureScreen();
    };
  }, [otpSent]);

  const handleSendOTP = async () => {
    if (!phone || phone.length < 10) {
      Alert.alert('Error', 'Please enter a valid 10-digit phone number');
      return;
    }

    // Test mode - bypass for all test phone numbers (for staff app)
    const STAFF_TEST_NUMBERS = [
      '8888888888', // Campus Manager
      '8888888890', // Rector/Warden
      '8888888891', // Guard
      '8888888892', // HK Supervisor
      '8888888893', // RM Supervisor
      '8888888896', // Laundry Manager
      '8888888897', // Sports Manager
    ];
    
    const testCred = isStudentApp() ? TEST_CREDENTIALS.student : TEST_CREDENTIALS.staff;
    
    // Check if it's a test phone number (for staff app) or the default test number
    if ((isStudentApp() && phone === testCred.phone) || 
        (!isStudentApp() && (phone === testCred.phone || STAFF_TEST_NUMBERS.includes(phone)))) {
      setOtpSent(true);
      Alert.alert('Test Mode', `Test OTP: 123456`);
      return;
    }

    try {
      const response = await sendOTP(phone);
      if (response.success) {
        setOtpSent(true);
        Alert.alert('Success', response.message || 'OTP sent successfully');
      }
    } catch (error: any) {
      Alert.alert('Error', error.message);
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
    } catch (error: any) {
      Alert.alert('Error', error.message);
    }
  };

  const handleResendOTP = () => {
    setOTP('');
    setOtpSent(false);
  };

  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}>
      <ScrollView contentContainerStyle={styles.scrollContent}>
        <View style={styles.content}>
          {/* Header */}
          <View style={styles.header}>
            {/* Logo */}
            <View style={styles.logoContainer}>
              <Image 
                source={require('../../assets/map-logo.png')} 
                style={styles.logoImage}
                resizeMode="contain"
              />
            </View>
            <Text style={styles.title}>Hostel Management System</Text>
            <Text style={styles.subtitle}>
              {isStudentApp() ? 'Student App' : 'Staff App'}
            </Text>
            <View style={styles.testInfo}>
              <Ionicons name="flask-outline" size={14} color="#666" style={{ marginRight: 6 }} />
              <Text style={styles.testInfoText}>
                Test Login: {isStudentApp() ? '9999999999' : '8888888888'} / OTP: 123456
              </Text>
            </View>
          </View>

          {/* Form */}
          <View style={styles.form}>
            {/* Phone Input */}
            <View style={styles.inputContainer}>
              <Text style={styles.label}>Phone Number</Text>
              <TextInput
                style={[styles.input, otpSent && styles.inputDisabled]}
                placeholder="Enter your registered phone number"
                keyboardType="phone-pad"
                maxLength={10}
                value={phone}
                onChangeText={setPhone}
                editable={!otpSent && !isLoading}
                testID="phone-input"
              />
              {!otpSent && (
                <Text style={styles.helperText}>
                  We'll send a 6-digit OTP to this number
                </Text>
              )}
            </View>

            {/* OTP Input */}
            {otpSent && (
              <View style={styles.inputContainer}>
                <Text style={styles.label}>Verification Code</Text>
                <TextInput
                  style={styles.input}
                  placeholder="Enter 6-digit code"
                  keyboardType="number-pad"
                  maxLength={6}
                  value={otp}
                  onChangeText={setOTP}
                  editable={!isLoading}
                  autoFocus
                  testID="otp-input"
                />
                <Text style={styles.helperText}>
                  Check your SMS for the verification code
                </Text>
              </View>
            )}

            {/* Error Message */}
            {error && (
              <View style={styles.errorContainer}>
                <Text style={styles.errorText}>{error}</Text>
              </View>
            )}

            {/* Buttons */}
            {!otpSent ? (
              <TouchableOpacity
                style={[styles.button, isLoading && styles.buttonDisabled]}
                onPress={handleSendOTP}
                disabled={isLoading}
                accessibilityLabel="Send OTP">
                {isLoading ? (
                  <ActivityIndicator color="#fff" />
                ) : (
                  <Text style={styles.buttonText}>Send OTP</Text>
                )}
              </TouchableOpacity>
            ) : (
              <>
              <TouchableOpacity
                style={[styles.button, isLoading && styles.buttonDisabled]}
                onPress={handleVerifyOTP}
                disabled={isLoading}
                accessibilityLabel="Login"
                testID="verify-otp-button">
                {isLoading ? (
                  <ActivityIndicator color="#fff" />
                ) : (
                  <Text style={styles.buttonText}>Verify & Login</Text>
                )}
              </TouchableOpacity>

                <TouchableOpacity
                  style={styles.linkButton}
                  onPress={handleResendOTP}
                  disabled={isLoading}>
                  <Text style={styles.linkText}>Resend OTP</Text>
                </TouchableOpacity>
              </>
            )}
          </View>

          {/* Footer */}
          <View style={styles.footer}>
            <Text style={styles.footerText}>
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
    backgroundColor: '#fff',
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
  title: {
    fontSize: 28,
    fontWeight: 'bold',
    color: '#1a1a1a',
    marginBottom: 8,
  },
  subtitle: {
    fontSize: 18,
    color: '#666',
    marginBottom: 16,
  },
  testInfo: {
    backgroundColor: '#FFF5F2',
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#FFCBB8',
    marginTop: 16,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
  },
  testInfoText: {
    fontSize: 12,
    color: '#B33818',
    textAlign: 'center',
    fontWeight: '600',
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
    color: '#333',
    marginBottom: 8,
  },
  helperText: {
    fontSize: 12,
    color: '#666',
    marginTop: 4,
  },
  input: {
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 8,
    padding: 16,
    fontSize: 16,
    color: '#333',
  },
  inputDisabled: {
    backgroundColor: '#f5f5f5',
    color: '#999',
  },
  button: {
    backgroundColor: '#FF6B35',
    borderRadius: 8,
    padding: 16,
    alignItems: 'center',
    marginTop: 8,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  buttonDisabled: {
    backgroundColor: '#ccc',
  },
  buttonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
  linkButton: {
    padding: 16,
    alignItems: 'center',
  },
  linkText: {
    color: '#FF6B35',
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
    color: '#999',
    fontSize: 14,
  },
});

