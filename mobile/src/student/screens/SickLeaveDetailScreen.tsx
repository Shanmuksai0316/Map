import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { apiService } from '../../shared/services/api.service';
import { SickLeave } from '../../types';
import { APP_CONFIG } from '../../shared/config/app.config';
import { format } from 'date-fns';
import { theme } from '../../shared/theme/theme';
import { errorHandler } from '../../shared/utils/errorHandler';
import { ErrorState, LoadingState } from '../../shared/components';

export const SickLeaveDetailScreen = ({ navigation, route }: any) => {
  const insets = useSafeAreaInsets();
  const { sickLeaveId } = route.params;
  const [sickLeave, setSickLeave] = useState<SickLeave | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchSickLeaveDetail();
  }, [sickLeaveId]);

  const fetchSickLeaveDetail = async () => {
    try {
      setError(null);
      setLoading(true);
      const response = await apiService.get<{ data: SickLeave }>(
        `${APP_CONFIG.ENDPOINTS.SICK_LEAVES}/${sickLeaveId}`
      );
      setSickLeave(response.data || null);
    } catch (err) {
      const errorDetails = errorHandler.handleError(err);
      setError(errorDetails.message);
      setSickLeave(null);
    } finally {
      setLoading(false);
    }
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
          <TouchableOpacity
            style={styles.backButton}
            onPress={() => navigation.goBack()}
            accessibilityLabel="Go back">
            <Ionicons name="arrow-back" size={24} color={theme.colors.primary} />
          </TouchableOpacity>
          <Text style={styles.headerTitle}>Sick Leave Details</Text>
          <View style={styles.headerSpacer} />
        </View>
      </View>

      <ScrollView style={styles.content}>
        {loading ? (
          <LoadingState message="Loading sick leave details..." />
        ) : error ? (
          <ErrorState error={error} onRetry={fetchSickLeaveDetail} />
        ) : !sickLeave ? (
          <ErrorState 
            title="Sick Leave Not Found" 
            message="The requested sick leave could not be found." 
            onRetry={fetchSickLeaveDetail} 
          />
        ) : (
          <>
        {/* Illness for sick leave - Status on right side */}
        <View style={styles.statusRow}>
          <Text style={styles.label}>Illness for Sick Leave</Text>
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
        <Text style={styles.value}>{sickLeave.illness}</Text>

        {/* Unique ID */}
        <View style={styles.section}>
          <Text style={styles.label}>Unique ID</Text>
          <Text style={styles.value}>{sickLeave.unique_id}</Text>
        </View>

        {/* Description */}
        <View style={styles.section}>
          <Text style={styles.label}>Description</Text>
          <Text style={styles.descriptionText}>{sickLeave.description}</Text>
        </View>

        {/* Submitted Date */}
        <View style={styles.section}>
          <Text style={styles.label}>Submitted Date</Text>
          <Text style={styles.value}>
            {format(new Date(sickLeave.submitted_date), 'MMM dd, yyyy HH:mm')}
          </Text>
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
    backgroundColor: theme.colors.white,
    paddingHorizontal: theme.spacing.lg,
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
  headerSpacer: {
    width: 32,
  },
  headerTitle: {
    color: theme.colors.primary,
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
    textAlign: 'center',
    flex: 1,
  },
  loadingText: {
    textAlign: 'center',
    marginTop: theme.spacing.xl,
    color: theme.colors.textSecondary,
  },
  content: {
    flex: 1,
    padding: theme.spacing.lg,
  },
  statusRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: theme.spacing.xs,
  },
  section: {
    marginBottom: theme.spacing.lg,
  },
  label: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textMuted,
    fontWeight: theme.fontWeight.medium,
    marginBottom: theme.spacing.xs,
  },
  value: {
    fontSize: theme.fontSize.md,
    color: theme.colors.text,
    fontWeight: theme.fontWeight.semibold,
  },
  statusBadge: {
    paddingHorizontal: theme.spacing.md,
    paddingVertical: theme.spacing.sm,
    borderRadius: theme.borderRadius.xl,
  },
  statusText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.sm,
    fontWeight: theme.fontWeight.semibold,
  },
  descriptionText: {
    fontSize: theme.fontSize.md,
    color: theme.colors.textSecondary,
    lineHeight: 22,
  },
});
