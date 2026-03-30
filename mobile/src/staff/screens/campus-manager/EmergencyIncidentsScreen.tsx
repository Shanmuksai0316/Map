import React, { useEffect, useCallback, useState, useRef } from 'react';
import {
  View,
  Text,
  FlatList,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  Alert,
  Animated,
  Modal,
  ScrollView,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { useAuthStore } from '../../../shared/store/auth.store';
import { useEmergencyStore } from '../../../shared/store/emergency.store';
import { theme } from '../../../shared/theme/theme';
import type { Incident } from '../../../shared/types';
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

const getIncidentTypeConfig = (type: string): { icon: string; color: string; label: string } => {
  const configs: Record<string, { icon: string; color: string; label: string }> = {
    LateReturn: { icon: 'clock-alert', color: theme.colors.warning, label: 'Late Return' },
    MissedAttendance: { icon: 'account-remove', color: theme.colors.error, label: 'Missed Attendance' },
    EmergencyExit: { icon: 'exit-run', color: '#DC2626', label: 'Emergency Exit' },
    Security: { icon: 'shield-alert', color: '#7C3AED', label: 'Security' },
    Medical: { icon: 'medical-bag', color: theme.colors.error, label: 'Medical' },
  };
  return configs[type] || { icon: 'alert-circle', color: theme.colors.textSecondary, label: type };
};

export const EmergencyIncidentsScreen: React.FC<Props> = ({ navigation }) => {
  const { user } = useAuthStore();
  const {
    incidents,
    isLoading,
    fetchIncidents,
    acknowledgeIncident,
    unacknowledgedCount,
  } = useEmergencyStore();

  const isWarden = user?.role?.toLowerCase() === 'warden';
  const [selectedIncident, setSelectedIncident] = useState<Incident | null>(null);
  const [showDetailModal, setShowDetailModal] = useState(false);

  useEffect(() => {
    fetchIncidents(1, isWarden ? false : undefined);
  }, [fetchIncidents, isWarden]);

  const onRefresh = useCallback(() => {
    fetchIncidents(1, isWarden ? false : undefined);
  }, [fetchIncidents, isWarden]);

  const handleViewDetails = (incident: Incident) => {
    setSelectedIncident(incident);
    setShowDetailModal(true);
  };

  const handleAcknowledge = async (incident: Incident) => {
    Alert.alert(
      'Acknowledge Incident',
      `Are you sure you want to acknowledge this ${incident.type} incident?`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Acknowledge',
          style: 'default',
          onPress: async () => {
            try {
              await acknowledgeIncident(incident.id);
              Alert.alert('Success', 'Incident acknowledged');
              setShowDetailModal(false);
            } catch (error) {
              Alert.alert('Error', 'Failed to acknowledge incident');
            }
          },
        },
      ]
    );
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

  const renderIncidentItem = ({ item }: { item: Incident }) => {
    const config = getIncidentTypeConfig(item.type);
    const isUnacknowledged = !item.acknowledged;

    return (
      <BlinkingCard isBlinking={isUnacknowledged} style={styles.cardWrapper}>
        <View style={[styles.incidentCard, isUnacknowledged && styles.incidentUnacknowledged]}>
          {/* Left Red Border for unacknowledged */}
          {isUnacknowledged && <View style={styles.redBorder} />}

          <View style={styles.cardContent}>
            {/* Header */}
            <View style={styles.cardHeader}>
              <View style={styles.studentInfo}>
                <Text style={styles.studentName}>{item.student?.name || 'Unknown'}</Text>
                {isUnacknowledged && (
                  <View style={styles.newBadge}>
                    <Text style={styles.newText}>NEW</Text>
                  </View>
                )}
              </View>
              <View style={[styles.typeBadge, { backgroundColor: config.color + '20' }]}>
                <Icon name={config.icon} size={14} color={config.color} />
                <Text style={[styles.typeText, { color: config.color }]}>{config.label}</Text>
              </View>
            </View>

            {/* Room Info */}
            <View style={styles.roomRow}>
              <Icon name="door" size={16} color={theme.colors.textSecondary} />
              <Text style={styles.roomText}>
                Room {item.student?.room || 'N/A'} • {item.hostel?.name || 'N/A'}
              </Text>
            </View>

            {/* Submitted Date & Time */}
            <View style={styles.dateRow}>
              <Icon name="clock-outline" size={16} color={theme.colors.textSecondary} />
              <Text style={styles.dateText}>{formatDateTime(item.opened_at)}</Text>
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
      <Icon name="shield-check" size={64} color={theme.colors.border} />
      <Text style={styles.emptyTitle}>No Incidents</Text>
      <Text style={styles.emptySubtitle}>No security incidents reported</Text>
    </View>
  );

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Incident Requests" />
      {/* Alert Banner */}
      {unacknowledgedCount > 0 && (
        <View style={styles.alertBanner}>
          <Icon name="alert" size={20} color={theme.colors.white} />
          <Text style={styles.alertText}>
            {unacknowledgedCount} unacknowledged incident{unacknowledgedCount > 1 ? 's' : ''}
          </Text>
        </View>
      )}

      {/* Incident List */}
      <FlatList
        data={incidents}
        keyExtractor={(item) => String(item.id)}
        renderItem={renderIncidentItem}
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
              <Text style={styles.modalTitle}>Incident Details</Text>
              <TouchableOpacity onPress={() => setShowDetailModal(false)}>
                <Icon name="close" size={24} color={theme.colors.text} />
              </TouchableOpacity>
            </View>

            {selectedIncident && (
              <ScrollView style={styles.modalBody}>
                {/* Student Name */}
                <View style={styles.fieldRow}>
                  <Text style={styles.fieldLabel}>Student Name</Text>
                  <Text style={styles.fieldValue}>{selectedIncident.student?.name || 'Unknown'}</Text>
                </View>

                {/* Room Number */}
                <View style={styles.fieldRow}>
                  <Text style={styles.fieldLabel}>Room Number</Text>
                  <Text style={styles.fieldValue}>
                    {selectedIncident.student?.room || 'N/A'} • {selectedIncident.hostel?.name || 'N/A'}
                  </Text>
                </View>

                {/* Submitted Date & Time */}
                <View style={styles.fieldRow}>
                  <Text style={styles.fieldLabel}>Submitted Date & Time</Text>
                  <Text style={styles.fieldValue}>{formatDateTime(selectedIncident.opened_at)}</Text>
                </View>

                {/* Incident Type */}
                <View style={styles.fieldRow}>
                  <Text style={styles.fieldLabel}>Incident Type</Text>
                  <View style={[styles.typeBadgeLarge, { backgroundColor: getIncidentTypeConfig(selectedIncident.type).color + '20' }]}>
                    <Icon name={getIncidentTypeConfig(selectedIncident.type).icon} size={16} color={getIncidentTypeConfig(selectedIncident.type).color} />
                    <Text style={[styles.typeBadgeText, { color: getIncidentTypeConfig(selectedIncident.type).color }]}>
                      {getIncidentTypeConfig(selectedIncident.type).label}
                    </Text>
                  </View>
                </View>

                {/* Note/Description */}
                <View style={styles.fieldRow}>
                  <Text style={styles.fieldLabel}>Description</Text>
                  <Text style={styles.fieldValue}>{selectedIncident.note || 'No description provided'}</Text>
                </View>

                {/* Opened By */}
                <View style={styles.fieldRow}>
                  <Text style={styles.fieldLabel}>Reported By</Text>
                  <Text style={styles.fieldValue}>{selectedIncident.opened_by?.name || 'System'}</Text>
                </View>

                {/* Status */}
                <View style={styles.fieldRow}>
                  <Text style={styles.fieldLabel}>Status</Text>
                  <View style={[
                    styles.statusBadge,
                    { backgroundColor: selectedIncident.acknowledged ? theme.colors.successLight : theme.colors.errorLight }
                  ]}>
                    <Text style={[
                      styles.statusText,
                      { color: selectedIncident.acknowledged ? theme.colors.success : theme.colors.error }
                    ]}>
                      {selectedIncident.acknowledged ? 'Acknowledged' : 'Pending'}
                    </Text>
                  </View>
                </View>

                {/* Acknowledged Info */}
                {selectedIncident.acknowledged && selectedIncident.acknowledged_at && (
                  <View style={styles.acknowledgedInfo}>
                    <Icon name="check-circle" size={16} color={theme.colors.success} />
                    <Text style={styles.acknowledgedInfoText}>
                      Acknowledged by {selectedIncident.acknowledged_by?.name} at {formatDateTime(selectedIncident.acknowledged_at)}
                    </Text>
                  </View>
                )}

                {/* Acknowledge Button (Warden only; Campus Manager is view-only) */}
                {!selectedIncident.acknowledged && isWarden && (
                  <GradientButton
                    style={styles.acknowledgeButton}
                    onPress={() => handleAcknowledge(selectedIncident)}
                  >
                    <Icon name="check" size={20} color={theme.colors.white} />
                    <Text style={styles.acknowledgeButtonText}>Acknowledge Incident</Text>
                  </GradientButton>
                )}
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
  header: {
    backgroundColor: theme.colors.warning,
    paddingBottom: 20,
    paddingHorizontal: 20,
    flexDirection: 'row',
    alignItems: 'center',
  },
  headerTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: theme.colors.white,
  },
  headerSubtitle: {
    fontSize: 14,
    color: 'rgba(255, 255, 255, 0.8)',
    marginTop: 2,
  },
  alertBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: theme.colors.error,
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
  incidentCard: {
    flexDirection: 'row',
    backgroundColor: theme.colors.card,
    borderRadius: 12,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  incidentUnacknowledged: {
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
  newBadge: {
    backgroundColor: theme.colors.error,
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 8,
  },
  newText: {
    color: theme.colors.white,
    fontSize: 10,
    fontWeight: '700',
  },
  typeBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
    gap: 4,
  },
  typeText: {
    fontSize: 11,
    fontWeight: '600',
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
  typeBadgeLarge: {
    flexDirection: 'row',
    alignItems: 'center',
    alignSelf: 'flex-start',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 12,
    gap: 6,
  },
  typeBadgeText: {
    fontSize: 14,
    fontWeight: '600',
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
  acknowledgeButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: theme.colors.warning,
    paddingVertical: 16,
    borderRadius: 10,
    gap: 8,
    marginBottom: 20,
  },
  acknowledgeButtonText: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.white,
  },
});

export default EmergencyIncidentsScreen;
