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
import { apiService } from '../../../shared/services/api.service';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface GuestEntry {
  id: number;
  student_name: string;
  student_id?: string;
  room_number?: string;
  reason?: string;
  status: string;
  visitor_name?: string;
  guest_name?: string;
  guest_relationship?: string;
  guest_phone?: string;
  number_of_guests?: number;
  visit_date?: string;
  time?: string;
}

interface Props {
  visible: boolean;
  guest: GuestEntry;
  onClose: () => void;
  onMarkEntryComplete?: () => void;
}

export const GuardGuestEntryDetailScreen: React.FC<Props> = ({ visible, guest, onClose, onMarkEntryComplete }) => {
  const [isSubmitting, setIsSubmitting] = useState(false);

  const canMarkEntry = (guest?.status || '').toLowerCase() === 'approved';

  const markEntry = async () => {
    try {
      setIsSubmitting(true);
      await apiService.post(`/guard/guest-entries/${guest.id}/mark-entry`);
      Alert.alert('Success', 'Entry marked. Request completed.');
      onClose();
      onMarkEntryComplete?.();
    } catch (e: any) {
      const msg = e?.response?.data?.message || e?.response?.data?.detail || 'Failed to mark entry.';
      Alert.alert('Error', msg);
    } finally {
      setIsSubmitting(false);
    }
  };

  const guestName = guest.visitor_name || guest.guest_name || '—';

  return (
    <Modal
      visible={visible}
      animationType="slide"
      transparent={false}
      onRequestClose={onClose}>
      <View style={styles.container}>
        <StaffScreenHeader onBack={onClose} showBell={false}
          title="Guest Entry Details"
        />
        <ScrollView style={styles.content}>
          <View style={styles.detailSection}>
            <Text style={styles.label}>Request #</Text>
            <Text style={styles.value}>{guest.id}</Text>
          </View>
          <View style={styles.detailSection}>
            <Text style={styles.label}>Student Name</Text>
            <Text style={styles.value}>{guest.student_name}</Text>
          </View>
          {guest.student_id && (
            <View style={styles.detailSection}>
              <Text style={styles.label}>Student ID</Text>
              <Text style={styles.value}>{guest.student_id}</Text>
            </View>
          )}
          <View style={styles.detailSection}>
            <Text style={styles.label}>Room Number</Text>
            <Text style={styles.value}>Room {guest.room_number || 'N/A'}</Text>
          </View>
          {guest.number_of_guests != null && (
            <View style={styles.detailSection}>
              <Text style={styles.label}>Number of guests</Text>
              <Text style={styles.value}>{guest.number_of_guests}</Text>
            </View>
          )}
          {guest.visit_date && (
            <View style={styles.detailSection}>
              <Text style={styles.label}>Date of arrival</Text>
              <Text style={styles.value}>{guest.visit_date}</Text>
            </View>
          )}
          {guest.reason && (
            <View style={styles.detailSection}>
              <Text style={styles.label}>Purpose / Reason</Text>
              <Text style={styles.value}>{guest.reason}</Text>
            </View>
          )}
          <View style={styles.detailSection}>
            <Text style={styles.label}>Status</Text>
            <View style={[styles.statusBadge, { backgroundColor: '#4CAF5020' }]}>
              <Text style={[styles.statusText, { color: '#4CAF50' }]}>{guest.status.toUpperCase()}</Text>
            </View>
          </View>
          <View style={styles.detailSection}>
            <Text style={styles.label}>Guest name</Text>
            <Text style={styles.value}>{guestName}</Text>
          </View>
          {guest.guest_relationship && (
            <View style={styles.detailSection}>
              <Text style={styles.label}>Relationship with student</Text>
              <Text style={styles.value}>{guest.guest_relationship}</Text>
            </View>
          )}
          {guest.guest_phone && (
            <View style={styles.detailSection}>
              <Text style={styles.label}>Guest phone</Text>
              <Text style={styles.value}>{guest.guest_phone}</Text>
            </View>
          )}

          {canMarkEntry && (
            <GradientButton
              style={[styles.actionButton, isSubmitting && styles.actionButtonDisabled]}
              onPress={markEntry}
              disabled={isSubmitting}
            >
              {isSubmitting ? (
                <ActivityIndicator color={colors.surface} size="small" />
              ) : (
                <>
                  <Ionicons name="log-in-outline" size={18} color={colors.surface} />
                  <Text style={styles.actionButtonText}>Mark entry</Text>
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
