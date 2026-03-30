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
import { useAuthStore } from '../../shared/store/auth.store';
import { apiService } from '../../shared/services/api.service';
import { Attendance } from '../../types';
import { APP_CONFIG } from '../../shared/config/app.config';
import { format, isSameDay, addDays } from 'date-fns';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { theme } from '../../shared/theme/theme';
import { errorHandler } from '../../shared/utils/errorHandler';
import { ErrorState, LoadingState } from '../../shared/components';

export const AttendanceScreen = ({ navigation }: any) => {
  const insets = useSafeAreaInsets();
  const { user } = useAuthStore();
  const [attendance, setAttendance] = useState<Attendance[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const today = new Date();
  const yesterday = addDays(today, -1);
  const dayBeforeYesterday = addDays(today, -2);

  const dayTabs = [
    { id: 'db_yesterday', label: 'Day before yesterday', date: dayBeforeYesterday },
    { id: 'yesterday', label: 'Yesterday', date: yesterday },
    { id: 'today', label: 'Today', date: today },
  ] as const;

  const [selectedDayId, setSelectedDayId] =
    useState<'db_yesterday' | 'yesterday' | 'today'>('today');

  const fetchAttendance = async () => {
    try {
      setError(null);
      setLoading(true);
      const response = await apiService.get<{ data: Attendance[] }>(
        APP_CONFIG.ENDPOINTS.ATTENDANCE
      );
      setAttendance(response.data || []);
    } catch (err) {
      const errorDetails = errorHandler.handleError(err);
      setError(errorDetails.message);
      setAttendance([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchAttendance();
  }, []);

  const onRefresh = () => {
    setRefreshing(true);
    fetchAttendance();
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'present':
        return theme.colors.success;
      case 'absent':
        return theme.colors.error;
      case 'on_leave':
        return theme.colors.warning;
      default:
        return theme.colors.textMuted;
    }
  };

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'present':
        return 'checkmark-circle';
      case 'absent':
        return 'close-circle';
      case 'on_leave':
        return 'home';
      default:
        return 'help-circle-outline';
    }
  };

  const getStatusText = (status: string) => {
    switch (status) {
      case 'present':
        return 'Present';
      case 'absent':
        return 'Absent';
      case 'on_leave':
        return 'On Leave';
      default:
        return 'Unknown';
    }
  };
  const getAttendanceForDate = (date: Date) =>
    attendance.find((record) => isSameDay(new Date(record.date), date));

  const HEADER_ROW_HEIGHT = 52;
  const HEADER_PADDING_TOP = Math.max(insets.top, 10);
  const HEADER_PADDING_BOTTOM = 6;

  return (
    <View style={styles.container}>
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
          <GradientButton
            style={styles.headerBackButton}
            onPress={() => (navigation?.canGoBack?.() ? navigation.goBack() : navigation.navigate('Home'))}
            accessibilityLabel="Go back">
            <Ionicons name="arrow-back" size={24} color={theme.colors.primary} />
          </GradientButton>
          <Text style={styles.headerTitle}>My Attendance</Text>
          <View style={styles.headerSpacer} />
        </View>
      </View>
      <ScrollView
        style={styles.content}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>
        {loading ? (
          <LoadingState message="Loading attendance..." />
        ) : error ? (
          <ErrorState error={error} onRetry={fetchAttendance} />
        ) : (
          <>
            {/* Top 3-day tabs */}
            <View style={styles.tabsContainer}>
              {dayTabs.map((tab) => {
                const isActive = tab.id === selectedDayId;
                return (
                  <TouchableOpacity
                    key={tab.id}
                    style={[styles.tabItem, isActive && styles.tabItemActive]}
                    onPress={() => setSelectedDayId(tab.id)}>
                    <Text style={[styles.tabLabel, isActive && styles.tabLabelActive]}>
                      {tab.label}
                    </Text>
                    <Text style={styles.tabDate}>
                      {format(tab.date, 'EEE, MMM dd')}
                    </Text>
                  </TouchableOpacity>
                );
              })}
            </View>

            {/* Selected day status */}
            <View style={styles.dailyCard}>
              {(() => {
                const selected = dayTabs.find((d) => d.id === selectedDayId)!;
                const record = getAttendanceForDate(selected.date);
                const status = record?.status ?? 'unknown';

                return (
                  <>
                    <Text style={styles.dailyDate}>
                      {format(selected.date, 'EEEE, MMMM dd, yyyy')}
                    </Text>
                    <View
                      style={[
                        styles.dailyStatusBadge,
                        { backgroundColor: getStatusColor(status) },
                      ]}>
                      <Ionicons
                        name={getStatusIcon(status)}
                        size={24}
                        color={theme.colors.white}
                        style={styles.dailyStatusIcon}
                      />
                      <Text style={styles.dailyStatusText}>
                        {getStatusText(status)}
                      </Text>
                    </View>
                    {record?.marked_at && (
                      <Text style={styles.dailyMeta}>
                        Marked at:{' '}
                        {format(new Date(record.marked_at), 'dd MMM yyyy, HH:mm')}
                      </Text>
                    )}
                    {!record && (
                      <Text style={styles.dailyMeta}>
                        No attendance record found for this day.
                      </Text>
                    )}
                  </>
                );
              })()}
            </View>
          </>
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
    paddingHorizontal: theme.spacing.lg,
    backgroundColor: theme.colors.white,
    ...theme.shadows.medium,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  headerBackButton: {
    paddingVertical: theme.spacing.xs,
    paddingRight: theme.spacing.sm,
  },
  headerTitle: {
    flex: 1,
    textAlign: 'center',
    fontSize: theme.fontSize.lg,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.primary,
  },
  headerSpacer: {
    width: 24,
  },
  content: {
    flex: 1,
  },
  tabsContainer: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingHorizontal: theme.spacing.lg,
    paddingTop: theme.spacing.lg,
    paddingBottom: theme.spacing.md,
    backgroundColor: theme.colors.white,
  },
  tabItem: {
    flex: 1,
    marginHorizontal: 4,
    paddingVertical: theme.spacing.sm,
    paddingHorizontal: theme.spacing.xs,
    borderRadius: theme.borderRadius.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.card,
  },
  tabItemActive: {
    borderColor: theme.colors.primary,
    backgroundColor: theme.colors.hover,
  },
  tabLabel: {
    fontSize: theme.fontSize.sm,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.textSecondary,
  },
  tabLabelActive: {
    color: theme.colors.primary,
  },
  tabDate: {
    fontSize: theme.fontSize.xs,
    color: theme.colors.textMuted,
    marginTop: 2,
  },
  dailyCard: {
    marginHorizontal: theme.spacing.md,
    marginTop: theme.spacing.sm,
    backgroundColor: theme.colors.card,
    borderRadius: theme.borderRadius.lg,
    padding: theme.spacing.lg,
    borderWidth: 1,
    borderColor: theme.colors.border,
    ...theme.shadows.small,
  },
  dailyDate: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
    marginBottom: theme.spacing.md,
  },
  dailyStatusBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    alignSelf: 'flex-start',
    paddingHorizontal: theme.spacing.lg,
    paddingVertical: theme.spacing.sm,
    borderRadius: theme.borderRadius.xl,
    marginBottom: theme.spacing.sm,
  },
  dailyStatusIcon: {
    marginRight: theme.spacing.xs,
  },
  dailyStatusText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.lg,
    fontWeight: theme.fontWeight.bold,
  },
  dailyMeta: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
    marginTop: theme.spacing.xs,
  },
});
