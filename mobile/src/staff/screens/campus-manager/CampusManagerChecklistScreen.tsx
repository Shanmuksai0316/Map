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
  ScrollView,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { launchCamera } from 'react-native-image-picker';
import { apiService } from '../../../shared/services/api.service';
import { tenantService } from '../../../shared/services/tenant.service';
import { StorageService } from '../../../shared/services/storage.service';
import { APP_CONFIG } from '../../../shared/config/app.config';
import { theme } from '../../../shared/theme/theme';
import type { ChecklistTask, ChecklistInstance } from '../../../shared/types';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface Props {
  navigation: any;
}

interface StaffChecklistSummary {
  user_id: number;
  user_name: string;
  role: string;
  total_tasks: number;
  completed_tasks: number;
  completion_percentage: number;
  submitted_at?: string;
  last_submitted_at?: string | null;
  last_checklist_date?: string | null;
}

interface StaffChecklistDetail {
  checklist_date: string;
  tasks: ChecklistTask[];
  submitted_at: string;
}

type TabType = 'my-checklist' | 'staff-checklist';

const shouldTryFallback = (status?: number): boolean => status === 404 || status === 403;

export const CampusManagerChecklistScreen: React.FC<Props> = ({ navigation }) => {
  const [activeTab, setActiveTab] = useState<TabType>('my-checklist');
  
  // My Checklist State (campus-manager endpoints; guard store is Guard-only)
  const [myChecklist, setMyChecklist] = useState<ChecklistInstance | null>(null);
  const [myChecklistLoading, setMyChecklistLoading] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  
  // Staff Checklist State
  const [staffSummaries, setStaffSummaries] = useState<StaffChecklistSummary[]>([]);
  const [staffLoading, setStaffLoading] = useState(false);
  const [selectedStaff, setSelectedStaff] = useState<StaffChecklistSummary | null>(null);
  const [staffDetail, setStaffDetail] = useState<StaffChecklistDetail | null>(null);
  const [showStaffDetail, setShowStaffDetail] = useState(false);
  const [loadingStaffDetailFor, setLoadingStaffDetailFor] = useState<number | null>(null);

  const fetchMyChecklist = useCallback(async () => {
    setMyChecklistLoading(true);
    try {
      const pathsToTry = [
        '/mobile/campus-manager/checklists/current',
        '/campus-manager/checklists/current',
      ];

      let checklist: ChecklistInstance | null = null;
      let lastError: unknown = null;

      for (const path of pathsToTry) {
        try {
          const response = await apiService.get<{ data?: ChecklistInstance | null } | ChecklistInstance | null>(path);
          const payload = response as unknown as { data?: ChecklistInstance | null } | ChecklistInstance | null;
          checklist = (payload as { data?: ChecklistInstance | null })?.data ?? (payload as ChecklistInstance | null) ?? null;
          lastError = null;
          break;
        } catch (error: unknown) {
          lastError = error;
          const status = (error as { response?: { status?: number } })?.response?.status;
          if (shouldTryFallback(status) && pathsToTry.indexOf(path) < pathsToTry.length - 1) {
            continue;
          }
          break;
        }
      }

      if (lastError && !checklist) {
        console.error('Failed to fetch my checklist:', lastError);
      }

      setMyChecklist(checklist);
    } catch (err) {
      console.error('Failed to fetch my checklist:', err);
      setMyChecklist(null);
    } finally {
      setMyChecklistLoading(false);
    }
  }, []);

  const fetchStaffChecklists = useCallback(async () => {
    setStaffLoading(true);
    // Try both path patterns: with and without /mobile (backend registers both)
    const pathsToTry = [
      '/campus-manager/checklists/staff-summary',
      '/mobile/campus-manager/checklists/staff-summary',
    ];
    const baseURL = tenantService.getCurrentApiUrl();
    const fullUrls = pathsToTry.map(p => (baseURL.replace(/\/+$/, '') + '/' + p.replace(/^\//, '')));

    let response: { data?: Record<string, unknown>[] } | null = null;
    let lastError: unknown = null;
    for (const path of pathsToTry) {
      try {
        response = await apiService.get<{ data: Record<string, unknown>[] }>(path);
        lastError = null;
        break;
      } catch (err: unknown) {
        lastError = err;
        const ax = err as { response?: { status?: number } };
        const status = ax?.response?.status;

        if (shouldTryFallback(status) && pathsToTry.indexOf(path) < pathsToTry.length - 1) continue;
        break;
      }
    }
    if (lastError && !response) {
      const fullUrlForFallback = fullUrls[0];
      const fallbackOk = await (async (): Promise<boolean> => {
        try {
          const rawToken = await StorageService.get(APP_CONFIG.STORAGE_KEYS.AUTH_TOKEN);
          const token = typeof rawToken === 'string' ? rawToken : (rawToken as any)?.token ?? '';
          const tenant = tenantService.getSelectedTenant();
          const res = await fetch(fullUrlForFallback, {
            method: 'GET',
            headers: {
              Accept: 'application/json',
              ...(token ? { Authorization: `Bearer ${token}` } : {}),
              ...(tenant?.code ? { 'X-Tenant-Code': tenant.code } : {}),
            },
          });
          if (!res.ok) return false;
          const json = await res.json();
          const raw = json?.data ?? [];
          const mapped: StaffChecklistSummary[] = raw.map((s: Record<string, unknown>) => ({
            user_id: s.user_id as number,
            user_name: (s.user_name as string) ?? '',
            role: (s.role as string) ?? '',
            total_tasks: (s.total_task_items as number) ?? (s.total_tasks as number) ?? 0,
            completed_tasks: (s.total_completed_tasks as number) ?? (s.completed_tasks as number) ?? 0,
            completion_percentage: (s.task_completion_percentage as number) ?? (s.completion_percentage as number) ?? 0,
            last_submitted_at: (s.last_submitted_at as string) ?? null,
            last_checklist_date: (s.last_checklist_date as string) ?? null,
          }));
          setStaffSummaries(mapped);
          return true;
        } catch {
          return false;
        }
      })();
      if (fallbackOk) {
        setStaffLoading(false);
        return;
      }
      setStaffSummaries([]);
      setStaffLoading(false);
      return;
    }
    try {
      const raw = response?.data ?? [];
      const mapped: StaffChecklistSummary[] = raw.map((s: Record<string, unknown>) => ({
        user_id: s.user_id as number,
        user_name: (s.user_name as string) ?? '',
        role: (s.role as string) ?? '',
        total_tasks: (s.total_task_items as number) ?? (s.total_tasks as number) ?? 0,
        completed_tasks: (s.total_completed_tasks as number) ?? (s.completed_tasks as number) ?? 0,
        completion_percentage: (s.task_completion_percentage as number) ?? (s.completion_percentage as number) ?? 0,
        last_submitted_at: (s.last_submitted_at as string) ?? null,
        last_checklist_date: (s.last_checklist_date as string) ?? null,
      }));
      setStaffSummaries(mapped);
    } finally {
      setStaffLoading(false);
    }
  }, []);

  useEffect(() => {
    if (activeTab === 'my-checklist') {
      fetchMyChecklist();
    } else {
      fetchStaffChecklists();
    }
  }, [activeTab, fetchMyChecklist, fetchStaffChecklists]);

  const fetchStaffDetail = async (staff: StaffChecklistSummary) => {
    if (loadingStaffDetailFor === staff.user_id) {
      return;
    }

    setLoadingStaffDetailFor(staff.user_id);
    try {
      const pathsToTry = [
        `/campus-manager/checklists/staff/${staff.user_id}`,
        `/mobile/campus-manager/checklists/staff/${staff.user_id}`,
      ];
      let detail: StaffChecklistDetail | null = null;

      for (const path of pathsToTry) {
        try {
          const response = await apiService.get<{ data?: StaffChecklistDetail } | StaffChecklistDetail>(path);
          const payload = response as { data?: StaffChecklistDetail } | StaffChecklistDetail;
          detail = (payload as { data?: StaffChecklistDetail })?.data ?? (payload as StaffChecklistDetail);
          break;
        } catch (err: any) {
          const status = err?.response?.status;
          if (shouldTryFallback(status) && pathsToTry.indexOf(path) < pathsToTry.length - 1) {
            continue;
          }
          throw err;
        }
      }

      if (!detail) {
        throw new Error('No detail data returned');
      }

      setStaffDetail(detail);
      setSelectedStaff(staff);
      setShowStaffDetail(true);
    } catch (error) {
      console.error('Failed to fetch staff detail:', error);
      Alert.alert('Error', 'Failed to load checklist details');
    } finally {
      setLoadingStaffDetailFor(null);
    }
  };

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    if (activeTab === 'my-checklist') {
      await fetchMyChecklist();
    } else {
      await fetchStaffChecklists();
    }
    setRefreshing(false);
  }, [activeTab, fetchMyChecklist, fetchStaffChecklists]);

  const handleTaskPress = async (task: ChecklistTask) => {
    if (task.completed) return;

    if (task.requires_photo && !task.photo_url) {
      Alert.alert(
        'Photo Required',
        'Please take a photo to complete this task.',
        [
          { text: 'Cancel', style: 'cancel' },
          { text: 'Take Photo', onPress: () => handleTakePhoto(task.index) },
        ]
      );
      return;
    }

    try {
      await apiService.post(
        `/mobile/campus-manager/checklists/items/${task.index}/complete`,
        { comment: task.comment, photo_url: task.photo_url }
      );
      await fetchMyChecklist();
    } catch (error: any) {
      const status = error?.response?.status;
      if (shouldTryFallback(status)) {
        try {
          await apiService.post(
            `/campus-manager/checklists/items/${task.index}/complete`,
            { comment: task.comment, photo_url: task.photo_url }
          );
          await fetchMyChecklist();
          return;
        } catch (fallbackError) {
          console.error('Failed to complete task (fallback):', fallbackError);
        }
      } else {
        console.error('Failed to complete task:', error);
      }
      Alert.alert('Error', 'Failed to complete task');
    }
  };

  const handleTakePhoto = async (taskIndex: number) => {
    try {
      const result = await launchCamera({
        mediaType: 'photo',
        quality: 0.8,
        saveToPhotos: false,
      });

      if (result.assets && result.assets[0]?.uri) {
        const asset = result.assets[0];
        const formData = new FormData();
        formData.append('photo', {
          uri: asset.uri,
          type: asset.type ?? 'image/jpeg',
          name: asset.fileName ?? `checklist_task_${taskIndex}_${Date.now()}.jpg`,
        } as any);
        let uploadRes: { data: { photo_url: string } } | null = null;
        try {
          uploadRes = await apiService.post<{ data: { photo_url: string } }>(
            `/mobile/campus-manager/checklists/items/${taskIndex}/photo`,
            formData,
            { headers: { 'Content-Type': 'multipart/form-data' } }
          );
        } catch (error: any) {
          const status = error?.response?.status;
          if (!shouldTryFallback(status)) {
            throw error;
          }
          uploadRes = await apiService.post<{ data: { photo_url: string } }>(
            `/campus-manager/checklists/items/${taskIndex}/photo`,
            formData,
            { headers: { 'Content-Type': 'multipart/form-data' } }
          );
        }
        const photoUrl = uploadRes?.data?.photo_url;
        if (photoUrl) {
          try {
            await apiService.post(
              `/mobile/campus-manager/checklists/items/${taskIndex}/complete`,
              { photo_url: photoUrl }
            );
          } catch (error: any) {
            const status = error?.response?.status;
            if (!shouldTryFallback(status)) {
              throw error;
            }
            await apiService.post(
              `/campus-manager/checklists/items/${taskIndex}/complete`,
              { photo_url: photoUrl }
            );
          }
          await fetchMyChecklist();
        }
      }
    } catch (error: any) {
      console.error('Failed to capture or upload photo:', error);
      Alert.alert('Error', 'Failed to capture or upload photo');
    }
  };

  const handleSubmitChecklist = async () => {
    if (!myChecklist || myChecklist.completed_count < myChecklist.total_count) {
      Alert.alert('Incomplete', 'Please complete all tasks before submitting');
      return;
    }

    try {
      await apiService.post('/mobile/campus-manager/checklists/submit');
      Alert.alert('Success', 'Daily checklist submitted successfully');
      await fetchMyChecklist();
    } catch (error: any) {
      const status = error?.response?.status;
      if (shouldTryFallback(status)) {
        try {
          await apiService.post('/campus-manager/checklists/submit');
          Alert.alert('Success', 'Daily checklist submitted successfully');
          await fetchMyChecklist();
          return;
        } catch (fallbackError) {
          console.error('Failed to submit checklist (fallback):', fallbackError);
        }
      } else {
        console.error('Failed to submit checklist:', error);
      }
      Alert.alert('Error', 'Failed to submit checklist');
    }
  };

  const getProgressColor = (percentage: number): string => {
    if (percentage >= 80) return theme.colors.success;
    if (percentage >= 50) return theme.colors.warning;
    return theme.colors.error;
  };

  const formatDate = () => {
    const date = new Date();
    return date.toLocaleDateString('en-US', {
      weekday: 'long',
      month: 'long',
      day: 'numeric',
      year: 'numeric',
    });
  };

  // Render My Checklist Task Item
  const renderTaskItem = ({ item }: { item: ChecklistTask }) => (
    <TouchableOpacity
      style={[styles.taskCard, item.completed && styles.taskCompleted]}
      onPress={() => handleTaskPress(item)}
      disabled={item.completed}
    >
      <View style={styles.taskContent}>
        <View style={styles.taskHeader}>
          <View
            style={[
              styles.checkbox,
              item.completed && styles.checkboxCompleted,
            ]}
          >
            {item.completed && <Icon name="check" size={16} color={theme.colors.white} />}
          </View>
          <View style={styles.taskInfo}>
            <Text
              style={[styles.taskTitle, item.completed && styles.taskTitleCompleted]}
            >
              {item.title}
            </Text>
            {item.description && (
              <Text style={styles.taskDescription}>{item.description}</Text>
            )}
          </View>
        </View>

        <View style={styles.taskFooter}>
          {item.requires_photo && (
            <View style={styles.photoRequirement}>
              <Icon
                name={item.photo_url ? 'camera-check' : 'camera'}
                size={16}
                color={item.photo_url ? theme.colors.success : theme.colors.warning}
              />
              <Text
                style={[
                  styles.photoText,
                  { color: item.photo_url ? theme.colors.success : theme.colors.warning },
                ]}
              >
                {item.photo_url ? 'Photo attached' : 'Photo required'}
              </Text>
            </View>
          )}

          {item.completed && item.completed_at && (
            <Text style={styles.completedTime}>
              Completed at {new Date(item.completed_at).toLocaleTimeString()}
            </Text>
          )}
        </View>
      </View>

      {!item.completed && (
        <Icon name="chevron-right" size={20} color={theme.colors.border} />
      )}
    </TouchableOpacity>
  );

  // Render Staff Summary Item
  const renderStaffItem = ({ item }: { item: StaffChecklistSummary }) => {
    const progressColor = getProgressColor(item.completion_percentage);
    const latestCompletedAt = item.last_submitted_at || item.last_checklist_date;
    const historyLabel = latestCompletedAt
      ? `Last completed: ${new Date(latestCompletedAt).toLocaleDateString()}`
      : 'No checklist history yet';

    return (
      <TouchableOpacity
        style={styles.staffCard}
        onPress={() => fetchStaffDetail(item)}
        disabled={loadingStaffDetailFor === item.user_id}
      >
        <View style={styles.staffHeader}>
          <View style={styles.staffHeaderMain}>
            <View style={styles.staffAvatarContainer}>
              <Text style={styles.staffAvatarText}>
                {item.user_name
                  .split(' ')
                  .map((n) => n[0])
                  .join('')
                  .substring(0, 2)}
              </Text>
            </View>
            <View style={styles.staffInfo}>
              <Text style={styles.staffName}>{item.user_name}</Text>
              <Text style={styles.staffRole}>{item.role}</Text>
              <Text style={styles.staffHistory}>{historyLabel}</Text>
            </View>
          </View>
          <View style={styles.staffActions}>
            <View style={styles.staffProgress}>
              <Text style={[styles.staffPercentage, { color: progressColor }]}>
                {item.completion_percentage}%
              </Text>
              <Text style={styles.staffStatus}>
                {item.completion_percentage === 100 ? 'Completed' : 'In Progress'}
              </Text>
            </View>
            <View style={styles.viewButton}>
              <Text style={styles.viewButtonText}>
                {loadingStaffDetailFor === item.user_id ? 'Loading...' : 'View'}
              </Text>
            </View>
          </View>
        </View>

        <View style={styles.staffProgressBar}>
          <View
            style={[
              styles.staffProgressFill,
              {
                width: `${item.completion_percentage}%`,
                backgroundColor: progressColor,
              },
            ]}
          />
        </View>

        <Text style={styles.staffTaskCount}>
          {item.completed_tasks} / {item.total_tasks} tasks completed
        </Text>
      </TouchableOpacity>
    );
  };

  const renderMyChecklistHeader = () => (
    <View style={styles.myChecklistHeader}>
      <Text style={styles.checklistHeading}>Campus Manager Daily Routine</Text>
      <Text style={styles.checklistDate}>{formatDate()}</Text>
      
      {myChecklist && (
        <View style={styles.progressContainer}>
          <View style={styles.progressInfo}>
            <Text style={styles.progressTitle}>Today's Progress</Text>
            <Text style={styles.progressCount}>
              {myChecklist.completed_count} / {myChecklist.total_count} tasks
            </Text>
          </View>
          <View style={styles.progressBar}>
            <View
              style={[
                styles.progressFill,
                {
                  width: `${
                    (myChecklist.completed_count / myChecklist.total_count) * 100
                  }%`,
                },
              ]}
            />
          </View>
        </View>
      )}
    </View>
  );

  const renderMyChecklistFooter = () => (
    <View style={styles.submitContainer}>
      <GradientButton
        style={[
          styles.submitButton,
          (!myChecklist || myChecklist.completed_count < myChecklist.total_count) &&
            styles.submitButtonDisabled,
        ]}
        onPress={handleSubmitChecklist}
        disabled={
          !myChecklist || myChecklist.completed_count < myChecklist.total_count
        }
      >
        <Icon name="check-all" size={20} color={theme.colors.white} />
        <Text style={styles.submitButtonText}>Submit Daily Checklist</Text>
      </GradientButton>
    </View>
  );

  const renderEmptyState = (message: string) => (
    <View style={styles.emptyState}>
      <Icon name="clipboard-check-outline" size={64} color={theme.colors.border} />
      <Text style={styles.emptyTitle}>No Checklist</Text>
      <Text style={styles.emptySubtitle}>{message}</Text>
    </View>
  );

  return (
    <View style={styles.container}>
      <StaffScreenHeader
        onBack={() => navigation.goBack()}
        showBell={false}
        title="Checklists"
        variant="minimal"
        showLogo={false}
      />
      {/* Tabs */}
      <View style={styles.tabsContainer}>
        <TouchableOpacity
          style={[styles.tab, activeTab === 'my-checklist' && styles.tabActive]}
          onPress={() => setActiveTab('my-checklist')}
        >
          <Icon
            name="clipboard-check"
            size={18}
            color={activeTab === 'my-checklist' ? theme.colors.white : theme.colors.textSecondary}
          />
          <Text
            style={[styles.tabText, activeTab === 'my-checklist' && styles.tabTextActive]}
          >
            My Daily Checklist
          </Text>
        </TouchableOpacity>
        <TouchableOpacity
          style={[styles.tab, activeTab === 'staff-checklist' && styles.tabActive]}
          onPress={() => setActiveTab('staff-checklist')}
        >
          <Icon
            name="account-group"
            size={18}
            color={activeTab === 'staff-checklist' ? theme.colors.white : theme.colors.textSecondary}
          />
          <Text
            style={[styles.tabText, activeTab === 'staff-checklist' && styles.tabTextActive]}
          >
            Staff Checklist
          </Text>
        </TouchableOpacity>
      </View>

      {/* Date Filter removed – Staff Checklist now shows aggregated data without date toggle */}

      {/* Content */}
      {activeTab === 'my-checklist' ? (
        <FlatList
          data={myChecklist?.tasks || []}
          keyExtractor={(item) => String(item.index)}
          renderItem={renderTaskItem}
          ListHeaderComponent={renderMyChecklistHeader}
          ListFooterComponent={renderMyChecklistFooter}
          ListEmptyComponent={
            !myChecklistLoading ? renderEmptyState('No tasks assigned for today') : null
          }
          contentContainerStyle={styles.listContent}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
          }
        />
      ) : (
        <FlatList
          data={staffSummaries}
          keyExtractor={(item) => String(item.user_id)}
          renderItem={renderStaffItem}
          ListEmptyComponent={
            !staffLoading
              ? renderEmptyState('No staff checklist data available')
              : null
          }
          contentContainerStyle={styles.listContent}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
          }
        />
      )}

      {/* Staff Detail Modal */}
      <Modal
        visible={showStaffDetail}
        animationType="slide"
        transparent
        onRequestClose={() => setShowStaffDetail(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <View style={styles.modalHeader}>
              <View>
                <Text style={styles.modalTitle}>{selectedStaff?.user_name}</Text>
                <Text style={styles.modalSubtitle}>{selectedStaff?.role}</Text>
              </View>
              <TouchableOpacity onPress={() => setShowStaffDetail(false)}>
                <Icon name="close" size={24} color={theme.colors.text} />
              </TouchableOpacity>
            </View>

            {staffDetail && (
              <>
                <Text style={styles.modalDate}>
                  Submitted: {staffDetail.submitted_at 
                    ? new Date(staffDetail.submitted_at).toLocaleString()
                    : 'Not submitted'}
                </Text>

                <ScrollView style={styles.modalTaskList}>
                  {staffDetail.tasks.map((task, index) => (
                    <View key={index} style={styles.modalTaskItem}>
                      <View
                        style={[
                          styles.modalTaskCheckbox,
                          task.completed && styles.modalTaskCheckboxCompleted,
                        ]}
                      >
                        {task.completed && (
                          <Icon name="check" size={14} color={theme.colors.white} />
                        )}
                      </View>
                      <View style={styles.modalTaskContent}>
                        <Text
                          style={[
                            styles.modalTaskTitle,
                            task.completed && styles.modalTaskTitleCompleted,
                          ]}
                        >
                          {task.title}
                        </Text>
                        {task.completed_at && (
                          <Text style={styles.modalTaskTime}>
                            Completed at {new Date(task.completed_at).toLocaleTimeString()}
                          </Text>
                        )}
                      </View>
                    </View>
                  ))}
                </ScrollView>
              </>
            )}
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
  subHeader: {
    paddingHorizontal: 16,
    paddingTop: 12,
    paddingBottom: 8,
  },
  subHeaderTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: theme.colors.textHeading,
  },
  tabsContainer: {
    flexDirection: 'row',
    backgroundColor: theme.colors.card,
    paddingHorizontal: 16,
    paddingVertical: 12,
    gap: 12,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
  },
  tab: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 10,
    borderRadius: 10,
    backgroundColor: theme.colors.surfaceMuted,
    gap: 6,
  },
  tabActive: {
    backgroundColor: theme.colors.primary,
  },
  tabText: {
    fontSize: 13,
    fontWeight: '600',
    color: theme.colors.textSecondary,
  },
  tabTextActive: {
    color: theme.colors.white,
  },
  listContent: {
    padding: 16,
    flexGrow: 1,
  },
  myChecklistHeader: {
    marginBottom: 16,
  },
  checklistHeading: {
    fontSize: 20,
    fontWeight: '700',
    color: theme.colors.text,
    marginBottom: 4,
  },
  checklistDate: {
    fontSize: 14,
    color: theme.colors.textSecondary,
    marginBottom: 16,
  },
  progressContainer: {
    backgroundColor: theme.colors.card,
    padding: 16,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  progressInfo: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  progressTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.text,
  },
  progressCount: {
    fontSize: 14,
    fontWeight: '600',
    color: theme.colors.primary,
  },
  progressBar: {
    height: 8,
    backgroundColor: theme.colors.border,
    borderRadius: 4,
    overflow: 'hidden',
  },
  progressFill: {
    height: '100%',
    backgroundColor: theme.colors.primary,
    borderRadius: 4,
  },
  taskCard: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: theme.colors.card,
    padding: 16,
    borderRadius: 12,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  taskCompleted: {
    backgroundColor: theme.colors.successLight,
    borderColor: theme.colors.success,
  },
  taskContent: {
    flex: 1,
  },
  taskHeader: {
    flexDirection: 'row',
    alignItems: 'flex-start',
  },
  checkbox: {
    width: 24,
    height: 24,
    borderRadius: 6,
    borderWidth: 2,
    borderColor: theme.colors.border,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
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
  },
  taskTitleCompleted: {
    textDecorationLine: 'line-through',
    color: theme.colors.textSecondary,
  },
  taskDescription: {
    fontSize: 13,
    color: theme.colors.textSecondary,
    marginTop: 4,
    lineHeight: 18,
  },
  taskFooter: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: 10,
    marginLeft: 36,
  },
  photoRequirement: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  photoText: {
    fontSize: 12,
    fontWeight: '500',
    marginLeft: 6,
  },
  completedTime: {
    fontSize: 12,
    color: theme.colors.success,
    fontWeight: '500',
  },
  submitContainer: {
    paddingVertical: 16,
  },
  submitButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#D79F24',
    paddingVertical: 16,
    borderRadius: 12,
    gap: 8,
    ...theme.shadows.medium,
  },
  submitButtonDisabled: {
    opacity: 0.6,
  },
  submitButtonText: {
    fontSize: 16,
    fontWeight: '700',
    color: theme.colors.primary,
  },
  staffCard: {
    backgroundColor: theme.colors.card,
    padding: 16,
    borderRadius: 12,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  staffHeader: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    marginBottom: 12,
  },
  staffHeaderMain: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
    minWidth: 0,
    paddingRight: 8,
  },
  staffAvatarContainer: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: theme.colors.primary,
    justifyContent: 'center',
    alignItems: 'center',
  },
  staffAvatarText: {
    color: theme.colors.white,
    fontSize: 16,
    fontWeight: '700',
  },
  staffInfo: {
    flex: 1,
    marginLeft: 12,
    minWidth: 0,
  },
  staffName: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.text,
    flexShrink: 1,
  },
  staffRole: {
    fontSize: 13,
    color: theme.colors.textSecondary,
    marginTop: 2,
  },
  staffHistory: {
    fontSize: 12,
    color: theme.colors.textMuted,
    marginTop: 2,
  },
  staffProgress: {
    alignItems: 'flex-end',
  },
  staffActions: {
    alignItems: 'flex-end',
    gap: 8,
  },
  staffPercentage: {
    fontSize: 18,
    fontWeight: '700',
  },
  staffStatus: {
    fontSize: 11,
    color: theme.colors.textSecondary,
    marginTop: 2,
  },
  viewButton: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    backgroundColor: theme.colors.primary,
    borderRadius: 8,
  },
  viewButtonText: {
    fontSize: 13,
    fontWeight: '600',
    color: theme.colors.white,
  },
  staffProgressBar: {
    height: 6,
    backgroundColor: theme.colors.border,
    borderRadius: 3,
    overflow: 'hidden',
    marginBottom: 8,
  },
  staffProgressFill: {
    height: '100%',
    borderRadius: 3,
  },
  staffTaskCount: {
    fontSize: 13,
    color: theme.colors.textSecondary,
  },
  emptyState: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: 48,
  },
  emptyTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: theme.colors.textSecondary,
    marginTop: 16,
  },
  emptySubtitle: {
    fontSize: 14,
    color: theme.colors.textMuted,
    marginTop: 4,
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'flex-end',
  },
  modalContent: {
    backgroundColor: theme.colors.background,
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    maxHeight: '80%',
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    padding: 20,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
  },
  modalTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: theme.colors.text,
  },
  modalSubtitle: {
    fontSize: 14,
    color: theme.colors.textSecondary,
    marginTop: 2,
  },
  modalDate: {
    fontSize: 13,
    color: theme.colors.textSecondary,
    paddingHorizontal: 20,
    paddingVertical: 12,
    backgroundColor: theme.colors.surfaceMuted,
  },
  modalTaskList: {
    padding: 20,
  },
  modalTaskItem: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    marginBottom: 16,
  },
  modalTaskCheckbox: {
    width: 20,
    height: 20,
    borderRadius: 4,
    borderWidth: 2,
    borderColor: theme.colors.border,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
    marginTop: 2,
  },
  modalTaskCheckboxCompleted: {
    backgroundColor: theme.colors.success,
    borderColor: theme.colors.success,
  },
  modalTaskContent: {
    flex: 1,
  },
  modalTaskTitle: {
    fontSize: 15,
    fontWeight: '500',
    color: theme.colors.text,
  },
  modalTaskTitleCompleted: {
    textDecorationLine: 'line-through',
    color: theme.colors.textSecondary,
  },
  modalTaskTime: {
    fontSize: 12,
    color: theme.colors.success,
    marginTop: 4,
  },
});

export default CampusManagerChecklistScreen;
