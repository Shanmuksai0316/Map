import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  Alert,
  Modal,
  TextInput,
} from 'react-native';
import { GradientButton } from '../../shared/components/GradientButton';
import { useAuthStore } from '../../shared/store/auth.store';
import { apiService } from '../../shared/services/api.service';
import { APP_CONFIG } from '../../shared/config/app.config';
import { format } from 'date-fns';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { theme } from '../../shared/theme/theme';
import { errorHandler } from '../../shared/utils/errorHandler';
import { ErrorState, LoadingState } from '../../shared/components';
import { hapticService } from '../../shared/services/haptic.service';

interface LaundryRequest {
  id: number;
  request_number: string;
  pickup_code?: string;
  item_counts: {
    shirts: number;
    pants: number;
    towels: number;
    bedsheets: number;
    others: number;
  };
  total_weight: number;
  status: 'requested' | 'processing' | 'ready' | 'completed';
  requested_at: string;
  ready_at?: string;
  completed_at?: string;
  handover_note?: string;
  verify_note?: string;
}

export const LaundryRequestScreen = ({ navigation }: any) => {
  const insets = useSafeAreaInsets();
  const { user } = useAuthStore();
  const [requests, setRequests] = useState<LaundryRequest[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [selectedRequest, setSelectedRequest] = useState<LaundryRequest | null>(null);
  const [showDetailModal, setShowDetailModal] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [newRequest, setNewRequest] = useState({
    shirts: 0,
    pants: 0,
    towels: 0,
    bedsheets: 0,
    others: 0,
    total_weight: 0,
  });

  const fetchRequests = async () => {
    try {
      setError(null);
      setLoading(true);
      const response = await apiService.get<{ data: LaundryRequest[] }>(
        APP_CONFIG.ENDPOINTS.LAUNDRY_REQUESTS
      );
      setRequests(response.data || []);
    } catch (err) {
      const errorDetails = errorHandler.handleError(err);
      setError(errorDetails.message);
      setRequests([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchRequests();
  }, []);

  const onRefresh = () => {
    setRefreshing(true);
    fetchRequests();
  };

  const handleCreateRequest = async () => {
    const totalItems = 
      newRequest.shirts + 
      newRequest.pants + 
      newRequest.towels + 
      newRequest.bedsheets + 
      newRequest.others;

    if (totalItems === 0) {
      hapticService.onError();
      Alert.alert('Validation Error', 'Please add at least one item');
      return;
    }

    if (newRequest.total_weight <= 0) {
      hapticService.onError();
      Alert.alert('Validation Error', 'Please enter the total weight (must be greater than 0)');
      return;
    }
    
    if (newRequest.total_weight > 50) {
      hapticService.onError();
      Alert.alert('Validation Error', 'Total weight cannot exceed 50 kg');
      return;
    }

    try {
      await apiService.post(`${APP_CONFIG.ENDPOINTS.LAUNDRY_REQUESTS}/raise`, {
        item_counts: {
          shirts: newRequest.shirts,
          pants: newRequest.pants,
          towels: newRequest.towels,
          bedsheets: newRequest.bedsheets,
          others: newRequest.others,
        },
        total_weight: newRequest.total_weight,
      });

      hapticService.onSuccess();
      Alert.alert('Success', 'Laundry request created successfully!');
      setShowCreateModal(false);
      setNewRequest({
        shirts: 0,
        pants: 0,
        towels: 0,
        bedsheets: 0,
        others: 0,
        total_weight: 0,
      });
      fetchRequests();
    } catch (err) {
      hapticService.onError();
      const errorDetails = errorHandler.handleError(err);
      Alert.alert('Error', errorDetails.message);
    }
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'requested':
        return theme.colors.warning;
      case 'processing':
        return theme.colors.info;
      case 'ready':
        return theme.colors.success;
      case 'completed':
        return theme.colors.textSecondary;
      default:
        return theme.colors.textSecondary;
    }
  };

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'requested':
        return 'time-outline';
      case 'processing':
        return 'cart-outline';
      case 'ready':
        return 'refresh-outline';
      case 'completed':
        return 'checkmark-circle';
      default:
        return 'help-circle-outline';
    }
  };

  const incrementItem = (item: keyof typeof newRequest) => {
    if (item === 'total_weight') {
      setNewRequest(prev => ({ ...prev, [item]: prev[item] + 0.5 }));
    } else {
      setNewRequest(prev => ({ ...prev, [item]: prev[item] + 1 }));
    }
  };

  const decrementItem = (item: keyof typeof newRequest) => {
    if (item === 'total_weight') {
      setNewRequest(prev => ({ ...prev, [item]: Math.max(0, prev[item] - 0.5) }));
    } else {
      setNewRequest(prev => ({ ...prev, [item]: Math.max(0, prev[item] - 1) }));
    }
  };

  const activeRequests = requests.filter(r => r.status !== 'completed');
  const completedRequests = requests.filter(r => r.status === 'completed');

  const HEADER_ROW_HEIGHT = 52;
  const HEADER_PADDING_TOP = Math.max(insets.top, 10);
  const HEADER_PADDING_BOTTOM = 6;

  return (
    <View style={styles.container}>
      {/* Header - Removed "New" button as students can't raise laundry requests */}
      <View
        style={[
          styles.header,
          {
            paddingTop: HEADER_PADDING_TOP,
            paddingBottom: HEADER_PADDING_BOTTOM,
            minHeight: HEADER_PADDING_TOP + HEADER_ROW_HEIGHT + HEADER_PADDING_BOTTOM,
          },
        ]}>
        <View style={[styles.headerRow, { height: HEADER_ROW_HEIGHT }]}>
          <TouchableOpacity
            style={styles.backButton}
            onPress={() => navigation.goBack()}
            accessibilityLabel="Go back">
            <Ionicons name="arrow-back" size={24} color={theme.colors.primary} />
          </TouchableOpacity>
          <Text style={styles.headerTitle}>Laundry</Text>
          <View style={styles.headerSpacer} />
        </View>
      </View>

      <ScrollView
        style={styles.content}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>
        {loading ? (
          <LoadingState message="Loading laundry requests..." />
        ) : error ? (
          <ErrorState error={error} onRetry={fetchRequests} />
        ) : requests.length === 0 ? (
          <View style={styles.emptyState}>
            <Ionicons
              name="shirt-outline"
              size={64}
              color={theme.colors.textSecondary}
              style={styles.emptyIcon}
            />
            <Text style={styles.emptyTitle}>No active requests</Text>
            <Text style={styles.emptySubtitle}>
              Your laundry service requests will appear here.
            </Text>
          </View>
        ) : (
          <>
        {/* Active Requests */}
        {activeRequests.length > 0 && (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>Active Requests</Text>
            {activeRequests.map(request => {
              const itemCounts = request.item_counts || {
                shirts: 0,
                pants: 0,
                towels: 0,
                bedsheets: 0,
                others: 0,
              };
              
              return (
              <TouchableOpacity
                key={request.id}
                style={styles.requestCard}
                activeOpacity={0.8}
                onPress={() => {
                  setSelectedRequest(request);
                  setShowDetailModal(true);
                }}>
                <View style={styles.requestHeader}>
                  <View>
                    <Text style={styles.requestNumber}>{request.request_number}</Text>
                    <Text style={styles.requestDate}>
                      {format(new Date(request.requested_at), 'MMM dd, yyyy HH:mm')}
                    </Text>
                  </View>
                  <View
                    style={[
                      styles.statusBadge,
                      { backgroundColor: getStatusColor(request.status) },
                    ]}>
                    <Ionicons
                      name={getStatusIcon(request.status)}
                      size={16}
                      color={theme.colors.white}
                      style={styles.statusIcon}
                    />
                    <Text style={styles.statusText}>
                      {request.status.toUpperCase()}
                    </Text>
                  </View>
                </View>

                <View style={styles.itemsContainer}>
                  <Text style={styles.itemsTitle}>Items:</Text>
                  <View style={styles.itemsGrid}>
                    {itemCounts.shirts > 0 && (
                      <Text style={styles.itemText}>
                        <Ionicons
                          name="shirt-outline"
                          size={16}
                          color={theme.colors.textSecondary}
                          style={styles.itemIcon}
                        />
                        {itemCounts.shirts} Shirts
                      </Text>
                    )}
                    {itemCounts.pants > 0 && (
                      <Text style={styles.itemText}>
                        <Ionicons
                          name="trail-sign-outline"
                          size={16}
                          color={theme.colors.textSecondary}
                          style={styles.itemIcon}
                        />
                        {itemCounts.pants} Pants
                      </Text>
                    )}
                    {itemCounts.towels > 0 && (
                      <Text style={styles.itemText}>
                        <Ionicons
                          name="shirt-outline"
                          size={16}
                          color={theme.colors.textSecondary}
                          style={styles.itemIcon}
                        />
                        {itemCounts.towels} Towels
                      </Text>
                    )}
                    {itemCounts.bedsheets > 0 && (
                      <Text style={styles.itemText}>
                        <Ionicons
                          name="bed-outline"
                          size={16}
                          color={theme.colors.textSecondary}
                          style={styles.itemIcon}
                        />
                        {itemCounts.bedsheets} Bedsheets
                      </Text>
                    )}
                    {itemCounts.others > 0 && (
                      <Text style={styles.itemText}>
                        <Ionicons
                          name="cube-outline"
                          size={16}
                          color={theme.colors.textSecondary}
                          style={styles.itemIcon}
                        />
                        {itemCounts.others} Others
                      </Text>
                    )}
                  </View>
                  <Text style={styles.totalWeight}>
                    Total Weight: {request.total_weight} kg
                  </Text>
                </View>

                {request.status === 'ready' && (
                  <View style={styles.readyBanner}>
                    <Ionicons name="checkmark-circle" size={20} color={theme.colors.success} style={{ marginRight: 8 }} />
                    <Text style={styles.readyText}>
                      Your laundry is ready for pickup!
                    </Text>
                  </View>
                )}
              </TouchableOpacity>
            )})}
          </View>
        )}

        {/* Completed Requests */}
        {completedRequests.length > 0 && (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>Completed Requests</Text>
            {completedRequests.map(request => {
              const itemCounts = request.item_counts || {
                shirts: 0,
                pants: 0,
                towels: 0,
                bedsheets: 0,
                others: 0,
              };
              
              return (
              <TouchableOpacity
                key={request.id}
                style={[styles.requestCard, styles.completedCard]}
                activeOpacity={0.8}
                onPress={() => {
                  setSelectedRequest(request);
                  setShowDetailModal(true);
                }}>
                <View style={styles.requestHeader}>
                  <View>
                    <Text style={styles.requestNumber}>{request.request_number}</Text>
                    <Text style={styles.requestDate}>
                      {format(new Date(request.requested_at), 'MMM dd, yyyy')}
                    </Text>
                  </View>
                  <View
                    style={[
                      styles.statusBadge,
                      { backgroundColor: getStatusColor(request.status) },
                    ]}>
                    <Ionicons
                      name={getStatusIcon(request.status)}
                      size={16}
                      color={theme.colors.white}
                      style={styles.statusIcon}
                    />
                    <Text style={styles.statusText}>COMPLETED</Text>
                  </View>
                </View>

                <View style={styles.itemsContainer}>
                  <Text style={styles.itemsTitle}>Items:</Text>
                  <View style={styles.itemsGrid}>
                    {itemCounts.shirts > 0 && (
                      <Text style={styles.itemText}>
                        <Ionicons
                          name="shirt-outline"
                          size={16}
                          color={theme.colors.textSecondary}
                          style={styles.itemIcon}
                        />
                        {itemCounts.shirts}
                      </Text>
                    )}
                    {itemCounts.pants > 0 && (
                      <Text style={styles.itemText}>
                        <Ionicons
                          name="trail-sign-outline"
                          size={16}
                          color={theme.colors.textSecondary}
                          style={styles.itemIcon}
                        />
                        {itemCounts.pants}
                      </Text>
                    )}
                    {itemCounts.towels > 0 && (
                      <Text style={styles.itemText}>
                        <Ionicons
                          name="shirt-outline"
                          size={16}
                          color={theme.colors.textSecondary}
                          style={styles.itemIcon}
                        />
                        {itemCounts.towels}
                      </Text>
                    )}
                    {itemCounts.bedsheets > 0 && (
                      <Text style={styles.itemText}>
                        <Ionicons
                          name="bed-outline"
                          size={16}
                          color={theme.colors.textSecondary}
                          style={styles.itemIcon}
                        />
                        {itemCounts.bedsheets}
                      </Text>
                    )}
                    {itemCounts.others > 0 && (
                      <Text style={styles.itemText}>
                        <Ionicons
                          name="cube-outline"
                          size={16}
                          color={theme.colors.textSecondary}
                          style={styles.itemIcon}
                        />
                        {itemCounts.others}
                      </Text>
                    )}
                  </View>
                </View>

                {request.handover_note && (
                  <Text style={styles.handoverNote}>Note: {request.handover_note}</Text>
                )}
              </TouchableOpacity>
            )})}
          </View>
        )}
          </>
        )}
      </ScrollView>

      {/* Request Detail Modal */}
      <Modal
        visible={!!selectedRequest && showDetailModal}
        animationType="slide"
        transparent
        onRequestClose={() => setShowDetailModal(false)}>
        <View style={styles.detailModalOverlay}>
          <View style={styles.detailModalContent}>
            <View style={styles.detailModalHeader}>
              <Text style={styles.detailModalTitle}>Laundry Request Details</Text>
              <TouchableOpacity
                onPress={() => setShowDetailModal(false)}
                style={styles.detailModalCloseButton}>
                <Ionicons name="arrow-back" size={20} color={theme.colors.textPrimary} />
              </TouchableOpacity>
            </View>

            {selectedRequest && (
              <>
                <Text style={styles.detailLabel}>Request No.</Text>
                <Text style={styles.detailValue}>{selectedRequest.request_number}</Text>

                <Text style={styles.detailLabel}>Status</Text>
                <Text style={styles.detailValue}>{selectedRequest.status.toUpperCase()}</Text>

                {selectedRequest.pickup_code && (
                  <>
                    <Text style={styles.detailLabel}>Pickup Code</Text>
                    <Text style={styles.detailValue}>{selectedRequest.pickup_code}</Text>
                  </>
                )}

                <Text style={styles.detailLabel}>Requested At</Text>
                <Text style={styles.detailValue}>
                  {format(new Date(selectedRequest.requested_at), 'MMM dd, yyyy HH:mm')}
                </Text>

                {selectedRequest.ready_at && (
                  <>
                    <Text style={styles.detailLabel}>Ready At</Text>
                    <Text style={styles.detailValue}>
                      {format(new Date(selectedRequest.ready_at), 'MMM dd, yyyy HH:mm')}
                    </Text>
                  </>
                )}

                {selectedRequest.completed_at && (
                  <>
                    <Text style={styles.detailLabel}>Completed At</Text>
                    <Text style={styles.detailValue}>
                      {format(new Date(selectedRequest.completed_at), 'MMM dd, yyyy HH:mm')}
                    </Text>
                  </>
                )}

                <Text style={styles.detailLabel}>Items</Text>
                <Text style={styles.detailValue}>
                  Shirts: {selectedRequest.item_counts?.shirts ?? 0}{'\n'}
                  Pants: {selectedRequest.item_counts?.pants ?? 0}{'\n'}
                  Towels: {selectedRequest.item_counts?.towels ?? 0}{'\n'}
                  Bedsheets: {selectedRequest.item_counts?.bedsheets ?? 0}{'\n'}
                  Others: {selectedRequest.item_counts?.others ?? 0}
                </Text>

                <Text style={styles.detailLabel}>Total Weight</Text>
                <Text style={styles.detailValue}>{selectedRequest.total_weight} kg</Text>

                {selectedRequest.handover_note && (
                  <>
                    <Text style={styles.detailLabel}>Handover Note</Text>
                    <Text style={styles.detailValue}>{selectedRequest.handover_note}</Text>
                  </>
                )}
                {selectedRequest.verify_note && (
                  <>
                    <Text style={styles.detailLabel}>Verification Note</Text>
                    <Text style={styles.detailValue}>{selectedRequest.verify_note}</Text>
                  </>
                )}
              </>
            )}
          </View>
        </View>
      </Modal>

      {/* Create Request Modal */}
      <Modal
        visible={showCreateModal}
        animationType="slide"
        presentationStyle="pageSheet"
        onRequestClose={() => setShowCreateModal(false)}>
        <View style={styles.modalContainer}>
          <View style={styles.modalHeader}>
            <Text style={styles.modalTitle}>New Laundry Request</Text>
            <TouchableOpacity onPress={() => setShowCreateModal(false)}>
              <Ionicons name="arrow-back" size={24} color={theme.colors.textSecondary} />
            </TouchableOpacity>
          </View>

          <ScrollView style={styles.modalContent}>
            <Text style={styles.modalSubtitle}>
              Add item counts and total weight
            </Text>

            {/* Item Counters */}
            <View style={styles.formSection}>
              <Text style={styles.sectionLabel}>Item Counts</Text>
              <View style={styles.countRow}>
                <Text style={styles.counterLabel}>
                  <Ionicons name="shirt-outline" size={16} color={theme.colors.textSecondary} style={styles.itemIcon} />
                  {' '}Shirts
                </Text>
                <View style={styles.counterControls}>
                  <TouchableOpacity
                    style={styles.counterButton}
                    onPress={() => decrementItem('shirts')}>
                    <Ionicons name="remove-outline" size={18} color={theme.colors.text} />
                  </TouchableOpacity>
                  <Text style={styles.counterValue}>{newRequest.shirts}</Text>
                  <TouchableOpacity
                    style={styles.counterButton}
                    onPress={() => incrementItem('shirts')}>
                    <Ionicons name="add-outline" size={18} color={theme.colors.text} />
                  </TouchableOpacity>
                </View>
              </View>
              <View style={styles.countRow}>
                <Text style={styles.counterLabel}>
                  <Ionicons name="trail-sign-outline" size={16} color={theme.colors.textSecondary} style={styles.itemIcon} />
                  {' '}Pants
                </Text>
                <View style={styles.counterControls}>
                  <TouchableOpacity
                    style={styles.counterButton}
                    onPress={() => decrementItem('pants')}>
                    <Ionicons name="remove-outline" size={18} color={theme.colors.text} />
                  </TouchableOpacity>
                  <Text style={styles.counterValue}>{newRequest.pants}</Text>
                  <TouchableOpacity
                    style={styles.counterButton}
                    onPress={() => incrementItem('pants')}>
                    <Ionicons name="add-outline" size={18} color={theme.colors.text} />
                  </TouchableOpacity>
                </View>
              </View>
              <View style={styles.countRow}>
                <Text style={styles.counterLabel}>
                  <Ionicons name="shirt-outline" size={16} color={theme.colors.textSecondary} style={styles.itemIcon} />
                  {' '}Towels
                </Text>
                <View style={styles.counterControls}>
                  <TouchableOpacity
                    style={styles.counterButton}
                    onPress={() => decrementItem('towels')}>
                    <Ionicons name="remove-outline" size={18} color={theme.colors.text} />
                  </TouchableOpacity>
                  <Text style={styles.counterValue}>{newRequest.towels}</Text>
                  <TouchableOpacity
                    style={styles.counterButton}
                    onPress={() => incrementItem('towels')}>
                    <Ionicons name="add-outline" size={18} color={theme.colors.text} />
                  </TouchableOpacity>
                </View>
              </View>
              <View style={styles.countRow}>
                <Text style={styles.counterLabel}>
                  <Ionicons name="bed-outline" size={16} color={theme.colors.textSecondary} style={styles.itemIcon} />
                  {' '}Bedsheets
                </Text>
                <View style={styles.counterControls}>
                  <TouchableOpacity
                    style={styles.counterButton}
                    onPress={() => decrementItem('bedsheets')}>
                    <Ionicons name="remove-outline" size={18} color={theme.colors.text} />
                  </TouchableOpacity>
                  <Text style={styles.counterValue}>{newRequest.bedsheets}</Text>
                  <TouchableOpacity
                    style={styles.counterButton}
                    onPress={() => incrementItem('bedsheets')}>
                    <Ionicons name="add-outline" size={18} color={theme.colors.text} />
                  </TouchableOpacity>
                </View>
              </View>
              <View style={styles.countRow}>
                <Text style={styles.counterLabel}>
                  <Ionicons name="cube-outline" size={16} color={theme.colors.textSecondary} style={styles.itemIcon} />
                  {' '}Others
                </Text>
                <View style={styles.counterControls}>
                  <TouchableOpacity
                    style={styles.counterButton}
                    onPress={() => decrementItem('others')}>
                    <Ionicons name="remove-outline" size={18} color={theme.colors.text} />
                  </TouchableOpacity>
                  <Text style={styles.counterValue}>{newRequest.others}</Text>
                  <TouchableOpacity
                    style={styles.counterButton}
                    onPress={() => incrementItem('others')}>
                    <Ionicons name="add-outline" size={18} color={theme.colors.text} />
                  </TouchableOpacity>
                </View>
              </View>
            </View>

            {/* Total Weight */}
            <View style={styles.formSection}>
              <Text style={styles.sectionLabel}>Total Weight (kg)</Text>
              <View style={styles.countRow}>
                <View style={styles.counterControls}>
                  <TouchableOpacity
                    style={styles.counterButton}
                    onPress={() => decrementItem('total_weight')}>
                    <Text style={styles.counterButtonText}>−</Text>
                  </TouchableOpacity>
                  <TextInput
                    style={styles.counterValue}
                    keyboardType="numeric"
                    value={newRequest.total_weight.toFixed(1)}
                    editable={false}
                  />
                  <TouchableOpacity
                    style={styles.counterButton}
                    onPress={() => incrementItem('total_weight')}>
                    <Text style={styles.counterButtonText}>+</Text>
                  </TouchableOpacity>
                </View>
              </View>
            </View>

            <GradientButton
              style={styles.submitButton}
              onPress={handleCreateRequest}>
              <Text style={styles.submitButtonText}>Submit Request</Text>
            </GradientButton>
          </ScrollView>
        </View>
      </Modal>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.white,
  },
  header: {
    backgroundColor: theme.colors.white,
    paddingHorizontal: theme.spacing.lg,
    ...theme.shadows.medium,
  },
  headerRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  backButton: {
    padding: theme.spacing.xs,
  },
  headerTitle: {
    color: theme.colors.primary,
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
  },
  headerSpacer: {
    width: 80,
  },
  createButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.xs,
    backgroundColor: theme.colors.white,
    paddingHorizontal: theme.spacing.lg,
    paddingVertical: theme.spacing.sm,
    borderRadius: theme.borderRadius.md,
  },
  createButtonText: {
    color: theme.colors.primary,
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
  },
  content: {
    flex: 1,
    padding: theme.spacing.md,
  },
  infoBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: theme.colors.surface,
    padding: theme.spacing.md,
    borderRadius: theme.borderRadius.lg,
    marginBottom: theme.spacing.lg,
    borderWidth: 1,
    borderColor: theme.colors.border,
    ...theme.shadows.small,
  },
  infoIcon: {
    fontSize: theme.fontSize.xl,
    marginRight: theme.spacing.md,
  },
  infoContent: {
    flex: 1,
    gap: theme.spacing.xs,
  },
  infoText: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
  },
  section: {
    marginBottom: theme.spacing.lg,
  },
  sectionTitle: {
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
    marginBottom: theme.spacing.sm,
  },
  requestCard: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.borderRadius.lg,
    padding: theme.spacing.md,
    marginBottom: theme.spacing.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    ...theme.shadows.small,
  },
  completedCard: {
    opacity: 0.7,
  },
  requestHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: theme.spacing.sm,
  },
  requestInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.xs,
  },
  requestIcon: {
    marginRight: theme.spacing.xs,
  },
  requestNumber: {
    fontSize: theme.fontSize.lg,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
  },
  requestDate: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
  },
  requestTitle: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
  },
  requestSubtitle: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
    marginTop: theme.spacing.xs,
  },
  statusBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: theme.spacing.sm,
    paddingVertical: theme.spacing.xs,
    borderRadius: theme.borderRadius.xl,
  },
  statusIcon: {
    marginRight: theme.spacing.xs,
  },
  statusText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.xs,
    fontWeight: theme.fontWeight.semibold,
  },
  itemsContainer: {
    marginTop: theme.spacing.sm,
  },
  itemsTitle: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
    marginBottom: theme.spacing.xs,
  },
  itemsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: theme.spacing.sm,
  },
  itemText: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
  },
  itemIcon: {
    marginRight: theme.spacing.xs,
  },
  totalWeight: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.text,
    marginTop: theme.spacing.sm,
  },
  handoverNote: {
    marginTop: theme.spacing.sm,
    paddingTop: theme.spacing.sm,
    borderTopWidth: 1,
    borderTopColor: theme.colors.divider,
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
  },
  readyBanner: {
    backgroundColor: theme.colors.successLight,
    borderRadius: theme.borderRadius.md,
    padding: theme.spacing.sm,
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.xs,
    marginTop: theme.spacing.sm,
  },
  readyIcon: {
    fontSize: theme.fontSize.lg,
  },
  readyText: {
    fontSize: theme.fontSize.sm,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.success,
    flex: 1,
  },
  noteText: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
    fontStyle: 'italic',
    marginTop: theme.spacing.sm,
    paddingTop: theme.spacing.sm,
    borderTopWidth: 1,
    borderTopColor: theme.colors.divider,
  },
  emptyState: {
    backgroundColor: theme.colors.white,
    borderRadius: theme.borderRadius.lg,
    padding: theme.spacing.xl,
    alignItems: 'center',
    ...theme.shadows.small,
  },
  emptyIcon: {
    marginBottom: theme.spacing.md,
  },
  emptyTitle: {
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
    marginBottom: theme.spacing.xs,
  },
  emptySubtitle: {
    fontSize: theme.fontSize.md,
    color: theme.colors.textSecondary,
    textAlign: 'center',
    marginBottom: theme.spacing.lg,
  },
  emptyButton: {
    backgroundColor: theme.colors.primary,
    paddingHorizontal: theme.spacing.lg,
    paddingVertical: theme.spacing.md,
    borderRadius: theme.borderRadius.md,
  },
  emptyButtonText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
  },
  detailModalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.4)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: theme.spacing.lg,
  },
  detailModalContent: {
    width: '100%',
    maxHeight: '80%',
    backgroundColor: theme.colors.card,
    borderRadius: theme.borderRadius.lg,
    padding: theme.spacing.lg,
  },
  detailModalHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: theme.spacing.md,
  },
  detailModalTitle: {
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
  },
  detailModalCloseButton: {
    padding: theme.spacing.xs,
  },
  detailLabel: {
    marginTop: theme.spacing.sm,
    fontSize: theme.fontSize.sm,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.textSecondary,
  },
  detailValue: {
    fontSize: theme.fontSize.md,
    color: theme.colors.text,
    marginTop: theme.spacing.xs / 2,
  },
  modalContainer: {
    flex: 1,
    backgroundColor: theme.colors.card,
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: theme.spacing.lg,
    paddingTop: theme.spacing.xl * 2,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.divider,
  },
  modalTitle: {
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
  },
  modalClose: {
    fontSize: 28,
    color: theme.colors.textSecondary,
  },
  modalContent: {
    flex: 1,
    padding: theme.spacing.lg,
  },
  modalSubtitle: {
    fontSize: theme.fontSize.md,
    color: theme.colors.textSecondary,
    marginBottom: theme.spacing.lg,
  },
  formSection: {
    marginBottom: theme.spacing.lg,
  },
  sectionLabel: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
    marginBottom: theme.spacing.sm,
  },
  countRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: theme.spacing.sm,
  },
  counterLabel: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
    flexDirection: 'row',
    alignItems: 'center',
  },
  counterControls: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.xs,
  },
  counterButton: {
    backgroundColor: theme.colors.surface,
    borderRadius: theme.borderRadius.md,
    padding: theme.spacing.xs,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  counterValue: {
    fontSize: theme.fontSize.md,
    color: theme.colors.text,
    width: 32,
    textAlign: 'center',
  },
  weightInput: {
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.borderRadius.md,
    padding: theme.spacing.sm,
    fontSize: theme.fontSize.md,
    color: theme.colors.text,
    backgroundColor: theme.colors.white,
    marginTop: theme.spacing.sm,
  },
  submitButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#D79F24',
    padding: 16,
    borderRadius: theme.borderRadius.lg,
    marginTop: theme.spacing.lg,
    ...theme.shadows.medium,
  },
  submitButtonText: {
    color: theme.colors.primary,
    fontSize: 16,
    fontWeight: '600',
  },
  counterButtonText: {
    color: theme.colors.white,
    fontSize: 20,
    fontWeight: 'bold',
  },
});
