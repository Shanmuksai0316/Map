import React, { useState, useEffect } from 'react';
import { View, Text, TextInput, TouchableOpacity, StyleSheet } from 'react-native';
import { GradientButton } from './GradientButton';
import Modal from 'react-native-modal';
import { theme } from '../theme/theme';
import { enableSecureScreen, disableSecureScreen } from '../utils/secure-screen.util';

interface OtpInputModalProps {
  visible: boolean;
  onVerify: (otp: string) => void;
  onCancel: () => void;
  title?: string;
  message?: string;
}

export const OtpInputModal: React.FC<OtpInputModalProps> = ({
  visible,
  onVerify,
  onCancel,
  title = 'Security Verification',
  message = 'Enter the verification code sent to your phone.',
}) => {
  const [otp, setOtp] = useState('');

  // Enable screenshot blocking when OTP modal is visible (MASVS L2 requirement)
  useEffect(() => {
    if (visible) {
      enableSecureScreen();
    } else {
      disableSecureScreen();
    }
    return () => {
      disableSecureScreen();
    };
  }, [visible]);

  const handleVerify = () => {
    if (otp.length === 6) {
      onVerify(otp);
      setOtp('');
    }
  };

  return (
    <Modal isVisible={visible} onBackdropPress={onCancel}>
      <View style={styles.modalContent}>
        <Text style={styles.title}>{title}</Text>
        <Text style={styles.message}>{message}</Text>
        <TextInput
          style={styles.input}
          value={otp}
          onChangeText={setOtp}
          keyboardType="number-pad"
          maxLength={6}
          autoFocus
        />
        <View style={styles.buttons}>
          <GradientButton style={styles.cancelButton} onPress={onCancel}>
            <Text style={styles.cancelText}>Cancel</Text>
          </GradientButton>
          <GradientButton 
            style={[styles.verifyButton, { opacity: otp.length === 6 ? 1 : 0.5 }]} 
            onPress={handleVerify}
            disabled={otp.length !== 6}
          >
            <Text style={styles.verifyText}>Verify</Text>
          </GradientButton>
        </View>
      </View>
    </Modal>
  );
};

const styles = StyleSheet.create({
  modalContent: {
    backgroundColor: theme.colors.white,
    padding: 24,
    borderRadius: 20,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  title: {
    fontSize: 18,
    fontWeight: 'bold',
    marginBottom: 10,
    color: theme.colors.primary,
  },
  message: {
    fontSize: 14,
    textAlign: 'center',
    marginBottom: 20,
    color: theme.colors.textSecondary,
  },
  input: {
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: 12,
    padding: 14,
    width: '100%',
    fontSize: 16,
    textAlign: 'center',
    color: theme.colors.primary,
  },
  buttons: {
    flexDirection: 'row',
    justifyContent: 'space-around',
    width: '100%',
    marginTop: 20,
  },
  cancelButton: {
    paddingVertical: 12,
    paddingHorizontal: 18,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  cancelText: {
    color: theme.colors.primary,
    fontWeight: '600',
  },
  verifyButton: {
    backgroundColor: theme.colors.accent,
    paddingVertical: 12,
    paddingHorizontal: 18,
    borderRadius: 12,
  },
  verifyText: {
    color: theme.colors.primary,
    fontWeight: 'bold',
  },
});
