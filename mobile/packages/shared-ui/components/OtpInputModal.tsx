import React, { useState, useEffect } from 'react';
import { View, Text, TextInput, TouchableOpacity, StyleSheet } from 'react-native';
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
    if (otp.length >= 4) {
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
          <TouchableOpacity style={styles.cancelButton} onPress={onCancel}>
            <Text style={styles.cancelText}>Cancel</Text>
          </TouchableOpacity>
          <TouchableOpacity 
            style={[styles.verifyButton, { opacity: otp.length >= 4 ? 1 : 0.5 }]} 
            onPress={handleVerify}
            disabled={otp.length < 4}
          >
            <Text style={styles.verifyText}>Verify</Text>
          </TouchableOpacity>
        </View>
      </View>
    </Modal>
  );
};

const styles = StyleSheet.create({
  modalContent: {
    backgroundColor: theme.colors.white,
    padding: 20,
    borderRadius: 10,
    alignItems: 'center',
  },
  title: {
    fontSize: 18,
    fontWeight: 'bold',
    marginBottom: 10,
  },
  message: {
    fontSize: 14,
    textAlign: 'center',
    marginBottom: 20,
  },
  input: {
    borderWidth: 1,
    borderColor: theme.colors.gray,
    borderRadius: 5,
    padding: 10,
    width: '100%',
    fontSize: 16,
    textAlign: 'center',
  },
  buttons: {
    flexDirection: 'row',
    justifyContent: 'space-around',
    width: '100%',
    marginTop: 20,
  },
  cancelButton: {
    padding: 10,
  },
  cancelText: {
    color: theme.colors.primary,
  },
  verifyButton: {
    backgroundColor: theme.colors.primary,
    padding: 10,
    borderRadius: 5,
  },
  verifyText: {
    color: theme.colors.white,
    fontWeight: 'bold',
  },
});
