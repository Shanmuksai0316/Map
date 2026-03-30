/**
 * SupervisorChecklistDetailScreen
 * 
 * Allows HK and RM Supervisors to view and complete daily checklists.
 * Supports offline mode with optimistic updates.
 */

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
import { useOfflineQueue } from '../../hooks/useOfflineQueue';
import { OfflineIndicator } from '../../components/shared/OfflineIndicator';
import { ChecklistItemRow } from '../../components/supervisor/ChecklistItemRow';
import { theme } from '../../theme/theme';
import { format } from 'date-fns';

interface ChecklistItem {
  id: number;
  code: string;
  label: string;
  state: 'pending' | 'done' | 'skipped';
  require_photo?: boolean;
  require_comment?: boolean;
  comment?: string;
  photo_url?: string;
}

interface ChecklistInstance {
  id: number;
  template: {
    id: number;
    title: string;
    role: string;
  };
  date: string;
  shift: string;
  status: 'pending' | 'submitted' | 'approved' | 'sent_back';
  total_tasks: number;
  completed_tasks: number;
  items: ChecklistItem[];
  submitted_at?: string;
  manager_note?: string;
}

export const SupervisorChecklistDetailScreen = ({ navigation }: any) => {
  const { user } = useAuthStore();
  const { addAction, isOnline } = useOfflineQueue();
  const [checklist, setChecklist] = useState<ChecklistInstance | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [checklistId, setChecklistId] = useState<number | null>(null);

  const fetchChecklist = async () => {
    try {
      setLoading(true);
      const response = await apiService.get<{ data: ChecklistInstance }>(
        '/checklists/today?role=HKSupervisor&shift=Daily'
      );
      const instances = (response as any)?.data ?? [];
      const instance = instances[0];
      if (instance) {
        setChecklistId(instance.id);
        setChecklist(instance);
      } else {
        setChecklist(null);
      }
    } catch (error) {
      console.error('Checklist fetch error:', error);
      // Mock data for demo
      setChecklist({
        id: 1,
        template: {
          id: 1,
          title: user?.role === 'hk_supervisor' ? 'Daily Housekeeping Checklist' : 'Daily Maintenance Checklist',
          role: user?.role || 'Supervisor',
        },
        date: new Date().toISOString().split('T')[0],
        shift: 'Morning',
        status: 'pending',
        total_tasks: 8,
        completed_tasks: 0,
        items: [
          {
            id: 1,
            code: 'common_area_cleaning',
            label: 'Clean common areas (lobby, corridors)',
            state: 'pending',
            require_comment: true,
          },
          {
            id: 2,
            code: 'restroom_maintenance',
            label: 'Check and maintain restrooms',
            state: 'pending',
            require_comment: false,
            require_photo: true,
          },
          {
            id: 3,
            code: 'waste_disposal',
            label: 'Dispose of waste and empty bins',
            state: 'pending',
            require_comment: false,
          },
          {
            id: 4,
            code: 'supplies_check',
            label: 'Check cleaning supplies inventory',
            state: 'pending',
            require_comment: true,
          },
          {
            id: 5,
            code: 'safety_check',
            label: 'Safety equipment inspection',
            state: 'pending',
            require_comment: true,
            require_photo: true,
          },
          {
            id: 6,
            code: 'equipment_maintenance',
            label: 'Maintenance equipment check',
            state: 'pending',
            require_comment: true,
          },
          {
            id: 7,
            code: 'student_feedback',
            label: 'Review student complaints/feedback',
            state: 'pending',
            require_comment: false,
          },
          {
            id: 8,
            code: 'daily_report',
            label: 'Complete daily report',
            state: 'pending',
            require_comment: true,
          },
        ],
      });
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

  const handleToggleItem = async (itemCode: string, newState: 'pending' | 'done' | 'skipped') => {
    if (!checklist) return;

    const payload = {
      state: newState,
    };

    try {
      if (isOnline) {
        await apiService.post(`/admin/checklists/${checklist.id}/items/${itemCode}`, payload);
      } else {
        await addAction('checklist_item_update', {
          checklist_id: checklist.id,
          item_code: itemCode,
          ...payload,
        });
      }

      // Update local state optimistically
      setChecklist(prev => {
        if (!prev) return null;
        const updatedItems = prev.items.map(item =>
          item.code === itemCode ? { ...item, state: newState } : item
        );
        const completedCount = updatedItems.filter(item => item.state === 'done').length;
        return {
          ...prev,
          items: updatedItems,
          completed_tasks: completedCount,
        };
      });
    } catch (error) {
      console.error('Checklist item update error:', error);
      Alert.alert('Error', 'Failed to update checklist item');
    }
  };

  const handleCommentChange = (itemCode: string, comment: string) => {
    if (!checklist) return;

    setChecklist(prev => {
      if (!prev) return null;
      return {
        ...prev,
        items: prev.items.map(item =>
          item.code === itemCode ? { ...item, comment } : item
        ),
      };
    });
  };

  const handlePhotoUpload = (itemCode: string) => {
    Alert.alert('Photo Upload', 'Photo upload feature will be available soon');
  };

  const handleSubmit = async () => {
    if (!checklist) return;

    // Validate all required items are marked as done
    const allMarked = checklist.items.every(item => 
      item.state === 'done' || item.state === 'skipped'
    );

    if (!allMarked) {
      const unmarkedCount = checklist.items.filter(item => item.state === 'pending').length;
      Alert.alert(
        'Cannot Submit',
        `${unmarkedCount} item${unmarkedCount > 1 ? 's' : ''} still pending. Please mark all items before submitting.`
      );
      return;
    }

    // Validate required comments
    const missingComments = checklist.items.filter(
      item => item.state === 'done' && item.require_comment && !item.comment
    );
    if (missingComments.length > 0) {
      Alert.alert(
        'Missing Comments',
        `${missingComments.length} item${missingComments.length > 1 ? 's' : ''} require comments. Please add comments before submitting.`
      );
      return;
    }

    // Validate required photos
    const missingPhotos = checklist.items.filter(
      item => item.state === 'done' && item.require_photo && !item.photo_url
    );
    if (missingPhotos.length > 0) {
      Alert.alert(
        'Missing Photos',
        `${missingPhotos.length} item${missingPhotos.length > 1 ? 's' : ''} require photos. Please upload photos before submitting.`
      );
      return;
    }

    Alert.alert(
      'Submit Checklist',
      'Are you sure you want to submit this checklist?',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Submit',
          style: 'default',
          onPress: async () => {
            try {
              setSubmitting(true);

              if (isOnline && checklistId) {
                await apiService.post(`/checklists/${checklistId}/submit`);

                setChecklist(prev => {
                  if (!prev) return null;
                  return {
                    ...prev,
                    status: 'submitted',
                    submitted_at: new Date().toISOString(),
                  };
                });

                Alert.alert('Success', 'Checklist submitted successfully');
                navigation.goBack();
              } else {
                await addAction('checklist_submit', {
                  checklist_id: checklist?.id,
                });
                Alert.alert('Queued', 'Checklist submission queued for sync when online');
              }
            } catch (error) {
              console.error('Checklist submit error:', error);
              Alert.alert('Error', 'Failed to submit checklist');
            } finally {
              setSubmitting(false);
            }
          },
        },
      ]
    );
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'submitted':
        return theme.colors.info;
      case 'approved':
        return theme.colors.success;
      case 'sent_back':
        return theme.colors.warning;
      default:
        return theme.colors.textMuted;
    }
  };

  const getStatusText = (status: string) => {
    switch (status) {
      case 'pending':
        return 'Pending';
      case 'submitted':
        return 'Submitted';
      case 'approved':
        return 'Approved';
      case 'sent_back':
        return 'Needs Review';
      default:
        return status;
    }
  };

  if (loading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color={theme.colors.primary} />
        <Text style={styles.loadingText}>Loading checklist...</Text>
      </View>
    );
  }

  if (!checklist) {
    return (
      <ScrollView style={styles.container}>
        <OfflineIndicator />
        <View style={styles.header}>
          <TouchableOpacity
            style={styles.backButton}
            onPress={() => navigation.goBack()}
          >
            <Ionicons name="arrow-back" size={24} color={theme.colors.white} />
          </TouchableOpacity>
          <Text style={styles.title}>Daily Checklist</Text>
          <Text style={styles.subtitle}>No checklist assigned for today</Text>
        </View>
        <View style={styles.emptyState}>
          <Ionicons name="clipboard-outline" size={64} color={theme.colors.textMuted} />
          <Text style={styles.emptyStateText}>No checklist available</Text>
          <Text style={styles.emptyStateSubtext}>
            Check with your Campus Manager for daily tasks
          </Text>
        </View>
      </ScrollView>
    );
  }

  const completionPercentage = Math.round(
    (checklist.completed_tasks / checklist.total_tasks) * 100
  );

  return (
    <View style={styles.container}>
      <OfflineIndicator />
      <ScrollView
        style={styles.scrollView}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
      >
        <View style={styles.header}>
          <TouchableOpacity
            style={styles.backButton}
            onPress={() => navigation.goBack()}
          >
            <Ionicons name="arrow-back" size={24} color={theme.colors.white} />
          </TouchableOpacity>
          <Text style={styles.title}>{checklist.template.title}</Text>
          <Text style={styles.subtitle}>
            {format(new Date(checklist.date), 'MMMM dd, yyyy')} • {checklist.shift} Shift
          </Text>
        </View>

        <View style={styles.progressContainer}>
          <View style={styles.progressHeader}>
            <Text style={styles.progressTitle}>Progress</Text>
            <Text style={styles.progressPercentage}>{completionPercentage}%</Text>
          </View>
          <View style={styles.progressBar}>
            <View
              style={[styles.progressFill, { width: `${completionPercentage}%` }]}
            />
          </View>
          <Text style={styles.progressText}>
            {checklist.completed_tasks} of {checklist.total_tasks} tasks completed
          </Text>
        </View>

        <View style={styles.statusContainer}>
          <View style={[styles.statusBadge, { backgroundColor: getStatusColor(checklist.status) }]}>
            <Text style={styles.statusText}>{getStatusText(checklist.status)}</Text>
          </View>
        </View>

        <View style={styles.itemsContainer}>
          <Text style={styles.itemsTitle}>Tasks</Text>
          {checklist.items.map((item) => (
            <ChecklistItemRow
              key={item.id}
              item={item}
              onToggle={handleToggleItem}
              onCommentChange={handleCommentChange}
              onPhotoUpload={handlePhotoUpload}
            />
          ))}
        </View>

        {checklist.status === 'pending' && (
          <View style={styles.actionsContainer}>
            <TouchableOpacity
              style={[styles.submitButton, submitting && styles.submitButtonDisabled]}
              onPress={handleSubmit}
              disabled={submitting}
            >
              {submitting ? (
                <ActivityIndicator color={theme.colors.white} />
              ) : (
                <Text style={styles.submitButtonText}>Submit Checklist</Text>
              )}
            </TouchableOpacity>
          </View>
        )}

        {checklist.status === 'sent_back' && checklist.manager_note && (
          <View style={styles.feedbackContainer}>
            <Text style={styles.feedbackTitle}>Manager Feedback:</Text>
            <Text style={styles.feedbackText}>{checklist.manager_note}</Text>
          </View>
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
  scrollView: {
    flex: 1,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: theme.colors.background,
  },
  loadingText: {
    fontSize: theme.fontSize.md,
    color: theme.colors.textSecondary,
    marginTop: theme.spacing.md,
  },
  header: {
    padding: theme.spacing.lg,
    paddingTop: theme.spacing.xxl,
    backgroundColor: theme.colors.primary,
  },
  backButton: {
    marginBottom: theme.spacing.md,
    alignSelf: 'flex-start',
  },
  title: {
    fontSize: theme.fontSize.xxl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.white,
    marginBottom: theme.spacing.xs,
  },
  subtitle: {
    fontSize: theme.fontSize.md,
    color: 'rgba(255, 255, 255, 0.8)',
  },
  progressContainer: {
    backgroundColor: theme.colors.white,
    margin: theme.spacing.lg,
    padding: theme.spacing.lg,
    borderRadius: theme.borderRadius.lg,
    ...theme.shadows.medium,
  },
  progressHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: theme.spacing.md,
  },
  progressTitle: {
    fontSize: theme.fontSize.lg,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
  },
  progressPercentage: {
    fontSize: theme.fontSize.xxl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.primary,
  },
  progressBar: {
    height: 8,
    backgroundColor: theme.colors.border,
    borderRadius: 4,
    marginBottom: theme.spacing.md,
  },
  progressFill: {
    height: '100%',
    backgroundColor: theme.colors.success,
    borderRadius: 4,
  },
  progressText: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
    textAlign: 'center',
  },
  statusContainer: {
    paddingHorizontal: theme.spacing.lg,
    marginBottom: theme.spacing.lg,
  },
  statusBadge: {
    paddingHorizontal: theme.spacing.md,
    paddingVertical: theme.spacing.sm,
    borderRadius: 16,
    alignSelf: 'flex-start',
  },
  statusText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.sm,
    fontWeight: theme.fontWeight.bold,
  },
  itemsContainer: {
    padding: theme.spacing.lg,
  },
  itemsTitle: {
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
    marginBottom: theme.spacing.md,
  },
  actionsContainer: {
    padding: theme.spacing.lg,
    paddingBottom: theme.spacing.xxl,
  },
  submitButton: {
    backgroundColor: theme.colors.success,
    padding: theme.spacing.md,
    borderRadius: theme.borderRadius.lg,
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: 48,
  },
  submitButtonDisabled: {
    opacity: 0.6,
  },
  submitButtonText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.bold,
  },
  feedbackContainer: {
    backgroundColor: '#FFF3E0',
    margin: theme.spacing.lg,
    padding: theme.spacing.md,
    borderRadius: theme.borderRadius.lg,
    borderLeftWidth: 4,
    borderLeftColor: theme.colors.warning,
  },
  feedbackTitle: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.bold,
    color: '#E65100',
    marginBottom: theme.spacing.xs,
  },
  feedbackText: {
    fontSize: theme.fontSize.sm,
    color: '#BF360C',
  },
  emptyState: {
    padding: theme.spacing.xxl,
    alignItems: 'center',
  },
  emptyStateText: {
    fontSize: theme.fontSize.lg,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.textSecondary,
    marginTop: theme.spacing.md,
    marginBottom: theme.spacing.xs,
  },
  emptyStateSubtext: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textMuted,
    textAlign: 'center',
  },
});

