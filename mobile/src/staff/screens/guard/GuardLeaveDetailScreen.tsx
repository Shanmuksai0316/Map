import React, { useMemo, useState } from 'react';
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

interface Leave {
  id: number;
  student_name: string;
  room_number?: string;
  reason?: string;
  status: string;
  from_date: string;
  to_date: string;
  emergency_contact?: string;
  actual_departure_time?: string | null;
}

interface Props {
  visible: boolean;
  leave: Leave;
  onClose: () => void;
  onMarkedComplete?: () => void;
}

export const GuardLeaveDetailScreen: React.FC<Props> = ({ visible, leave, onClose, onMarkedComplete }) => {
  const [isSubmitting, setIsSubmitting] = useState(false);

  const canRecordExit = useMemo(() => {
    return (leave?.status || '').toLowerCase() === 'approved' && !leave?.actual_departure_time;
  }, [leave]);

  const recordExitTime = async () => {
    try {
      setIsSubmitting(true);
      await apiService.post('/guard/verify-time', {
        type: 'leave',
        id: leave.id,
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
          title="Leave Details"
        />
        <ScrollView style={styles.content}>
          <View style={styles.detailSection}>
            <Text style={styles.label}>Student Name</Text>
            <Text style={styles.value}>{leave.student_name}</Text>
          </View>

          <View style={styles.detailSection}>
            <Text style={styles.label}>Room Number</Text>
            <Text style={styles.value}>Room {leave.room_number || 'N/A'}</Text>
          </View>

          {leave.reason && (
            <View style={styles.detailSection}>
              <Text style={styles.label}>Reason</Text>
              <Text style={styles.value}>{leave.reason}</Text>
            </View>
          )}

          <View style={styles.detailSection}>
            <Text style={styles.label}>Status</Text>
            <View style={[styles.statusBadge, { backgroundColor: '#4CAF50' }]}>
              <Text style={styles.statusText}>{leave.status.toUpperCase()}</Text>
            </View>
          </View>

          <View style={styles.detailSection}>
            <Text style={styles.label}>From Date</Text>
            <Text style={styles.value}>{format(new Date(leave.from_date), 'MMM dd, yyyy')}</Text>
          </View>

          <View style={styles.detailSection}>
            <Text style={styles.label}>To Date</Text>
            <Text style={styles.value}>{format(new Date(leave.to_date), 'MMM dd, yyyy')}</Text>
          </View>

          {leave.emergency_contact && (
            <View style={styles.detailSection}>
              <Text style={styles.label}>Emergency Contact</Text>
              <Text style={styles.value}>{leave.emergency_contact}</Text>
            </View>
          )}

          {canRecordExit && (
            <GradientButton
              style={[styles.actionButton, isSubmitting && styles.actionButtonDisabled]}
              onPress={recordExitTime}
              disabled={isSubmitting}
            >
              {isSubmitting ? (
                <ActivityIndicator color={colors.surface} />
              ) : (
                <>
                  <Ionicons name="checkmark-circle-outline" size={18} color={colors.surface} />
                  <Text style={styles.actionButtonText}>Mark as exit</Text>
                </>
              )}
            </GradientButton>
          )}
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
    marginTop: 12,
    backgroundColor: colors.primary,
    paddingVertical: 12,
    borderRadius: 10,
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'center',
    gap: 8,
  },
  actionButtonDisabled: {
    opacity: 0.7,
  },
  actionButtonText: {
    color: colors.surface,
    fontSize: 14,
    fontWeight: '600',
  },
});
