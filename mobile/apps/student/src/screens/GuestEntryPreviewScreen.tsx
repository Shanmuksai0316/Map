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
import { GuestEntry } from '../../types';
import { APP_CONFIG } from '../../config/app.config';
import { format } from 'date-fns';
import { theme } from '../../theme/theme';
import { errorHandler } from '../../utils/errorHandler';
import { ErrorState, LoadingState } from '../../components';

export const GuestEntryPreviewScreen = ({ navigation }: any) => {
  const { user } = useAuthStore();
  const [guestEntries, setGuestEntries] = useState<GuestEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchGuestEntries();
  }, []);

  const fetchGuestEntries = async () => {
    try {
      setError(null);
      setLoading(true);
      const response = await apiService.get<{ data: GuestEntry[] }>(
        APP_CONFIG.ENDPOINTS.GUEST_ENTRIES
      );
      setGuestEntries(response.data || []);
    } catch (err) {
      const errorDetails = errorHandler.handleError(err);
      setError(errorDetails.message);
      setGuestEntries([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  const onRefresh = () => {
    setRefreshing(true);
    fetchGuestEntries();
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

  // Match NotificationsScreen header height and spacing
  const HEADER_ROW_HEIGHT = 52;
  const HEADER_PADDING_TOP = 10;
  const HEADER_PADDING_BOTTOM = 6;

  return (
    <View style={styles.container}>
      {/* Header - same height/spacing as Notifications screen, no "Back" text */}
      <View style={[styles.header, { paddingTop: HEADER_PADDING_TOP, paddingBottom: HEADER_PADDING_BOTTOM, minHeight: HEADER_PADDING_TOP + HEADER_ROW_HEIGHT + HEADER_PADDING_BOTTOM }]}>
        <View style={[styles.headerRow, { height: HEADER_ROW_HEIGHT }]}>
          <TouchableOpacity
            style={styles.backButton}
            onPress={() => navigation.goBack()}
            accessibilityLabel="Go back">
            <Ionicons name="arrow-back" size={24} color={theme.colors.white} />
          </TouchableOpacity>
          <Text style={styles.headerTitle}>
            Guest Entry ({guestEntries.length} requests)
          </Text>
          <TouchableOpacity
            style={styles.createButton}
            onPress={() => navigation.navigate('GuestEntryForm')}>
            <Ionicons name="add" size={18} color={theme.colors.primary} />
            <Text style={styles.createButtonText}>New</Text>
          </TouchableOpacity>
        </View>
      </View>

      {/* Guest Entries List */}
      <ScrollView
        style={styles.content}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>
        {loading ? (
          <LoadingState message="Loading guest entry requests..." />
        ) : error ? (
          <ErrorState error={error} onRetry={fetchGuestEntries} />
        ) : guestEntries.length === 0 ? (
          <View style={styles.emptyState}>
            <Ionicons
              name="people-outline"
              size={64}
              color={theme.colors.textSecondary}
              style={styles.emptyIcon}
            />
            <Text style={styles.emptyTitle}>No Guest Entry Requests</Text>
            <Text style={styles.emptySubtitle}>
              You haven't submitted any guest entry requests yet
            </Text>
            <TouchableOpacity
              style={styles.emptyButton}
              onPress={() => navigation.navigate('GuestEntryForm')}>
              <Text style={styles.emptyButtonText}>Create First Guest Entry</Text>
            </TouchableOpacity>
          </View>
        ) : (
          guestEntries.map((guestEntry) => (
            <TouchableOpacity
              key={guestEntry.id}
              style={styles.guestEntryCard}
              onPress={() => navigation.navigate('GuestEntryDetail', { guestEntryId: guestEntry.id })}>
              {/* Title "Parents Visit" */}
              <Text style={styles.guestEntryTitle}>{guestEntry.title}</Text>

              <View style={styles.guestEntryRow}>
                {/* Unique ID */}
                <View style={styles.guestEntryInfo}>
                  <Text style={styles.guestEntryLabel}>Unique ID:</Text>
                  <Text style={styles.guestEntryValue}>{guestEntry.unique_id}</Text>
                </View>

                {/* Status on right side (with reason if rejected) */}
                <View
                  style={[
                    styles.statusBadge,
                    { backgroundColor: getStatusColor(guestEntry.status) },
                  ]}>
                  <Text style={styles.statusText}>
                    {guestEntry.status.toUpperCase()}
                    {guestEntry.rejection_reason && ' (with reason)'}
                  </Text>
                </View>
              </View>

              {/* Description */}
              <Text style={styles.guestEntryDescription} numberOfLines={2}>
                {guestEntry.description}
              </Text>

              {/* Date on submitted */}
              <Text style={styles.guestEntryDate}>
                Submitted: {format(new Date(guestEntry.submitted_date), 'MMM dd, yyyy')}
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
    paddingHorizontal: 20,
    flexDirection: 'row',
    alignItems: 'flex-end',
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    width: '100%',
  },
  backButton: {
    padding: 8,
    marginLeft: -8,
  },
  headerTitle: {
    color: theme.colors.white,
    fontSize: 20,
    fontWeight: 'bold',
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
  guestEntryCard: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.borderRadius.lg,
    padding: theme.spacing.md,
    marginBottom: theme.spacing.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    ...theme.shadows.small,
  },
  guestEntryTitle: {
    fontSize: theme.fontSize.lg,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
    marginBottom: theme.spacing.sm,
  },
  guestEntryRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: theme.spacing.sm,
  },
  guestEntryInfo: {
    flex: 1,
  },
  guestEntryLabel: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textMuted,
    marginBottom: theme.spacing.xs,
  },
  guestEntryValue: {
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
  guestEntryDescription: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
    marginBottom: theme.spacing.sm,
    lineHeight: 20,
  },
  guestEntryDate: {
    fontSize: theme.fontSize.xs,
    color: theme.colors.textMuted,
  },
});

