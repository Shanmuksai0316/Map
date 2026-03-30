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
import { RoomChange } from '../../types';
import { APP_CONFIG } from '../../config/app.config';
import { format } from 'date-fns';
import { theme } from '../../theme/theme';
import { errorHandler } from '../../utils/errorHandler';
import { ErrorState, LoadingState } from '../../components';

export const RoomChangePreviewScreen = ({ navigation }: any) => {
  const { user } = useAuthStore();
  const [roomChanges, setRoomChanges] = useState<RoomChange[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchRoomChanges();
  }, []);

  const fetchRoomChanges = async () => {
    try {
      setError(null);
      setLoading(true);
      const response = await apiService.get<{ data: RoomChange[] }>(
        APP_CONFIG.ENDPOINTS.ROOM_CHANGES
      );
      setRoomChanges(response.data || []);
    } catch (err) {
      const errorDetails = errorHandler.handleError(err);
      setError(errorDetails.message);
      setRoomChanges([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  const onRefresh = () => {
    setRefreshing(true);
    fetchRoomChanges();
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
          Room Change ({roomChanges.length} requests)
        </Text>
        <TouchableOpacity
          style={styles.createButton}
          onPress={() => navigation.navigate('RoomChangeForm')}>
          <Ionicons name="add" size={18} color={theme.colors.primary} />
          <Text style={styles.createButtonText}>New</Text>
        </TouchableOpacity>
      </View>

      {/* Room Changes List */}
      <ScrollView
        style={styles.content}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>
        {loading ? (
          <LoadingState message="Loading room change requests..." />
        ) : error ? (
          <ErrorState error={error} onRetry={fetchRoomChanges} />
        ) : roomChanges.length === 0 ? (
          <View style={styles.emptyState}>
            <Ionicons
              name="swap-horizontal-outline"
              size={64}
              color={theme.colors.textSecondary}
              style={styles.emptyIcon}
            />
            <Text style={styles.emptyTitle}>No Room Change Requests</Text>
            <Text style={styles.emptySubtitle}>
              You haven't submitted any room change requests yet
            </Text>
            <TouchableOpacity
              style={styles.emptyButton}
              onPress={() => navigation.navigate('RoomChangeForm')}>
              <Text style={styles.emptyButtonText}>Create First Room Change</Text>
            </TouchableOpacity>
          </View>
        ) : (
          roomChanges.map((roomChange) => (
            <TouchableOpacity
              key={roomChange.id}
              style={styles.roomChangeCard}
              onPress={() => navigation.navigate('RoomChangeDetail', { roomChangeId: roomChange.id })}>
              {/* Title "Room Change Request" */}
              <Text style={styles.roomChangeTitle}>{roomChange.title}</Text>

              <View style={styles.roomChangeRow}>
                {/* Unique ID */}
                <View style={styles.roomChangeInfo}>
                  <Text style={styles.roomChangeLabel}>Unique ID:</Text>
                  <Text style={styles.roomChangeValue}>{roomChange.unique_id}</Text>
                </View>

                {/* Status on right side (with reason if rejected) */}
                <View
                  style={[
                    styles.statusBadge,
                    { backgroundColor: getStatusColor(roomChange.status) },
                  ]}>
                  <Text style={styles.statusText}>
                    {roomChange.status.toUpperCase()}
                    {roomChange.rejection_reason && ' (with reason)'}
                  </Text>
                </View>
              </View>

              {/* Description */}
              <Text style={styles.roomChangeDescription} numberOfLines={2}>
                {roomChange.description}
              </Text>

              {/* Date on submitted */}
              <Text style={styles.roomChangeDate}>
                Submitted: {format(new Date(roomChange.submitted_date), 'MMM dd, yyyy')}
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
  roomChangeCard: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.borderRadius.lg,
    padding: theme.spacing.md,
    marginBottom: theme.spacing.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    ...theme.shadows.small,
  },
  roomChangeTitle: {
    fontSize: theme.fontSize.lg,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
    marginBottom: theme.spacing.sm,
  },
  roomChangeRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: theme.spacing.sm,
  },
  roomChangeInfo: {
    flex: 1,
  },
  roomChangeLabel: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textMuted,
    marginBottom: theme.spacing.xs,
  },
  roomChangeValue: {
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
  roomChangeDescription: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
    marginBottom: theme.spacing.sm,
    lineHeight: 20,
  },
  roomChangeDate: {
    fontSize: theme.fontSize.xs,
    color: theme.colors.textMuted,
  },
});

