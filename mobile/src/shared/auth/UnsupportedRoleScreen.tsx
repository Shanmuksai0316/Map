import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity, Platform } from 'react-native';
import { GradientButton } from '../components/GradientButton';
import { APP_CONFIG, isStudentApp } from '../config/app.config';

interface UnsupportedRoleScreenProps {
  userRole: string;
  onLogout: () => void;
}

export const UnsupportedRoleScreen: React.FC<UnsupportedRoleScreenProps> = ({ userRole, onLogout }) => {
  const currentApp = isStudentApp() ? 'Student App' : 'Staff App';
  const targetApp = isStudentApp() ? 'Staff App' : 'Student App';
  const targetBundleId = isStudentApp() ? 'com.mapmars.hmsstaff' : 'com.maphms.student';

  return (
    <View style={styles.container}>
      <View style={styles.card}>
        <View style={styles.iconContainer}>
          <Text style={styles.icon}>⚠️</Text>
        </View>

        <Text style={styles.title}>Unsupported Role</Text>
        <Text style={styles.subtitle}>You are signed in with the role:</Text>
        <Text style={styles.role}>{userRole}</Text>

        <View style={styles.messageContainer}>
          <Text style={styles.message}>
            The <Text style={styles.strong}>{currentApp}</Text> only supports the
            {isStudentApp() ? ' Student ' : ' Staff '} role. Please download the
            {' '}
            <Text style={styles.strong}>{targetApp}</Text> to continue using your
            account.
          </Text>
        </View>

        <View style={styles.instructions}>
          <Text style={styles.instructionsTitle}>Next steps:</Text>
          <Text style={styles.instructionsText}>1. Logout below.</Text>
          <Text style={styles.instructionsText}>
            2. Download the {targetApp} ({targetBundleId}).
          </Text>
          <Text style={styles.instructionsText}>
            3. Sign in with the same phone number on the correct app.
          </Text>
        </View>

        <GradientButton onPress={onLogout} style={styles.button}>
          <Text style={styles.buttonText}>Logout & Exit</Text>
        </GradientButton>

        <Text style={styles.footer}>
          Need help? Contact your campus administrator.
        </Text>
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f8f9fa',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
  },
  card: {
    width: '100%',
    maxWidth: 420,
    backgroundColor: '#fff',
    borderRadius: 24,
    padding: 24,
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.08,
    shadowRadius: 16,
    elevation: 6,
  },
  iconContainer: {
    width: 88,
    height: 88,
    borderRadius: 44,
    backgroundColor: '#FFE5E5',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 16,
  },
  icon: {
    fontSize: 40,
  },
  title: {
    fontSize: 24,
    fontWeight: '700',
    color: '#1a1a1a',
    marginBottom: 8,
  },
  subtitle: {
    fontSize: 16,
    color: '#6c757d',
    marginBottom: 4,
  },
  role: {
    fontSize: 20,
    fontWeight: '600',
    color: '#dc3545',
    marginBottom: 16,
    textTransform: 'capitalize',
  },
  messageContainer: {
    backgroundColor: '#f1f3f5',
    borderRadius: 16,
    padding: 16,
    marginBottom: 20,
  },
  message: {
    fontSize: 15,
    color: '#495057',
    lineHeight: 22,
    textAlign: 'center',
  },
  strong: {
    fontWeight: '700',
    color: '#212529',
  },
  instructions: {
    width: '100%',
    backgroundColor: '#f8f9fa',
    borderRadius: 16,
    padding: 16,
    marginBottom: 24,
  },
  instructionsTitle: {
    fontSize: 16,
    fontWeight: '600',
    marginBottom: 12,
    color: '#1a1a1a',
  },
  instructionsText: {
    fontSize: 14,
    color: '#495057',
    marginBottom: 6,
  },
  button: {
    width: '100%',
    backgroundColor: '#dc3545',
    paddingVertical: 16,
    borderRadius: 12,
    alignItems: 'center',
    marginBottom: 12,
  },
  buttonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
    letterSpacing: 0.2,
  },
  footer: {
    fontSize: 12,
    color: '#868e96',
    textAlign: 'center',
  },
});

export default UnsupportedRoleScreen;

