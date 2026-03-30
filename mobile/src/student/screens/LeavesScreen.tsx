import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
} from 'react-native';
import { GradientButton } from '../../shared/components/GradientButton';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useAuthStore } from '../../shared/store/auth.store';
import { apiService } from '../../shared/services/api.service';
import { Leave } from '../../types';
import { APP_CONFIG } from '../../shared/config/app.config';
import { format } from 'date-fns';
import { theme } from '../../shared/theme/theme';
import { errorHandler } from '../../shared/utils/errorHandler';
import { ErrorState, LoadingState, EmptyState } from '../../shared/components';
import { hapticService } from '../../shared/services/haptic.service';

type TabType = 'approved' | 'pending' | 'rejected' | 'completed';

export const LeavesScreen = ({ navigation }: any) => {
  const insets = useSafeAreaInsets();
  const { user } = useAuthStore();
  const [leaves, setLeaves] = useState<Leave[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [selectedTab, setSelectedTab] = useState<TabType>('pending');

  useEffect(() => {
    fetchLeaves();
  }, []);

  const fetchLeaves = async () => {
    try {
      setError(null);
      setLoading(true);
      const response = await apiService.get<{ data: Leave[] }>(
        APP_CONFIG.ENDPOINTS.LEAVES
      );
      setLeaves(response.data || []);
    } catch (err) {
      const errorDetails = errorHandler.handleError(err);
      setError(errorDetails.message);
      setLeaves([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  const onRefresh = () => {
    setRefreshing(true);
    fetchLeaves();
  };

  const getFilteredLeaves = () => {
    return leaves.filter(leave => {
      if (selectedTab === 'completed') {
        return leave.status === 'used' || leave.status === 'completed';
      }
      return leave.status === selectedTab;
    });
  };

  const filteredLeaves = getFilteredLeaves();

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'approved':
        return theme.colors.success;
      case 'pending':
        return theme.colors.warning;
      case 'rejected':
        return theme.colors.error;
      case 'used':
      case 'completed':
        return theme.colors.info;
      default:
        return theme.colors.textMuted;
    }
  };

  const tabs: { key: TabType; label: string }[] = [
    { key: 'approved', label: 'Approved' },
    { key: 'pending', label: 'Pending' },
    { key: 'rejected', label: 'Rejected' },
    { key: 'completed', label: 'Completed' },
  ];

  const HEADER_ROW_HEIGHT = 52;
  const HEADER_PADDING_TOP = Math.max(insets.top, 10);
  const HEADER_PADDING_BOTTOM = 6;

  return (
    <View style={styles.container}>
      {/* Header - compact height, same feel as home screen */}
      <View
        style={[
          styles.header,
          {
            paddingTop: HEADER_PADDING_TOP,
            paddingBottom: HEADER_PADDING_BOTTOM,
            minHeight: HEADER_PADDING_TOP + HEADER_ROW_HEIGHT + HEADER_PADDING_BOTTOM,
          },
        ]}>
        <View style={[styles.headerRow, { height: HEADER_ROW_HEIGHT }]}>
          <TouchableOpacity
            style={styles.backButton}
            onPress={() => {
              hapticService.onButtonPress();
              if (navigation?.canGoBack?.()) {
                navigation.goBack();
              } else {
                navigation.navigate('Home');
              }
            }}
            accessibilityLabel="Go back">
            <Ionicons name="arrow-back" size={24} color={theme.colors.primary} />
          </TouchableOpacity>
          <Text style={styles.headerTitle}>Leave</Text>
          <GradientButton
            style={styles.createButton}
            onPress={() => {
              hapticService.onButtonPress();
              navigation.navigate('LeaveForm');
            }}>
            <Ionicons name="add" size={18} color={theme.colors.primary} />
            <Text style={styles.createButtonText}>New</Text>
          </GradientButton>
        </View>
      </View>

      {/* Tabs */}
      <View style={styles.tabsContainer}>
        <ScrollView horizontal showsHorizontalScrollIndicator={false}>
          {tabs.map(tab => (
            <TouchableOpacity
              key={tab.key}
              style={[styles.tab, selectedTab === tab.key && styles.tabActive]}
              onPress={() => {
                hapticService.onButtonPress();
                setSelectedTab(tab.key);
              }}>
              <Text style={[styles.tabText, selectedTab === tab.key && styles.tabTextActive]}>
                {tab.label}
              </Text>
            </TouchableOpacity>
          ))}
        </ScrollView>
      </View>

      {/* Leaves List */}
      <ScrollView
        style={styles.content}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>
        {loading ? (
          <LoadingState message="Loading leaves..." />
        ) : error ? (
          <ErrorState error={error} onRetry={fetchLeaves} />
        ) : filteredLeaves.length === 0 ? (
          <EmptyState
            variant="no-data"
            title="No Leaves"
            subtitle={`You don't have any ${selectedTab} leaves`}
          />
        ) : (
          filteredLeaves.map((leave) => (
            <TouchableOpacity
              key={leave.id}
              style={styles.leaveCard}
              onPress={() => navigation.navigate('LeaveDetail', { leaveId: leave.id })}>
              {/* Title of the leave */}
              <View style={styles.leaveHeader}>
                <Text style={styles.leaveTitle}>{leave.title}</Text>
                <View
                  style={[
                    styles.statusBadge,
                    { backgroundColor: getStatusColor(leave.status) },
                  ]}>
                  <Text style={styles.statusText}>
                    {leave.status.toUpperCase()}
                  </Text>
                </View>
              </View>

              {/* Unique ID */}
              <View style={styles.leaveInfo}>
                <Text style={styles.leaveLabel}>Unique ID: {leave.unique_id}</Text>
              </View>

              {/* Date Range */}
              {leave.from_date && leave.to_date && (
                <View style={styles.leaveInfo}>
                  <Ionicons name="calendar-outline" size={16} color={theme.colors.textSecondary} style={styles.infoIcon} />
                  <Text style={styles.leaveLabel}>
                    {format(new Date(leave.from_date), 'MMM dd')} - {format(new Date(leave.to_date), 'MMM dd, yyyy')}
                  </Text>
                </View>
              )}

              {/* Description */}
              {leave.description && (
                <Text style={styles.leaveDescription} numberOfLines={2}>
                  {leave.description}
                </Text>
              )}

              {/* Date submitted */}
              {leave.submitted_date && (
                <Text style={styles.leaveDate}>
                  Submitted: {format(new Date(leave.submitted_date), 'MMM dd, yyyy')}
                </Text>
              )}
            </TouchableOpacity>
          ))
        )}
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.white,
  },
  header: {
    backgroundColor: theme.colors.white,
    paddingHorizontal: 20,
    ...theme.shadows.medium,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  backButton: {
    padding: theme.spacing.xs,
  },
  headerTitle: {
    color: theme.colors.primary,
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
    flex: 1,
    textAlign: 'center',
  },
  createButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.xs,
    backgroundColor: theme.colors.white,
    paddingHorizontal: theme.spacing.md,
    paddingVertical: theme.spacing.sm,
    borderRadius: theme.borderRadius.md,
  },
  createButtonText: {
    color: theme.colors.primary,
    fontSize: theme.fontSize.sm,
    fontWeight: theme.fontWeight.semibold,
  },
  tabsContainer: {
    backgroundColor: theme.colors.white,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
  },
  tab: {
    paddingHorizontal: theme.spacing.lg,
    paddingVertical: theme.spacing.md,
    borderBottomWidth: 2,
    borderBottomColor: 'transparent',
  },
  tabActive: {
    borderBottomColor: theme.colors.primary,
  },
  tabText: {
    fontSize: theme.fontSize.md,
    color: theme.colors.textSecondary,
    fontWeight: theme.fontWeight.medium,
  },
  tabTextActive: {
    color: theme.colors.primary,
    fontWeight: theme.fontWeight.bold,
  },
  content: {
    flex: 1,
    padding: theme.spacing.lg,
  },
  leaveCard: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.borderRadius.lg,
    padding: theme.spacing.md,
    marginBottom: theme.spacing.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    ...theme.shadows.small,
  },
  leaveHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: theme.spacing.sm,
  },
  leaveTitle: {
    fontSize: theme.fontSize.lg,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
    flex: 1,
    marginRight: theme.spacing.sm,
  },
  statusBadge: {
    paddingHorizontal: theme.spacing.md,
    paddingVertical: theme.spacing.sm,
    borderRadius: theme.borderRadius.xl,
  },
  statusText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.xs,
    fontWeight: theme.fontWeight.semibold,
  },
  leaveInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: theme.spacing.xs,
  },
  infoIcon: {
    marginRight: theme.spacing.xs,
  },
  leaveLabel: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
  },
  leaveDescription: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
    marginTop: theme.spacing.sm,
    marginBottom: theme.spacing.sm,
    lineHeight: 20,
  },
  leaveDate: {
    fontSize: theme.fontSize.xs,
    color: theme.colors.textMuted,
    marginTop: theme.spacing.xs,
  },
});
