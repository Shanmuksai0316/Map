import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
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

export const GuestEntryDetailScreen = ({ navigation, route }: any) => {
  const { user } = useAuthStore();
  const { guestEntryId } = route.params;
  const [guestEntry, setGuestEntry] = useState<GuestEntry | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchGuestEntryDetail();
  }, [guestEntryId]);

  const fetchGuestEntryDetail = async () => {
    try {
      setError(null);
      setLoading(true);
      const response = await apiService.get<{ data: GuestEntry }>(
        `${APP_CONFIG.ENDPOINTS.GUEST_ENTRIES}/${guestEntryId}`
      );
      setGuestEntry(response.data || null);
    } catch (err) {
      const errorDetails = errorHandler.handleError(err);
      setError(errorDetails.message);
      setGuestEntry(null);
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

  const getIdTypeLabel = (idType: string) => {
    const labels: Record<string, string> = {
      aadhar_card: 'Aadhar Card',
      driving_license: 'Driving License',
      passport: 'Passport',
      voter_id: 'Voter ID',
    };
    return labels[idType] || idType;
  };

  return (
    <View style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity
          style={styles.closeButton}
          onPress={() => navigation.goBack()}>
          <Ionicons name="close" size={24} color={theme.colors.white} />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Guest Entry Details</Text>
        <View style={{ width: 24 }} />
      </View>

      <ScrollView style={styles.content}>
        {loading ? (
          <LoadingState message="Loading guest entry details..." />
        ) : error ? (
          <ErrorState error={error} onRetry={fetchGuestEntryDetail} />
        ) : !guestEntry ? (
          <ErrorState 
            title="Guest Entry Not Found" 
            message="The requested guest entry could not be found." 
            onRetry={fetchGuestEntryDetail} 
          />
        ) : (
          <>
        {/* Title "Guest Entry" - Status on right side */}
        <View style={styles.statusRow}>
          <Text style={styles.label}>Guest Entry</Text>
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

        {/* Unique ID */}
        <View style={styles.section}>
          <Text style={styles.label}>Unique ID</Text>
          <Text style={styles.value}>{guestEntry.unique_id}</Text>
        </View>

        {/* Description */}
        <View style={styles.section}>
          <Text style={styles.label}>Description</Text>
          <Text style={styles.descriptionText}>{guestEntry.description}</Text>
        </View>

        {/* Guests List */}
        <View style={styles.section}>
          <Text style={styles.label}>Guests ({guestEntry.guests.length})</Text>
          {guestEntry.guests.map((guest, index) => (
            <View key={index} style={styles.guestItem}>
              <Text style={styles.guestName}>{guest.name}</Text>
              <Text style={styles.guestDetail}>Relationship: {guest.relationship}</Text>
              <Text style={styles.guestDetail}>ID Type: {getIdTypeLabel(guest.id_type)}</Text>
              <Text style={styles.guestDetail}>ID Number: {guest.id_number}</Text>
              {guest.phone && (
                <Text style={styles.guestDetail}>Phone: {guest.phone}</Text>
              )}
            </View>
          ))}
        </View>

        {/* Visit Date */}
        <View style={styles.section}>
          <Text style={styles.label}>Visit Date</Text>
          <Text style={styles.value}>
            {format(new Date(guestEntry.visit_date), 'MMM dd, yyyy')}
          </Text>
        </View>

        {/* Check-in and Check-out Time */}
        <View style={styles.section}>
          <Text style={styles.label}>Check-in / Check-out Time</Text>
          <Text style={styles.value}>
            {guestEntry.check_in_time} - {guestEntry.check_out_time}
          </Text>
        </View>

        {/* Purpose to Visit */}
        <View style={styles.section}>
          <Text style={styles.label}>Purpose to Visit</Text>
          <Text style={styles.value}>{guestEntry.purpose_to_visit}</Text>
        </View>

        {/* Submitted Date */}
        <View style={styles.section}>
          <Text style={styles.label}>Submitted Date</Text>
          <Text style={styles.value}>
            {format(new Date(guestEntry.submitted_date), 'MMM dd, yyyy HH:mm')}
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
  closeButton: {
    padding: theme.spacing.xs,
  },
  headerTitle: {
    color: theme.colors.white,
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
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
  guestItem: {
    backgroundColor: theme.colors.surface,
    padding: theme.spacing.md,
    borderRadius: theme.borderRadius.md,
    marginBottom: theme.spacing.sm,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  guestName: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
    marginBottom: theme.spacing.xs,
  },
  guestDetail: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
    marginBottom: theme.spacing.xs,
  },
});

