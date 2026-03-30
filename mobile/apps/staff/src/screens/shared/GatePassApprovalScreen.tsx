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
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useAuthStore } from '../../store/auth.store';
import { apiService } from '../../services/api.service';
import { APP_CONFIG } from '../../config/app.config';
import { GatePass } from '../../types';
import { format } from 'date-fns';
import { theme } from '../../theme/theme';

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
        <ActivityIndicator size="large" color="#4CAF50" />
        <Text style={styles.loadingText}>Loading gate passes...</Text>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity
          style={styles.backButton}
          onPress={() => navigation.goBack()}>
          <Text style={styles.backButtonText}>← Back</Text>
        </TouchableOpacity>
        <Text style={styles.headerTitle} numberOfLines={1} ellipsizeMode="tail">
          Gate Pass Approvals
        </Text>
        {pendingPasses.length > 0 && (
          <TouchableOpacity
            style={styles.selectAllButton}
            onPress={handleSelectAll}
          >
            <Text style={styles.selectAllText} numberOfLines={1}>
              {isSelectAll ? 'Deselect' : 'Select All'}
            </Text>
          </TouchableOpacity>
        )}
      </View>

      {/* Bulk Approve Button */}
      {selectedPassIds.size > 0 && (
        <View style={styles.bulkActionBar}>
          <Text style={styles.bulkActionText}>
            {selectedPassIds.size} selected
          </Text>
          <TouchableOpacity
            style={[styles.bulkApproveButton, isBulkProcessing && styles.disabledButton]}
            onPress={handleBulkApprove}
            disabled={isBulkProcessing}
          >
            {isBulkProcessing ? (
              <ActivityIndicator size="small" color="#fff" />
            ) : (
              <>
                <Ionicons name="checkmark-circle-outline" size={20} color="#fff" style={{ marginRight: 6 }} />
                <Text style={styles.bulkApproveButtonText}>
                  Approve Selected ({selectedPassIds.size})
                </Text>
              </>
            )}
          </TouchableOpacity>
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
            <Ionicons name="checkmark-done-circle" size={64} color="#4CAF50" style={styles.emptyIcon} />
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
                    <Ionicons name="time-outline" size={14} color="#fff" style={{ marginRight: 4 }} />
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
                  <Ionicons name="information-circle-outline" size={18} color="#FF9800" style={{ marginRight: 8 }} />
                  <Text style={styles.securityText}>
                    Review request details before taking action
                  </Text>
                </View>

                {/* Action Buttons */}
                {selectedPassIds.size === 0 && (
                  <View style={styles.actionButtons}>
                    <TouchableOpacity
                      style={[
                        styles.actionButton,
                        styles.rejectButton,
                        isProcessing(pass.id) && styles.disabledButton,
                      ]}
                      onPress={() => handleReject(pass)}
                      disabled={isProcessing(pass.id)}>
                      {isProcessing(pass.id) ? (
                        <ActivityIndicator size="small" color="#fff" />
                      ) : (
                        <>
                          <Ionicons name="close-circle-outline" size={20} color="#fff" style={{ marginRight: 6 }} />
                          <Text style={styles.actionButtonText}>Reject</Text>
                        </>
                      )}
                    </TouchableOpacity>

                    <TouchableOpacity
                      style={[
                        styles.actionButton,
                        styles.approveButton,
                        isProcessing(pass.id) && styles.disabledButton,
                      ]}
                      onPress={() => handleApprove(pass)}
                      disabled={isProcessing(pass.id)}>
                      {isProcessing(pass.id) ? (
                        <ActivityIndicator size="small" color="#fff" />
                      ) : (
                        <>
                          <Ionicons name="checkmark-circle-outline" size={20} color="#fff" style={{ marginRight: 6 }} />
                          <Text style={styles.actionButtonText}>Approve</Text>
                        </>
                      )}
                    </TouchableOpacity>
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
    backgroundColor: '#f5f5f5',
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#f5f5f5',
  },
  loadingText: {
    marginTop: 16,
    fontSize: 16,
    color: '#666',
  },
  header: {
    backgroundColor: '#4CAF50',
    padding: 20,
    paddingTop: 60,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  backButton: {
    padding: 8,
  },
  backButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
  headerTitle: {
    color: '#fff',
    fontSize: 20,
    fontWeight: 'bold',
  },
  placeholder: {
    width: 60,
  },
  selectAllButton: {
    padding: 8,
  },
  selectAllText: {
    color: '#fff',
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
    color: '#fff',
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
    color: '#fff',
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
    backgroundColor: '#F3F4F6',
  },
  content: {
    flex: 1,
    padding: 16,
  },
  countBanner: {
    backgroundColor: '#fff3cd',
    padding: 12,
    borderRadius: 8,
    marginBottom: 16,
    borderLeftWidth: 4,
    borderLeftColor: '#FF9800',
  },
  countText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#856404',
  },
  emptyState: {
    backgroundColor: '#fff',
    padding: 60,
    borderRadius: 12,
    alignItems: 'center',
    marginTop: 40,
    elevation: 2,
    shadowColor: '#000',
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
    color: '#333',
    marginBottom: 8,
  },
  emptySubtitle: {
    fontSize: 16,
    color: '#666',
    textAlign: 'center',
  },
  passCard: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 16,
    marginBottom: 16,
    elevation: 2,
    shadowColor: '#000',
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
    color: '#333',
    marginBottom: 4,
  },
  passHostel: {
    fontSize: 14,
    color: '#666',
  },
  pendingBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#FF9800',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 16,
  },
  pendingText: {
    color: '#fff',
    fontSize: 12,
    fontWeight: '600',
  },
  purposeSection: {
    marginBottom: 16,
  },
  purposeLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: '#666',
    marginBottom: 4,
  },
  purposeText: {
    fontSize: 16,
    color: '#333',
    lineHeight: 22,
  },
  scheduleSection: {
    backgroundColor: '#f8f9fa',
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
    color: '#666',
  },
  scheduleValue: {
    fontSize: 14,
    color: '#333',
    flex: 1,
    textAlign: 'right',
  },
  requestedDate: {
    fontSize: 12,
    color: '#999',
    fontStyle: 'italic',
    marginBottom: 12,
  },
  securityNotice: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#e3f2fd',
    padding: 10,
    borderRadius: 8,
    marginBottom: 16,
  },
  securityText: {
    fontSize: 13,
    color: '#1976d2',
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
    backgroundColor: '#4CAF50',
  },
  rejectButton: {
    backgroundColor: '#f44336',
  },
  disabledButton: {
    opacity: 0.6,
  },
  actionButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
});

export default GatePassApprovalScreen;
