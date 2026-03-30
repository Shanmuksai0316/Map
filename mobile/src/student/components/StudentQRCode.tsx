import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity } from 'react-native';
import { GradientButton } from '../../shared/components/GradientButton';
import QRCode from 'react-native-qrcode-svg';

interface StudentQRCodeProps {
  size?: number;
  showLabel?: boolean;
  onClose?: () => void;
  outPassId: string;
  backupCode?: string | null;
  validUntil: string;
  uniqueId: string;
}

export const StudentQRCode: React.FC<StudentQRCodeProps> = ({
  size = 200,
  showLabel = true,
  onClose,
  outPassId,
  backupCode,
  validUntil,
  uniqueId,
}) => {
  const expiryDate = new Date(validUntil);
  const isExpired = Number.isFinite(expiryDate.getTime()) ? Date.now() > expiryDate.getTime() : false;

  // QR contains only the outpass id; backup code (if present) is optional.
  const qrData = JSON.stringify({
    type: 'OUTPASS_GATE_PASS',
    out_pass_id: outPassId,
    ...(backupCode ? { backup_code: backupCode } : {}),
  });

  return (
    <View style={styles.container}>
      {showLabel && (
        <View style={styles.header}>
          <Text style={styles.title}>Gate Pass</Text>
          <Text style={styles.subtitle}>Show this QR code to security</Text>
        </View>
      )}

      <View style={styles.qrContainer}>
        <View style={[styles.qrInner, isExpired && styles.qrExpired]}>
        <QRCode
          value={qrData}
          size={size}
          backgroundColor="white"
          color="black"
          // logo={require('../../assets/icon.png')}
          // logoSize={size * 0.2}
          // logoBackgroundColor="white"
          // logoBorderRadius={8}
        />
        </View>
        {isExpired && (
          <View style={styles.expiredOverlay}>
            <Text style={styles.expiredText}>Expired</Text>
          </View>
        )}
      </View>

      <View style={styles.infoContainer}>
        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Gate Pass ID:</Text>
          <Text style={styles.infoValue}>{uniqueId}</Text>
        </View>
        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Expires:</Text>
          <Text style={styles.infoValue}>{Number.isFinite(expiryDate.getTime()) ? expiryDate.toLocaleString() : 'N/A'}</Text>
        </View>
      </View>

      {backupCode ? (
        <View style={styles.backupCodeContainer}>
          <Text style={styles.backupCodeLabel}>Use this if scanner fails</Text>
          <Text style={styles.backupCodeValue}>{backupCode}</Text>
        </View>
      ) : (
        <View style={[styles.backupCodeContainer, styles.backupCodeMissing]}>
          <Text style={styles.backupCodeLabel}>Backup code not available</Text>
          <Text style={styles.backupCodeMissingText}>Ask the guard to scan the QR (no code needed).</Text>
        </View>
      )}

      <View style={styles.instructions}>
        <Text style={styles.instructionsTitle}>Instructions:</Text>
        <Text style={styles.instructionsText}>
          • Present this QR code at the security gate
        </Text>
        <Text style={styles.instructionsText}>
          • This QR is valid only until the expiry time
        </Text>
        <Text style={styles.instructionsText}>
          • If scan fails, tell the guard the 4-digit backup code
        </Text>
      </View>

      {onClose && (
        <GradientButton style={styles.closeButton} onPress={onClose}>
          <Text style={styles.closeButtonText}>Close</Text>
        </GradientButton>
      )}

      <Text style={styles.footer}>
        MAP HMS • Secure Gate Pass System
      </Text>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    alignItems: 'center',
    padding: 20,
    backgroundColor: '#fff',
  },
  header: {
    alignItems: 'center',
    marginBottom: 24,
  },
  title: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#1a1a1a',
    marginBottom: 8,
  },
  subtitle: {
    fontSize: 16,
    color: '#666',
    textAlign: 'center',
  },
  qrContainer: {
    padding: 20,
    backgroundColor: '#fff',
    borderRadius: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.1,
    shadowRadius: 8,
    elevation: 5,
    marginBottom: 24,
  },
  qrInner: {
    backgroundColor: '#fff',
  },
  qrExpired: {
    opacity: 0.3,
  },
  expiredOverlay: {
    position: 'absolute',
    left: 0,
    right: 0,
    top: 0,
    bottom: 0,
    alignItems: 'center',
    justifyContent: 'center',
  },
  expiredText: {
    fontSize: 28,
    fontWeight: '800',
    color: '#d32f2f',
    backgroundColor: 'rgba(255,255,255,0.9)',
    paddingHorizontal: 14,
    paddingVertical: 6,
    borderRadius: 10,
    overflow: 'hidden',
  },
  infoContainer: {
    width: '100%',
    backgroundColor: '#f8f9fa',
    borderRadius: 12,
    padding: 16,
    marginBottom: 20,
  },
  infoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  infoLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: '#666',
  },
  infoValue: {
    fontSize: 14,
    color: '#1a1a1a',
    fontWeight: '500',
  },
  backupCodeContainer: {
    width: '100%',
    backgroundColor: '#fff7ed',
    borderRadius: 12,
    padding: 16,
    marginBottom: 20,
    borderWidth: 1,
    borderColor: '#fed7aa',
    alignItems: 'center',
  },
  backupCodeLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: '#9a3412',
    marginBottom: 6,
  },
  backupCodeValue: {
    fontSize: 32,
    fontWeight: '800',
    letterSpacing: 6,
    color: '#9a3412',
  },
  backupCodeMissing: {
    backgroundColor: '#f3f4f6',
    borderColor: '#e5e7eb',
  },
  backupCodeMissingText: {
    fontSize: 13,
    color: '#374151',
    textAlign: 'center',
    marginTop: 6,
    lineHeight: 18,
  },
  instructions: {
    width: '100%',
    backgroundColor: '#e3f2fd',
    borderRadius: 12,
    padding: 16,
    marginBottom: 20,
  },
  instructionsTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: '#1976d2',
    marginBottom: 8,
  },
  instructionsText: {
    fontSize: 14,
    color: '#424242',
    marginBottom: 4,
    lineHeight: 20,
  },
  closeButton: {
    width: '100%',
    backgroundColor: '#4CAF50',
    padding: 16,
    borderRadius: 12,
    alignItems: 'center',
    marginBottom: 12,
  },
  closeButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
  footer: {
    fontSize: 12,
    color: '#999',
    textAlign: 'center',
    marginTop: 8,
  },
});

export default StudentQRCode;

