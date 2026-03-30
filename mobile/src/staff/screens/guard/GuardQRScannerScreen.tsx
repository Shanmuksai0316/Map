import React, { useCallback, useRef, useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  Alert,
  TextInput,
  ActivityIndicator,
} from 'react-native';
import { Camera, useCodeScanner, useCameraDevice, useCameraPermission } from 'react-native-vision-camera';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { apiService } from '../../../shared/services/api.service';
import { theme } from '../../../shared/theme/theme';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';
import { GradientButton } from '../../../shared/components/GradientButton';

type OutPassScanResponse =
  | { ok: true; message: string; out_pass_id: string }
  | { error: string; message: string };

export const GuardQRScannerScreen = ({ navigation }: any) => {
  const [backupCode, setBackupCode] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [showCamera, setShowCamera] = useState(false);
  const lastScannedRef = useRef<string | null>(null);

  const { hasPermission, requestPermission } = useCameraPermission();
  const device = useCameraDevice('back');

  const submitScan = async (payload: { out_pass_id: string; backup_code?: string; method: 'qr' | 'backup_code' }) => {
    setIsLoading(true);
    setShowCamera(false);

    try {
      const response = await apiService.post<OutPassScanResponse>('/gate/outpass-scans', payload);

      if ('ok' in response && response.ok) {
        const outPassId = (response as { out_pass_id?: string }).out_pass_id;
        setBackupCode('');
        if (outPassId && navigation?.navigate) {
          navigation.navigate('GuardOutpassDetail', { outpassId: Number(outPassId) });
        } else {
          Alert.alert('Success', 'Allow entry');
        }
        return;
      }

      const errPayload: any = response as any;
      Alert.alert('Denied', errPayload?.message || 'Out-pass invalid');
    } catch (error: any) {
      const errData = error?.response?.data;
      const message =
        errData?.error === 'outside_curfew_window'
          ? 'QR scans are only accepted during curfew hours. Use manual gate-out outside curfew.'
          : errData?.message || 'Unable to verify. Please try again.';
      Alert.alert('Denied', message);
    } finally {
      setIsLoading(false);
    }
  };

  const handleScanQR = useCallback(async (rawQr?: string) => {
    const qr = (rawQr || '').trim();
    if (!qr || isLoading) return;

    // Avoid processing the same code twice in quick succession
    if (lastScannedRef.current === qr) return;
    lastScannedRef.current = qr;
    setTimeout(() => {
      lastScannedRef.current = null;
    }, 3000);

    try {
      const parsed = JSON.parse(qr);
      const outPass = parsed?.out_pass_id?.toString?.() ?? parsed?.outPassId?.toString?.();
      const code = parsed?.backup_code?.toString?.() ?? parsed?.backupCode?.toString?.();

      if (!outPass) {
        Alert.alert('Invalid QR', 'QR is missing out_pass_id');
        return;
      }

      await submitScan({ out_pass_id: outPass, ...(code ? { backup_code: code } : {}), method: 'qr' });
    } catch {
      Alert.alert('Invalid QR', 'QR data is not valid JSON');
    }
  }, [isLoading]);

  const handleScanQRRef = useRef(handleScanQR);
  handleScanQRRef.current = handleScanQR;

  const handleManualSubmit = async () => {
    const code = backupCode.trim();
    if (!code || code.length !== 4) {
      Alert.alert('Error', 'Enter 4-digit backup code');
      return;
    }
    try {
      setIsLoading(true);
      setShowCamera(false);
      const response = await apiService.post<{ ok?: boolean; out_pass_id?: string; error?: string; message?: string }>(
        '/gate/outpass-verify-by-code',
        { backup_code: code }
      );
      if (response?.ok && response?.out_pass_id) {
        setBackupCode('');
        navigation?.navigate?.('GuardOutpassDetail', { outpassId: Number(response.out_pass_id) });
        return;
      }
      Alert.alert('Denied', (response as any)?.message || 'Invalid or already used code.');
    } catch (e: any) {
      const msg = e?.response?.data?.message || e?.response?.data?.detail || 'Unable to verify. Please try again.';
      Alert.alert('Denied', msg);
    } finally {
      setIsLoading(false);
    }
  };

  const codeScanner = useCodeScanner({
    codeTypes: ['qr'],
    onCodeScanned: (codes) => {
      const value = codes[0]?.value;
      if (value) handleScanQRRef.current(value);
    },
    scanInterval: 2000,
  });

  if (showCamera) {
    if (!hasPermission) {
      return (
        <View style={styles.container}>
          <View style={styles.cameraHeader}>
            <GradientButton style={styles.backButton} onPress={() => setShowCamera(false)}>
              <Ionicons name="arrow-back" size={24} color={theme.colors.white} />
            </GradientButton>
            <Text style={styles.headerTitle}>Scan QR Code</Text>
            <View style={{ width: 24 }} />
          </View>
          <View style={styles.permissionBox}>
            <Text style={styles.permissionText}>Camera access is needed to scan the student&apos;s QR code.</Text>
            <GradientButton style={styles.permissionButton} onPress={requestPermission}>
              <Text style={styles.permissionButtonText}>Grant camera access</Text>
            </GradientButton>
            <GradientButton style={styles.backToFormButton} onPress={() => setShowCamera(false)}>
              <Text style={styles.backToFormText}>Back</Text>
            </GradientButton>
          </View>
        </View>
      );
    }

    if (!device) {
      return (
        <View style={styles.container}>
          <View style={styles.cameraHeader}>
            <GradientButton style={styles.backButton} onPress={() => setShowCamera(false)}>
              <Ionicons name="arrow-back" size={24} color={theme.colors.white} />
            </GradientButton>
            <Text style={styles.headerTitle}>Scan QR Code</Text>
            <View style={{ width: 24 }} />
          </View>
          <View style={styles.permissionBox}>
            <Text style={styles.permissionText}>No camera device found.</Text>
            <GradientButton style={styles.backToFormButton} onPress={() => setShowCamera(false)}>
              <Text style={styles.backToFormText}>Back</Text>
            </GradientButton>
          </View>
        </View>
      );
    }

    return (
      <View style={styles.container}>
        <View style={styles.cameraHeader}>
          <GradientButton style={styles.backButton} onPress={() => setShowCamera(false)}>
            <Ionicons name="arrow-back" size={24} color={theme.colors.white} />
          </GradientButton>
          <Text style={styles.headerTitle}>Scan student QR code</Text>
          <View style={{ width: 24 }} />
        </View>
        <Camera
          style={StyleSheet.absoluteFill}
          device={device}
          isActive={showCamera}
          codeScanner={codeScanner}
        />
        <View style={styles.scanOverlay}>
          <View style={styles.scanFrame} />
          <Text style={styles.scanHint}>Position the student&apos;s QR code within the frame</Text>
        </View>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="QR Scanner" />

      <View style={styles.content}>
        <View style={styles.scanButtonContainer}>
          <GradientButton
            style={[styles.scanButton, isLoading && styles.scanButtonDisabled]}
            onPress={() => setShowCamera(true)}
            disabled={isLoading}>
            {isLoading ? (
              <ActivityIndicator size="large" color={theme.colors.white} />
            ) : (
              <>
                <Ionicons name="qr-code-outline" size={64} color={theme.colors.white} />
                <Text style={styles.scanButtonText}>Scan student QR code</Text>
              </>
            )}
          </GradientButton>
        </View>

        <View style={styles.manualEntry}>
          <Text style={styles.manualLabel}>If QR scanner fails, enter 4-digit backup code:</Text>
          <TextInput
            style={styles.input}
            value={backupCode}
            onChangeText={(t) => setBackupCode(t.replace(/\D/g, '').slice(0, 4))}
            placeholder="4-digit backup code"
            placeholderTextColor={theme.colors.textSecondary}
            editable={!isLoading}
            keyboardType="number-pad"
            maxLength={4}
          />
          <GradientButton
            style={[styles.submitButton, isLoading && styles.submitButtonDisabled]}
            onPress={handleManualSubmit}
            disabled={isLoading}>
            {isLoading ? (
              <ActivityIndicator size="small" color={theme.colors.white} />
            ) : (
              <Text style={styles.submitButtonText}>Verify Code</Text>
            )}
          </GradientButton>
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
    backgroundColor: theme.colors.primary,
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
  scanButtonDisabled: {
    opacity: 0.7,
  },
  scanButtonText: {
    color: theme.colors.white,
    fontSize: 16,
    fontWeight: '600',
    marginTop: 12,
  },
  manualEntry: {
    backgroundColor: theme.colors.white,
    borderRadius: 12,
    padding: 20,
  },
  manualLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: theme.colors.textHeading,
    marginBottom: 12,
  },
  input: {
    backgroundColor: theme.colors.background,
    borderRadius: 8,
    padding: 12,
    fontSize: 16,
    color: theme.colors.text,
    borderWidth: 1,
    borderColor: theme.colors.border,
    marginBottom: 16,
  },
  submitButton: {
    backgroundColor: '#D79F24',
    borderRadius: theme.borderRadius.sm,
    padding: 14,
    alignItems: 'center',
    ...theme.shadows.medium,
  },
  submitButtonDisabled: {
    opacity: 0.7,
  },
  submitButtonText: {
    color: theme.colors.primary,
    fontSize: 16,
    fontWeight: '600',
  },
  cameraHeader: {
    backgroundColor: theme.colors.primary,
    padding: 20,
    paddingTop: 60,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    zIndex: 10,
  },
  backButton: {
    padding: 8,
    borderRadius: 999,
  },
  permissionBox: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 24,
  },
  permissionText: {
    fontSize: 16,
    color: theme.colors.text,
    textAlign: 'center',
    marginBottom: 24,
  },
  permissionButton: {
    backgroundColor: theme.colors.primary,
    paddingVertical: 14,
    paddingHorizontal: 24,
    borderRadius: 8,
    marginBottom: 16,
  },
  permissionButtonText: {
    color: theme.colors.white,
    fontSize: 16,
    fontWeight: '600',
  },
  backToFormButton: {
    paddingVertical: 12,
  },
  backToFormText: {
    color: theme.colors.primary,
    fontSize: 16,
  },
  scanOverlay: {
    ...StyleSheet.absoluteFillObject,
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 5,
    pointerEvents: 'none',
  },
  scanFrame: {
    width: 250,
    height: 250,
    borderWidth: 2,
    borderColor: theme.colors.primary,
    borderRadius: 12,
    backgroundColor: 'transparent',
  },
  scanHint: {
    marginTop: 20,
    color: theme.colors.white,
    fontSize: 14,
    backgroundColor: 'rgba(0,0,0,0.6)',
    padding: 8,
    borderRadius: 8,
  },
});
