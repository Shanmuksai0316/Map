import React, { useState } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  Modal,
  Alert,
  ActivityIndicator,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { colors } from '../../../shared/theme/colors';
import { format } from 'date-fns';
import { apiService } from '../../../shared/services/api.service';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface Outpass {
  id: number;
  student_name: string;
  room_number?: string;
  reason: string;
  status: string;
  out_date: string;
  out_time: string;
  expected_in_date: string;
  expected_in_time: string;
}

interface Props {
  visible: boolean;
  outpass: Outpass;
  onClose: () => void;
  onMarkedComplete?: () => void;
}

export const GuardOutpassDetailScreen: React.FC<Props> = ({ visible, outpass, onClose, onMarkedComplete }) => {
  const [isSubmitting, setIsSubmitting] = useState(false);

  const markAsCompleted = async () => {
    try {
      setIsSubmitting(true);
      await apiService.post('/guard/verify-time', {
        type: 'outpass',
        id: outpass.id,
        direction: 'out',
        timestamp: new Date().toISOString(),
      });
      Alert.alert('Success', 'Verified at gate. Request marked as completed.');
      onClose();
      onMarkedComplete?.();
    } catch (e) {
      Alert.alert('Error', 'Failed to record. Please try again.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <Modal
      visible={visible}
      animationType="slide"
      transparent={false}
      onRequestClose={onClose}>
      <View style={styles.container}>
        <StaffScreenHeader onBack={onClose} showBell={false}
          title="Outpass Details"
        />
        <ScrollView style={styles.content}>
          <View style={styles.detailSection}>
            <Text style={styles.label}>Student Name</Text>
            <Text style={styles.value}>{outpass.student_name}</Text>
          </View>

          <View style={styles.detailSection}>
            <Text style={styles.label}>Room Number</Text>
            <Text style={styles.value}>Room {outpass.room_number || 'N/A'}</Text>
          </View>

          <View style={styles.detailSection}>
            <Text style={styles.label}>Reason</Text>
            <Text style={styles.value}>{outpass.reason}</Text>
          </View>

          <View style={styles.detailSection}>
            <Text style={styles.label}>Status</Text>
            <View style={[styles.statusBadge, { backgroundColor: '#4CAF50' }]}>
              <Text style={styles.statusText}>{outpass.status.toUpperCase()}</Text>
            </View>
          </View>

          <View style={styles.detailSection}>
            <Text style={styles.label}>Exit Time</Text>
            <Text style={styles.value}>
              {format(new Date(outpass.out_date), 'MMM dd, yyyy')} at {outpass.out_time}
            </Text>
          </View>

          <View style={styles.detailSection}>
            <Text style={styles.label}>Entry Time</Text>
            <Text style={styles.value}>
              {format(new Date(outpass.expected_in_date), 'MMM dd, yyyy')} at {outpass.expected_in_time}
            </Text>
          </View>

          <GradientButton
            style={[styles.actionButton, isSubmitting && styles.actionButtonDisabled]}
            onPress={markAsCompleted}
            disabled={isSubmitting}
          >
            {isSubmitting ? (
              <ActivityIndicator color={colors.surface} size="small" />
            ) : (
              <>
                <Ionicons name="checkmark-circle-outline" size={18} color={colors.surface} />
                <Text style={styles.actionButtonText}>Mark as exit</Text>
              </>
            )}
          </GradientButton>
        </ScrollView>
      </View>
    </Modal>
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
  },
  detailSection: {
    marginBottom: 24,
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textMuted,
    marginBottom: 8,
  },
  value: {
    fontSize: 16,
    color: colors.textPrimary,
  },
  statusBadge: {
    alignSelf: 'flex-start',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 8,
  },
  statusText: {
    color: colors.surface,
    fontSize: 12,
    fontWeight: '600',
  },
  actionButton: {
    marginTop: 24,
    backgroundColor: colors.primary,
    paddingVertical: 14,
    borderRadius: 10,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  actionButtonDisabled: { opacity: 0.7 },
  actionButtonText: { color: colors.surface, fontSize: 16, fontWeight: '600' },
});
