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
import { GradientButton } from '../../../shared/components/GradientButton';
import Ionicons from 'react-native-vector-icons/Ionicons';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { useAuthStore } from '../../../shared/store/auth.store';
import { apiService } from '../../../shared/services/api.service';
import { APP_CONFIG } from '../../../shared/config/app.config';
import { theme } from '../../../shared/theme/theme';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';
import { StaffPrimaryButton } from '../../components/StaffPrimaryButton';

interface ChecklistTask {
  id: number;
  title: string;
  requires_comment: boolean;
  requires_photo: boolean;
  completed: boolean;
  comment?: string;
  photo?: string;
}

const HOUSEKEEPING_TASKS: ChecklistTask[] = [
  {
    id: 1,
    title: 'Clean common areas (lobby, corridors)',
    requires_comment: true,
    requires_photo: false,
    completed: false,
  },
  {
    id: 2,
    title: 'Sanitize and maintain restrooms',
    requires_photo: true,
    requires_comment: false,
    completed: false,
  },
  {
    id: 3,
    title: 'Dispose of waste and empty bins',
    requires_comment: false,
    requires_photo: false,
    completed: false,
  },
  {
    id: 4,
    title: 'Check cleaning supplies inventory',
    requires_comment: true,
    requires_photo: false,
    completed: false,
  },
  {
    id: 5,
    title: 'Clean and sanitize dining area',
    requires_comment: true,
    requires_photo: true,
    completed: false,
  },
  {
    id: 6,
    title: 'Verify room cleaning completion',
    requires_comment: true,
    requires_photo: false,
    completed: false,
  },
  {
    id: 7,
    title: 'Clean and organize laundry area',
    requires_comment: false,
    requires_photo: false,
    completed: false,
  },
  {
    id: 8,
    title: 'Complete daily housekeeping report',
    requires_comment: true,
    requires_photo: false,
    completed: false,
  },
];

interface Props {
  navigation: any;
}

const getCurrentDate = () => {
  const date = new Date();
  const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
  const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  return `${days[date.getDay()]}, ${months[date.getMonth()]} ${String(date.getDate()).padStart(2, '0')}, ${date.getFullYear()}`;
};

export const HKSupervisorChecklistScreen: React.FC<Props> = ({ navigation }) => {
  const { user } = useAuthStore();
  const [tasks, setTasks] = useState<ChecklistTask[]>(HOUSEKEEPING_TASKS);
  const [refreshing, setRefreshing] = useState(false);
  const [selectedTask, setSelectedTask] = useState<ChecklistTask | null>(null);
  const [showTaskModal, setShowTaskModal] = useState(false);
  const [comment, setComment] = useState('');
  const [hasPermission, setHasPermission] = useState<boolean | null>(null);
  const [showCamera, setShowCamera] = useState(false);

  useEffect(() => {
    // In a real app, you would request camera permissions here
    setHasPermission(true);
  }, []);

  const handleTaskPress = (task: ChecklistTask) => {
    setSelectedTask(task);
    setComment(task.comment || '');
    setShowTaskModal(true);
  };

  const handleCompleteTask = async () => {
    if (!selectedTask) return;

    if (selectedTask.requires_comment && !comment.trim()) {
      Alert.alert('Error', 'Comment is required for this task');
      return;
    }

    if (selectedTask.requires_photo && !selectedTask.photo) {
      Alert.alert('Error', 'Photo is required for this task');
      return;
    }

    try {
      const updatedTasks = tasks.map((task) =>
        task.id === selectedTask.id
          ? { ...task, completed: true, comment: comment.trim() }
          : task
      );
      setTasks(updatedTasks);

      // Here you would save to API
      // await apiService.post(`${APP_CONFIG.ENDPOINTS.CHECKLISTS}/tasks/${selectedTask.id}/complete`, {
      //   comment,
      //   photo: selectedTask.photo,
      // });

      Alert.alert('Success', 'Task completed successfully');
      setShowTaskModal(false);
    } catch (error) {
      console.error('Task completion error:', error);
      Alert.alert('Error', 'Failed to complete task');
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

  const allTasksCompleted = completedCount === totalCount && totalCount > 0;

  const handleSubmitChecklist = () => {
    if (allTasksCompleted) {
      Alert.alert('Success', 'Checklist completed successfully!', [
        { text: 'OK', onPress: () => navigation.goBack() }
      ]);
    } else {
      Alert.alert('Incomplete', 'Please complete all tasks before submitting.');
    }
  };

  return (
    <View style={styles.container}>
      <StaffScreenHeader
        title="Daily Checklist"
        variant="minimal"
        onBack={() => navigation.goBack()}
        showBell={false}
        showLogo={false}
      />

      <ScrollView
        style={styles.scrollView}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={() => {}} />
        }>
        {/* Date */}
        <View style={styles.dateContainer}>
          <Text style={styles.dateText}>{getCurrentDate()}</Text>
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
                        <Ionicons name="chatbubble-outline" size={12} color={theme.colors.primary} />
                        <Text style={styles.requirementText}>Comment</Text>
                      </View>
                    )}
                    {task.requires_photo && (
                      <View style={styles.requirementBadge}>
                        <Ionicons name="camera-outline" size={12} color={theme.colors.primary} />
                        <Text style={styles.requirementText}>Photo</Text>
                      </View>
                    )}
                  </View>
                </View>
                {task.completed ? (
                  <Ionicons name="checkmark-circle" size={28} color={theme.colors.primary} />
                ) : (
                  <Ionicons name="ellipse-outline" size={28} color={theme.colors.textMuted} />
                )}
              </View>
            </TouchableOpacity>
          ))}

          {/* Submit Button */}
          <StaffPrimaryButton
            label="Submit Checklist"
            onPress={handleSubmitChecklist}
            disabled={!allTasksCompleted}
            style={styles.submitButtonWrap}
          />
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
              <Ionicons name="close" size={24} color={theme.colors.text} />
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
                      <GradientButton
                        style={styles.photoButton}
                        onPress={handleTakePhoto}>
                        <Ionicons name="camera" size={24} color={theme.colors.primary} />
                        <Text style={styles.photoButtonText}>Take Photo</Text>
                      </GradientButton>
                    )}
                  </View>
                )}

                <GradientButton
                  style={[
                    styles.completeButton,
                    selectedTask.completed && styles.completeButtonDisabled,
                  ]}
                  onPress={handleCompleteTask}
                  disabled={selectedTask.completed}>
                  <Text style={styles.completeButtonText}>
                    {selectedTask.completed ? 'Completed' : 'Mark as Complete'}
                  </Text>
                </GradientButton>
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
    backgroundColor: theme.colors.background,
  },
  scrollView: {
    flex: 1,
  },
  dateContainer: {
    backgroundColor: theme.colors.white,
    marginHorizontal: 16,
    marginTop: 16,
    padding: 16,
    borderRadius: 12,
    ...theme.shadows.medium,
  },
  dateText: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.textHeading,
  },
  content: {
    padding: 16,
  },
  taskCard: {
    backgroundColor: theme.colors.white,
    padding: 16,
    borderRadius: 12,
    marginBottom: 12,
    ...theme.shadows.medium,
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
    color: theme.colors.text,
    marginBottom: 8,
  },
  taskTitleCompleted: {
    textDecorationLine: 'line-through',
    color: theme.colors.textMuted,
  },
  taskRequirements: {
    flexDirection: 'row',
    gap: 8,
  },
  requirementBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: theme.colors.accentMuted,
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 4,
    gap: 4,
  },
  requirementText: {
    fontSize: 10,
    color: theme.colors.primary,
    fontWeight: '600',
  },
  submitButtonWrap: {
    marginTop: 8,
  },
  modalContainer: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 20,
    paddingTop: 60,
    backgroundColor: theme.colors.primary,
  },
  modalTitle: {
    color: theme.colors.white,
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
    color: theme.colors.text,
    marginBottom: 8,
  },
  commentInput: {
    backgroundColor: theme.colors.white,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: 8,
    padding: 12,
    fontSize: 16,
    color: theme.colors.text,
    minHeight: 100,
    textAlignVertical: 'top',
  },
  photoButton: {
    backgroundColor: theme.colors.white,
    borderWidth: 2,
    borderColor: theme.colors.border,
    borderStyle: 'dashed',
    borderRadius: 8,
    padding: 40,
    alignItems: 'center',
    justifyContent: 'center',
  },
  photoButtonText: {
    marginTop: 8,
    fontSize: 14,
    color: theme.colors.primary,
    fontWeight: '600',
  },
  photoPreview: {
    width: '100%',
    height: 200,
    borderRadius: 8,
  },
  completeButton: {
    backgroundColor: theme.colors.primary,
    paddingVertical: 14,
    borderRadius: 8,
    alignItems: 'center',
    marginTop: 20,
  },
  completeButtonDisabled: {
    backgroundColor: theme.colors.textMuted,
  },
  completeButtonText: {
    color: theme.colors.white,
    fontSize: 16,
    fontWeight: '600',
  },
});
