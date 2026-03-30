import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  Alert,
  TextInput,
  ActivityIndicator,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { colors } from '../../../shared/theme/colors';
import { apiService } from '../../../shared/services/api.service';
import { APP_CONFIG } from '../../../shared/config/app.config';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

export const LaundryManagerQRScannerScreen = ({ navigation }: any) => {
  const [manualCode, setManualCode] = useState('');
  const [isProcessing, setIsProcessing] = useState(false);

  const handleCodeLookup = async (code: string) => {
    if (!code.trim()) {
      Alert.alert('Error', 'Please enter a QR code');
      return;
    }

    setIsProcessing(true);
    try {
      const response = await apiService.get<{ data?: any }>(
        `${APP_CONFIG.ENDPOINTS.LAUNDRY_REQUESTS}/${code.trim()}`
      );
      const request = response.data?.data ?? response.data;
      navigation.navigate('LaundryRequestDetail', { requestId: code.trim(), request });
      setManualCode('');
    } catch (error: any) {
      const detail = error?.response?.data?.detail || 'Request not found';
      Alert.alert('Not Found', detail);
    } finally {
      setIsProcessing(false);
    }
  };

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="QR Scanner" />
      <View style={styles.content}>
        <View style={styles.scanSection}>
          <View style={styles.scanPlaceholder}>
            <Ionicons name="qr-code-outline" size={72} color={colors.white} />
            <Text style={styles.scanPlaceholderText}>
              Camera scanning to open laundry request by QR code
            </Text>
            <Text style={styles.scanPlaceholderSubtext}>
              Use manual entry below while camera access is unavailable.
            </Text>
        </View>

        <View style={styles.manualEntry}>
            <Text style={styles.manualLabel}>Manual QR Code Entry</Text>
          <TextInput
            style={styles.input}
            value={manualCode}
            onChangeText={setManualCode}
              placeholder="Enter QR code or request ID"
            placeholderTextColor={colors.textMuted}
              autoCapitalize="none"
              autoCorrect={false}
          />
          <GradientButton
              style={[styles.submitButton, isProcessing && styles.buttonDisabled]}
              onPress={() => handleCodeLookup(manualCode)}
              disabled={isProcessing}>
              {isProcessing ? (
                <ActivityIndicator size="small" color={colors.surface} />
              ) : (
                <Text style={styles.submitButtonText}>Look Up Request</Text>
              )}
          </GradientButton>
          </View>
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
  subHeader: {
    paddingHorizontal: 16,
    paddingTop: 12,
    paddingBottom: 8,
  },
  subHeaderTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: colors.textHeading,
  },
  content: {
    flex: 1,
    padding: 20,
    justifyContent: 'center',
  },
  scanSection: {
    gap: 20,
  },
  scanPlaceholder: {
    backgroundColor: colors.primary,
    padding: 24,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
  },
  scanPlaceholderText: {
    color: colors.surface,
    fontSize: 16,
    fontWeight: '700',
    textAlign: 'center',
    marginTop: 12,
  },
  scanPlaceholderSubtext: {
    color: colors.surface,
    fontSize: 14,
    textAlign: 'center',
    marginTop: 8,
    opacity: 0.9,
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
    backgroundColor: '#D79F24',
    borderRadius: 8,
    padding: 14,
    alignItems: 'center',
  },
  buttonDisabled: {
    opacity: 0.7,
  },
  submitButtonText: {
    color: colors.primary,
    fontSize: 16,
    fontWeight: '600',
  },
});
