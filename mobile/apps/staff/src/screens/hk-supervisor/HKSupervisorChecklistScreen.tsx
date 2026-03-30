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
  Modal,
  Image,
  Platform,
} from 'react-native';
import { launchCamera, launchImageLibrary, ImagePickerResponse } from 'react-native-image-picker';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useAuthStore } from '../../store/auth.store';
import { apiService } from '../../services/api.service';
import { colors } from '../../theme/colors';

interface ChecklistTask {
  id: number;
  code?: string;
  title: string;
  requires_comment: boolean;
  requires_photo: boolean;
  completed: boolean;
  comment?: string;
  photo?: string;
}

export const HKSupervisorChecklistScreen = ({ navigation }: any) => {
  const { user } = useAuthStore();
  const [checklistId, setChecklistId] = useState<number | null>(null);
  const [tasks, setTasks] = useState<ChecklistTask[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [selectedTask, setSelectedTask] = useState<ChecklistTask | null>(null);
  const [showTaskModal, setShowTaskModal] = useState(false);
  const [comment, setComment] = useState('');
  const [hasPermission, setHasPermission] = useState<boolean | null>(null);
  const [showCamera, setShowCamera] = useState(false);
  const [uploading, setUploading] = useState(false);

  useEffect(() => {
    setHasPermission(true);
    fetchChecklist();
  }, []);

  const fetchChecklist = async () => {
    try {
      const response = await apiService.get<{ data: any[] }>('/checklists/today?role=HKSupervisor&shift=Daily');
      const instances = response?.data ?? [];
      const instance = instances[0];

      if (instance) {
        setChecklistId(instance.id);
        const mapped = (instance.items ?? []).map((item: any, index: number) => ({
          id: item.id ?? index,
          code: item.code ?? item.label ?? `task-${index}`,
          title: item.label ?? item.code ?? `Task ${index + 1}`,
          requires_comment: Boolean(item.require_comment),
          requires_photo: Boolean(item.require_photo),
          completed: item.state?.toLowerCase() === 'done',
          comment: item.comment ?? '',
          photo: (item.photo_urls ?? [])[0],
        })) as ChecklistTask[];
        setTasks(mapped);
      } else {
        setTasks([]);
      }
    } catch (error) {
      console.error('Checklist fetch error', error);
      setTasks([]);
    } finally {
      setRefreshing(false);
    }
  };

  const handleTaskPress = (task: ChecklistTask) => {
    setSelectedTask(task);
    setComment(task.comment || '');
    setShowTaskModal(true);
  };

  const handleCompleteTask = async () => {
    if (!selectedTask || !checklistId) return;

    if (selectedTask.requires_comment && !comment.trim()) {
      Alert.alert('Error', 'Comment is required for this task');
      return;
    }

    if (selectedTask.requires_photo && !selectedTask.photo) {
      Alert.alert('Error', 'Photo is required for this task');
      return;
    }

    try {
      await apiService.post(`/checklists/${checklistId}/items/${selectedTask.code ?? selectedTask.id}`, {
        state: 'Done',
        comment: comment.trim() || undefined,
      });

      const updatedTasks = tasks.map((task) =>
        task.id === selectedTask.id
          ? { ...task, completed: true, comment: comment.trim() }
          : task
      );
      setTasks(updatedTasks);

      Alert.alert('Success', 'Task completed successfully');
      setShowTaskModal(false);
    } catch (error) {
      console.error('Task completion error:', error);
      Alert.alert('Error', 'Failed to complete task');
    }
  };

  const handlePhotoPick = async (useCamera: boolean) => {
    if (!selectedTask || !checklistId) return;

    const picker = useCamera ? launchCamera : launchImageLibrary;
    try {
      const result = await picker({ mediaType: 'photo', quality: 0.8 });
      if (result.didCancel || result.errorCode) {
        if (result.errorMessage) {
          Alert.alert('Error', result.errorMessage);
        }
        return;
      }

      const asset = result.assets?.[0];
      if (!asset?.uri) return;

      setUploading(true);
      const formData = new FormData();
      formData.append('photo', {
        uri: asset.uri,
        type: asset.type ?? 'image/jpeg',
        name: asset.fileName ?? `checklist_${selectedTask.code ?? selectedTask.id}_${Date.now()}.jpg`,
      } as any);

      const resp = await apiService.post(
        `/checklists/${checklistId}/items/${selectedTask.code ?? selectedTask.id}/photo`,
        formData,
        { headers: { 'Content-Type': 'multipart/form-data' } }
      );

      const photoUrl = resp?.photo_url ?? resp?.data?.photo_url ?? asset.uri;

      setTasks(prev =>
        prev.map(task =>
          task.id === selectedTask.id ? { ...task, photo: photoUrl } : task
        )
      );

      setSelectedTask(prev => (prev ? { ...prev, photo: photoUrl } : prev));
    } catch (error) {
      console.error('Photo upload failed', error);
      Alert.alert('Error', 'Failed to upload photo');
    } finally {
      setUploading(false);
    }
  };

  const handleTakePhoto = async () => {
    if (hasPermission === false) {
      Alert.alert('Permission Required', 'Camera permission is required');
      return;
    }

    // In a real app, you would use expo-camera or react-native-image-picker
    // This is a simplified version
    Alert.alert('Photo', 'Photo capture would be implemented here');
  };

  const completedCount = tasks.filter((t) => t.completed).length;
  const totalCount = tasks.length;

  return (
    <View style={styles.container}>
      <ScrollView
        style={styles.scrollView}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={() => {
              setRefreshing(true);
              fetchChecklist();
            }}
          />
        }>
        <View style={styles.header}>
          <Text style={styles.headerTitle}>Housekeeping Checklist</Text>
          <Text style={styles.headerSubtitle}>
            {completedCount} of {totalCount} tasks completed
          </Text>
        </View>

        <View style={styles.progressContainer}>
          <View style={styles.progressBar}>
            <View
              style={[
                styles.progressFill,
                { width: `${(completedCount / totalCount) * 100}%` },
              ]}
            />
          </View>
        </View>

        <View style={styles.content}>
          {tasks.map((task) => (
            <TouchableOpacity
              key={task.id}
              style={[
                styles.taskCard,
                task.completed && styles.taskCardCompleted,
              ]}
              onPress={() => handleTaskPress(task)}>
              <View style={styles.taskHeader}>
                <View style={styles.taskInfo}>
                  <Text
                    style={[
                      styles.taskTitle,
                      task.completed && styles.taskTitleCompleted,
                    ]}>
                    {task.title}
                  </Text>
                  <View style={styles.taskRequirements}>
                    {task.requires_comment && (
                      <View style={styles.requirementBadge}>
                        <Ionicons name="chatbubble-outline" size={12} color={colors.primary} />
                        <Text style={styles.requirementText}>Comment</Text>
                      </View>
                    )}
                    {task.requires_photo && (
                      <View style={styles.requirementBadge}>
                        <Ionicons name="camera-outline" size={12} color={colors.primary} />
                        <Text style={styles.requirementText}>Photo</Text>
                      </View>
                    )}
                  </View>
                </View>
                {task.completed ? (
                  <Ionicons name="checkmark-circle" size={24} color={colors.success} />
                ) : (
                  <Ionicons name="ellipse-outline" size={24} color={colors.textMuted} />
                )}
              </View>
            </TouchableOpacity>
          ))}
        </View>
      </ScrollView>

      {/* Task Detail Modal */}
      <Modal
        visible={showTaskModal}
        animationType="slide"
        transparent={false}
        onRequestClose={() => setShowTaskModal(false)}>
        <View style={styles.modalContainer}>
          <View style={styles.modalHeader}>
            <Text style={styles.modalTitle}>
              {selectedTask?.title}
            </Text>
            <TouchableOpacity
              onPress={() => setShowTaskModal(false)}
              style={styles.closeButton}>
              <Ionicons name="close" size={24} color={colors.textPrimary} />
            </TouchableOpacity>
          </View>

          <ScrollView style={styles.modalContent}>
            {selectedTask && (
              <>
                {selectedTask.requires_comment && (
                  <View style={styles.inputSection}>
                    <Text style={styles.inputLabel}>Comment *</Text>
                    <TextInput
                      style={styles.commentInput}
                      value={comment}
                      onChangeText={setComment}
                      placeholder="Enter your comment..."
                      multiline
                      numberOfLines={4}
                    />
                  </View>
                )}

                {selectedTask.requires_photo && (
                  <View style={styles.inputSection}>
                    <Text style={styles.inputLabel}>Photo *</Text>
                    {selectedTask.photo ? (
                      <Image
                        source={{ uri: selectedTask.photo }}
                        style={styles.photoPreview}
                      />
                    ) : (
                      <View style={{ flexDirection: 'row', gap: 12 }}>
                        <TouchableOpacity
                          style={styles.photoButton}
                          onPress={() => handlePhotoPick(true)}
                          disabled={uploading}>
                          <Ionicons name="camera" size={24} color={colors.primary} />
                          <Text style={styles.photoButtonText}>
                            {uploading ? 'Uploading...' : 'Camera'}
                          </Text>
                        </TouchableOpacity>
                        <TouchableOpacity
                          style={styles.photoButton}
                          onPress={() => handlePhotoPick(false)}
                          disabled={uploading}>
                          <Ionicons name="image" size={24} color={colors.primary} />
                          <Text style={styles.photoButtonText}>
                            {uploading ? 'Uploading...' : 'Gallery'}
                          </Text>
                        </TouchableOpacity>
                      </View>
                    )}
                  </View>
                )}

                <TouchableOpacity
                  style={[
                    styles.completeButton,
                    selectedTask.completed && styles.completeButtonDisabled,
                  ]}
                  onPress={handleCompleteTask}
                  disabled={selectedTask.completed}>
                  <Text style={styles.completeButtonText}>
                    {selectedTask.completed ? 'Completed' : 'Mark as Complete'}
                  </Text>
                </TouchableOpacity>
              </>
            )}
          </ScrollView>
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
  scrollView: {
    flex: 1,
  },
  header: {
    backgroundColor: colors.primary,
    padding: 20,
    paddingTop: 60,
  },
  headerTitle: {
    color: colors.surface,
    fontSize: 24,
    fontWeight: 'bold',
    marginBottom: 4,
  },
  headerSubtitle: {
    color: colors.surface,
    fontSize: 14,
    opacity: 0.9,
  },
  progressContainer: {
    padding: 20,
    backgroundColor: colors.surface,
  },
  progressBar: {
    height: 8,
    backgroundColor: colors.border,
    borderRadius: 4,
    overflow: 'hidden',
  },
  progressFill: {
    height: '100%',
    backgroundColor: colors.success,
    borderRadius: 4,
  },
  content: {
    padding: 20,
  },
  taskCard: {
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
  taskCardCompleted: {
    opacity: 0.7,
  },
  taskHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  taskInfo: {
    flex: 1,
    marginRight: 12,
  },
  taskTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: 8,
  },
  taskTitleCompleted: {
    textDecorationLine: 'line-through',
    color: colors.textMuted,
  },
  taskRequirements: {
    flexDirection: 'row',
    gap: 8,
  },
  requirementBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: 'rgba(255, 107, 53, 0.1)',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 4,
    gap: 4,
  },
  requirementText: {
    fontSize: 10,
    color: colors.primary,
    fontWeight: '600',
  },
  modalContainer: {
    flex: 1,
    backgroundColor: colors.background,
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 20,
    paddingTop: 60,
    backgroundColor: colors.primary,
  },
  modalTitle: {
    color: colors.surface,
    fontSize: 20,
    fontWeight: 'bold',
    flex: 1,
  },
  closeButton: {
    padding: 8,
  },
  modalContent: {
    flex: 1,
    padding: 20,
  },
  inputSection: {
    marginBottom: 24,
  },
  inputLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: 8,
  },
  commentInput: {
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 8,
    padding: 12,
    fontSize: 16,
    color: colors.textPrimary,
    minHeight: 100,
    textAlignVertical: 'top',
  },
  photoButton: {
    backgroundColor: colors.surface,
    borderWidth: 2,
    borderColor: colors.border,
    borderStyle: 'dashed',
    borderRadius: 8,
    padding: 40,
    alignItems: 'center',
    justifyContent: 'center',
  },
  photoButtonText: {
    marginTop: 8,
    fontSize: 14,
    color: colors.primary,
    fontWeight: '600',
  },
  photoPreview: {
    width: '100%',
    height: 200,
    borderRadius: 8,
  },
  completeButton: {
    backgroundColor: colors.primary,
    paddingVertical: 14,
    borderRadius: 8,
    alignItems: 'center',
    marginTop: 20,
  },
  completeButtonDisabled: {
    backgroundColor: colors.textMuted,
  },
  completeButtonText: {
    color: colors.surface,
    fontSize: 16,
    fontWeight: '600',
  },
});

