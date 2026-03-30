import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useAuthStore } from '../../store/auth.store';
import { apiService } from '../../services/api.service';
import { SickLeave } from '../../types';
import { APP_CONFIG } from '../../config/app.config';
import { format } from 'date-fns';
import { theme } from '../../theme/theme';
import { errorHandler } from '../../utils/errorHandler';
import { ErrorState, LoadingState } from '../../components';

export const SickLeavePreviewScreen = ({ navigation }: any) => {
  const { user } = useAuthStore();
  const [sickLeaves, setSickLeaves] = useState<SickLeave[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchSickLeaves();
  }, []);

  const fetchSickLeaves = async () => {
    try {
      setError(null);
      setLoading(true);
      const response = await apiService.get<{ data: SickLeave[] }>(
        APP_CONFIG.ENDPOINTS.SICK_LEAVES
      );
      setSickLeaves(response.data || []);
    } catch (err) {
      const errorDetails = errorHandler.handleError(err);
      setError(errorDetails.message);
      setSickLeaves([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  const onRefresh = () => {
    setRefreshing(true);
    fetchSickLeaves();
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'approved':
        return theme.colors.success;
      case 'pending':
        return theme.colors.warning;
      case 'rejected':
        return theme.colors.error;
      default:
        return theme.colors.textMuted;
    }
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
        <Text style={styles.headerTitle}>
          Sick Leave ({sickLeaves.length} requests)
        </Text>
        <TouchableOpacity
          style={styles.createButton}
          onPress={() => navigation.navigate('SickLeaveForm')}>
          <Ionicons name="add" size={18} color={theme.colors.primary} />
          <Text style={styles.createButtonText}>New</Text>
        </TouchableOpacity>
      </View>

      {/* Sick Leaves List */}
      <ScrollView
        style={styles.content}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>
        {loading ? (
          <LoadingState message="Loading sick leave requests..." />
        ) : error ? (
          <ErrorState error={error} onRetry={fetchSickLeaves} />
        ) : sickLeaves.length === 0 ? (
          <View style={styles.emptyState}>
            <Ionicons
              name="medical-outline"
              size={64}
              color={theme.colors.textSecondary}
              style={styles.emptyIcon}
            />
            <Text style={styles.emptyTitle}>No Sick Leave Requests</Text>
            <Text style={styles.emptySubtitle}>
              You haven't submitted any sick leave requests yet
            </Text>
            <TouchableOpacity
              style={styles.emptyButton}
              onPress={() => navigation.navigate('SickLeaveForm')}>
              <Text style={styles.emptyButtonText}>Create First Sick Leave</Text>
            </TouchableOpacity>
          </View>
        ) : (
          sickLeaves.map((sickLeave) => (
            <TouchableOpacity
              key={sickLeave.id}
              style={styles.sickLeaveCard}
              onPress={() => navigation.navigate('SickLeaveDetail', { sickLeaveId: sickLeave.id })}>
              {/* Title of the sick leave */}
              <Text style={styles.sickLeaveTitle}>{sickLeave.title}</Text>

              <View style={styles.sickLeaveRow}>
                {/* Unique ID */}
                <View style={styles.sickLeaveInfo}>
                  <Text style={styles.sickLeaveLabel}>Unique ID:</Text>
                  <Text style={styles.sickLeaveValue}>{sickLeave.unique_id}</Text>
                </View>

                {/* Status on right side (with reason if rejected) */}
                <View
                  style={[
                    styles.statusBadge,
                    { backgroundColor: getStatusColor(sickLeave.status) },
                  ]}>
                  <Text style={styles.statusText}>
                    {sickLeave.status.toUpperCase()}
                    {sickLeave.rejection_reason && ' (with reason)'}
                  </Text>
                </View>
              </View>

              {/* Description */}
              <Text style={styles.sickLeaveDescription} numberOfLines={2}>
                {sickLeave.description}
              </Text>

              {/* Date on submitted */}
              <Text style={styles.sickLeaveDate}>
                Submitted: {format(new Date(sickLeave.submitted_date), 'MMM dd, yyyy')}
              </Text>
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
    backgroundColor: theme.colors.background,
  },
  header: {
    backgroundColor: theme.colors.primary,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: theme.spacing.lg,
    paddingTop: theme.spacing.xl * 2,
  },
  backButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.xs,
  },
  backButtonText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.medium,
  },
  headerTitle: {
    color: theme.colors.white,
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
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
  content: {
    flex: 1,
    padding: theme.spacing.lg,
  },
  emptyState: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: theme.spacing.xxl,
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
    fontSize: theme.fontSize.md,
    color: theme.colors.textSecondary,
    textAlign: 'center',
    marginBottom: theme.spacing.lg,
  },
  emptyButton: {
    backgroundColor: theme.colors.primary,
    paddingHorizontal: theme.spacing.xl,
    paddingVertical: theme.spacing.md,
    borderRadius: theme.borderRadius.md,
  },
  emptyButtonText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
  },
  sickLeaveCard: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.borderRadius.lg,
    padding: theme.spacing.md,
    marginBottom: theme.spacing.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    ...theme.shadows.small,
  },
  sickLeaveTitle: {
    fontSize: theme.fontSize.lg,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
    marginBottom: theme.spacing.sm,
  },
  sickLeaveRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: theme.spacing.sm,
  },
  sickLeaveInfo: {
    flex: 1,
  },
  sickLeaveLabel: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textMuted,
    marginBottom: theme.spacing.xs,
  },
  sickLeaveValue: {
    fontSize: theme.fontSize.md,
    color: theme.colors.text,
    fontWeight: theme.fontWeight.medium,
  },
  statusBadge: {
    paddingHorizontal: theme.spacing.md,
    paddingVertical: theme.spacing.sm,
    borderRadius: theme.borderRadius.xl,
    alignSelf: 'flex-start',
  },
  statusText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.xs,
    fontWeight: theme.fontWeight.semibold,
  },
  sickLeaveDescription: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
    marginBottom: theme.spacing.sm,
    lineHeight: 20,
  },
  sickLeaveDate: {
    fontSize: theme.fontSize.xs,
    color: theme.colors.textMuted,
  },
});

