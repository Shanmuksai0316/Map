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
import { RoomChange } from '../../types';
import { APP_CONFIG } from '../../shared/config/app.config';
import { format } from 'date-fns';
import { theme } from '../../shared/theme/theme';
import { errorHandler } from '../../shared/utils/errorHandler';
import { ErrorState, LoadingState } from '../../shared/components';

export const RoomChangeDetailScreen = ({ navigation, route }: any) => {
  const insets = useSafeAreaInsets();
  const { roomChangeId } = route.params;
  const [roomChange, setRoomChange] = useState<RoomChange | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchRoomChangeDetail();
  }, [roomChangeId]);

  const fetchRoomChangeDetail = async () => {
    try {
      setError(null);
      setLoading(true);
      const response = await apiService.get<{ data: RoomChange }>(
        `${APP_CONFIG.ENDPOINTS.ROOM_CHANGES}/${roomChangeId}`
      );
      setRoomChange(response.data || null);
    } catch (err) {
      const errorDetails = errorHandler.handleError(err);
      setError(errorDetails.message);
      setRoomChange(null);
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

  const getSharingLabel = (preference?: string) => {
    if (!preference) return 'Not specified';
    const labels: Record<string, string> = {
      single: 'Single Occupancy',
      double: 'Double Occupancy',
      triple: 'Triple Occupancy',
      quad: 'Quad Occupancy',
    };
    return labels[preference] || preference;
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
          <Text style={styles.headerTitle}>Room Change Details</Text>
          <View style={styles.headerSpacer} />
        </View>
      </View>

      <ScrollView style={styles.content}>
        {loading ? (
          <LoadingState message="Loading room change details..." />
        ) : error ? (
          <ErrorState error={error} onRetry={fetchRoomChangeDetail} />
        ) : !roomChange ? (
          <ErrorState 
            title="Room Change Not Found" 
            message="The requested room change could not be found." 
            onRetry={fetchRoomChangeDetail} 
          />
        ) : (
          <>
        {/* Title "Room Change Request" - Status on right side */}
        <View style={styles.statusRow}>
          <Text style={styles.label}>Room Change Request</Text>
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

        {/* Unique ID */}
        <View style={styles.section}>
          <Text style={styles.label}>Unique ID</Text>
          <Text style={styles.value}>{roomChange.unique_id}</Text>
        </View>

        {/* Description */}
        <View style={styles.section}>
          <Text style={styles.label}>Description</Text>
          <Text style={styles.descriptionText}>{roomChange.description}</Text>
        </View>

        {/* Preferred Room Number */}
        {roomChange.preferred_room_number && (
          <View style={styles.section}>
            <Text style={styles.label}>Preferred Room Number</Text>
            <Text style={styles.value}>{roomChange.preferred_room_number}</Text>
          </View>
        )}

        {/* Preferred Floor */}
        {roomChange.preferred_floor && (
          <View style={styles.section}>
            <Text style={styles.label}>Preferred Floor</Text>
            <Text style={styles.value}>{roomChange.preferred_floor}</Text>
          </View>
        )}

        {/* Sharing Preference */}
        {roomChange.sharing_preference && (
          <View style={styles.section}>
            <Text style={styles.label}>Sharing Preference</Text>
            <Text style={styles.value}>{getSharingLabel(roomChange.sharing_preference)}</Text>
          </View>
        )}

        {/* Date Required */}
        {roomChange.date_required && (
          <View style={styles.section}>
            <Text style={styles.label}>Date Required</Text>
            <Text style={styles.value}>
              {format(new Date(roomChange.date_required), 'MMM dd, yyyy')}
            </Text>
          </View>
        )}

        {/* Submitted Date */}
        <View style={styles.section}>
          <Text style={styles.label}>Submitted Date</Text>
          <Text style={styles.value}>
            {format(new Date(roomChange.submitted_date), 'MMM dd, yyyy HH:mm')}
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
