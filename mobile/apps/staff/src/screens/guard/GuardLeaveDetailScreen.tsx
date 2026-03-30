import React from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  Modal,
} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { colors } from '../../theme/colors';
import { format } from 'date-fns';

interface Leave {
  id: number;
  student_name: string;
  room_number?: string;
  reason?: string;
  status: string;
  from_date: string;
  to_date: string;
  emergency_contact?: string;
}

interface Props {
  visible: boolean;
  leave: Leave;
  onClose: () => void;
}

export const GuardLeaveDetailScreen: React.FC<Props> = ({ visible, leave, onClose }) => {
  return (
    <Modal
      visible={visible}
      animationType="slide"
      transparent={false}
      onRequestClose={onClose}>
      <View style={styles.container}>
        <View style={styles.header}>
          <Text style={styles.headerTitle}>Leave Details</Text>
          <TouchableOpacity onPress={onClose} style={styles.closeButton}>
            <Ionicons name="close" size={24} color={colors.surface} />
          </TouchableOpacity>
        </View>

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
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: colors.primary,
    padding: 20,
    paddingTop: 60,
  },
  headerTitle: {
    color: colors.surface,
    fontSize: 20,
    fontWeight: 'bold',
  },
  closeButton: {
    padding: 8,
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
});

