import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity } from 'react-native';
import QRCode from 'react-native-qrcode-svg';
import { useAuthStore } from '../store/auth.store';

interface StudentQRCodeProps {
  size?: number;
  showLabel?: boolean;
  onClose?: () => void;
}

export const StudentQRCode: React.FC<StudentQRCodeProps> = ({
  size = 200,
  showLabel = true,
  onClose,
}) => {
  const { user } = useAuthStore();

  if (!user || !user.student_uid) {
    return (
      <View style={styles.errorContainer}>
        <Text style={styles.errorText}>Unable to generate QR code</Text>
        <Text style={styles.errorSubtext}>Student information not available</Text>
      </View>
    );
  }

  // Generate QR data: MAP Student ID format
  // Format: MAP-STUDENT-{tenant_id}-{student_uid}
  const qrData = JSON.stringify({
    type: 'MAP_STUDENT_ID',
    tenant_id: user.tenant_id,
    student_uid: user.student_uid,
    name: user.name,
    hostel_id: user.hostel_id,
    timestamp: new Date().toISOString(),
  });

  return (
    <View style={styles.container}>
      {showLabel && (
        <View style={styles.header}>
          <Text style={styles.title}>Student Gate Pass</Text>
          <Text style={styles.subtitle}>Show this QR code to security</Text>
        </View>
      )}

      <View style={styles.qrContainer}>
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

      <View style={styles.infoContainer}>
        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Name:</Text>
          <Text style={styles.infoValue}>{user.name}</Text>
        </View>
        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Student ID:</Text>
          <Text style={styles.infoValue}>{user.student_uid}</Text>
        </View>
        {user.hostel_name && (
          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>Hostel:</Text>
            <Text style={styles.infoValue}>{user.hostel_name}</Text>
          </View>
        )}
      </View>

      <View style={styles.instructions}>
        <Text style={styles.instructionsTitle}>Instructions:</Text>
        <Text style={styles.instructionsText}>
          • Present this QR code at the security gate
        </Text>
        <Text style={styles.instructionsText}>
          • Ensure you have an approved gate pass
        </Text>
        <Text style={styles.instructionsText}>
          • QR code refreshes automatically
        </Text>
      </View>

      {onClose && (
        <TouchableOpacity style={styles.closeButton} onPress={onClose}>
          <Text style={styles.closeButtonText}>Close</Text>
        </TouchableOpacity>
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
  errorContainer: {
    padding: 40,
    alignItems: 'center',
  },
  errorText: {
    fontSize: 18,
    fontWeight: '600',
    color: '#d32f2f',
    marginBottom: 8,
  },
  errorSubtext: {
    fontSize: 14,
    color: '#666',
    textAlign: 'center',
  },
});

export default StudentQRCode;

