import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  Alert,
  TextInput,
  Image,
} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useAuthStore } from '../../store/auth.store';
import { apiService } from '../../services/api.service';
import { APP_CONFIG } from '../../config/app.config';
import { colors } from '../../theme/colors';
import { OfflineIndicator } from '../../components/shared/OfflineIndicator';
import { launchImageLibrary, launchCamera, ImagePickerResponse } from 'react-native-image-picker';

interface ChecklistItem {
  id: number;
  code?: string;
  title: string;
  description: string;
  category: 'security' | 'cleanliness' | 'maintenance' | 'general';
  status: 'pending' | 'completed' | 'in_progress';
  priority: 'low' | 'medium' | 'high';
  assigned_to?: string;
  due_date?: string;
  completed_at?: string;
  require_photo?: boolean;
  require_comment?: boolean;
  tenant_id: string;
  created_at: string;
  updated_at: string;
}

export const WardenChecklistScreen = ({ navigation }: any) => {
  const { user } = useAuthStore();
  const [checklistItems, setChecklistItems] = useState<ChecklistItem[]>([]);
  const [checklistId, setChecklistId] = useState<number | null>(null);
  const [itemPhotos, setItemPhotos] = useState<Record<number, string[]>>({});
  const [itemNotes, setItemNotes] = useState<Record<number, string>>({});
  const [itemChecked, setItemChecked] = useState<Record<number, boolean>>({});
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [uploadingTaskId, setUploadingTaskId] = useState<number | null>(null);

  const fetchChecklist = async () => {
    try {
      const response = await apiService.get<{ data: any[] }>('/checklists/today?role=Warden&shift=Daily');
      const instances = response?.data ?? [];
      const instance = instances[0];

      if (instance) {
        setChecklistId(instance.id);
        const mapped = (instance.items ?? []).map((item: any) => ({
          id: item.id,
          code: item.code,
          title: item.label ?? item.code,
          description: item.description ?? '',
          category: 'general',
          status: item.state?.toLowerCase() === 'done' ? 'completed' : 'pending',
          priority: 'medium',
          completed_at: item.completed_at,
          require_photo: Boolean(item.require_photo),
          require_comment: Boolean(item.require_comment),
          tenant_id: instance.tenant_id ?? '',
          created_at: item.created_at,
          updated_at: item.updated_at,
        })) as ChecklistItem[];

        // initialize checkbox and notes state
        const checked: Record<number, boolean> = {};
        const notes: Record<number, string> = {};
        mapped.forEach((item) => {
          checked[item.id] = item.status === 'completed';
          notes[item.id] = itemNotes[item.id] ?? '';
        });

        setItemChecked(checked);
        setChecklistItems(mapped);
      } else {
        setChecklistItems([]);
      }
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchChecklist();
  }, []);

  const onRefresh = () => {
    setRefreshing(true);
    fetchChecklist();
  };

  const handleToggleCheckbox = async (itemId: number) => {
    if (!checklistId) return;

    const item = checklistItems.find(ci => ci.id === itemId);
    if (!item || itemChecked[itemId]) return;

    try {
      await apiService.post(`/checklists/${checklistId}/items/${item.code ?? item.id}`, {
        state: 'Done',
        comment: itemNotes[itemId] ?? undefined,
      });

      setItemChecked(prev => ({
        ...prev,
        [itemId]: true,
      }));

      setChecklistItems(prev =>
        prev.map(ci =>
          ci.id === itemId
            ? { ...ci, status: 'completed', completed_at: new Date().toISOString() }
            : ci
        )
      );
    } catch (error) {
      Alert.alert('Error', 'Failed to update task');
    }
  };

  const handlePhotoUpload = async (itemId: number) => {
    Alert.alert(
      'Add Photo',
      'Choose photo source',
      [
        {
          text: 'Camera',
          onPress: async () => {
            try {
              const result = await launchCamera({
                mediaType: 'photo',
                quality: 0.8,
                saveToPhotos: false,
              });
              handleImagePick(result, itemId);
            } catch (error) {
              console.error('Camera error:', error);
              Alert.alert('Error', 'Failed to open camera');
            }
          },
        },
        {
          text: 'Gallery',
          onPress: async () => {
            try {
              const result = await launchImageLibrary({
                mediaType: 'photo',
                quality: 0.8,
              });
              handleImagePick(result, itemId);
            } catch (error) {
              console.error('Gallery error:', error);
              Alert.alert('Error', 'Failed to open gallery');
            }
          },
        },
        { text: 'Cancel', style: 'cancel' },
      ]
    );
  };

  const handleImagePick = async (result: ImagePickerResponse, itemId: number) => {
    if (result.didCancel) return;

    if (result.errorCode) {
      Alert.alert('Error', result.errorMessage || 'Failed to select photo');
      return;
    }

    if (result.assets && result.assets.length > 0) {
      const photoUri = result.assets[0].uri;
      if (!photoUri || !checklistId) return;

      const item = checklistItems.find(ci => ci.id === itemId);
      if (!item) return;

      try {
        setUploadingTaskId(itemId);
        const formData = new FormData();
        formData.append('photo', {
          uri: photoUri,
          type: 'image/jpeg',
          name: `checklist_${item.code ?? item.id}_${Date.now()}.jpg`,
        } as any);

        const resp = await apiService.post(
          `/checklists/${checklistId}/items/${item.code ?? item.id}/photo`,
          formData,
          { headers: { 'Content-Type': 'multipart/form-data' } }
        );

        const photoUrl = resp?.photo_url ?? resp?.data?.photo_url;

        setItemPhotos(prev => ({
          ...prev,
          [itemId]: [...(prev[itemId] || []), photoUrl || photoUri],
        }));
      } catch (error) {
        console.error('Photo upload failed', error);
        Alert.alert('Error', 'Failed to upload photo');
      } finally {
        setUploadingTaskId(null);
      }
    }
  };

  const handleRemovePhoto = (itemId: number, photoIndex: number) => {
    setItemPhotos(prev => ({
      ...prev,
      [itemId]: prev[itemId]?.filter((_, index) => index !== photoIndex) || [],
    }));
  };

  const canSubmitChecklist = () => {
    if (checklistItems.length === 0) return false;

    const allChecked = checklistItems.every(item => itemChecked[item.id]);
    if (!allChecked) return false;

    const notesRequired = checklistItems.filter(item => item.require_comment);
    const notesProvided = notesRequired.every(
      item => itemNotes[item.id]?.trim()?.length > 0
    );
    if (!notesProvided) return false;

    return true;
  };

  const handleSubmitChecklist = async () => {
    if (!canSubmitChecklist()) {
      Alert.alert(
        'Validation Error',
        'Please complete all tasks. Ensure all items are checked, required photos are uploaded, and required notes are added.',
      );
      return;
    }

    Alert.alert(
      'Submit Checklist',
      'Are you sure you want to submit the checklist?',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Submit',
          onPress: submitChecklist,
        },
      ]
    );
  };

  const submitChecklist = async () => {
    if (!checklistId) return;
    setSubmitting(true);
    try {
      await apiService.post(`/checklists/${checklistId}/submit`);
      Alert.alert('Success', 'Checklist submitted successfully');
      fetchChecklist();
    } catch (error) {
      console.error('Checklist submit error:', error);
      Alert.alert('Error', 'Failed to submit checklist. Please try again.');
    } finally {
      setSubmitting(false);
    }
  };

  const getCategoryIcon = (category: string) => {
    switch (category) {
      case 'security': return 'lock-closed-outline';
      case 'cleanliness': return 'broom-outline';
      case 'maintenance': return 'construct-outline';
      default: return 'clipboard-outline';
    }
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'completed': return colors.success;
      case 'in_progress': return colors.warning;
      default: return colors.textMuted;
    }
  };

  const ChecklistCard = ({ item }: { item: ChecklistItem }) => {
    const isChecked = itemChecked[item.id] || false;
    const photos = itemPhotos[item.id] || [];
    const notes = itemNotes[item.id] || '';

    return (
      <View style={styles.checklistCard}>
        <View style={styles.cardHeader}>
          <View style={styles.checkboxContainer}>
            <TouchableOpacity
              style={[styles.checkbox, isChecked && styles.checkboxChecked]}
              onPress={() => handleToggleCheckbox(item.id)}>
              {isChecked && <Ionicons name="checkmark" size={16} color={colors.surface} />}
            </TouchableOpacity>
            <View style={styles.categoryContainer}>
              <Ionicons name={getCategoryIcon(item.category)} size={20} color={colors.primary} style={styles.categoryIcon} />
              <Text style={styles.categoryText}>{item.category.toUpperCase()}</Text>
            </View>
          </View>
        </View>

        <Text style={styles.title}>{item.title}</Text>
        <Text style={styles.description}>{item.description}</Text>

        {item.assigned_to && (
          <Text style={styles.assignedTo}>Assigned to: {item.assigned_to}</Text>
        )}

        {item.due_date && (
          <Text style={styles.dueDate}>Due: {new Date(item.due_date).toLocaleDateString()}</Text>
        )}

        {/* Photo Upload Section */}
        {item.require_photo && (
          <View style={styles.photoSection}>
            <View style={styles.photoHeader}>
              <Text style={styles.photoLabel}>
                Photos {item.require_photo ? '*' : ''}
              </Text>
              {photos.length > 0 && (
                <Text style={styles.photoCount}>{photos.length} photo(s)</Text>
              )}
            </View>
            
            {photos.length > 0 && (
              <View style={styles.photoList}>
                {photos.map((photo, index) => (
                  <View key={index} style={styles.photoItem}>
                    <Image source={{ uri: photo }} style={styles.photoImage} />
                    <TouchableOpacity
                      style={styles.removePhotoButton}
                      onPress={() => handleRemovePhoto(item.id, index)}>
                      <Ionicons name="close-circle" size={20} color={colors.error} />
                    </TouchableOpacity>
                  </View>
                ))}
              </View>
            )}
            
            <TouchableOpacity
              style={styles.addPhotoButton}
              onPress={() => handlePhotoUpload(item.id)}>
              <Ionicons name="camera-outline" size={20} color={colors.primary} />
              <Text style={styles.addPhotoText}>Add Photo</Text>
            </TouchableOpacity>
          </View>
        )}

        {/* Notes Section */}
        <View style={styles.notesSection}>
          <Text style={styles.notesLabel}>
            Notes {item.require_comment ? '*' : '(optional)'}
          </Text>
          <TextInput
            style={styles.notesInput}
            placeholder={item.require_comment ? 'Enter notes (required)' : 'Add notes (optional)'}
            value={notes}
            onChangeText={(text) => setItemNotes(prev => ({ ...prev, [item.id]: text }))}
            multiline
            numberOfLines={3}
            placeholderTextColor={colors.textMuted}
          />
        </View>
      </View>
    );
  };

  const pendingCount = checklistItems.filter(item => item.status === 'pending').length;
  const inProgressCount = checklistItems.filter(item => item.status === 'in_progress').length;
  const completedCount = checklistItems.filter(item => item.status === 'completed').length;

  return (
    <ScrollView
      style={styles.container}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
      }>
      <OfflineIndicator />
      
      {/* Header */}
      <View style={styles.header}>
        <View>
          <Text style={styles.greeting}>Checklist</Text>
          <Text style={styles.subGreeting}>Daily tasks and inspections</Text>
        </View>
      </View>

      {/* Summary */}
      <View style={styles.summary}>
        <View style={styles.summaryItem}>
          <Text style={[styles.summaryNumber, { color: colors.error }]}>{pendingCount}</Text>
          <Text style={styles.summaryLabel}>Pending</Text>
        </View>
        <View style={styles.summaryItem}>
          <Text style={[styles.summaryNumber, { color: colors.warning }]}>{inProgressCount}</Text>
          <Text style={styles.summaryLabel}>In Progress</Text>
        </View>
        <View style={styles.summaryItem}>
          <Text style={[styles.summaryNumber, { color: colors.success }]}>{completedCount}</Text>
          <Text style={styles.summaryLabel}>Completed</Text>
        </View>
      </View>

      {/* Checklist Items */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Daily Checklist</Text>
        {checklistItems.map((item) => (
          <ChecklistCard key={item.id} item={item} />
        ))}
      </View>

      {/* Submit Button */}
      <View style={styles.footer}>
        <TouchableOpacity
          style={[
            styles.submitButton,
            (!canSubmitChecklist() || submitting) && styles.submitButtonDisabled,
          ]}
          onPress={handleSubmitChecklist}
          disabled={!canSubmitChecklist() || submitting}>
          {submitting ? (
            <Text style={styles.submitButtonText}>Submitting...</Text>
          ) : (
            <Text style={styles.submitButtonText}>Submit Checklist</Text>
          )}
        </TouchableOpacity>
      </View>
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  header: {
    backgroundColor: colors.primary,
    padding: 20,
    paddingTop: 60,
  },
  greeting: {
    color: colors.surface,
    fontSize: 24,
    fontWeight: 'bold',
  },
  subGreeting: {
    color: colors.surface,
    fontSize: 14,
    opacity: 0.8,
    marginTop: 4,
  },
  summary: {
    backgroundColor: colors.surface,
    padding: 16,
    flexDirection: 'row',
    justifyContent: 'space-around',
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  summaryItem: {
    alignItems: 'center',
  },
  summaryNumber: {
    fontSize: 24,
    fontWeight: 'bold',
  },
  summaryLabel: {
    fontSize: 12,
    color: colors.textMuted,
    marginTop: 2,
  },
  section: {
    padding: 20,
  },
  sectionTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: colors.textPrimary,
    marginBottom: 16,
  },
  checklistCard: {
    backgroundColor: colors.surface,
    padding: 16,
    borderRadius: 12,
    marginBottom: 12,
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  checkboxContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  checkbox: {
    width: 24,
    height: 24,
    borderRadius: 4,
    borderWidth: 2,
    borderColor: colors.primary,
    backgroundColor: colors.surface,
    marginRight: 12,
    alignItems: 'center',
    justifyContent: 'center',
  },
  checkboxChecked: {
    backgroundColor: colors.primary,
  },
  categoryContainer: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  categoryIcon: {
    fontSize: 16,
    marginRight: 8,
  },
  categoryText: {
    fontSize: 12,
    fontWeight: '600',
    color: colors.primary,
  },
  statusBadge: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
  },
  statusText: {
    color: colors.surface,
    fontSize: 10,
    fontWeight: '600',
  },
  title: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: 8,
  },
  description: {
    fontSize: 14,
    color: colors.textMuted,
    marginBottom: 8,
  },
  assignedTo: {
    fontSize: 12,
    color: colors.textMuted,
    marginBottom: 4,
  },
  dueDate: {
    fontSize: 12,
    color: colors.warning,
    marginBottom: 12,
  },
  actions: {
    flexDirection: 'row',
    gap: 12,
  },
  actionButton: {
    flex: 1,
    paddingVertical: 10,
    borderRadius: 8,
    alignItems: 'center',
  },
  startButton: {
    backgroundColor: colors.warning,
  },
  completeButton: {
    backgroundColor: colors.success,
  },
  startButtonText: {
    color: colors.surface,
    fontSize: 14,
    fontWeight: '600',
  },
  completeButtonText: {
    color: colors.surface,
    fontSize: 14,
    fontWeight: '600',
  },
  photoSection: {
    marginTop: 12,
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  photoHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  photoLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textPrimary,
  },
  photoCount: {
    fontSize: 12,
    color: colors.textMuted,
  },
  photoList: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    marginBottom: 12,
  },
  photoItem: {
    width: 80,
    height: 80,
    borderRadius: 8,
    overflow: 'hidden',
    position: 'relative',
  },
  photoImage: {
    width: '100%',
    height: '100%',
    resizeMode: 'cover',
  },
  removePhotoButton: {
    position: 'absolute',
    top: 4,
    right: 4,
    backgroundColor: colors.surface,
    borderRadius: 12,
  },
  addPhotoButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 12,
    borderWidth: 2,
    borderColor: colors.primary,
    borderRadius: 8,
    borderStyle: 'dashed',
    gap: 8,
  },
  addPhotoText: {
    fontSize: 14,
    color: colors.primary,
    fontWeight: '600',
  },
  notesSection: {
    marginTop: 12,
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  notesLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: 8,
  },
  notesInput: {
    backgroundColor: colors.surfaceMuted,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 8,
    padding: 12,
    fontSize: 14,
    color: colors.textPrimary,
    minHeight: 80,
    textAlignVertical: 'top',
  },
  footer: {
    backgroundColor: colors.surface,
    padding: 20,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  submitButton: {
    backgroundColor: colors.primary,
    paddingVertical: 16,
    borderRadius: 12,
    alignItems: 'center',
  },
  submitButtonDisabled: {
    backgroundColor: colors.textMuted,
  },
  submitButtonText: {
    color: colors.surface,
    fontSize: 16,
    fontWeight: '600',
  },
});
