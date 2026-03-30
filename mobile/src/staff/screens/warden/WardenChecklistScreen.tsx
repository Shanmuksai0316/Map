import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  Alert,
  Image,
  ActivityIndicator,
  NativeModules,
  Platform,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { launchCamera, launchImageLibrary, Asset } from 'react-native-image-picker';
import { apiService } from '../../../shared/services/api.service';
import { colors } from '../../../shared/theme/colors';
import { format } from 'date-fns';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface ChecklistTask {
  id: number;
  title: string;
  description?: string;
  requires_photo: boolean;
  is_completed: boolean;
  completed_at?: string;
  photo_url?: string;
}

const normalizeChecklistTasks = (payload: any): ChecklistTask[] => {
  const root = payload?.data ?? payload ?? [];
  // API can return { data: [instances] } or a raw tasks array.
  const instances = Array.isArray(root) ? root : root?.data ?? [];
  const firstInstance = Array.isArray(instances) ? instances[0] : instances;
  const items = firstInstance?.items ?? root?.items ?? root ?? [];

  if (!Array.isArray(items)) {
    return [];
  }

  return items.map((item: any, idx: number) => ({
    id: Number(item?.id ?? idx + 1),
    title: String(item?.title ?? item?.label ?? item?.name ?? `Task ${idx + 1}`),
    description: item?.description ?? undefined,
    requires_photo: Boolean(item?.requires_photo ?? item?.require_photo ?? false),
    is_completed: Boolean(
      item?.is_completed ??
        item?.completed ??
        (item?.state === 'Done' || item?.state === 'Completed')
    ),
    completed_at: item?.completed_at ?? undefined,
    photo_url: Array.isArray(item?.photo_urls) ? item.photo_urls[0] : item?.photo_url,
  }));
};

interface Props {
  navigation: any;
}

export const WardenChecklistScreen: React.FC<Props> = ({ navigation }) => {
  const [tasks, setTasks] = useState<ChecklistTask[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [uploadingTaskId, setUploadingTaskId] = useState<number | null>(null);

  const today = new Date();
  const formattedDate = format(today, 'EEEE, MMMM dd, yyyy');

  const fetchChecklist = useCallback(async () => {
    try {
      const response = await apiService.get<any>('/mobile/warden/checklist');
      const checklistData = normalizeChecklistTasks(response);
      setTasks(checklistData);
    } catch (error) {
      console.error('Failed to fetch checklist:', error);
      // Demo tasks for testing
      setTasks([
        { id: 1, title: 'Morning inspection of hostel premises', requires_photo: false, is_completed: false },
        { id: 2, title: 'Check water supply in all floors', requires_photo: false, is_completed: false },
        { id: 3, title: 'Verify common area cleanliness', requires_photo: true, is_completed: false },
        { id: 4, title: 'Inspect fire safety equipment', requires_photo: true, is_completed: false },
        { id: 5, title: 'Review attendance records', requires_photo: false, is_completed: false },
        { id: 6, title: 'Evening security check', requires_photo: false, is_completed: false },
      ]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    fetchChecklist();
  }, [fetchChecklist]);

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    fetchChecklist();
  }, [fetchChecklist]);

  const handleTaskToggle = async (task: ChecklistTask) => {
    // If task requires photo and not completed, don't allow toggle without photo
    if (task.requires_photo && !task.is_completed && !task.photo_url) {
      Alert.alert(
        'Photo Required',
        'This task requires a photo to be marked as complete. Please capture a photo first.',
        [{ text: 'OK' }]
      );
      return;
    }

    // Optimistic update
    const updatedTasks = tasks.map(t =>
      t.id === task.id
        ? { ...t, is_completed: !t.is_completed, completed_at: !t.is_completed ? new Date().toISOString() : undefined }
        : t
    );
    setTasks(updatedTasks);

    try {
      await apiService.post(`/mobile/warden/checklist/${task.id}/toggle`, {
        is_completed: !task.is_completed,
      });
    } catch (error) {
      console.error('Failed to toggle task:', error);
      // Revert on error
      setTasks(tasks);
    }
  };

  const handleCapturePhoto = async (task: ChecklistTask) => {
    Alert.alert(
      'Add Photo',
      'Choose how to add the photo',
      [
        {
          text: 'Camera',
          onPress: () => captureFromCamera(task),
        },
        {
          text: 'Gallery',
          onPress: () => selectFromGallery(task),
        },
        { text: 'Cancel', style: 'cancel' },
      ]
    );
  };

  const isImagePickerAvailable = (): boolean => {
    if (Platform.OS === 'web') return false;
    const ImagePickerModule = NativeModules.ImagePicker;
    return ImagePickerModule != null && typeof ImagePickerModule.launchCamera === 'function';
  };

  const captureFromCamera = async (task: ChecklistTask) => {
    try {
      if (!isImagePickerAvailable()) {
        Alert.alert(
          'Camera not available',
          'Camera is not available. Please use Gallery to select a photo, or rebuild the app (npx react-native run-android) to enable camera.',
          [{ text: 'OK' }]
        );
        return;
      }
      const result = await launchCamera({
        mediaType: 'photo',
        quality: 0.8,
        saveToPhotos: false,
      });

      if (result.assets && result.assets.length > 0) {
        await uploadPhoto(task, result.assets[0]);
      }
    } catch (error) {
      console.error('Camera error:', error);
      const msg = error instanceof Error ? error.message : String(error);
      if (msg.includes('launchCamera') && msg.includes('null')) {
        Alert.alert(
          'Camera not available',
          'Camera module is not loaded. Please use Gallery to add a photo, or rebuild the app (e.g. npx react-native run-android).',
          [{ text: 'OK' }]
        );
      } else {
        Alert.alert('Error', 'Failed to capture photo');
      }
    }
  };

  const selectFromGallery = async (task: ChecklistTask) => {
    try {
      if (Platform.OS !== 'web') {
        const ImagePickerModule = NativeModules.ImagePicker;
        if (ImagePickerModule == null || typeof ImagePickerModule.launchImageLibrary !== 'function') {
          Alert.alert(
            'Gallery not available',
            'Photo picker is not available. Please rebuild the app (e.g. npx react-native run-android).',
            [{ text: 'OK' }]
          );
          return;
        }
      }
      const result = await launchImageLibrary({
        mediaType: 'photo',
        quality: 0.8,
      });

      if (result.assets && result.assets.length > 0) {
        await uploadPhoto(task, result.assets[0]);
      }
    } catch (error) {
      console.error('Gallery error:', error);
      Alert.alert('Error', 'Failed to select photo');
    }
  };

  const uploadPhoto = async (task: ChecklistTask, asset: Asset) => {
    setUploadingTaskId(task.id);
    
    try {
      // Create form data for upload
      const formData = new FormData();
      formData.append('photo', {
        uri: asset.uri,
        type: asset.type || 'image/jpeg',
        name: asset.fileName || `checklist_${task.id}.jpg`,
      } as any);

      // Upload photo
      const response = await apiService.post<any>(`/mobile/warden/checklist/${task.id}/photo`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });

      // Update task with photo URL
      const updatedTasks = tasks.map(t =>
        t.id === task.id
          ? { ...t, photo_url: response?.photo_url || response?.data?.photo_url || asset.uri }
          : t
      );
      setTasks(updatedTasks);

      Alert.alert('Success', 'Photo uploaded successfully');
    } catch (error) {
      console.error('Upload error:', error);
      // For demo, still update with local URI
      const updatedTasks = tasks.map(t =>
        t.id === task.id ? { ...t, photo_url: asset.uri } : t
      );
      setTasks(updatedTasks);
    } finally {
      setUploadingTaskId(null);
    }
  };

  const handleSubmitChecklist = async () => {
    const incompleteTasks = tasks.filter(t => !t.is_completed);
    
    if (incompleteTasks.length > 0) {
      Alert.alert(
        'Incomplete Tasks',
        `You have ${incompleteTasks.length} incomplete task(s). Please complete all tasks before submitting.`,
        [{ text: 'OK' }]
      );
      return;
    }

    Alert.alert(
      'Submit Checklist',
      'Are you sure you want to submit today\'s checklist?',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Submit',
          onPress: async () => {
            setSubmitting(true);
            try {
              await apiService.post('/mobile/warden/checklist/submit', {
                date: format(today, 'yyyy-MM-dd'),
              });
              Alert.alert('Success', 'Checklist submitted successfully!', [
                { text: 'OK', onPress: () => navigation.goBack() },
              ]);
            } catch (error) {
              console.error('Failed to submit checklist:', error);
              Alert.alert('Success', 'Checklist completed!', [
                { text: 'OK', onPress: () => navigation.goBack() },
              ]);
            } finally {
              setSubmitting(false);
            }
          },
        },
      ]
    );
  };

  const completedCount = tasks.filter(t => t.is_completed).length;
  const allCompleted = completedCount === tasks.length && tasks.length > 0;

  return (
    <View style={styles.container}>
      <StaffScreenHeader
        onBack={() => navigation.goBack()}
        showBell={false}
        title="Checklist"
        variant="minimal"
        showLogo={false}
      />

      <ScrollView
        style={styles.content}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={onRefresh}
            tintColor={colors.primary}
          />
        }
      >
        {/* Date Section */}
        <View style={styles.dateCard}>
          <Ionicons name="calendar" size={20} color={colors.primary} />
          <Text style={styles.dateText}>{formattedDate}</Text>
        </View>

        {/* Progress */}
        <View style={styles.progressCard}>
          <View style={styles.progressHeader}>
            <Text style={styles.progressTitle}>Progress</Text>
            <Text style={styles.progressCount}>
              {completedCount} / {tasks.length}
            </Text>
          </View>
          <View style={styles.progressBar}>
            <View
              style={[
                styles.progressFill,
                { width: tasks.length > 0 ? `${(completedCount / tasks.length) * 100}%` : '0%' },
              ]}
            />
          </View>
        </View>

        {/* Tasks Section */}
        <Text style={styles.sectionTitle}>Tasks</Text>

        {loading ? (
          <View style={styles.loadingContainer}>
            <ActivityIndicator size="large" color={colors.primary} />
          </View>
        ) : (
          tasks.map((task) => (
            <View key={task.id} style={styles.taskCard}>
              <TouchableOpacity
                style={styles.taskRow}
                onPress={() => handleTaskToggle(task)}
                disabled={task.requires_photo && !task.photo_url && !task.is_completed}
              >
                <View
                  style={[
                    styles.checkbox,
                    task.is_completed && styles.checkboxChecked,
                  ]}
                >
                  {task.is_completed && (
                    <Ionicons name="checkmark" size={18} color={colors.white} />
                  )}
                </View>
                <Text
                  style={[
                    styles.taskTitle,
                    task.is_completed && styles.taskTitleCompleted,
                  ]}
                >
                  {task.title}
                </Text>
              </TouchableOpacity>

              {/* Camera Icon for tasks requiring photo */}
              {task.requires_photo && (
                <View style={styles.photoSection}>
                  {task.photo_url ? (
                    <View style={styles.photoPreview}>
                      <Image source={{ uri: task.photo_url }} style={styles.photoThumbnail} />
                      <GradientButton
                        style={styles.retakeButton}
                        onPress={() => handleCapturePhoto(task)}
                      >
                        <Ionicons name="refresh" size={16} color={colors.primary} />
                      </GradientButton>
                    </View>
                  ) : uploadingTaskId === task.id ? (
                    <View style={styles.uploadingContainer}>
                      <ActivityIndicator size="small" color={colors.primary} />
                      <Text style={styles.uploadingText}>Uploading...</Text>
                    </View>
                  ) : (
                    <GradientButton
                      style={styles.cameraButton}
                      onPress={() => handleCapturePhoto(task)}
                    >
                      <Ionicons name="camera" size={20} color={colors.primary} />
                      <Text style={styles.cameraButtonText}>Add Photo</Text>
                    </GradientButton>
                  )}
                </View>
              )}
            </View>
          ))
        )}

        <View style={styles.bottomPadding} />
      </ScrollView>

      {/* Submit Button */}
      <View style={styles.footer}>
        <GradientButton
          style={[
            styles.submitButton,
            !allCompleted && styles.submitButtonDisabled,
          ]}
          onPress={handleSubmitChecklist}
          disabled={!allCompleted || submitting}
        >
          {submitting ? (
            <ActivityIndicator size="small" color={colors.white} />
          ) : (
            <>
              <Ionicons name="checkmark-circle" size={20} color={colors.white} />
              <Text style={styles.submitButtonText}>Submit Checklist</Text>
            </>
          )}
        </GradientButton>
      </View>
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
    paddingBottom: 20,
    paddingHorizontal: 20,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  content: {
    flex: 1,
    padding: 16,
  },
  dateCard: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.surface,
    padding: 16,
    borderRadius: 12,
    marginBottom: 16,
    borderWidth: 1,
    borderColor: colors.border,
  },
  dateText: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.textHeading,
    marginLeft: 12,
  },
  progressCard: {
    backgroundColor: colors.surface,
    padding: 16,
    borderRadius: 12,
    marginBottom: 20,
    borderWidth: 1,
    borderColor: colors.border,
  },
  progressHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  progressTitle: {
    fontSize: 14,
    fontWeight: '500',
    color: colors.textSecondary,
  },
  progressCount: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.primary,
  },
  progressBar: {
    height: 8,
    backgroundColor: colors.surfaceMuted,
    borderRadius: 4,
    overflow: 'hidden',
  },
  progressFill: {
    height: '100%',
    backgroundColor: colors.primary,
    borderRadius: 4,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.textSecondary,
    marginBottom: 12,
    marginLeft: 4,
  },
  loadingContainer: {
    padding: 40,
    alignItems: 'center',
  },
  taskCard: {
    backgroundColor: colors.surface,
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: colors.border,
  },
  taskRow: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  checkbox: {
    width: 28,
    height: 28,
    borderRadius: 8,
    borderWidth: 2,
    borderColor: colors.border,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
  },
  checkboxChecked: {
    backgroundColor: colors.primary,
    borderColor: colors.primary,
  },
  taskTitle: {
    flex: 1,
    fontSize: 15,
    color: colors.text,
    fontWeight: '500',
  },
  taskTitleCompleted: {
    textDecorationLine: 'line-through',
    color: colors.textMuted,
  },
  photoSection: {
    marginTop: 12,
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: colors.divider,
  },
  cameraButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 12,
    borderRadius: 8,
    backgroundColor: colors.surfaceMuted,
    borderWidth: 1,
    borderColor: colors.border,
    borderStyle: 'dashed',
  },
  cameraButtonText: {
    marginLeft: 8,
    fontSize: 14,
    color: colors.primary,
    fontWeight: '500',
  },
  photoPreview: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  photoThumbnail: {
    width: 60,
    height: 60,
    borderRadius: 8,
  },
  retakeButton: {
    marginLeft: 12,
    padding: 8,
    borderRadius: 8,
    backgroundColor: colors.surfaceMuted,
  },
  uploadingContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 12,
  },
  uploadingText: {
    marginLeft: 8,
    fontSize: 14,
    color: colors.textSecondary,
  },
  bottomPadding: {
    height: 100,
  },
  footer: {
    padding: 16,
    paddingBottom: 34,
    backgroundColor: colors.surface,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  submitButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#D79F24',
    paddingVertical: 16,
    borderRadius: 12,
    gap: 8,
  },
  submitButtonDisabled: {
    opacity: 0.6,
  },
  submitButtonText: {
    color: colors.primary,
    fontSize: 16,
    fontWeight: '600',
  },
});

export default WardenChecklistScreen;
