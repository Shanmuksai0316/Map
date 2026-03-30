import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  FlatList,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  TextInput,
  Modal,
  Alert,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { api } from '../../../services/api';
import { theme } from '../../../shared/theme/theme';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface OutpassToVerify {
  id: number;
  student_name: string;
  student_id: string;
  room_number: string;
  pass_type: string;
  valid_from: string;
  valid_until: string;
  status: string;
  actual_out_time?: string;
  actual_in_time?: string;
  photo_url?: string;
}

interface Props {
  navigation: any;
}

export const VerifyTimeScreen: React.FC<Props> = ({ navigation }) => {
  const [outpasses, setOutpasses] = useState<OutpassToVerify[]>([]);
  const [_isLoading, setIsLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedOutpass, setSelectedOutpass] = useState<OutpassToVerify | null>(null);
  const [verifyModalVisible, setVerifyModalVisible] = useState(false);
  const [verifyType, setVerifyType] = useState<'out' | 'in'>('out');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const fetchOutpasses = useCallback(async () => {
    try {
      const response = await api.get('/guard/outpasses/active');
      setOutpasses(response.data.data || []);
    } catch (err) {
      console.error('Failed to fetch outpasses:', err);
      // Show empty state - no mock data in production
      setOutpasses([]);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchOutpasses();
  }, [fetchOutpasses]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await fetchOutpasses();
    setRefreshing(false);
  }, [fetchOutpasses]);

  const filteredOutpasses = outpasses.filter(
    o =>
      o.student_name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      o.student_id.toLowerCase().includes(searchQuery.toLowerCase()) ||
      o.room_number.toLowerCase().includes(searchQuery.toLowerCase())
  );

  const handleVerify = (outpass: OutpassToVerify, type: 'out' | 'in') => {
    setSelectedOutpass(outpass);
    setVerifyType(type);
    setVerifyModalVisible(true);
  };

  const confirmVerification = async () => {
    if (!selectedOutpass) return;

    setIsSubmitting(true);
    try {
      await api.post('/guard/gate/verify-time', {
        outpass_id: selectedOutpass.id,
        verification_type: verifyType,
        verified_at: new Date().toISOString(),
      });

      Alert.alert(
        'Success',
        `${verifyType === 'out' ? 'Check-out' : 'Check-in'} time recorded successfully`
      );
      setVerifyModalVisible(false);
      fetchOutpasses();
    } catch {
      Alert.alert('Error', 'Failed to record verification');
    } finally {
      setIsSubmitting(false);
    }
  };

  const formatTime = (dateString: string) => {
    return new Date(dateString).toLocaleTimeString([], {
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString([], {
      day: 'numeric',
      month: 'short',
    });
  };

  const getPassTypeLabel = (type: string) => {
    switch (type) {
      case 'day_pass':
        return 'Day Pass';
      case 'night_out':
        return 'Night Out';
      case 'weekend':
        return 'Weekend';
      case 'emergency':
        return 'Emergency';
      default:
        return type;
    }
  };

  const getPassTypeColor = (type: string) => {
    switch (type) {
      case 'day_pass':
        return theme.colors.primary;
      case 'night_out':
        return theme.colors.primaryLight;
      case 'weekend':
        return theme.colors.success;
      case 'emergency':
        return theme.colors.error;
      default:
        return theme.colors.textSecondary;
    }
  };

  const renderOutpass = ({ item }: { item: OutpassToVerify }) => (
    <View style={styles.outpassCard}>
      <View style={styles.cardHeader}>
        <View style={styles.studentInfo}>
          <Icon name="account-circle" size={40} color={theme.colors.border} />
          <View style={styles.studentDetails}>
            <Text style={styles.studentName}>{item.student_name}</Text>
            <Text style={styles.studentMeta}>
              {item.student_id} • Room {item.room_number}
            </Text>
          </View>
        </View>
        <View
          style={[
            styles.passTypeBadge,
            { backgroundColor: getPassTypeColor(item.pass_type) + '20' },
          ]}
        >
          <Text
            style={[
              styles.passTypeText,
              { color: getPassTypeColor(item.pass_type) },
            ]}
          >
            {getPassTypeLabel(item.pass_type)}
          </Text>
        </View>
      </View>

      <View style={styles.validitySection}>
        <View style={styles.validityItem}>
          <Text style={styles.validityLabel}>Valid From</Text>
          <Text style={styles.validityValue}>
            {formatDate(item.valid_from)} • {formatTime(item.valid_from)}
          </Text>
        </View>
        <View style={styles.validityItem}>
          <Text style={styles.validityLabel}>Valid Until</Text>
          <Text style={styles.validityValue}>
            {formatDate(item.valid_until)} • {formatTime(item.valid_until)}
          </Text>
        </View>
      </View>

      {/* Time Verification Status */}
      <View style={styles.verificationSection}>
        <View style={styles.verificationRow}>
          <View style={styles.verificationItem}>
            <Icon
              name={item.actual_out_time ? 'check-circle' : 'circle-outline'}
              size={18}
              color={item.actual_out_time ? theme.colors.success : theme.colors.textMuted}
            />
            <View style={styles.verificationInfo}>
              <Text style={styles.verificationLabel}>Check-out</Text>
              <Text style={styles.verificationValue}>
                {item.actual_out_time
                  ? formatTime(item.actual_out_time)
                  : 'Not recorded'}
              </Text>
            </View>
          </View>
          <View style={styles.verificationItem}>
            <Icon
              name={item.actual_in_time ? 'check-circle' : 'circle-outline'}
              size={18}
              color={item.actual_in_time ? theme.colors.success : theme.colors.textMuted}
            />
            <View style={styles.verificationInfo}>
              <Text style={styles.verificationLabel}>Check-in</Text>
              <Text style={styles.verificationValue}>
                {item.actual_in_time
                  ? formatTime(item.actual_in_time)
                  : 'Not recorded'}
              </Text>
            </View>
          </View>
        </View>
      </View>

      {/* Action Buttons */}
      <View style={styles.actionButtons}>
        {!item.actual_out_time && (
          <GradientButton
            style={[styles.actionButton, styles.outButton]}
            onPress={() => handleVerify(item, 'out')}
          >
            <Icon name="exit-run" size={18} color={theme.colors.white} />
            <Text style={styles.actionButtonText}>Record Check-out</Text>
          </GradientButton>
        )}
        {item.actual_out_time && !item.actual_in_time && (
          <GradientButton
            style={[styles.actionButton, styles.inButton]}
            onPress={() => handleVerify(item, 'in')}
          >
            <Icon name="login" size={18} color={theme.colors.white} />
            <Text style={styles.actionButtonText}>Record Check-in</Text>
          </GradientButton>
        )}
        {item.actual_out_time && item.actual_in_time && (
          <View style={styles.completedBadge}>
            <Icon name="check-all" size={18} color={theme.colors.success} />
            <Text style={styles.completedText}>Verification Complete</Text>
          </View>
        )}
      </View>
    </View>
  );

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Verify Time" />

      {/* Search */}
      <View style={styles.searchContainer}>
        <View style={styles.searchBox}>
          <Icon name="magnify" size={20} color={theme.colors.textMuted} />
          <TextInput
            style={styles.searchInput}
            value={searchQuery}
            onChangeText={setSearchQuery}
            placeholder="Search by name, ID, or room..."
            placeholderTextColor={theme.colors.textMuted}
          />
          {searchQuery.length > 0 && (
            <TouchableOpacity onPress={() => setSearchQuery('')}>
              <Icon name="close-circle" size={20} color={theme.colors.textMuted} />
            </TouchableOpacity>
          )}
        </View>
      </View>

      {/* List */}
      <FlatList
        data={filteredOutpasses}
        renderItem={renderOutpass}
        keyExtractor={item => item.id.toString()}
        contentContainerStyle={styles.listContent}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
        ListEmptyComponent={
          <View style={styles.emptyState}>
            <Icon name="clipboard-check-outline" size={64} color={theme.colors.border} />
            <Text style={styles.emptyText}>No active gate passes</Text>
            <Text style={styles.emptySubtext}>
              Gate passes to verify will appear here
            </Text>
          </View>
        }
      />

      {/* Verification Modal */}
      <Modal
        visible={verifyModalVisible}
        transparent
        animationType="fade"
        onRequestClose={() => setVerifyModalVisible(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <View style={styles.modalHeader}>
              <Icon
                name={verifyType === 'out' ? 'exit-run' : 'login'}
                size={32}
                color={theme.colors.primary}
              />
              <Text style={styles.modalTitle}>
                Confirm {verifyType === 'out' ? 'Check-out' : 'Check-in'}
              </Text>
            </View>

            {selectedOutpass && (
              <View style={styles.modalBody}>
                <Text style={styles.modalStudentName}>
                  {selectedOutpass.student_name}
                </Text>
                <Text style={styles.modalStudentMeta}>
                  {selectedOutpass.student_id} • Room {selectedOutpass.room_number}
                </Text>

                <View style={styles.modalTimeCard}>
                  <Icon name="clock-outline" size={24} color={theme.colors.primary} />
                  <View>
                    <Text style={styles.modalTimeLabel}>Current Time</Text>
                    <Text style={styles.modalTimeValue}>
                      {new Date().toLocaleTimeString([], {
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit',
                      })}
                    </Text>
                  </View>
                </View>

                <Text style={styles.modalConfirmText}>
                  Are you sure you want to record the{' '}
                  {verifyType === 'out' ? 'check-out' : 'check-in'} time?
                </Text>
              </View>
            )}

            <View style={styles.modalActions}>
              <GradientButton
                style={styles.modalCancelButton}
                onPress={() => setVerifyModalVisible(false)}
                disabled={isSubmitting}
              >
                <Text style={styles.modalCancelText}>Cancel</Text>
              </GradientButton>
              <GradientButton
                style={[
                  styles.modalConfirmButton,
                  isSubmitting && styles.buttonDisabled,
                ]}
                onPress={confirmVerification}
                disabled={isSubmitting}
              >
                <Text style={styles.modalConfirmButtonText}>
                  {isSubmitting ? 'Recording...' : 'Confirm'}
                </Text>
              </GradientButton>
            </View>
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
  searchContainer: {
    padding: 16,
  },
  searchBox: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: theme.colors.white,
    borderRadius: 12,
    paddingHorizontal: 12,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  searchInput: {
    flex: 1,
    paddingVertical: 12,
    paddingHorizontal: 8,
    fontSize: 15,
    color: theme.colors.text,
  },
  listContent: {
    padding: 16,
    paddingTop: 0,
  },
  outpassCard: {
    backgroundColor: theme.colors.white,
    borderRadius: 16,
    padding: 16,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 12,
  },
  studentInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  studentDetails: {
    marginLeft: 12,
    flex: 1,
  },
  studentName: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.text,
  },
  studentMeta: {
    fontSize: 13,
    color: theme.colors.textSecondary,
    marginTop: 2,
  },
  passTypeBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 8,
  },
  passTypeText: {
    fontSize: 12,
    fontWeight: '600',
  },
  validitySection: {
    flexDirection: 'row',
    backgroundColor: theme.colors.background,
    borderRadius: 8,
    padding: 12,
    marginBottom: 12,
  },
  validityItem: {
    flex: 1,
  },
  validityLabel: {
    fontSize: 11,
    color: theme.colors.textMuted,
    textTransform: 'uppercase',
    marginBottom: 4,
  },
  validityValue: {
    fontSize: 13,
    fontWeight: '500',
    color: theme.colors.text,
  },
  verificationSection: {
    marginBottom: 12,
  },
  verificationRow: {
    flexDirection: 'row',
  },
  verificationItem: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
  },
  verificationInfo: {
    marginLeft: 8,
  },
  verificationLabel: {
    fontSize: 11,
    color: theme.colors.textSecondary,
  },
  verificationValue: {
    fontSize: 13,
    fontWeight: '500',
    color: theme.colors.text,
  },
  actionButtons: {
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: theme.colors.divider,
  },
  actionButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 12,
    borderRadius: 10,
  },
  outButton: {
    backgroundColor: theme.colors.primary,
  },
  inButton: {
    backgroundColor: theme.colors.success,
  },
  actionButtonText: {
    color: theme.colors.white,
    fontSize: 14,
    fontWeight: '600',
    marginLeft: 8,
  },
  completedBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: theme.colors.successLight,
    paddingVertical: 10,
    borderRadius: 10,
  },
  completedText: {
    color: theme.colors.success,
    fontSize: 14,
    fontWeight: '600',
    marginLeft: 8,
  },
  emptyState: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 60,
  },
  emptyText: {
    fontSize: 16,
    fontWeight: '500',
    color: theme.colors.textSecondary,
    marginTop: 16,
  },
  emptySubtext: {
    fontSize: 14,
    color: theme.colors.textMuted,
    marginTop: 4,
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  modalContent: {
    backgroundColor: theme.colors.white,
    borderRadius: 20,
    padding: 24,
    width: '85%',
    maxWidth: 400,
  },
  modalHeader: {
    alignItems: 'center',
    marginBottom: 20,
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: theme.colors.text,
    marginTop: 12,
  },
  modalBody: {
    alignItems: 'center',
  },
  modalStudentName: {
    fontSize: 18,
    fontWeight: '600',
    color: theme.colors.text,
  },
  modalStudentMeta: {
    fontSize: 14,
    color: theme.colors.textSecondary,
    marginTop: 4,
  },
  modalTimeCard: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: theme.colors.infoLight,
    padding: 16,
    borderRadius: 12,
    marginTop: 20,
    marginBottom: 16,
    gap: 12,
  },
  modalTimeLabel: {
    fontSize: 12,
    color: theme.colors.textSecondary,
  },
  modalTimeValue: {
    fontSize: 20,
    fontWeight: '700',
    color: theme.colors.primary,
  },
  modalConfirmText: {
    fontSize: 14,
    color: theme.colors.textSecondary,
    textAlign: 'center',
    lineHeight: 20,
  },
  modalActions: {
    flexDirection: 'row',
    marginTop: 24,
    gap: 12,
  },
  modalCancelButton: {
    flex: 1,
    paddingVertical: 14,
    borderRadius: 10,
    backgroundColor: theme.colors.divider,
    alignItems: 'center',
  },
  modalCancelText: {
    fontSize: 15,
    fontWeight: '600',
    color: theme.colors.textSecondary,
  },
  modalConfirmButton: {
    flex: 1,
    paddingVertical: 14,
    borderRadius: 10,
    backgroundColor: theme.colors.primary,
    alignItems: 'center',
  },
  modalConfirmButtonText: {
    fontSize: 15,
    fontWeight: '600',
    color: theme.colors.white,
  },
  buttonDisabled: {
    opacity: 0.6,
  },
});

export default VerifyTimeScreen;
