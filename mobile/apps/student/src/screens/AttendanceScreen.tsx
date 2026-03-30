import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
} from 'react-native';
import { useAuthStore } from '../../store/auth.store';
import { apiService } from '../../services/api.service';
import { Attendance } from '../../types';
import { APP_CONFIG } from '../../config/app.config';
import { format, startOfMonth, endOfMonth, eachDayOfInterval, isSameDay } from 'date-fns';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { theme } from '../../theme/theme';
import { errorHandler } from '../../utils/errorHandler';
import { ErrorState, LoadingState } from '../../components';

export const AttendanceScreen = ({ navigation }: any) => {
  const { user } = useAuthStore();
  const [attendance, setAttendance] = useState<Attendance[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [selectedMonth, setSelectedMonth] = useState(new Date());
  const [error, setError] = useState<string | null>(null);

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

  // Calculate monthly statistics
  const getMonthlyStats = () => {
    const monthStart = startOfMonth(selectedMonth);
    const monthEnd = endOfMonth(selectedMonth);
    const daysInMonth = eachDayOfInterval({ start: monthStart, end: monthEnd });

    const monthAttendance = attendance.filter(
      (record) => {
        const recordDate = new Date(record.date);
        return recordDate >= monthStart && recordDate <= monthEnd;
      }
    );

    const presentDays = monthAttendance.filter(record => record.status === 'present').length;
    const absentDays = monthAttendance.filter(record => record.status === 'absent').length;
    const leaveDays = monthAttendance.filter(record => record.status === 'on_leave').length;
    const totalDays = daysInMonth.length;

    return {
      present: presentDays,
      absent: absentDays,
      onLeave: leaveDays,
      total: totalDays,
      percentage: totalDays > 0 ? Math.round((presentDays / totalDays) * 100) : 0,
    };
  };

  const stats = getMonthlyStats();

  // Generate calendar view
  const renderCalendar = () => {
    const monthStart = startOfMonth(selectedMonth);
    const monthEnd = endOfMonth(monthStart);
    const startDate = startOfMonth(monthStart);
    const endDate = endOfMonth(monthStart);
    const days = eachDayOfInterval({ start: startDate, end: endDate });

    // Get attendance for this month
    const monthAttendance = attendance.filter(
      (record) => {
        const recordDate = new Date(record.date);
        return recordDate >= startDate && recordDate <= endDate;
      }
    );

    return (
      <View style={styles.calendar}>
        <Text style={styles.calendarTitle}>
          {format(selectedMonth, 'MMMM yyyy')}
        </Text>
        <View style={styles.calendarGrid}>
          {days.map((day, index) => {
            const dayAttendance = monthAttendance.find(record =>
              isSameDay(new Date(record.date), day)
            );
            const isToday = isSameDay(day, new Date());
            const isFuture = day > new Date();

            return (
              <View key={index} style={styles.calendarDay}>
                <Text style={[
                  styles.dayNumber,
                  isToday && styles.todayDay,
                  isFuture && styles.futureDay,
                ]}>
                  {format(day, 'd')}
                </Text>
                {dayAttendance && (
                  <View style={[
                    styles.statusDot,
                    { backgroundColor: getStatusColor(dayAttendance.status) }
                  ]}>
                    <Text style={styles.statusDotIcon}>
                      {getStatusIcon(dayAttendance.status)}
                    </Text>
                  </View>
                )}
              </View>
            );
          })}
        </View>
      </View>
    );
  };

  return (
    <View style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity
          style={styles.backButton}
          onPress={() => navigation.goBack()}>
          <Ionicons name="arrow-back" size={20} color={theme.colors.white} />
          <Text style={styles.backButtonText}>Back</Text>
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Attendance</Text>
        <View style={styles.headerSpacer} />
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
            {/* Monthly Statistics */}
            <View style={styles.section}>
          <Text style={styles.sectionTitle}>Monthly Overview</Text>
          <View style={styles.statsCard}>
            <View style={styles.statRow}>
              <View style={styles.statItem}>
                <Text style={styles.statNumber}>{stats.present}</Text>
                <Text style={styles.statLabel}>Present</Text>
              </View>
              <View style={styles.statItem}>
                <Text style={styles.statNumber}>{stats.absent}</Text>
                <Text style={styles.statLabel}>Absent</Text>
              </View>
              <View style={styles.statItem}>
                <Text style={styles.statNumber}>{stats.onLeave}</Text>
                <Text style={styles.statLabel}>On Leave</Text>
              </View>
            </View>
            <View style={styles.percentageContainer}>
              <Text style={styles.percentageLabel}>Attendance Rate</Text>
              <Text style={styles.percentageValue}>{stats.percentage}%</Text>
            </View>
          </View>
        </View>

        {/* Calendar View */}
        {renderCalendar()}

        {/* Recent Attendance */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Recent Records</Text>
          {attendance.length === 0 ? (
            <View style={styles.emptyState}>
              <Ionicons
                name="calendar-outline"
                size={48}
                color={theme.colors.textSecondary}
                style={styles.emptyIcon}
              />
              <Text style={styles.emptyTitle}>No Attendance Records</Text>
              <Text style={styles.emptySubtitle}>
                Your attendance records will appear here
              </Text>
            </View>
          ) : (
            attendance.slice(0, 10).map((record) => (
              <View key={record.id} style={styles.recordCard}>
                <View style={styles.recordHeader}>
                  <Text style={styles.recordDate}>
                    {format(new Date(record.date), 'MMM dd, yyyy')}
                  </Text>
                  <View
                    style={[
                      styles.statusBadge,
                      { backgroundColor: getStatusColor(record.status) },
                    ]}>
                    <Ionicons
                      name={getStatusIcon(record.status)}
                      size={14}
                      color={theme.colors.white}
                      style={styles.statusIcon}
                    />
                    <Text style={styles.statusText}>
                      {getStatusText(record.status)}
                    </Text>
                  </View>
                </View>
                {record.marked_by && (
                  <Text style={styles.markedBy}>
                    Marked by: {record.marked_by}
                  </Text>
                )}
                {record.marked_at && (
                  <Text style={styles.markedAt}>
                    {format(new Date(record.marked_at), 'HH:mm')}
                  </Text>
                )}
              </View>
            ))
          )}
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
    backgroundColor: theme.colors.background,
  },
  header: {
    backgroundColor: theme.colors.primary,
    padding: theme.spacing.lg,
    paddingTop: theme.spacing.xl * 2,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  backButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.xs,
    padding: theme.spacing.sm,
  },
  backButtonText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
  },
  headerTitle: {
    color: theme.colors.white,
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
  },
  headerSpacer: {
    width: 60,
  },
  content: {
    flex: 1,
  },
  section: {
    marginBottom: theme.spacing.md,
  },
  sectionTitle: {
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
    paddingHorizontal: theme.spacing.lg,
    marginBottom: theme.spacing.sm,
  },
  statsCard: {
    backgroundColor: theme.colors.card,
    marginHorizontal: theme.spacing.md,
    padding: theme.spacing.lg,
    borderRadius: theme.borderRadius.lg,
    borderWidth: 1,
    borderColor: theme.colors.border,
    ...theme.shadows.small,
  },
  statRow: {
    flexDirection: 'row',
    justifyContent: 'space-around',
    marginBottom: theme.spacing.lg,
  },
  statItem: {
    alignItems: 'center',
  },
  statNumber: {
    fontSize: theme.fontSize.xxl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
  },
  statLabel: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
    marginTop: theme.spacing.xs,
  },
  percentageContainer: {
    alignItems: 'center',
    paddingTop: theme.spacing.md,
    borderTopWidth: 1,
    borderTopColor: theme.colors.divider,
  },
  percentageLabel: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
    marginBottom: theme.spacing.xs,
  },
  percentageValue: {
    fontSize: theme.fontSize.xxxl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.success,
  },
  calendar: {
    backgroundColor: theme.colors.card,
    marginHorizontal: theme.spacing.md,
    padding: theme.spacing.lg,
    borderRadius: theme.borderRadius.lg,
    borderWidth: 1,
    borderColor: theme.colors.border,
    ...theme.shadows.small,
    marginBottom: theme.spacing.md,
  },
  calendarTitle: {
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
    textAlign: 'center',
    marginBottom: theme.spacing.md,
  },
  calendarGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
  },
  calendarDay: {
    width: '14%',
    aspectRatio: 1,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: theme.spacing.xs,
    position: 'relative',
  },
  dayNumber: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.text,
  },
  todayDay: {
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.primary,
  },
  futureDay: {
    color: theme.colors.textMuted,
  },
  statusDot: {
    position: 'absolute',
    bottom: 2,
    width: 16,
    height: 16,
    borderRadius: 8,
    justifyContent: 'center',
    alignItems: 'center',
  },
  statusDotIcon: {
    fontSize: 8,
    color: theme.colors.white,
  },
  emptyState: {
    backgroundColor: theme.colors.card,
    marginHorizontal: theme.spacing.md,
    padding: theme.spacing.xl,
    borderRadius: theme.borderRadius.lg,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: theme.colors.border,
    ...theme.shadows.small,
  },
  emptyIcon: {
    marginBottom: theme.spacing.md,
  },
  emptyTitle: {
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
    marginBottom: theme.spacing.sm,
  },
  emptySubtitle: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
    textAlign: 'center',
  },
  recordCard: {
    backgroundColor: theme.colors.card,
    marginHorizontal: theme.spacing.md,
    marginBottom: theme.spacing.sm,
    padding: theme.spacing.md,
    borderRadius: theme.borderRadius.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    ...theme.shadows.small,
  },
  recordHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: theme.spacing.xs,
  },
  recordDate: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
  },
  statusBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: theme.spacing.sm,
    paddingVertical: theme.spacing.xs,
    borderRadius: theme.borderRadius.xl,
  },
  statusIcon: {
    marginRight: theme.spacing.xs,
  },
  statusText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.xs,
    fontWeight: theme.fontWeight.semibold,
  },
  markedBy: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
    marginBottom: theme.spacing.xs,
  },
  markedAt: {
    fontSize: theme.fontSize.xs,
    color: theme.colors.textMuted,
  },
});
