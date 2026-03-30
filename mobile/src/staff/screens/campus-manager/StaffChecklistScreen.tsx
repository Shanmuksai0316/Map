import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  FlatList,
  StyleSheet,
  RefreshControl,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { apiService } from '../../../shared/services/api.service';
import { tenantService } from '../../../shared/services/tenant.service';
import { StorageService } from '../../../shared/services/storage.service';
import { APP_CONFIG } from '../../../shared/config/app.config';
import { theme } from '../../../shared/theme/theme';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface StaffChecklistSummary {
  user_id: number;
  user_name: string;
  total_tasks: number;
  completed_tasks: number;
  completion_percentage: number;
}

interface Props {
  navigation: any;
}

export const StaffChecklistScreen: React.FC<Props> = ({ navigation }) => {
  const [summary, setSummary] = useState<StaffChecklistSummary[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const fetchSummary = useCallback(async () => {
    setIsLoading(true);
    const pathsToTry = [
      '/mobile/campus-manager/checklists/staff-summary',
      '/campus-manager/checklists/staff-summary',
    ];
    const baseURL = tenantService.getCurrentApiUrl();
    const mapRow = (s: any): StaffChecklistSummary => ({
      user_id: s.user_id,
      user_name: s.user_name ?? '',
      total_tasks: s.total_task_items ?? s.total_tasks ?? 0,
      completed_tasks: s.total_completed_tasks ?? s.completed_tasks ?? 0,
      completion_percentage: s.task_completion_percentage ?? s.completion_percentage ?? 0,
    });
    let data: StaffChecklistSummary[] = [];
    for (const path of pathsToTry) {
      try {
        const response = await apiService.get<{ data: any[] }>(path);
        const raw = response?.data ?? [];
        data = raw.map(mapRow);
        break;
      } catch {
        continue;
      }
    }
    if (data.length === 0) {
      try {
        const fullUrl = baseURL.replace(/\/+$/, '') + '/' + pathsToTry[0].replace(/^\//, '');
        const rawToken = await StorageService.get(APP_CONFIG.STORAGE_KEYS.AUTH_TOKEN);
        const token = typeof rawToken === 'string' ? rawToken : (rawToken as any)?.token ?? '';
        const tenant = tenantService.getSelectedTenant();
        const res = await fetch(fullUrl, {
          method: 'GET',
          headers: {
            Accept: 'application/json',
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
            ...(tenant?.code ? { 'X-Tenant-Code': tenant.code } : {}),
          },
        });
        if (res.ok) {
          const json = await res.json();
          data = (json?.data ?? []).map(mapRow);
        }
      } catch {
        // keep data []
      }
    }
    setSummary(data);
    setIsLoading(false);
  }, []);

  useEffect(() => {
    fetchSummary();
  }, [fetchSummary]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await fetchSummary();
    setRefreshing(false);
  }, [fetchSummary]);

  const getProgressColor = (percentage: number): string => {
    if (percentage >= 80) return theme.colors.success;
    if (percentage >= 50) return theme.colors.warning;
    return theme.colors.error;
  };

  const renderStaffItem = ({ item }: { item: StaffChecklistSummary }) => {
    const progressColor = getProgressColor(item.completion_percentage);

    return (
      <View style={styles.staffCard}>
        <View style={styles.staffHeader}>
          <View style={styles.avatarContainer}>
            <Text style={styles.avatarText}>
              {item.user_name
                .split(' ')
                .map((n) => n[0])
                .join('')
                .substring(0, 2)}
            </Text>
          </View>
          <View style={styles.staffInfo}>
            <Text style={styles.staffName}>{item.user_name}</Text>
            <Text style={styles.taskCount}>
              {item.completed_tasks} / {item.total_tasks} tasks completed
            </Text>
          </View>
          <View style={styles.percentageContainer}>
            <Text style={[styles.percentage, { color: progressColor }]}>
              {item.completion_percentage}%
            </Text>
          </View>
        </View>

        <View style={styles.progressBar}>
          <View
            style={[
              styles.progressFill,
              {
                width: `${item.completion_percentage}%`,
                backgroundColor: progressColor,
              },
            ]}
          />
        </View>
      </View>
    );
  };

  const renderEmptyState = () => (
    <View style={styles.emptyState}>
      <Icon name="clipboard-text-outline" size={64} color={theme.colors.border} />
      <Text style={styles.emptyTitle}>No Checklists Found</Text>
      <Text style={styles.emptySubtitle}>
        No checklist data is available for assigned staff
      </Text>
    </View>
  );

  // Calculate overall stats
  const overallStats = summary.reduce(
    (acc, item) => ({
      totalTasks: acc.totalTasks + item.total_tasks,
      completedTasks: acc.completedTasks + item.completed_tasks,
    }),
    { totalTasks: 0, completedTasks: 0 }
  );

  const overallPercentage =
    overallStats.totalTasks > 0
      ? Math.round((overallStats.completedTasks / overallStats.totalTasks) * 100)
      : 0;

  return (
    <View style={styles.container}>
      <StaffScreenHeader
        onBack={() => navigation.goBack()}
        showBell={false}
        title="Staff Checklists"
        variant="minimal"
        showLogo={false}
      />
      {/* Overall Summary */}
      {summary.length > 0 && (
        <View style={styles.summaryCard}>
          <View style={styles.summaryRow}>
            <View style={styles.summaryItem}>
              <Text style={styles.summaryValue}>{summary.length}</Text>
              <Text style={styles.summaryLabel}>Staff</Text>
            </View>
            <View style={styles.summaryDivider} />
            <View style={styles.summaryItem}>
              <Text style={styles.summaryValue}>
                {overallStats.completedTasks}/{overallStats.totalTasks}
              </Text>
              <Text style={styles.summaryLabel}>Tasks Done</Text>
            </View>
            <View style={styles.summaryDivider} />
            <View style={styles.summaryItem}>
              <Text
                style={[
                  styles.summaryValue,
                  { color: getProgressColor(overallPercentage) },
                ]}
              >
                {overallPercentage}%
              </Text>
              <Text style={styles.summaryLabel}>Overall</Text>
            </View>
          </View>
        </View>
      )}

      {/* Staff List */}
      <FlatList
        data={summary}
        keyExtractor={(item) => String(item.user_id)}
        renderItem={renderStaffItem}
        ListEmptyComponent={!isLoading ? renderEmptyState : null}
        contentContainerStyle={styles.listContent}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
      />
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
  subHeaderSubtitle: {
    fontSize: 14,
    color: theme.colors.textMuted,
    marginTop: 4,
  },
  summaryCard: {
    backgroundColor: theme.colors.card,
    margin: 16,
    padding: 20,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  summaryRow: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  summaryItem: {
    flex: 1,
    alignItems: 'center',
  },
  summaryValue: {
    fontSize: 24,
    fontWeight: '700',
    color: theme.colors.text,
  },
  summaryLabel: {
    fontSize: 12,
    color: theme.colors.textSecondary,
    marginTop: 4,
  },
  summaryDivider: {
    width: 1,
    height: 40,
    backgroundColor: theme.colors.border,
  },
  listContent: {
    padding: 16,
    paddingTop: 0,
    flexGrow: 1,
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
    alignItems: 'center',
    marginBottom: 12,
  },
  avatarContainer: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: theme.colors.primary,
    justifyContent: 'center',
    alignItems: 'center',
  },
  avatarText: {
    color: theme.colors.white,
    fontSize: 16,
    fontWeight: '700',
  },
  staffInfo: {
    flex: 1,
    marginLeft: 12,
  },
  staffName: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.text,
  },
  taskCount: {
    fontSize: 13,
    color: theme.colors.textSecondary,
    marginTop: 2,
  },
  percentageContainer: {
    alignItems: 'flex-end',
  },
  percentage: {
    fontSize: 20,
    fontWeight: '700',
  },
  progressBar: {
    height: 6,
    backgroundColor: theme.colors.border,
    borderRadius: 3,
    overflow: 'hidden',
  },
  progressFill: {
    height: '100%',
    borderRadius: 3,
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
    textAlign: 'center',
  },
});

export default StaffChecklistScreen;
