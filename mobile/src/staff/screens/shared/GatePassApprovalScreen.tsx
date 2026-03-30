import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  Alert,
  RefreshControl,
  ActivityIndicator,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useAuthStore } from '../../../shared/store/auth.store';
import { apiService } from '../../../shared/services/api.service';
import { APP_CONFIG } from '../../../shared/config/app.config';
import { GatePass } from '../../../shared/types';
import { format } from 'date-fns';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';
import { theme } from '../../../shared/theme/theme';

export const GatePassApprovalScreen = ({ navigation }: any) => {
  const { user } = useAuthStore();
  const [pendingPasses, setPendingPasses] = useState<GatePass[]>([]);
  const [selectedPassIds, setSelectedPassIds] = useState<Set<number>>(new Set());
  const [isSelectAll, setIsSelectAll] = useState(false);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [processingId, setProcessingId] = useState<number | null>(null);
  const [isBulkProcessing, setIsBulkProcessing] = useState(false);

  const fetchPendingPasses = async () => {
    try {
      const response = await apiService.get<{ data: GatePass[] }>(
        `${APP_CONFIG.ENDPOINTS.RECTOR_APPROVALS}?status=pending&limit=50`
      );
      setPendingPasses(response.data);
    } catch (error) {
      console.error('Error fetching pending gate passes:', error);
      Alert.alert('Error', 'Failed to fetch pending gate passes');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchPendingPasses();
  }, []);

  const onRefresh = () => {
    setRefreshing(true);
    fetchPendingPasses();
  };

  const handleApprove = async (pass: GatePass) => {
    setProcessingId(pass.id);

    try {
      await apiService.put(
        `/mobile/rector/outpasses/${pass.id}/approve`,
        {
          approved_by: user?.name,
          approved_at: new Date().toISOString(),
        }
      );

      Alert.alert('Success', 'Gate pass approved successfully');
      fetchPendingPasses();
    } catch (error: any) {
      console.error('Approval error:', error);
      Alert.alert('Error', 'Failed to approve gate pass');
    } finally {
      setProcessingId(null);
    }
  };

  const handleToggleSelect = (passId: number) => {
    const newSelected = new Set(selectedPassIds);
    if (newSelected.has(passId)) {
      newSelected.delete(passId);
    } else {
      newSelected.add(passId);
    }
    setSelectedPassIds(newSelected);
    setIsSelectAll(newSelected.size === pendingPasses.length);
  };

  const handleSelectAll = () => {
    if (isSelectAll) {
      setSelectedPassIds(new Set());
      setIsSelectAll(false);
    } else {
      const allIds = new Set(pendingPasses.map(pass => pass.id));
      setSelectedPassIds(allIds);
      setIsSelectAll(true);
    }
  };

  const handleBulkApprove = async () => {
    if (selectedPassIds.size === 0) {
      Alert.alert('No Selection', 'Please select at least one gate pass to approve');
      return;
    }

    Alert.alert(
      'Bulk Approve',
      `Are you sure you want to approve ${selectedPassIds.size} gate pass${selectedPassIds.size > 1 ? 'es' : ''}?`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Approve',
          style: 'default',
          onPress: async () => {
            try {
              setIsBulkProcessing(true);

              await apiService.post<{
                approved: number;
                failed: number;
                errors: string[];
              }>(
                `${APP_CONFIG.ENDPOINTS.RECTOR_APPROVALS}/bulk`,
                {
                  outpass_ids: Array.from(selectedPassIds),
                  action: 'approve',
                }
              );

              Alert.alert(
                'Success',
                `${selectedPassIds.size} gate pass${selectedPassIds.size > 1 ? 'es' : ''} approved successfully`
              );
              
              // Clear selection and refresh
              setSelectedPassIds(new Set());
              setIsSelectAll(false);
              fetchPendingPasses();
            } catch (error: any) {
              console.error('Bulk approval error:', error);
              Alert.alert('Error', `Failed to approve gate passes: ${error.message || 'Unknown error'}`);
            } finally {
              setIsBulkProcessing(false);
            }
          },
        },
      ]
    );
  };

  const handleReject = async (pass: GatePass) => {
    Alert.prompt(
      'Reject Gate Pass',
      'Please provide a reason for rejection:',
      [
        {
          text: 'Cancel',
          style: 'cancel',
        },
        {
          text: 'Reject',
          style: 'destructive',
          onPress: async (reason) => {
            if (!reason || reason.trim().length === 0) {
              Alert.alert('Error', 'Rejection reason is required');
              return;
            }

            setProcessingId(pass.id);

            try {
              await apiService.put(
                `/mobile/rector/outpasses/${pass.id}/reject`,
                {
                  rejected_by: user?.name,
                  rejection_reason: reason.trim(),
                  rejected_at: new Date().toISOString(),
                }
              );

              Alert.alert('Success', 'Gate pass rejected');
              fetchPendingPasses();
            } catch (error: any) {
              console.error('Rejection error:', error);
              Alert.alert('Error', 'Failed to reject gate pass');
            } finally {
              setProcessingId(null);
            }
          },
        },
      ],
      'plain-text'
    );
  };

  const isProcessing = (passId: number) => processingId === passId;

  if (loading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color={theme.colors.primary} />
        <Text style={styles.loadingText}>Loading gate passes...</Text>
      </View>
    );
  }

  const selectAllAction = pendingPasses.length > 0 ? (
    <GradientButton
      style={styles.selectAllButton}
      onPress={handleSelectAll}
      accessibilityLabel={isSelectAll ? 'Deselect all' : 'Select all'}
    >
      <Text style={styles.selectAllText} numberOfLines={1}>
        {isSelectAll ? 'Deselect' : 'Select All'}
      </Text>
    </GradientButton>
  ) : null;

  return (
    <View style={styles.container}>
      <StaffScreenHeader
        onBack={() => navigation.goBack()}
        showBell={false}
        rightSlot={selectAllAction}  title="Gate Pass Approval" />

      {/* Bulk Approve Button */}
      {selectedPassIds.size > 0 && (
        <View style={styles.bulkActionBar}>
          <Text style={styles.bulkActionText}>
            {selectedPassIds.size} selected
          </Text>
          <GradientButton
            style={[styles.bulkApproveButton, isBulkProcessing && styles.disabledButton]}
            onPress={handleBulkApprove}
            disabled={isBulkProcessing}
          >
            {isBulkProcessing ? (
              <ActivityIndicator size="small" color={theme.colors.white} />
            ) : (
              <>
                <Ionicons name="checkmark-circle-outline" size={20} color={theme.colors.white} style={{ marginRight: 6 }} />
                <Text style={styles.bulkApproveButtonText}>
                  Approve Selected ({selectedPassIds.size})
                </Text>
              </>
            )}
          </GradientButton>
        </View>
      )}

      {/* Pending Gate Passes */}
      <ScrollView
        style={styles.content}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>
        {pendingPasses.length === 0 ? (
          <View style={styles.emptyState}>
            <Ionicons name="checkmark-done-circle" size={64} color={theme.colors.primary} style={styles.emptyIcon} />
            <Text style={styles.emptyTitle}>All Caught Up!</Text>
            <Text style={styles.emptySubtitle}>
              No pending gate passes require your approval
            </Text>
          </View>
        ) : (
          <>
            <View style={styles.countBanner}>
              <Text style={styles.countText}>
                {pendingPasses.length} pending approval{pendingPasses.length !== 1 ? 's' : ''}
              </Text>
            </View>

            {pendingPasses.map((pass) => (
              <View key={pass.id} style={[styles.passCard, selectedPassIds.has(pass.id) && styles.selectedCard]}>
                {/* Checkbox and Student Info */}
                <View style={styles.passHeader}>
                  <TouchableOpacity
                    style={styles.checkbox}
                    onPress={() => handleToggleSelect(pass.id)}
                    hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}
                  >
                    <Ionicons
                      name={selectedPassIds.has(pass.id) ? 'checkbox' : 'checkbox-outline'}
                      size={24}
                      color={selectedPassIds.has(pass.id) ? theme.colors.primary : theme.colors.textSecondary}
                    />
                  </TouchableOpacity>
                  <View style={styles.passHeaderContent}>
                    <Text style={styles.passStudent}>{pass.student_name}</Text>
                    <Text style={styles.passHostel}>
                      {pass.hostel_name} • Student ID: {pass.student_id}
                    </Text>
                  </View>
                  <View style={styles.pendingBadge}>
                    <Ionicons name="time-outline" size={14} color={theme.colors.white} style={{ marginRight: 4 }} />
                    <Text style={styles.pendingText}>PENDING</Text>
                  </View>
                </View>

                {/* Purpose */}
                <View style={styles.purposeSection}>
                  <Text style={styles.purposeLabel}>Purpose:</Text>
                  <Text style={styles.purposeText}>{pass.purpose}</Text>
                </View>

                {/* Schedule */}
                <View style={styles.scheduleSection}>
                  <View style={styles.scheduleRow}>
                    <Text style={styles.scheduleLabel}>Going Out:</Text>
                    <Text style={styles.scheduleValue}>
                      {format(new Date(pass.out_date), 'MMM dd, yyyy')} at {pass.out_time}
                    </Text>
                  </View>
                  <View style={styles.scheduleRow}>
                    <Text style={styles.scheduleLabel}>Expected Return:</Text>
                    <Text style={styles.scheduleValue}>
                      {format(new Date(pass.expected_in_date), 'MMM dd, yyyy')} at{' '}
                      {pass.expected_in_time}
                    </Text>
                  </View>
                </View>

                {/* Requested Date */}
                <Text style={styles.requestedDate}>
                  Requested: {format(new Date(pass.created_at), 'MMM dd, yyyy HH:mm')}
                </Text>

                {/* Security Badge */}
                <View style={styles.securityNotice}>
                  <Ionicons name="information-circle-outline" size={18} color={theme.colors.warning} style={{ marginRight: 8 }} />
                  <Text style={styles.securityText}>
                    Review request details before taking action
                  </Text>
                </View>

                {/* Action Buttons */}
                {selectedPassIds.size === 0 && (
                  <View style={styles.actionButtons}>
                    <GradientButton
                      style={[
                        styles.actionButton,
                        styles.rejectButton,
                        isProcessing(pass.id) && styles.disabledButton,
                      ]}
                      onPress={() => handleReject(pass)}
                      disabled={isProcessing(pass.id)}>
                      {isProcessing(pass.id) ? (
                        <ActivityIndicator size="small" color={theme.colors.white} />
                      ) : (
                        <>
                          <Ionicons name="close-circle-outline" size={20} color={theme.colors.white} style={{ marginRight: 6 }} />
                          <Text style={styles.actionButtonText}>Reject</Text>
                        </>
                      )}
                    </GradientButton>

                    <GradientButton
                      style={[
                        styles.actionButton,
                        styles.approveButton,
                        isProcessing(pass.id) && styles.disabledButton,
                      ]}
                      onPress={() => handleApprove(pass)}
                      disabled={isProcessing(pass.id)}>
                      {isProcessing(pass.id) ? (
                        <ActivityIndicator size="small" color={theme.colors.white} />
                      ) : (
                        <>
                          <Ionicons name="checkmark-circle-outline" size={20} color={theme.colors.white} style={{ marginRight: 6 }} />
                          <Text style={styles.actionButtonText}>Approve</Text>
                        </>
                      )}
                    </GradientButton>
                  </View>
                )}
              </View>
            ))}
          </>
        )}
      </ScrollView>

    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: theme.colors.background,
  },
  loadingText: {
    marginTop: 16,
    fontSize: 16,
    color: theme.colors.textSecondary,
  },
  selectAllButton: {
    padding: 6,
  },
  selectAllText: {
    color: theme.colors.primary,
    fontSize: 14,
    fontWeight: '600',
  },
  bulkActionBar: {
    backgroundColor: theme.colors.primary,
    padding: 12,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 16,
  },
  bulkActionText: {
    color: theme.colors.white,
    fontSize: 14,
    fontWeight: '600',
  },
  bulkApproveButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: theme.colors.success,
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: 8,
  },
  bulkApproveButtonText: {
    color: theme.colors.white,
    fontSize: 14,
    fontWeight: '600',
  },
  checkbox: {
    marginRight: 12,
    justifyContent: 'center',
    alignItems: 'center',
  },
  passHeaderContent: {
    flex: 1,
  },
  selectedCard: {
    borderWidth: 2,
    borderColor: theme.colors.primary,
    backgroundColor: theme.colors.divider,
  },
  content: {
    flex: 1,
    padding: 16,
  },
  countBanner: {
    backgroundColor: theme.colors.warningLight,
    padding: 12,
    borderRadius: 8,
    marginBottom: 16,
    borderLeftWidth: 4,
    borderLeftColor: theme.colors.warning,
  },
  countText: {
    fontSize: 14,
    fontWeight: '600',
    color: theme.colors.warning,
  },
  emptyState: {
    backgroundColor: theme.colors.white,
    padding: 60,
    borderRadius: 12,
    alignItems: 'center',
    marginTop: 40,
    elevation: 2,
    shadowColor: theme.colors.black,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  emptyIcon: {
    fontSize: 64,
    marginBottom: 16,
  },
  emptyTitle: {
    fontSize: 24,
    fontWeight: 'bold',
    color: theme.colors.text,
    marginBottom: 8,
  },
  emptySubtitle: {
    fontSize: 16,
    color: theme.colors.textSecondary,
    textAlign: 'center',
  },
  passCard: {
    backgroundColor: theme.colors.white,
    borderRadius: 12,
    padding: 16,
    marginBottom: 16,
    elevation: 2,
    shadowColor: theme.colors.black,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  passHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 16,
  },
  passStudent: {
    fontSize: 18,
    fontWeight: 'bold',
    color: theme.colors.text,
    marginBottom: 4,
  },
  passHostel: {
    fontSize: 14,
    color: theme.colors.textSecondary,
  },
  pendingBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: theme.colors.warning,
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 16,
  },
  pendingText: {
    color: theme.colors.white,
    fontSize: 12,
    fontWeight: '600',
  },
  purposeSection: {
    marginBottom: 16,
  },
  purposeLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: theme.colors.textSecondary,
    marginBottom: 4,
  },
  purposeText: {
    fontSize: 16,
    color: theme.colors.text,
    lineHeight: 22,
  },
  scheduleSection: {
    backgroundColor: theme.colors.surfaceMuted,
    padding: 12,
    borderRadius: 8,
    marginBottom: 12,
  },
  scheduleRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  scheduleLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: theme.colors.textSecondary,
  },
  scheduleValue: {
    fontSize: 14,
    color: theme.colors.text,
    flex: 1,
    textAlign: 'right',
  },
  requestedDate: {
    fontSize: 12,
    color: theme.colors.textMuted,
    fontStyle: 'italic',
    marginBottom: 12,
  },
  securityNotice: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: theme.colors.infoLight,
    padding: 10,
    borderRadius: 8,
    marginBottom: 16,
  },
  securityText: {
    fontSize: 13,
    color: theme.colors.info,
    fontWeight: '500',
    flex: 1,
  },
  actionButtons: {
    flexDirection: 'row',
    gap: 12,
  },
  actionButton: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 14,
    borderRadius: 8,
    gap: 8,
  },
  approveButton: {
    backgroundColor: theme.colors.primary,
  },
  rejectButton: {
    backgroundColor: theme.colors.error,
  },
  disabledButton: {
    opacity: 0.6,
  },
  actionButtonText: {
    color: theme.colors.white,
    fontSize: 16,
    fontWeight: '600',
  },
});

export default GatePassApprovalScreen;
