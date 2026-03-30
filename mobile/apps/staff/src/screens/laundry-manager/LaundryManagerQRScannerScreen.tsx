import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  Alert,
  TextInput,
} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { colors } from '../../theme/colors';

export const LaundryManagerQRScannerScreen = ({ navigation }: any) => {
  const [manualCode, setManualCode] = useState('');

  const handleScanQR = () => {
    if (manualCode.trim()) {
      Alert.alert(
        'QR Code Scanned',
        `Code: ${manualCode}`,
        [
          {
            text: 'OK',
            onPress: () => {
              setManualCode('');
            },
          },
        ]
      );
    } else {
      Alert.alert('Error', 'Please enter a QR code');
    }
  };

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.headerTitle}>Scan QR Code</Text>
      </View>

      <View style={styles.content}>
        <View style={styles.scanButtonContainer}>
          <TouchableOpacity
            style={styles.scanButton}
            onPress={handleScanQR}>
            <Ionicons name="qr-code-outline" size={64} color={colors.surface} />
            <Text style={styles.scanButtonText}>Scan QR Code</Text>
          </TouchableOpacity>
        </View>

        <View style={styles.manualEntry}>
          <Text style={styles.manualLabel}>Or Enter QR Code Manually:</Text>
          <TextInput
            style={styles.input}
            value={manualCode}
            onChangeText={setManualCode}
            placeholder="Enter QR code or gate pass ID"
            placeholderTextColor={colors.textMuted}
          />
          <TouchableOpacity
            style={styles.submitButton}
            onPress={handleScanQR}>
            <Text style={styles.submitButtonText}>Submit Code</Text>
          </TouchableOpacity>
        </View>
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  header: {
    backgroundColor: colors.primary,
    padding: 20,
    paddingTop: 60,
  },
  headerTitle: {
    color: colors.surface,
    fontSize: 24,
    fontWeight: 'bold',
  },
  content: {
    flex: 1,
    padding: 20,
    justifyContent: 'center',
  },
  scanButtonContainer: {
    alignItems: 'center',
    marginBottom: 40,
  },
  scanButton: {
    backgroundColor: colors.primary,
    width: 200,
    height: 200,
    borderRadius: 100,
    alignItems: 'center',
    justifyContent: 'center',
    elevation: 4,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.3,
    shadowRadius: 4,
  },
  scanButtonText: {
    color: colors.surface,
    fontSize: 16,
    fontWeight: '600',
    marginTop: 12,
  },
  manualEntry: {
    backgroundColor: colors.surface,
    borderRadius: 12,
    padding: 20,
  },
  manualLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: 12,
  },
  input: {
    backgroundColor: colors.background,
    borderRadius: 8,
    padding: 12,
    fontSize: 16,
    color: colors.textPrimary,
    borderWidth: 1,
    borderColor: colors.border,
    marginBottom: 16,
  },
  submitButton: {
    backgroundColor: colors.primary,
    borderRadius: 8,
    padding: 14,
    alignItems: 'center',
  },
  submitButtonText: {
    color: colors.surface,
    fontSize: 16,
    fontWeight: '600',
  },
});

