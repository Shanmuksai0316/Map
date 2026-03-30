import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  Image,
  Dimensions,
} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useAuthStore } from '../../store/auth.store';
import { apiService } from '../../services/api.service';
import { Ticket } from '../../types';
import { APP_CONFIG } from '../../config/app.config';
import { format } from 'date-fns';
import { theme } from '../../theme/theme';
import { errorHandler } from '../../utils/errorHandler';
import { ErrorState, LoadingState } from '../../components';

const { width } = Dimensions.get('window');

export const TicketDetailScreen = ({ navigation, route }: any) => {
  const { user } = useAuthStore();
  const { ticketId } = route.params;
  const [ticket, setTicket] = useState<Ticket | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchTicketDetail();
  }, [ticketId]);

  const fetchTicketDetail = async () => {
    try {
      setError(null);
      setLoading(true);
      const response = await apiService.get<{ data: Ticket }>(
        `${APP_CONFIG.ENDPOINTS.TICKETS}/${ticketId}`
      );
      setTicket(response.data || null);
    } catch (err) {
      const errorDetails = errorHandler.handleError(err);
      setError(errorDetails.message);
      setTicket(null);
    } finally {
      setLoading(false);
    }
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'done':
        return theme.colors.success;
      case 'pending':
        return theme.colors.warning;
      case 'in_progress':
        return theme.colors.info;
      case 'resolved':
      case 'closed':
        return theme.colors.textMuted;
      default:
        return theme.colors.textMuted;
    }
  };

  const getStatusLabel = (status: string) => {
    const labels: Record<string, string> = {
      pending: 'Pending',
      done: 'Done',
      in_progress: 'In Progress',
      resolved: 'Resolved',
      closed: 'Closed',
    };
    return labels[status] || status.toUpperCase();
  };

  const getRequestTypeLabel = (type?: string) => {
    if (!type) return 'N/A';
    const labels: Record<string, string> = {
      repair_maintenance: 'Repair & Maintenance',
      housekeeping: 'House Keeping',
    };
    return labels[type] || type;
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
        <Text style={styles.headerTitle}>Ticket Details</Text>
        <View style={{ width: 24 }} />
      </View>

      <ScrollView style={styles.content}>
        {loading ? (
          <LoadingState message="Loading ticket details..." />
        ) : error ? (
          <ErrorState error={error} onRetry={fetchTicketDetail} />
        ) : !ticket ? (
          <ErrorState 
            title="Ticket Not Found" 
            message="The requested ticket could not be found." 
            onRetry={fetchTicketDetail} 
          />
        ) : (
          <>
        {/* Title and Status (right side) */}
        <View style={styles.statusRow}>
          <Text style={styles.titleText}>{ticket.title}</Text>
          <View
            style={[
              styles.statusBadge,
              { backgroundColor: getStatusColor(ticket.status) },
            ]}>
            <Text style={styles.statusText}>
              {getStatusLabel(ticket.status)}
            </Text>
          </View>
        </View>

        {/* Student Name and Room */}
        <View style={styles.section}>
          <View style={styles.studentInfo}>
            <Text style={styles.studentName}>{ticket.student_name || user?.name}</Text>
            {ticket.student_room && (
              <Text style={styles.roomText}>Room: {ticket.student_room}</Text>
            )}
          </View>
        </View>

        {/* Request Type */}
        {ticket.request_type && (
          <View style={styles.section}>
            <Text style={styles.label}>Request Type</Text>
            <Text style={styles.value}>{getRequestTypeLabel(ticket.request_type)}</Text>
          </View>
        )}

        {/* Department */}
        {ticket.department && (
          <View style={styles.section}>
            <Text style={styles.label}>Department</Text>
            <Text style={styles.value}>{ticket.department}</Text>
          </View>
        )}

        {/* Description */}
        <View style={styles.section}>
          <Text style={styles.label}>Description</Text>
          <Text style={styles.descriptionText}>{ticket.description}</Text>
        </View>

        {/* Photos */}
        {ticket.photos && ticket.photos.length > 0 && (
          <View style={styles.section}>
            <Text style={styles.label}>Attachments ({ticket.photos.length})</Text>
            <ScrollView horizontal showsHorizontalScrollIndicator={false}>
              {ticket.photos.map((photo, index) => (
                <Image
                  key={index}
                  source={{ uri: photo }}
                  style={styles.photo}
                  resizeMode="cover"
                />
              ))}
            </ScrollView>
          </View>
        )}

        {/* Ticket Raised Time */}
        <View style={styles.section}>
          <View style={styles.timeRow}>
            <Ionicons name="time-outline" size={16} color={theme.colors.textSecondary} />
            <Text style={styles.timeText}>
              Raised: {format(new Date(ticket.created_at), 'MMM dd, yyyy HH:mm')}
              {ticket.time_elapsed && ` (${ticket.time_elapsed} ago)`}
            </Text>
          </View>
        </View>

        {/* Unique ID if available */}
        {ticket.id && (
          <View style={styles.section}>
            <Text style={styles.label}>Ticket ID</Text>
            <Text style={styles.value}>#{ticket.id}</Text>
          </View>
        )}
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
    alignItems: 'flex-start',
    marginBottom: theme.spacing.lg,
    gap: theme.spacing.md,
  },
  titleText: {
    flex: 1,
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
  },
  statusBadge: {
    paddingHorizontal: theme.spacing.md,
    paddingVertical: theme.spacing.sm,
    borderRadius: theme.borderRadius.xl,
    alignSelf: 'flex-start',
  },
  statusText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.sm,
    fontWeight: theme.fontWeight.semibold,
  },
  section: {
    marginBottom: theme.spacing.lg,
  },
  studentInfo: {
    backgroundColor: theme.colors.surface,
    padding: theme.spacing.md,
    borderRadius: theme.borderRadius.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  studentName: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
    marginBottom: theme.spacing.xs,
  },
  roomText: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
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
  descriptionText: {
    fontSize: theme.fontSize.md,
    color: theme.colors.textSecondary,
    lineHeight: 22,
    backgroundColor: theme.colors.surface,
    padding: theme.spacing.md,
    borderRadius: theme.borderRadius.md,
  },
  photo: {
    width: width * 0.6,
    height: 200,
    borderRadius: theme.borderRadius.md,
    marginRight: theme.spacing.md,
    backgroundColor: theme.colors.surface,
  },
  timeRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.xs,
  },
  timeText: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
  },
});
