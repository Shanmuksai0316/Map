import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  Alert,
  RefreshControl,
  FlatList,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import { useAuthStore } from '../../../shared/store/auth.store';
import { apiService } from '../../../shared/services/api.service';
import { APP_CONFIG } from '../../../shared/config/app.config';
import { errorHandler } from '../../../shared/utils/errorHandler';
import { ErrorState } from '../../../shared/components/shared/ErrorState';
import { ActivityIndicator } from 'react-native';
import { colors } from '../../../shared/theme/colors';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

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

export const SportsManagerChecklistScreen = ({ navigation }: any) => {
  const { user } = useAuthStore();
  const [checklists, setChecklists] = useState<ChecklistInstance[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<any>(null);

  const fetchChecklists = async () => {
    try {
      setError(null);
      const response = await apiService.get<{ data: ChecklistInstance[] }>(
        `${APP_CONFIG.ENDPOINTS.ADMIN_CHECKLISTS}/today`
      );
      setChecklists(response.data || []);
    } catch (err) {
      console.error('Checklists fetch error:', err);
      const errorDetails = errorHandler.handleError(err);
      setError(errorDetails);
      setChecklists([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchChecklists();
  }, []);

  const onRefresh = () => {
    setRefreshing(true);
    fetchChecklists();
  };

  const handleToggleItem = async (checklistId: number, itemCode: string, currentState: string) => {
    const newState = currentState === 'done' ? 'pending' : 'done';

    Alert.alert(
      newState === 'done' ? 'Mark Complete' : 'Mark Incomplete',
      `Mark "${itemCode}" as ${newState === 'done' ? 'completed' : 'incomplete'}?`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Confirm',
          style: 'default',
          onPress: async () => {
            try {
              await apiService.post(`${APP_CONFIG.ENDPOINTS.ADMIN_CHECKLISTS}/${checklistId}/items/${itemCode}`, {
                state: newState,
              });

              // Update local state
              setChecklists(prev => prev.map(checklist => ({
                ...checklist,
                items: checklist.items.map(item =>
                  item.code === itemCode ? { ...item, state: newState } : item
                ),
                completed_tasks: checklist.items.filter(item =>
                  item.code === itemCode ? newState === 'done' : item.state === 'done'
                ).length,
              })));

              Alert.alert('Success', 'Checklist item updated');
            } catch (error) {
              Alert.alert('Error', 'Failed to update checklist item');
            }
          },
        },
      ]
    );
  };

  const handleSubmitChecklist = async (checklistId: number) => {
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
              await apiService.post(`${APP_CONFIG.ENDPOINTS.ADMIN_CHECKLISTS}/${checklistId}/submit`);

              // Update local state
              setChecklists(prev => prev.map(checklist =>
                checklist.id === checklistId
                  ? { ...checklist, status: 'submitted', submitted_at: new Date().toISOString() }
                  : checklist
              ));

              Alert.alert('Success', 'Checklist submitted successfully');
            } catch (error) {
              Alert.alert('Error', 'Failed to submit checklist');
            }
          },
        },
      ]
    );
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'submitted':
        return colors.info;
      case 'approved':
        return colors.success;
      case 'sent_back':
        return colors.warning;
      default:
        return colors.textMuted;
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

  const renderChecklistItem = (item: ChecklistItem) => (
    <TouchableOpacity
      key={item.id}
      style={[styles.checklistItem, item.state === 'done' && styles.completedItem]}
      onPress={() => handleToggleItem(checklists[0]?.id || 0, item.code, item.state)}
    >
      <View style={styles.itemHeader}>
        <Text style={[styles.itemLabel, item.state === 'done' && styles.completedText]}>
          {item.label}
        </Text>
        <View style={[styles.statusIndicator, { backgroundColor: item.state === 'done' ? colors.success : colors.textMuted }]}>
          <Text style={styles.statusText}>{item.state === 'done' ? '✓' : '○'}</Text>
        </View>
      </View>
      {item.require_comment && item.comment && (
        <Text style={styles.itemComment}>{item.comment}</Text>
      )}
    </TouchableOpacity>
  );

  const currentChecklist = checklists[0];

  if (loading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color={colors.primary} />
        <Text style={styles.loadingText}>Loading checklist...</Text>
      </View>
    );
  }

  if (error) {
    return (
      <View style={styles.container}>
        <StaffScreenHeader
          onBack={() => navigation.goBack()}
          showBell={false}
          title="Checklist"
          variant="minimal"
          showLogo={false}
        />
        <View style={styles.header}>
          <Text style={styles.title}>Daily Checklist</Text>
        </View>
        <ErrorState error={error} onRetry={fetchChecklists} />
      </View>
    );
  }

  if (!currentChecklist) {
    return (
      <ScrollView style={styles.container}>
        <StaffScreenHeader
          onBack={() => navigation.goBack()}
          showBell={false}
          title="Checklist"
          variant="minimal"
          showLogo={false}
        />
        <View style={styles.header}>
          <Text style={styles.title}>Daily Checklist</Text>
          <Text style={styles.subtitle}>No checklist assigned for today</Text>
        </View>
        <View style={styles.emptyState}>
          <Text style={styles.emptyStateText}>No checklist available</Text>
          <Text style={styles.emptyStateSubtext}>
            Check with your Campus Manager for daily tasks
          </Text>
        </View>
      </ScrollView>
    );
  }

  const completionPercentage = Math.round((currentChecklist.completed_tasks / currentChecklist.total_tasks) * 100);

  return (
    <ScrollView
      style={styles.container}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
      }
    >
      <StaffScreenHeader
        onBack={() => navigation.goBack()}
        showBell={false}
        title="Checklist"
        variant="minimal"
        showLogo={false}
      />
      <View style={styles.header}>
        <Text style={styles.title}>{currentChecklist.template.title}</Text>
        <Text style={styles.subtitle}>
          {currentChecklist.date} • {currentChecklist.shift} Shift
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
          {currentChecklist.completed_tasks} of {currentChecklist.total_tasks} tasks completed
        </Text>
      </View>

      <View style={styles.statusContainer}>
        <Text style={[styles.statusBadge, { backgroundColor: getStatusColor(currentChecklist.status) }]}>
          {getStatusText(currentChecklist.status)}
        </Text>
      </View>

      <View style={styles.itemsContainer}>
        <Text style={styles.itemsTitle}>Tasks</Text>
        {currentChecklist.items.map(renderChecklistItem)}
      </View>

      {currentChecklist.status === 'pending' && (
        <View style={styles.actionsContainer}>
          <GradientButton
            style={styles.submitButton}
            onPress={() => handleSubmitChecklist(currentChecklist.id)}
          >
            <Text style={styles.submitButtonText}>Submit Checklist</Text>
          </GradientButton>
        </View>
      )}

      {currentChecklist.status === 'sent_back' && currentChecklist.manager_note && (
        <View style={styles.feedbackContainer}>
          <Text style={styles.feedbackTitle}>Manager Feedback:</Text>
          <Text style={styles.feedbackText}>{currentChecklist.manager_note}</Text>
        </View>
      )}
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  loadingText: {
    fontSize: 16,
    color: colors.textSecondary,
  },
  header: {
    padding: 20,
    backgroundColor: colors.white,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  title: {
    fontSize: 24,
    fontWeight: 'bold',
    color: colors.primary,
    marginBottom: 5,
  },
  subtitle: {
    fontSize: 16,
    color: colors.textSecondary,
  },
  progressContainer: {
    backgroundColor: colors.white,
    margin: 20,
    padding: 20,
    borderRadius: 12,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  progressHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 10,
  },
  progressTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: colors.text,
  },
  progressPercentage: {
    fontSize: 24,
    fontWeight: 'bold',
    color: colors.primary,
  },
  progressBar: {
    height: 8,
    backgroundColor: colors.border,
    borderRadius: 4,
    marginBottom: 10,
  },
  progressFill: {
    height: '100%',
    backgroundColor: colors.success,
    borderRadius: 4,
  },
  progressText: {
    fontSize: 14,
    color: colors.textSecondary,
    textAlign: 'center',
  },
  statusContainer: {
    paddingHorizontal: 20,
    marginBottom: 20,
  },
  statusBadge: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 16,
    color: colors.white,
    fontSize: 14,
    fontWeight: 'bold',
    alignSelf: 'flex-start',
  },
  itemsContainer: {
    padding: 20,
  },
  itemsTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: colors.text,
    marginBottom: 15,
  },
  checklistItem: {
    backgroundColor: colors.white,
    padding: 15,
    borderRadius: 12,
    marginBottom: 10,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  completedItem: {
    backgroundColor: colors.successLight,
    borderColor: colors.success,
    borderWidth: 1,
  },
  itemHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 8,
  },
  itemLabel: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.text,
    flex: 1,
    marginRight: 10,
  },
  completedText: {
    color: colors.success,
    textDecorationLine: 'line-through',
  },
  statusIndicator: {
    width: 24,
    height: 24,
    borderRadius: 12,
    justifyContent: 'center',
    alignItems: 'center',
  },
  statusText: {
    color: colors.white,
    fontSize: 14,
    fontWeight: 'bold',
  },
  itemComment: {
    fontSize: 14,
    color: colors.textSecondary,
    fontStyle: 'italic',
    backgroundColor: colors.surfaceMuted,
    padding: 8,
    borderRadius: 6,
  },
  actionsContainer: {
    padding: 20,
  },
  submitButton: {
    backgroundColor: '#D79F24',
    padding: 15,
    borderRadius: 12,
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.15,
    shadowRadius: 4,
    elevation: 3,
  },
  submitButtonText: {
    color: colors.primary,
    fontSize: 16,
    fontWeight: 'bold',
  },
  feedbackContainer: {
    backgroundColor: colors.warningLight,
    margin: 20,
    padding: 15,
    borderRadius: 12,
    borderLeftWidth: 4,
    borderLeftColor: colors.warning,
  },
  feedbackTitle: {
    fontSize: 16,
    fontWeight: 'bold',
    color: colors.warning,
    marginBottom: 8,
  },
  feedbackText: {
    fontSize: 14,
    color: colors.text,
  },
  emptyState: {
    padding: 40,
    alignItems: 'center',
  },
  emptyStateText: {
    fontSize: 18,
    fontWeight: 'bold',
    color: colors.textSecondary,
    marginBottom: 10,
  },
  emptyStateSubtext: {
    fontSize: 14,
    color: colors.textMuted,
    textAlign: 'center',
  },
});
