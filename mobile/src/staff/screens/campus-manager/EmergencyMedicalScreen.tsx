import React, { useEffect, useCallback, useRef } from 'react';
import {
  View,
  Text,
  FlatList,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  Alert,
  Linking,
  Animated,
  Modal,
  ScrollView,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { useAuthStore } from '../../../shared/store/auth.store';
import { useEmergencyStore } from '../../../shared/store/emergency.store';
import { theme } from '../../../shared/theme/theme';
import type { MedicalEmergency } from '../../../shared/types';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface Props {
  navigation: any;
}

// Blinking Card Component for unacknowledged items
const BlinkingCard: React.FC<{
  children: React.ReactNode;
  isBlinking: boolean;
  style?: any;
}> = ({ children, isBlinking, style }) => {
  const blinkAnim = useRef(new Animated.Value(1)).current;

  useEffect(() => {
    if (isBlinking) {
      const blink = Animated.loop(
        Animated.sequence([
          Animated.timing(blinkAnim, {
            toValue: 0.3,
            duration: 500,
            useNativeDriver: true,
          }),
          Animated.timing(blinkAnim, {
            toValue: 1,
            duration: 500,
            useNativeDriver: true,
          }),
        ])
      );
      blink.start();
      return () => blink.stop();
    } else {
      blinkAnim.setValue(1);
    }
  }, [isBlinking, blinkAnim]);

  return (
    <Animated.View style={[style, isBlinking && { opacity: blinkAnim }]}>
      {children}
    </Animated.View>
  );
};

export const EmergencyMedicalScreen: React.FC<Props> = ({ navigation }) => {
  const { user } = useAuthStore();
  const {
    medicalEmergencies,
    isLoading,
    fetchMedicalEmergencies,
    acknowledgeMedical,
  } = useEmergencyStore();

  const isWarden = user?.role?.toLowerCase() === 'warden';
  const [selectedEmergency, setSelectedEmergency] = React.useState<MedicalEmergency | null>(null);
  const [showDetailModal, setShowDetailModal] = React.useState(false);

  useEffect(() => {
    fetchMedicalEmergencies(1, isWarden ? false : undefined);
  }, [fetchMedicalEmergencies, isWarden]);

  const onRefresh = useCallback(() => {
    fetchMedicalEmergencies(1, isWarden ? false : undefined);
  }, [fetchMedicalEmergencies, isWarden]);

  const handleViewDetails = (emergency: MedicalEmergency) => {
    setSelectedEmergency(emergency);
    setShowDetailModal(true);
  };

  const handleAcknowledge = async (emergency: MedicalEmergency) => {
    Alert.alert(
      'Acknowledge Emergency',
      `Are you sure you want to acknowledge this medical emergency for ${emergency.student_name}?`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Acknowledge',
          style: 'default',
          onPress: async () => {
            try {
              await acknowledgeMedical(emergency.id);
              Alert.alert('Success', 'Emergency acknowledged');
              setShowDetailModal(false);
            } catch (error) {
              Alert.alert('Error', 'Failed to acknowledge emergency');
            }
          },
        },
      ]
    );
  };

  const handleCallStudent = (phone: string) => {
    Linking.openURL(`tel:${phone}`);
  };

  const formatDateTime = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const renderEmergencyItem = ({ item }: { item: MedicalEmergency }) => {
    const isUnacknowledged = !item.acknowledged;

    return (
      <BlinkingCard isBlinking={isUnacknowledged} style={styles.cardWrapper}>
        <View style={[styles.emergencyCard, isUnacknowledged && styles.emergencyUnacknowledged]}>
          {/* Left Red Border for unacknowledged */}
          {isUnacknowledged && <View style={styles.redBorder} />}

          <View style={styles.cardContent}>
            {/* Header */}
            <View style={styles.cardHeader}>
              <View style={styles.studentInfo}>
                <Text style={styles.studentName}>{item.student_name}</Text>
                {isUnacknowledged && (
                  <View style={styles.urgentBadge}>
                    <Icon name="alert" size={12} color={theme.colors.white} />
                    <Text style={styles.urgentText}>URGENT</Text>
                  </View>
                )}
              </View>
            </View>

            {/* Room Info */}
            <View style={styles.roomRow}>
              <Icon name="door" size={16} color={theme.colors.textSecondary} />
              <Text style={styles.roomText}>
                Room {item.room || 'N/A'} • {item.hostel || 'N/A'}
              </Text>
            </View>

            {/* Submitted Date & Time */}
            <View style={styles.dateRow}>
              <Icon name="clock-outline" size={16} color={theme.colors.textSecondary} />
              <Text style={styles.dateText}>{formatDateTime(item.created_at)}</Text>
            </View>

            {/* View Action */}
            <GradientButton
              style={styles.viewButton}
              onPress={() => handleViewDetails(item)}
            >
              <Text style={styles.viewButtonText}>View Details</Text>
              <Icon name="chevron-right" size={18} color={theme.colors.primary} />
            </GradientButton>
          </View>
        </View>
      </BlinkingCard>
    );
  };

  const renderEmptyState = () => (
    <View style={styles.emptyState}>
      <Icon name="medical-bag" size={64} color={theme.colors.border} />
      <Text style={styles.emptyTitle}>No Medical Emergencies</Text>
      <Text style={styles.emptySubtitle}>
        No students currently require medical attention
      </Text>
    </View>
  );

  const unacknowledgedCount = medicalEmergencies.filter((e) => !e.acknowledged).length;

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Medical Requests" />
      {/* Alert Banner */}
      {unacknowledgedCount > 0 && (
        <View style={styles.alertBanner}>
          <Icon name="alert" size={20} color={theme.colors.white} />
          <Text style={styles.alertText}>
            {unacknowledgedCount} unacknowledged emergency{unacknowledgedCount > 1 ? 's' : ''}
          </Text>
        </View>
      )}

      {/* Emergency List */}
      <FlatList
        data={medicalEmergencies}
        keyExtractor={(item) => String(item.id)}
        renderItem={renderEmergencyItem}
        ListEmptyComponent={!isLoading ? renderEmptyState : null}
        contentContainerStyle={styles.listContent}
        refreshControl={
          <RefreshControl refreshing={isLoading} onRefresh={onRefresh} />
        }
      />

      {/* Detail Modal */}
      <Modal
        visible={showDetailModal}
        animationType="slide"
        transparent
        onRequestClose={() => setShowDetailModal(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Medical Emergency</Text>
              <TouchableOpacity onPress={() => setShowDetailModal(false)}>
                <Icon name="close" size={24} color={theme.colors.text} />
              </TouchableOpacity>
            </View>

            {selectedEmergency && (
              <ScrollView style={styles.modalBody}>
                {/* Student Name */}
                <View style={styles.fieldRow}>
                  <Text style={styles.fieldLabel}>Student Name</Text>
                  <Text style={styles.fieldValue}>{selectedEmergency.student_name}</Text>
                </View>

                {/* Room Number */}
                <View style={styles.fieldRow}>
                  <Text style={styles.fieldLabel}>Room Number</Text>
                  <Text style={styles.fieldValue}>
                    {selectedEmergency.room || 'N/A'} • {selectedEmergency.hostel || 'N/A'}
                  </Text>
                </View>

                {/* Submitted Date & Time */}
                <View style={styles.fieldRow}>
                  <Text style={styles.fieldLabel}>Submitted Date & Time</Text>
                  <Text style={styles.fieldValue}>{formatDateTime(selectedEmergency.created_at)}</Text>
                </View>

                {/* Symptoms */}
                <View style={styles.fieldRow}>
                  <Text style={styles.fieldLabel}>Symptoms / Description</Text>
                  <Text style={styles.fieldValue}>{selectedEmergency.symptoms || 'No symptoms provided'}</Text>
                </View>

                {/* Status */}
                <View style={styles.fieldRow}>
                  <Text style={styles.fieldLabel}>Status</Text>
                  <View style={[
                    styles.statusBadge,
                    { backgroundColor: selectedEmergency.acknowledged ? theme.colors.successLight : theme.colors.errorLight }
                  ]}>
                    <Text style={[
                      styles.statusText,
                      { color: selectedEmergency.acknowledged ? theme.colors.success : theme.colors.error }
                    ]}>
                      {selectedEmergency.acknowledged ? 'Acknowledged' : 'Pending'}
                    </Text>
                  </View>
                </View>

                {/* Acknowledged Info */}
                {selectedEmergency.acknowledged && selectedEmergency.acknowledged_at && (
                  <View style={styles.acknowledgedInfo}>
                    <Icon name="check-circle" size={16} color={theme.colors.success} />
                    <Text style={styles.acknowledgedInfoText}>
                      Acknowledged by {selectedEmergency.acknowledged_by} at {formatDateTime(selectedEmergency.acknowledged_at)}
                    </Text>
                  </View>
                )}

                {/* Action Buttons */}
                <View style={styles.modalActions}>
                  {selectedEmergency.student_phone && (
                    <GradientButton
                      style={styles.callStudentButton}
                      onPress={() => handleCallStudent(selectedEmergency.student_phone!)}
                    >
                      <Icon name="phone" size={20} color={theme.colors.white} />
                      <Text style={styles.callButtonText}>Call Student</Text>
                    </GradientButton>
                  )}

                  {!selectedEmergency.acknowledged && isWarden && (
                    <GradientButton
                      style={styles.acknowledgeButton}
                      onPress={() => handleAcknowledge(selectedEmergency)}
                    >
                      <Icon name="check" size={20} color={theme.colors.white} />
                      <Text style={styles.acknowledgeButtonText}>Acknowledge</Text>
                    </GradientButton>
                  )}
                </View>
              </ScrollView>
            )}
          </View>
        </View>
      </Modal>
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
  subHeaderSubtitle: {
    fontSize: 14,
    color: theme.colors.textMuted,
    marginTop: 4,
  },
  alertBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#DC2626',
    paddingVertical: 12,
    paddingHorizontal: 16,
  },
  alertText: {
    color: theme.colors.white,
    fontSize: 14,
    fontWeight: '500',
    marginLeft: 10,
  },
  listContent: {
    padding: 16,
    flexGrow: 1,
  },
  cardWrapper: {
    marginBottom: 12,
  },
  emergencyCard: {
    flexDirection: 'row',
    backgroundColor: theme.colors.card,
    borderRadius: 12,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  emergencyUnacknowledged: {
    borderColor: theme.colors.error,
    backgroundColor: theme.colors.errorLight,
  },
  redBorder: {
    width: 4,
    backgroundColor: theme.colors.error,
  },
  cardContent: {
    flex: 1,
    padding: 16,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 8,
  },
  studentInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
    flexWrap: 'wrap',
    gap: 8,
  },
  studentName: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.text,
  },
  urgentBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: theme.colors.error,
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
    gap: 4,
  },
  urgentText: {
    fontSize: 10,
    fontWeight: '700',
    color: theme.colors.white,
  },
  roomRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 6,
    gap: 6,
  },
  roomText: {
    fontSize: 14,
    color: theme.colors.textSecondary,
  },
  dateRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 12,
    gap: 6,
  },
  dateText: {
    fontSize: 13,
    color: theme.colors.textSecondary,
  },
  viewButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: theme.colors.surfaceMuted,
    paddingVertical: 10,
    borderRadius: 8,
    gap: 4,
  },
  viewButtonText: {
    fontSize: 14,
    fontWeight: '600',
    color: theme.colors.primary,
  },
  emptyState: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: 48,
  },
  emptyTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: theme.colors.textSecondary,
    marginTop: 16,
  },
  emptySubtitle: {
    fontSize: 14,
    color: theme.colors.textMuted,
    marginTop: 4,
    textAlign: 'center',
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'flex-end',
  },
  modalContent: {
    backgroundColor: theme.colors.background,
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    maxHeight: '85%',
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 20,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: theme.colors.text,
  },
  modalBody: {
    padding: 20,
  },
  fieldRow: {
    marginBottom: 16,
  },
  fieldLabel: {
    fontSize: 13,
    fontWeight: '500',
    color: theme.colors.textSecondary,
    marginBottom: 4,
  },
  fieldValue: {
    fontSize: 15,
    fontWeight: '500',
    color: theme.colors.text,
    lineHeight: 22,
  },
  statusBadge: {
    alignSelf: 'flex-start',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 12,
  },
  statusText: {
    fontSize: 14,
    fontWeight: '600',
  },
  acknowledgedInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: theme.colors.successLight,
    padding: 12,
    borderRadius: 8,
    marginBottom: 16,
    gap: 8,
  },
  acknowledgedInfoText: {
    flex: 1,
    fontSize: 13,
    color: theme.colors.success,
  },
  modalActions: {
    flexDirection: 'row',
    gap: 12,
    marginTop: 8,
    paddingBottom: 20,
  },
  callStudentButton: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: theme.colors.success,
    paddingVertical: 14,
    borderRadius: 10,
    gap: 8,
  },
  callButtonText: {
    fontSize: 15,
    fontWeight: '600',
    color: theme.colors.white,
  },
  acknowledgeButton: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: theme.colors.error,
    paddingVertical: 14,
    borderRadius: 10,
    gap: 8,
  },
  acknowledgeButtonText: {
    fontSize: 15,
    fontWeight: '600',
    color: theme.colors.white,
  },
});

export default EmergencyMedicalScreen;
