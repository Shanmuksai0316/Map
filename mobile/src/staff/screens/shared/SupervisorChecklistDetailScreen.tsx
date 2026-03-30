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
import { launchImageLibrary } from 'react-native-image-picker';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useAuthStore } from '../../../shared/store/auth.store';
import { apiService } from '../../../shared/services/api.service';
import { useOfflineQueue } from '../../../shared/hooks/useOfflineQueue';
import { OfflineIndicator } from '../../../shared/components/shared/OfflineIndicator';
import { ChecklistItemRow } from '../../../staff/components/ChecklistItemRow';
import { theme } from '../../../shared/theme/theme';
import { format } from 'date-fns';
import { resolveTenantLogoUrl } from '../../../shared/utils/tenant-logo-url.util';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';
import { StaffPrimaryButton } from '../../components/StaffPrimaryButton';

interface ChecklistItem {
  id: number;
  code: string;
  label: string;
  // UI states (we map to backend states Done / NA)
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

  useEffect(() => {
    // #region agent log
    fetch('http://127.0.0.1:7242/ingest/26316810-a694-48b7-8f83-116907028f19', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Debug-Session-Id': '65d170',
      },
      body: JSON.stringify({
        sessionId: '65d170',
        runId: 'pre-fix',
        hypothesisId: 'H1',
        location: 'SupervisorChecklistDetailScreen.tsx:68',
        message: 'SupervisorChecklistDetailScreen mounted',
        data: {
          role: user?.role ?? null,
        },
        timestamp: Date.now(),
      }),
    }).catch(() => {});
    // #endregion
  }, [user?.role]);

  const normalizeChecklist = (raw: any): ChecklistInstance => {
    const statusRaw = (raw?.status ?? '').toString();
    const reviewRaw = (raw?.review_status ?? '').toString();

    // Backend uses: status = Pending|Submitted, review_status = Approved|SentBack
    const normalizedStatus: ChecklistInstance['status'] =
      reviewRaw === 'SentBack'
        ? 'sent_back'
        : reviewRaw === 'Approved'
          ? 'approved'
          : statusRaw === 'Submitted'
            ? 'submitted'
            : 'pending';

    const items: ChecklistItem[] = Array.isArray(raw?.items)
      ? raw.items.map((it: any) => {
          const stateRaw = (it?.state ?? '').toString();
          const state: ChecklistItem['state'] =
            stateRaw === 'Done' ? 'done' : stateRaw === 'NA' ? 'skipped' : 'pending';

          const photoUrls = Array.isArray(it?.photo_urls) ? it.photo_urls : [];
          const normalizedPhoto = resolveTenantLogoUrl(photoUrls[0] || undefined) ?? undefined;

          return {
            id: Number(it?.id ?? 0),
            code: (it?.code ?? '').toString(),
            label: (it?.label ?? it?.title ?? '').toString(),
            state,
            require_photo: Boolean(it?.require_photo ?? false),
            require_comment: Boolean(it?.require_comment ?? false),
            comment: (it?.comment ?? '').toString() || undefined,
            photo_url: normalizedPhoto,
          };
        })
      : [];

    return {
      id: Number(raw?.id ?? 0),
      template: raw?.template ?? { id: 0, title: 'Daily Checklist', role: raw?.role ?? '' },
      date: raw?.date,
      shift: raw?.shift ?? 'Daily',
      status: normalizedStatus,
      total_tasks: Number(raw?.total_tasks ?? items.length),
      completed_tasks: Number(raw?.completed_tasks ?? items.filter(i => i.state === 'done').length),
      items,
      submitted_at: raw?.submitted_at ?? undefined,
      manager_note: raw?.manager_note ?? undefined,
    };
  };

  const fetchChecklist = async () => {
    try {
      setLoading(true);
      // Determine checklist role based on supervisor type
      // Defaults to HKSupervisor but supports RM Supervisor as well
      const normalizedRole = (user?.role || '').toLowerCase();
      const checklistRole =
        normalizedRole.includes('rm_supervisor') || normalizedRole.includes('rm supervisor') || normalizedRole.includes('repair')
          ? 'RMSupervisor'
          : 'HKSupervisor';

      // Use shared checklists API used by other staff apps
      const response = await apiService.get<any>(
        `/checklists/today?role=${checklistRole}&shift=Daily`
      );
      const instances = Array.isArray(response)
        ? response
        : Array.isArray((response as any)?.data)
          ? (response as any).data
          : [];
      const instance = instances[0];
      if (instance) {
        setChecklist(normalizeChecklist(instance));
      } else {
        // No checklist configured for today
        setChecklist(null);
      }
    } catch (error: any) {
      console.error('Checklist fetch error:', error);
      // If API returns 404, treat it as "no checklist for today" instead of a hard error
      const status = error?.response?.status;
      if (status === 404) {
        setChecklist(null);
      } else {
        Alert.alert('Error', 'Failed to load checklist');
        setChecklist(null);
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

  const handleToggleItem = async (itemCode: string, newState: 'pending' | 'done' | 'skipped') => {
    if (!checklist) return;

    // Backend expects 'Done' or 'NA' (see MarkItemRequest).
    // We don't send "Pending" to backend; pending means "not marked yet".
    const stateForApi = newState === 'done' ? 'Done' : 'NA';
    const currentItem = checklist.items.find(i => i.code === itemCode);
    const payload: any = { state: stateForApi };
    if (currentItem?.comment) payload.comment = currentItem.comment;
    if (currentItem?.photo_url) payload.photo_urls = [currentItem.photo_url];

    try {
      if (isOnline) {
        await apiService.post(`/checklists/${checklist.id}/items/${itemCode}`, payload);
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

  const handlePhotoUpload = async (itemCode: string) => {
    if (!checklist) return;

    try {
      const result = await launchImageLibrary({
        mediaType: 'photo',
        quality: 0.8,
        selectionLimit: 1,
      });

      const asset = result.assets?.[0];
      if (!asset?.uri) {
        return;
      }

      const formData = new FormData();
      formData.append('photo', {
        uri: asset.uri,
        type: asset.type ?? 'image/jpeg',
        name: asset.fileName ?? `${itemCode}_${Date.now()}.jpg`,
      } as any);

      const response = await apiService.post<{ photo_url?: string; data?: { photo_url?: string } }>(
        `/checklists/${checklist.id}/items/${itemCode}/photo`,
        formData,
        { headers: { 'Content-Type': 'multipart/form-data' } }
      );
      const uploadedUrl = resolveTenantLogoUrl(response?.photo_url ?? response?.data?.photo_url ?? undefined);

      if (!uploadedUrl) {
        Alert.alert('Upload failed', 'Photo upload succeeded but image URL was not returned. Please retry.');
        return;
      }

      setChecklist(prev => {
        if (!prev) return prev;
        return {
          ...prev,
          items: prev.items.map(item =>
            item.code === itemCode ? { ...item, photo_url: uploadedUrl } : item
          ),
        };
      });
    } catch (error: any) {
      const message = error?.response?.data?.message || 'Unable to upload photo. Please try again.';
      Alert.alert('Upload failed', message);
    }
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
              
              if (isOnline) {
                await apiService.post(`/checklists/${checklist.id}/submit`);
                
                // Update local state
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
                  checklist_id: checklist.id,
                });
                Alert.alert('Queued', 'Checklist submission queued for sync when online');
              }
            } catch (error: any) {
              console.error('Checklist submit error:', error);
              const message = error?.response?.data?.message || 'Failed to submit checklist';
              Alert.alert('Submission failed', message);
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
        <StaffScreenHeader
          title="Daily Checklist"
          variant="minimal"
          onBack={() => navigation.goBack()}
          showBell={false}
          showLogo={false}
        />
        <View style={styles.header}>
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
        <StaffScreenHeader
          title="Daily Checklist"
          variant="minimal"
          onBack={() => navigation.goBack()}
          showBell={false}
          showLogo={false}
        />
        <View style={styles.header}>
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
            <StaffPrimaryButton
              label="Submit Checklist"
              onPress={handleSubmit}
              disabled={submitting}
              loading={submitting}
            />
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
    backgroundColor: theme.colors.white,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
  },
  title: {
    fontSize: theme.fontSize.xxl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.primary,
    marginBottom: theme.spacing.xs,
  },
  subtitle: {
    fontSize: theme.fontSize.md,
    color: theme.colors.textSecondary,
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
