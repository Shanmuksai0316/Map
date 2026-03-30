import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  FlatList,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  Alert,
  Modal,
  TextInput,
  Switch,
  ScrollView,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { apiService } from '../../../shared/services/api.service';
import { theme } from '../../../shared/theme/theme';
import type { SportsCourt } from '../../../shared/types';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface Props {
  navigation: any;
}

const CATEGORIES = ['Basketball', 'Football', 'Tennis', 'Badminton', 'Cricket', 'Volleyball', 'Table Tennis', 'Other'];

export const CourtSetupScreen: React.FC<Props> = ({ navigation }) => {
  const [courts, setCourts] = useState<SportsCourt[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [showAddModal, setShowAddModal] = useState(false);
  const [selectedCategory, setSelectedCategory] = useState<string | null>(null);
  
  // Form state
  const [formName, setFormName] = useState('');
  const [formCategory, setFormCategory] = useState('');
  const [formLocation, setFormLocation] = useState('');
  const [formCapacity, setFormCapacity] = useState('');
  const [editingCourt, setEditingCourt] = useState<SportsCourt | null>(null);

  const fetchCourts = useCallback(async () => {
    try {
      const params: Record<string, string> = {};
      if (selectedCategory) {
        params.category = selectedCategory;
      }
      const data = await apiService.get<{ data: SportsCourt[] }>(
        '/sports/courts',
        { params },
      );
      setCourts(data?.data || []);
    } catch (error) {
      console.error('Failed to fetch courts:', error);
    } finally {
      setIsLoading(false);
    }
  }, [selectedCategory]);

  useEffect(() => {
    fetchCourts();
  }, [fetchCourts]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await fetchCourts();
    setRefreshing(false);
  }, [fetchCourts]);

  const resetForm = () => {
    setFormName('');
    setFormCategory('');
    setFormLocation('');
    setFormCapacity('');
    setEditingCourt(null);
  };

  const handleSaveCourt = async () => {
    if (!formName.trim() || !formCategory) {
      Alert.alert('Error', 'Please fill in name and category');
      return;
    }

    try {
      const data = {
        name: formName.trim(),
        category: formCategory,
        location: formLocation.trim() || null,
        capacity: formCapacity ? parseInt(formCapacity) : null,
      };

      if (editingCourt) {
        await apiService.put(`/sports/courts/${editingCourt.id}`, data);
        Alert.alert('Success', 'Court updated successfully');
      } else {
        await apiService.post('/sports/courts', data);
        Alert.alert('Success', 'Court added successfully');
      }
      
      setShowAddModal(false);
      resetForm();
      fetchCourts();
    } catch (error) {
      Alert.alert('Error', 'Failed to save court');
    }
  };

  const handleToggleStatus = async (court: SportsCourt) => {
    try {
      await apiService.post(`/sports/courts/${court.id}/toggle-status`);
      fetchCourts();
    } catch (error) {
      Alert.alert('Error', 'Failed to update court status');
    }
  };

  const handleDeleteCourt = (court: SportsCourt) => {
    Alert.alert(
      'Delete Court',
      `Are you sure you want to delete "${court.name}"?`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Delete',
          style: 'destructive',
          onPress: async () => {
            try {
                  await apiService.delete(`/sports/courts/${court.id}`);
              fetchCourts();
            } catch (error: any) {
              Alert.alert('Error', error.response?.data?.error || 'Failed to delete court');
            }
          },
        },
      ]
    );
  };

  const handleEditCourt = (court: SportsCourt) => {
    setEditingCourt(court);
    setFormName(court.name);
    setFormCategory(court.category);
    setFormLocation(court.location || '');
    setFormCapacity(court.capacity?.toString() || '');
    setShowAddModal(true);
  };

  const getCategoryIcon = (category: string): string => {
    const icons: Record<string, string> = {
      Basketball: 'basketball',
      Football: 'soccer',
      Tennis: 'tennis',
      Badminton: 'badminton',
      Cricket: 'cricket',
      Volleyball: 'volleyball',
      'Table Tennis': 'table-tennis',
      Other: 'dots-horizontal',
    };
    return icons[category] || 'dots-horizontal';
  };


  const renderEmptyState = () => (
    <View style={styles.emptyState}>
      <Icon name="basketball" size={64} color={theme.colors.textMuted} />
      <Text style={styles.emptyTitle}>No Courts Found</Text>
      <Text style={styles.emptySubtitle}>
        Add your first court to get started
      </Text>
    </View>
  );

  const categories = [...new Set(courts.map((c) => c.category))];

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Court Setup" />

      <ScrollView
        style={styles.scrollView}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
      >
        {/* Court Setup Card with Add Court Button */}
        <View style={styles.setupCard}>
          <GradientButton
            style={styles.addCourtButton}
            onPress={() => {
              resetForm();
              setShowAddModal(true);
            }}
          >
            <Icon name="plus-circle" size={24} color={theme.colors.primary} />
            <Text style={styles.addCourtButtonText}>Add court</Text>
          </GradientButton>
        </View>

        {/* List of Courts */}
        <View style={styles.content}>
          <Text style={styles.sectionTitle}>List of Courts</Text>
          {courts.length === 0 && !isLoading ? (
            renderEmptyState()
          ) : (
            courts.map((court) => (
              <View key={court.id} style={styles.courtCard}>
                <View style={styles.courtHeader}>
                  <View style={styles.courtInfo}>
                    <Text style={styles.courtName}>{court.name}</Text>
                    <Text style={styles.courtCategory}>{court.category}</Text>
                  </View>
                  <Switch
                    value={court.is_active}
                    onValueChange={() => handleToggleStatus(court)}
                    trackColor={{ false: theme.colors.border, true: theme.colors.successLight }}
                    thumbColor={court.is_active ? theme.colors.success : theme.colors.textMuted}
                  />
                </View>
                
                <View style={styles.courtDetails}>
                  {court.location && (
                    <View style={styles.detailRow}>
                      <Icon name="map-marker" size={16} color={theme.colors.textSecondary} />
                      <Text style={styles.detailText}>{court.location}</Text>
                    </View>
                  )}
                  {court.capacity && (
                    <View style={styles.detailRow}>
                      <Icon name="account-group" size={16} color={theme.colors.textSecondary} />
                      <Text style={styles.detailText}>Slots capacity: {court.capacity}</Text>
                    </View>
                  )}
                </View>

                <View style={styles.courtActions}>
                  <GradientButton
                    style={styles.editButton}
                    onPress={() => handleEditCourt(court)}
                  >
                    <Icon name="pencil" size={20} color={theme.colors.primary} />
                  </GradientButton>
                  <GradientButton
                    style={styles.deleteButton}
                    onPress={() => handleDeleteCourt(court)}
                  >
                    <Icon name="delete" size={20} color={theme.colors.error} />
                  </GradientButton>
                </View>
              </View>
            ))
          )}
        </View>
      </ScrollView>

      {/* Add/Edit Court Modal */}
      <Modal
        visible={showAddModal}
        animationType="slide"
        transparent
        onRequestClose={() => setShowAddModal(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContainer}>
            <View style={styles.modalHeader}>
              <TouchableOpacity onPress={() => setShowAddModal(false)}>
                <Icon name="close" size={24} color={theme.colors.white} />
              </TouchableOpacity>
              <Text style={styles.modalTitle}>Add Court</Text>
              <View style={styles.placeholder} />
            </View>

            <ScrollView style={styles.modalScroll} showsVerticalScrollIndicator={false}>
              <View style={styles.formGroup}>
                <Text style={styles.formLabel}>Court Name</Text>
                <TextInput
                  style={styles.formInput}
                  value={formName}
                  onChangeText={setFormName}
                  placeholder="Enter court name"
                  placeholderTextColor={theme.colors.textSecondary}
                />
              </View>

              <View style={styles.formGroup}>
                <Text style={styles.formLabel}>Sport Category</Text>
                <View style={styles.categoryGrid}>
                  {CATEGORIES.map((cat) => (
                    <TouchableOpacity
                      key={cat}
                      style={[
                        styles.categoryChip,
                        formCategory === cat && styles.categoryChipActive,
                      ]}
                      onPress={() => setFormCategory(cat)}
                    >
                      <Icon
                        name={getCategoryIcon(cat)}
                        size={16}
                        color={formCategory === cat ? theme.colors.white : theme.colors.text}
                      />
                      <Text
                        style={[
                          styles.categoryChipText,
                          formCategory === cat && styles.categoryChipTextActive,
                        ]}
                      >
                        {cat}
                      </Text>
                    </TouchableOpacity>
                  ))}
                </View>
              </View>

              <View style={styles.formGroup}>
                <Text style={styles.formLabel}>Location</Text>
                <TextInput
                  style={styles.formInput}
                  value={formLocation}
                  onChangeText={setFormLocation}
                  placeholder="Enter location"
                  placeholderTextColor={theme.colors.textSecondary}
                />
              </View>

              <View style={styles.formGroup}>
                <Text style={styles.formLabel}>Capacity</Text>
                <TextInput
                  style={styles.formInput}
                  value={formCapacity}
                  onChangeText={setFormCapacity}
                  placeholder="Enter capacity"
                  placeholderTextColor={theme.colors.textSecondary}
                  keyboardType="number-pad"
                />
              </View>
            </ScrollView>

            <View style={styles.modalActions}>
              <GradientButton
                style={styles.cancelButton}
                onPress={() => setShowAddModal(false)}
              >
                <Text style={styles.cancelButtonText}>Cancel</Text>
              </GradientButton>
              <GradientButton style={styles.saveButton} onPress={handleSaveCourt}>
                <Text style={styles.saveButtonText}>Add Court</Text>
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
  scrollView: {
    flex: 1,
  },
  setupCard: {
    backgroundColor: theme.colors.white,
    marginHorizontal: 16,
    marginTop: 16,
    borderRadius: 16,
    padding: 20,
    ...theme.shadows.medium,
  },
  addCourtButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  addCourtButtonText: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.primary,
  },
  content: {
    padding: 16,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: theme.colors.textHeading,
    marginBottom: 16,
  },
  courtCard: {
    backgroundColor: theme.colors.white,
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    ...theme.shadows.medium,
  },
  courtHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  courtInfo: {
    flex: 1,
  },
  courtName: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.textHeading,
    marginBottom: 4,
  },
  courtCategory: {
    fontSize: 14,
    color: theme.colors.textSecondary,
  },
  courtDetails: {
    marginBottom: 12,
    gap: 8,
  },
  detailRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  detailText: {
    fontSize: 14,
    color: theme.colors.text,
  },
  courtActions: {
    flexDirection: 'row',
    justifyContent: 'flex-end',
    gap: 16,
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: theme.colors.border,
  },
  editButton: {
    padding: 8,
  },
  deleteButton: {
    padding: 8,
  },
  emptyState: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 60,
  },
  emptyTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: theme.colors.textHeading,
    marginTop: 16,
  },
  emptySubtitle: {
    fontSize: 14,
    color: theme.colors.textSecondary,
    marginTop: 4,
    textAlign: 'center',
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'flex-end',
  },
  modalContainer: {
    backgroundColor: theme.colors.white,
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    padding: 20,
    paddingBottom: 34,
    maxHeight: '90%',
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 20,
    backgroundColor: theme.colors.primary,
    marginHorizontal: -20,
    marginTop: -20,
    padding: 20,
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: theme.colors.white,
    flex: 1,
    textAlign: 'center',
  },
  modalScroll: {
    maxHeight: 400,
  },
  formGroup: {
    marginBottom: 16,
  },
  formLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: theme.colors.textHeading,
    marginBottom: 8,
  },
  formInput: {
    backgroundColor: theme.colors.background,
    borderRadius: 12,
    padding: 14,
    fontSize: 15,
    color: theme.colors.text,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  categoryGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  categoryChip: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: theme.colors.background,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  categoryChipActive: {
    backgroundColor: theme.colors.primary,
    borderColor: theme.colors.primary,
  },
  categoryChipText: {
    fontSize: 13,
    fontWeight: '500',
    color: theme.colors.text,
    marginLeft: 6,
  },
  categoryChipTextActive: {
    color: theme.colors.white,
  },
  modalActions: {
    flexDirection: 'row',
    gap: 12,
    marginTop: 8,
  },
  cancelButton: {
    flex: 1,
    paddingVertical: 14,
    borderRadius: 12,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  cancelButtonText: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.text,
  },
  saveButton: {
    flex: 1,
    backgroundColor: theme.colors.primary,
    borderRadius: 12,
    paddingVertical: 14,
    alignItems: 'center',
  },
  saveButtonText: {
    color: theme.colors.white,
    fontSize: 16,
    fontWeight: '600',
  },
});

export default CourtSetupScreen;
