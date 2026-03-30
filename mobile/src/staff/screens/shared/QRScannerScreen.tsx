import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  Alert,
  TextInput,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { theme } from '../../../shared/theme/theme';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

export const QRScannerScreen = ({ navigation }: any) => {
  const [manualCode, setManualCode] = useState('');

  const handleManualEntry = () => {
    if (manualCode.trim()) {
      Alert.alert(
        'QR Code Scanned',
        `Code: ${manualCode}`,
        [
          {
            text: 'OK',
            onPress: () => {
              setManualCode('');
              navigation.goBack();
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
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="QR Scanner" />
      <View style={styles.content}>
        <View style={styles.placeholderBox}>
          <Ionicons name="qr-code-outline" size={80} color={theme.colors.textMuted} />
          <Text style={styles.placeholderText}>
            Camera scanning not available in test mode
          </Text>
          <Text style={styles.placeholderSubtext}>
            Use manual entry below for testing
          </Text>
        </View>

        <View style={styles.manualEntry}>
          <Text style={styles.manualLabel}>Manual QR Code Entry:</Text>
          <TextInput
            style={styles.input}
            value={manualCode}
            onChangeText={setManualCode}
            placeholder="Enter QR code or gate pass ID"
            placeholderTextColor={theme.colors.textMuted}
          />
          <GradientButton
            style={styles.submitButton}
            onPress={handleManualEntry}>
            <Text style={styles.submitButtonText}>Submit Code</Text>
          </GradientButton>
        </View>

        <View style={styles.instructions}>
          <Text style={styles.instructionsTitle}>Instructions:</Text>
          <Text style={styles.instructionsText}>
            • For testing, enter any gate pass ID or code{'\n'}
            • In production, camera will scan QR codes automatically{'\n'}
            • Valid codes will show student gate pass details
          </Text>
        </View>
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  subHeader: {
    paddingHorizontal: 16,
    paddingTop: 12,
    paddingBottom: 8,
  },
  subHeaderTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: theme.colors.textHeading,
  },
  content: {
    flex: 1,
    padding: theme.spacing.lg,
  },
  placeholderBox: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.borderRadius.lg,
    padding: theme.spacing.xxl,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: theme.spacing.xl,
    borderWidth: 2,
    borderColor: theme.colors.border,
    borderStyle: 'dashed',
    minHeight: 200,
  },
  placeholderText: {
    fontSize: theme.fontSize.lg,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
    textAlign: 'center',
    marginTop: theme.spacing.md,
  },
  placeholderSubtext: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textMuted,
    textAlign: 'center',
    marginTop: theme.spacing.sm,
  },
  manualEntry: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.borderRadius.lg,
    padding: theme.spacing.lg,
    marginBottom: theme.spacing.lg,
  },
  manualLabel: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
    marginBottom: theme.spacing.sm,
  },
  input: {
    backgroundColor: theme.colors.background,
    borderRadius: theme.borderRadius.md,
    padding: theme.spacing.md,
    fontSize: theme.fontSize.md,
    color: theme.colors.text,
    borderWidth: 1,
    borderColor: theme.colors.border,
    marginBottom: theme.spacing.md,
  },
  submitButton: {
    backgroundColor: '#D79F24',
    borderRadius: theme.borderRadius.md,
    padding: theme.spacing.md,
    alignItems: 'center',
    ...theme.shadows.medium,
  },
  submitButtonText: {
    color: theme.colors.primary,
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
  },
  instructions: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.borderRadius.lg,
    padding: theme.spacing.lg,
    borderLeftWidth: 4,
    borderLeftColor: theme.colors.primary,
  },
  instructionsTitle: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
    marginBottom: theme.spacing.sm,
  },
  instructionsText: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
    lineHeight: 20,
  },
});
