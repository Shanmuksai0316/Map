import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  ActivityIndicator,
  Alert,
  Modal,
  TextInput,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import { useAuthStore } from '../../../shared/store/auth.store';
import { apiService } from '../../../shared/services/api.service';
import { APP_CONFIG } from '../../../shared/config/app.config';
import { format } from 'date-fns';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { colors } from '../../../shared/theme/colors';
import DatePicker from 'react-native-date-picker';
import { errorHandler } from '../../../shared/utils/errorHandler';
import { ErrorState } from '../../../shared/components/shared/ErrorState';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface SportsFacility {
  id: number;
  name: string;
  type: string;
  hostel_name?: string;
}

interface SportsBlockout {
  id: number;
  facility_id: number;
  facility_name: string;
  start_at: string;
  end_at: string;
  reason?: string;
  created_by: number;
  creator_name?: string;
  is_active: boolean;
  is_future: boolean;
  is_past: boolean;
}

export const SportsBlockoutScreen = ({ navigation, route }: any) => {
  const { user } = useAuthStore();
  const [facilities, setFacilities] = useState<SportsFacility[]>([]);
  const [blockouts, setBlockouts] = useState<SportsBlockout[]>([]);
  const [filteredBlockouts, setFilteredBlockouts] = useState<SportsBlockout[]>([]);
  const [selectedFacility, setSelectedFacility] = useState<number | null>(null);
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<any>(null);
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [creating, setCreating] = useState(false);

  // Form state
  const [formFacilityId, setFormFacilityId] = useState<string>('');
  const [formStartDate, setFormStartDate] = useState(new Date());
  const [formEndDate, setFormEndDate] = useState(new Date(Date.now() + 3600000)); // 1 hour later
  const [formReason, setFormReason] = useState('');
  const [showStartPicker, setShowStartPicker] = useState(false);
  const [showEndPicker, setShowEndPicker] = useState(false);

  const fetchFacilities = async () => {
    try {
      const response = await apiService.get<{ data: SportsFacility[] }>(
        APP_CONFIG.ENDPOINTS.SPORTS_FACILITIES || `${APP_CONFIG.ENDPOINTS.SPORTS}/facilities`
      );
      setFacilities(response.data || []);
    } catch (error) {
      console.error('Failed to fetch facilities:', error);
      setFacilities([]);
    }
  };

  const fetchBlockouts = async () => {
    try {
      setError(null);
      if (!selectedFacility) {
        setBlockouts([]);
        setFilteredBlockouts([]);
        setLoading(false);
        setRefreshing(false);
        return;
      }

      const response = await apiService.get<{ data: SportsBlockout[] }>(
        `${APP_CONFIG.ENDPOINTS.SPORTS_FACILITIES}/${selectedFacility}/blockouts`
      );
      
      const blockoutList = response.data || [];
      setBlockouts(blockoutList);
      setFilteredBlockouts(blockoutList);
    } catch (err) {
      console.error('Failed to fetch blockouts:', err);
      const errorDetails = errorHandler.handleError(err);
      setError(errorDetails);
      setBlockouts([]);
      setFilteredBlockouts([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchFacilities();
  }, []);

  useEffect(() => {
    if (selectedFacility) {
      setLoading(true);
      fetchBlockouts();
    } else {
      setBlockouts([]);
      setFilteredBlockouts([]);
      setLoading(false);
    }
  }, [selectedFacility]);

  const onRefresh = () => {
    setRefreshing(true);
    fetchBlockouts();
  };

  const handleCreateBlockout = async () => {
    if (!formFacilityId || !formReason.trim()) {
      Alert.alert('Validation Error', 'Please select a facility and provide a reason');
      return;
    }

    if (formEndDate <= formStartDate) {
      Alert.alert('Validation Error', 'End time must be after start time');
      return;
    }

    try {
      setCreating(true);
      await apiService.post(
        `${APP_CONFIG.ENDPOINTS.SPORTS_FACILITIES}/${formFacilityId}/blockouts`,
        {
          start_at: formStartDate.toISOString(),
          end_at: formEndDate.toISOString(),
          reason: formReason.trim(),
        }
      );

      Alert.alert('Success', 'Blockout created successfully');
      setShowCreateModal(false);
      resetForm();
      fetchBlockouts();
    } catch (error: any) {
      console.error('Failed to create blockout:', error);
      Alert.alert(
        'Error',
        error.response?.data?.detail || error.response?.data?.message || 'Failed to create blockout'
      );
    } finally {
      setCreating(false);
    }
  };

  const handleDeleteBlockout = async (blockoutId: number) => {
    Alert.alert(
      'Delete Blockout',
      'Are you sure you want to delete this blockout?',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Delete',
          style: 'destructive',
          onPress: async () => {
            try {
              await apiService.delete(
                `${APP_CONFIG.ENDPOINTS.SPORTS_FACILITIES}/blockouts/${blockoutId}`
              );
              Alert.alert('Success', 'Blockout deleted successfully');
              fetchBlockouts();
            } catch (error: any) {
              console.error('Failed to delete blockout:', error);
              Alert.alert(
                'Error',
                error.response?.data?.detail || error.response?.data?.message || 'Failed to delete blockout'
              );
            }
          },
        },
      ]
    );
  };

  const resetForm = () => {
    setFormFacilityId('');
    setFormStartDate(new Date());
    setFormEndDate(new Date(Date.now() + 3600000));
    setFormReason('');
  };

  const BlockoutCard = ({ blockout }: { blockout: SportsBlockout }) => (
    <View style={styles.blockoutCard}>
      <View style={styles.blockoutHeader}>
        <View style={styles.blockoutInfo}>
          <Text style={styles.blockoutFacility}>{blockout.facility_name}</Text>
          <Text style={styles.blockoutTime}>
            {format(new Date(blockout.start_at), 'MMM dd, yyyy HH:mm')} - {' '}
            {format(new Date(blockout.end_at), 'MMM dd, yyyy HH:mm')}
          </Text>
        </View>
        <View style={[
          styles.statusBadge,
          blockout.is_active ? styles.activeBadge : blockout.is_future ? styles.futureBadge : styles.pastBadge
        ]}>
          <Text style={styles.statusText}>
            {blockout.is_active ? 'Active' : blockout.is_future ? 'Upcoming' : 'Past'}
          </Text>
        </View>
      </View>
      {blockout.reason && (
        <Text style={styles.blockoutReason}>{blockout.reason}</Text>
      )}
      <View style={styles.blockoutFooter}>
        <Text style={styles.blockoutCreator}>
          Created by: {blockout.creator_name || 'Unknown'}
        </Text>
        {!blockout.is_past && (
          <GradientButton
            style={styles.deleteButton}
            onPress={() => handleDeleteBlockout(blockout.id)}>
            <Ionicons name="trash-outline" size={18} color={colors.error} />
            <Text style={styles.deleteButtonText}>Delete</Text>
          </GradientButton>
        )}
      </View>
    </View>
  );

  return (
    <View style={styles.container}>
      <StaffScreenHeader
        onBack={() => navigation.goBack()}
        showBell={false}
        rightSlot={
          <GradientButton
            style={styles.addButton}
            onPress={() => {
              resetForm();
              setShowCreateModal(true);
            }}>
            <Ionicons name="add" size={24} color={colors.primary} />
          </GradientButton>
        }  title="Blockout" />

      {/* Facility Selector */}
      <View style={styles.facilitySelector}>
        <Text style={styles.selectorLabel}>Select Facility:</Text>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.facilityScroll}>
          {facilities.map((facility) => (
            <TouchableOpacity
              key={facility.id}
              style={[
                styles.facilityChip,
                selectedFacility === facility.id && styles.facilityChipActive,
              ]}
              onPress={() => setSelectedFacility(facility.id)}>
              <Text
                style={[
                  styles.facilityChipText,
                  selectedFacility === facility.id && styles.facilityChipTextActive,
                ]}>
                {facility.name}
              </Text>
            </TouchableOpacity>
          ))}
        </ScrollView>
      </View>

      {/* Content */}
      {loading ? (
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color={colors.primary} />
          <Text style={styles.loadingText}>Loading blockouts...</Text>
        </View>
      ) : error ? (
        <ErrorState error={error} onRetry={fetchBlockouts} />
      ) : (
        <ScrollView
          style={styles.content}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
          }>
          {!selectedFacility ? (
            <View style={styles.emptyState}>
              <Ionicons name="business-outline" size={48} color={colors.textMuted} />
              <Text style={styles.emptyTitle}>Select a Facility</Text>
              <Text style={styles.emptySubtitle}>
                Choose a facility to view and manage blockouts
              </Text>
            </View>
          ) : filteredBlockouts.length === 0 ? (
            <View style={styles.emptyState}>
              <Ionicons name="calendar-clear-outline" size={48} color={colors.textMuted} />
              <Text style={styles.emptyTitle}>No Blockouts</Text>
              <Text style={styles.emptySubtitle}>
                No blockouts found for this facility
              </Text>
            </View>
          ) : (
            <>
              <View style={styles.resultsHeader}>
                <Text style={styles.resultsCount}>
                  {filteredBlockouts.length} blockout{filteredBlockouts.length !== 1 ? 's' : ''} found
                </Text>
              </View>
              {filteredBlockouts.map((blockout) => (
                <BlockoutCard key={blockout.id} blockout={blockout} />
              ))}
            </>
          )}
        </ScrollView>
      )}

      {/* Create Blockout Modal */}
      <Modal
        visible={showCreateModal}
        transparent
        animationType="slide"
        onRequestClose={() => setShowCreateModal(false)}>
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Create Blockout</Text>
              <TouchableOpacity
                onPress={() => setShowCreateModal(false)}
                disabled={creating}>
                <Ionicons name="close" size={24} color={colors.text} />
              </TouchableOpacity>
            </View>

            <ScrollView style={styles.modalBody}>
              {/* Facility Selector */}
              <View style={styles.formGroup}>
                <Text style={styles.label}>Facility *</Text>
                <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.facilityScroll}>
                  {facilities.map((facility) => (
                    <TouchableOpacity
                      key={facility.id}
                      style={[
                        styles.facilityChip,
                        formFacilityId === String(facility.id) && styles.facilityChipActive,
                      ]}
                      onPress={() => setFormFacilityId(String(facility.id))}>
                      <Text
                        style={[
                          styles.facilityChipText,
                          formFacilityId === String(facility.id) && styles.facilityChipTextActive,
                        ]}>
                        {facility.name}
                      </Text>
                    </TouchableOpacity>
                  ))}
                </ScrollView>
              </View>

              {/* Start Date/Time */}
              <View style={styles.formGroup}>
                <Text style={styles.label}>Start Date & Time *</Text>
                <TouchableOpacity
                  style={styles.dateButton}
                  onPress={() => setShowStartPicker(true)}>
                  <Ionicons name="calendar-outline" size={20} color={colors.primary} />
                  <Text style={styles.dateButtonText}>
                    {format(formStartDate, 'MMM dd, yyyy HH:mm')}
                  </Text>
                </TouchableOpacity>
                {showStartPicker && (
                  <DatePicker
                    modal
                    open={showStartPicker}
                    date={formStartDate}
                    mode="datetime"
                    onConfirm={(date) => {
                      setShowStartPicker(false);
                      setFormStartDate(date);
                      if (date >= formEndDate) {
                        setFormEndDate(new Date(date.getTime() + 3600000));
                      }
                    }}
                    onCancel={() => setShowStartPicker(false)}
                  />
                )}
              </View>

              {/* End Date/Time */}
              <View style={styles.formGroup}>
                <Text style={styles.label}>End Date & Time *</Text>
                <TouchableOpacity
                  style={styles.dateButton}
                  onPress={() => setShowEndPicker(true)}>
                  <Ionicons name="calendar-outline" size={20} color={colors.primary} />
                  <Text style={styles.dateButtonText}>
                    {format(formEndDate, 'MMM dd, yyyy HH:mm')}
                  </Text>
                </TouchableOpacity>
                {showEndPicker && (
                  <DatePicker
                    modal
                    open={showEndPicker}
                    date={formEndDate}
                    mode="datetime"
                    minimumDate={formStartDate}
                    onConfirm={(date) => {
                      setShowEndPicker(false);
                      setFormEndDate(date);
                    }}
                    onCancel={() => setShowEndPicker(false)}
                  />
                )}
              </View>

              {/* Reason */}
              <View style={styles.formGroup}>
                <Text style={styles.label}>Reason *</Text>
                <TextInput
                  style={styles.textInput}
                  placeholder="e.g., Maintenance, Event, etc."
                  value={formReason}
                  onChangeText={setFormReason}
                  multiline
                  numberOfLines={3}
                  placeholderTextColor={colors.textMuted}
                />
              </View>
            </ScrollView>

            {/* Modal Actions */}
            <View style={styles.modalActions}>
              <GradientButton
                style={[styles.modalButton, styles.cancelButton]}
                onPress={() => setShowCreateModal(false)}
                disabled={creating}>
                <Text style={styles.cancelButtonText}>Cancel</Text>
              </GradientButton>
              <GradientButton
                style={[styles.modalButton, styles.createButton, creating && styles.buttonDisabled]}
                onPress={handleCreateBlockout}
                disabled={creating}>
                {creating ? (
                  <ActivityIndicator size="small" color={colors.white} />
                ) : (
                  <>
                    <Ionicons name="checkmark-circle-outline" size={20} color={colors.white} />
                    <Text style={styles.createButtonText}>Create</Text>
                  </>
                )}
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
    backgroundColor: colors.background,
  },
  header: {
    backgroundColor: colors.primary,
    paddingBottom: 16,
    paddingHorizontal: 16,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  addButton: {
    padding: 8,
  },
  facilitySelector: {
    backgroundColor: colors.white,
    paddingVertical: 16,
    paddingHorizontal: 16,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  selectorLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.text,
    marginBottom: 12,
  },
  facilityScroll: {
    maxHeight: 50,
  },
  facilityChip: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: colors.background,
    marginRight: 8,
    borderWidth: 1,
    borderColor: colors.border,
  },
  facilityChipActive: {
    backgroundColor: colors.primary,
    borderColor: colors.primary,
  },
  facilityChipText: {
    fontSize: 14,
    fontWeight: '500',
    color: colors.text,
  },
  facilityChipTextActive: {
    color: colors.white,
    fontWeight: '600',
  },
  content: {
    flex: 1,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
  },
  loadingText: {
    marginTop: 16,
    fontSize: 16,
    color: colors.textSecondary,
  },
  resultsHeader: {
    paddingHorizontal: 16,
    paddingTop: 16,
    paddingBottom: 8,
  },
  resultsCount: {
    fontSize: 14,
    color: colors.textSecondary,
    fontWeight: '500',
  },
  emptyState: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
    marginTop: 100,
  },
  emptyTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: colors.text,
    marginTop: 16,
    marginBottom: 8,
  },
  emptySubtitle: {
    fontSize: 14,
    color: colors.textSecondary,
    textAlign: 'center',
  },
  blockoutCard: {
    backgroundColor: colors.white,
    marginHorizontal: 16,
    marginBottom: 16,
    borderRadius: 12,
    padding: 16,
    elevation: 2,
    shadowColor: colors.black,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  blockoutHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 12,
  },
  blockoutInfo: {
    flex: 1,
    marginRight: 12,
  },
  blockoutFacility: {
    fontSize: 18,
    fontWeight: 'bold',
    color: colors.text,
    marginBottom: 4,
  },
  blockoutTime: {
    fontSize: 14,
    color: colors.textSecondary,
  },
  statusBadge: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 16,
  },
  activeBadge: {
    backgroundColor: colors.error + '20',
  },
  futureBadge: {
    backgroundColor: colors.warning + '20',
  },
  pastBadge: {
    backgroundColor: colors.textMuted + '20',
  },
  statusText: {
    fontSize: 12,
    fontWeight: '600',
    color: colors.text,
  },
  blockoutReason: {
    fontSize: 14,
    color: colors.text,
    marginBottom: 12,
    fontStyle: 'italic',
  },
  blockoutFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  blockoutCreator: {
    fontSize: 12,
    color: colors.textSecondary,
  },
  deleteButton: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 8,
    backgroundColor: colors.error + '20',
    gap: 6,
  },
  deleteButtonText: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.error,
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'flex-end',
  },
  modalContent: {
    backgroundColor: colors.white,
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    maxHeight: '90%',
    paddingBottom: 20,
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 20,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: colors.text,
  },
  modalBody: {
    padding: 20,
    maxHeight: 500,
  },
  formGroup: {
    marginBottom: 20,
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.text,
    marginBottom: 8,
  },
  dateButton: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 12,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.background,
    gap: 8,
  },
  dateButtonText: {
    fontSize: 16,
    color: colors.text,
  },
  textInput: {
    padding: 12,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.background,
    fontSize: 16,
    color: colors.text,
    minHeight: 80,
    textAlignVertical: 'top',
  },
  modalActions: {
    flexDirection: 'row',
    gap: 12,
    paddingHorizontal: 20,
    paddingTop: 12,
  },
  modalButton: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 14,
    borderRadius: 12,
    gap: 8,
  },
  cancelButton: {
    backgroundColor: colors.background,
    borderWidth: 1,
    borderColor: colors.border,
  },
  cancelButtonText: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.text,
  },
  createButton: {
    backgroundColor: colors.primary,
  },
  createButtonText: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.white,
  },
  buttonDisabled: {
    opacity: 0.6,
  },
});
