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
  TextInput,
  NativeModules,
  Platform,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { launchCamera, launchImageLibrary } from 'react-native-image-picker';
import { useChecklistStore } from '../../../shared/store/checklist.store';
import type { ChecklistTask } from '../../../shared/types';
import { theme } from '../../../shared/theme/theme';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';
import { StaffPrimaryButton } from '../../components/StaffPrimaryButton';

interface Props {
  navigation: any;
}

export const GuardChecklistScreen: React.FC<Props> = ({ navigation }) => {
  const {
    tasks,
    isLoading,
    fetchTodayChecklist,
    completeTask,
    uploadTaskPhoto,
    submitChecklist,
  } = useChecklistStore();
  
  const [refreshing, setRefreshing] = useState(false);
  const [uploadingTaskId, setUploadingTaskId] = useState<number | null>(null);
  const [commentsByIndex, setCommentsByIndex] = useState<Record<number, string>>({});

  useEffect(() => {
    fetchTodayChecklist();
  }, [fetchTodayChecklist]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await fetchTodayChecklist();
    setRefreshing(false);
  }, [fetchTodayChecklist]);

  const handleCompleteTask = async (task: ChecklistTask) => {
    if (task.requires_photo && !task.photo_url) {
      Alert.alert(
        'Photo Required',
        'This task requires a photo before completion. Please upload a photo first.',
        [{ text: 'OK' }]
      );
      return;
    }

    if (task.requires_comment) {
      const comment = (commentsByIndex[task.index] ?? '').trim();
      if (!comment) {
        Alert.alert('Comment Required', 'Please enter a comment before completion.', [
          { text: 'OK' },
        ]);
        return;
      }
    }

    try {
      const comment = (commentsByIndex[task.index] ?? '').trim() || undefined;
      await completeTask(task.id, comment);
    } catch (error) {
      Alert.alert('Error', 'Failed to complete task');
    }
  };

  const handleSubmit = async () => {
    try {
      await submitChecklist();
      setCommentsByIndex({});
      Alert.alert('Submitted', 'Checklist submitted successfully.', [{ text: 'OK' }]);
    } catch (e: any) {
      Alert.alert(
        'Submit Failed',
        e?.response?.data?.message || e?.message || 'Failed to submit checklist'
      );
    }
  };

  const handleUploadPhoto = async (task: ChecklistTask) => {
    Alert.alert('Upload Photo', 'Choose an option', [
      {
        text: 'Take Photo',
        onPress: () => takePhoto(task.id),
      },
      {
        text: 'Choose from Library',
        onPress: () => chooseFromLibrary(task.id),
      },
      {
        text: 'Cancel',
        style: 'cancel',
      },
    ]);
  };

  const isImagePickerAvailable = (): boolean => {
    if (Platform.OS === 'web') return false;
    const M = NativeModules.ImagePicker;
    return M != null && typeof M.launchCamera === 'function';
  };

  const takePhoto = async (taskId: number) => {
    try {
      if (!isImagePickerAvailable()) {
        Alert.alert(
          'Camera not available',
          'Please use Gallery to select a photo, or rebuild the app (npx react-native run-android) to enable camera.',
          [{ text: 'OK' }]
        );
        return;
      }
      const result = await launchCamera({
        mediaType: 'photo',
        quality: 0.8,
        maxWidth: 1280,
        maxHeight: 1280,
      });

      if (result.assets && result.assets[0]) {
        setUploadingTaskId(taskId);
        await uploadTaskPhoto(taskId, result.assets[0]);
        setUploadingTaskId(null);
      }
    } catch (error) {
      setUploadingTaskId(null);
      const msg = error instanceof Error ? error.message : String(error);
      if (msg.includes('launchCamera') && msg.includes('null')) {
        Alert.alert(
          'Camera not available',
          'Please use Gallery to add a photo, or rebuild the app (npx react-native run-android).',
          [{ text: 'OK' }]
        );
      } else {
        Alert.alert('Error', 'Failed to take photo');
      }
    }
  };

  const chooseFromLibrary = async (taskId: number) => {
    try {
      if (Platform.OS !== 'web' && (NativeModules.ImagePicker == null || typeof NativeModules.ImagePicker.launchImageLibrary !== 'function')) {
        Alert.alert(
          'Gallery not available',
          'Photo picker is not available. Please rebuild the app (npx react-native run-android).',
          [{ text: 'OK' }]
        );
        return;
      }
      const result = await launchImageLibrary({
        mediaType: 'photo',
        quality: 0.8,
        maxWidth: 1280,
        maxHeight: 1280,
      });

      if (result.assets && result.assets[0]) {
        setUploadingTaskId(taskId);
        await uploadTaskPhoto(taskId, result.assets[0]);
        setUploadingTaskId(null);
      }
    } catch (error) {
      setUploadingTaskId(null);
      Alert.alert('Error', 'Failed to select photo');
    }
  };

  const getCompletedCount = () => tasks.filter(t => t.is_completed).length;
  const getTotalCount = () => tasks.length;
  const getProgressPercent = () =>
    getTotalCount() > 0 ? Math.round((getCompletedCount() / getTotalCount()) * 100) : 0;

  const renderTask = (task: ChecklistTask) => (
    <View
      key={task.id}
      style={[styles.taskCard, task.is_completed && styles.taskCardCompleted]}
    >
      <View style={styles.taskHeader}>
        <TouchableOpacity
          style={[
            styles.checkbox,
            task.is_completed && styles.checkboxCompleted,
          ]}
          onPress={() => !task.is_completed && handleCompleteTask(task)}
          disabled={task.is_completed}
        >
          {task.is_completed && (
            <Icon name="check" size={16} color="#FFFFFF" />
          )}
        </TouchableOpacity>
        <View style={styles.taskInfo}>
          <Text
            style={[
              styles.taskTitle,
              task.is_completed && styles.taskTitleCompleted,
            ]}
          >
            {task.title}
          </Text>
          {task.description && (
            <Text style={styles.taskDescription}>{task.description}</Text>
          )}
          {task.requires_comment && !task.is_completed && (
            <View style={styles.commentBox}>
              <Text style={styles.commentLabel}>Comment (required)</Text>
              <TextInput
                value={commentsByIndex[task.index] ?? ''}
                onChangeText={(text) =>
                  setCommentsByIndex((prev) => ({ ...prev, [task.index]: text }))
                }
                placeholder="Enter comment"
                placeholderTextColor="#9CA3AF"
                style={styles.commentInput}
                multiline
              />
            </View>
          )}
        </View>
      </View>

      {task.requires_photo && (
        <View style={styles.photoSection}>
          {task.photo_url ? (
            <View style={styles.photoPreview}>
              <Image source={{ uri: task.photo_url }} style={styles.photoImage} />
              <View style={styles.photoUploaded}>
                <Icon name="check-circle" size={16} color="#10B981" />
                <Text style={styles.photoUploadedText}>Photo uploaded</Text>
              </View>
            </View>
          ) : (
            <GradientButton
              style={styles.uploadButton}
              onPress={() => handleUploadPhoto(task)}
              disabled={uploadingTaskId === task.id}
            >
              <Icon
                name={uploadingTaskId === task.id ? 'loading' : 'camera-plus'}
                size={20}
                color="#3B82F6"
              />
              <Text style={styles.uploadButtonText}>
                {uploadingTaskId === task.id ? 'Uploading...' : 'Upload Photo'}
              </Text>
            </GradientButton>
          )}
        </View>
      )}

      {task.is_completed && task.completed_at && (
        <View style={styles.completedInfo}>
          <Icon name="check-circle" size={14} color="#10B981" />
          <Text style={styles.completedText}>
            Completed at{' '}
            {new Date(task.completed_at).toLocaleTimeString([], {
              hour: '2-digit',
              minute: '2-digit',
            })}
          </Text>
        </View>
      )}
    </View>
  );

  const canSubmit = tasks.length > 0 && tasks.every((t) => t.is_completed);

  return (
    <View style={styles.container}>
      <StaffScreenHeader
        onBack={() => navigation.goBack()}
        showBell={false}
        title="Checklist"
        variant="minimal"
        showLogo={false}
      />

      {/* Progress Summary */}
      <View style={styles.progressSummary}>
        <View style={styles.progressInfo}>
          <Text style={styles.progressLabel}>Progress</Text>
          <Text style={styles.progressValue}>
            {getCompletedCount()} / {getTotalCount()} tasks
          </Text>
        </View>
        <View style={styles.progressBarContainer}>
          <View style={styles.progressBarBg}>
            <View
              style={[styles.progressBar, { width: `${getProgressPercent()}%` }]}
            />
          </View>
          <Text style={styles.progressPercent}>{getProgressPercent()}%</Text>
        </View>
      </View>

      <ScrollView
        style={styles.scrollView}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
      >
        {/* Form Fields (always show all fields) */}
        {tasks.length > 0 && (
          <View style={styles.section}>
            <View style={styles.sectionHeader}>
              <Icon name="clipboard-check-outline" size={20} color={theme.colors.primary} />
              <Text style={styles.sectionTitle}>Checklist Form</Text>
              <View style={styles.sectionBadge}>
                <Text style={styles.sectionBadgeText}>{tasks.length}</Text>
              </View>
            </View>
            {tasks.map(renderTask)}
          </View>
        )}

        {tasks.length === 0 && !isLoading && (
          <View style={styles.emptyState}>
            <Icon name="clipboard-check-outline" size={64} color="#E5E7EB" />
            <Text style={styles.emptyText}>No checklist fields configured</Text>
          </View>
        )}

        {tasks.length > 0 && (
          <View style={styles.submitContainer}>
            <StaffPrimaryButton
              label={canSubmit ? 'Submit Form' : 'Complete all fields to submit'}
              onPress={handleSubmit}
              disabled={!canSubmit || isLoading}
              loading={isLoading}
            />
          </View>
        )}

        <View style={styles.bottomPadding} />
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  progressSummary: {
    backgroundColor: theme.colors.white,
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
  },
  progressInfo: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  progressLabel: {
    fontSize: 14,
    color: theme.colors.textSecondary,
  },
  progressValue: {
    fontSize: 14,
    fontWeight: '600',
    color: theme.colors.textHeading,
  },
  progressBarContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  progressBarBg: {
    flex: 1,
    height: 8,
    backgroundColor: theme.colors.border,
    borderRadius: 4,
    overflow: 'hidden',
  },
  progressBar: {
    height: '100%',
    backgroundColor: theme.colors.success,
    borderRadius: 4,
  },
  progressPercent: {
    fontSize: 14,
    fontWeight: '600',
    color: theme.colors.success,
    width: 40,
    textAlign: 'right',
  },
  scrollView: {
    flex: 1,
  },
  section: {
    padding: 16,
  },
  sectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 12,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.textHeading,
    marginLeft: 8,
    flex: 1,
  },
  sectionBadge: {
    backgroundColor: theme.colors.accentMuted,
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
  },
  sectionBadgeText: {
    fontSize: 12,
    fontWeight: '600',
    color: theme.colors.accent,
  },
  commentBox: {
    marginTop: 10,
  },
  commentLabel: {
    fontSize: 12,
    color: theme.colors.textSecondary,
    marginBottom: 6,
  },
  commentInput: {
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 10,
    minHeight: 44,
    color: theme.colors.textHeading,
    backgroundColor: theme.colors.background,
  },
  taskCard: {
    backgroundColor: theme.colors.white,
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  taskCardCompleted: {
    backgroundColor: theme.colors.background,
    borderColor: theme.colors.successLight,
  },
  taskHeader: {
    flexDirection: 'row',
    alignItems: 'flex-start',
  },
  checkbox: {
    width: 24,
    height: 24,
    borderRadius: 12,
    borderWidth: 2,
    borderColor: theme.colors.border,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
    marginTop: 2,
  },
  checkboxCompleted: {
    backgroundColor: theme.colors.success,
    borderColor: theme.colors.success,
  },
  taskInfo: {
    flex: 1,
  },
  taskTitle: {
    fontSize: 15,
    fontWeight: '600',
    color: theme.colors.text,
    marginBottom: 4,
  },
  taskTitleCompleted: {
    color: theme.colors.textSecondary,
    textDecorationLine: 'line-through',
  },
  taskDescription: {
    fontSize: 13,
    color: theme.colors.textSecondary,
    marginBottom: 4,
  },
  timeRow: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  timeText: {
    fontSize: 12,
    color: theme.colors.textSecondary,
    marginLeft: 4,
  },
  photoSection: {
    marginTop: 12,
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: theme.colors.border,
  },
  uploadButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: theme.colors.accentMuted,
    paddingVertical: 12,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: theme.colors.accent,
    borderStyle: 'dashed',
  },
  uploadButtonText: {
    color: theme.colors.primary,
    fontSize: 14,
    fontWeight: '500',
    marginLeft: 8,
  },
  photoPreview: {
    alignItems: 'center',
  },
  photoImage: {
    width: '100%',
    height: 150,
    borderRadius: 8,
    marginBottom: 8,
  },
  photoUploaded: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  photoUploadedText: {
    color: theme.colors.success,
    fontSize: 13,
    marginLeft: 4,
  },
  completedInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: 12,
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: theme.colors.border,
  },
  completedText: {
    color: theme.colors.success,
    fontSize: 12,
    marginLeft: 6,
  },
  emptyState: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 60,
  },
  emptyText: {
    fontSize: 16,
    color: theme.colors.textSecondary,
    marginTop: 16,
  },
  submitContainer: {
    paddingHorizontal: 16,
    paddingBottom: 12,
  },
  bottomPadding: {
    height: 40,
  },
});

export default GuardChecklistScreen;
