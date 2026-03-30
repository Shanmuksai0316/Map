import React from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { colors } from '../../../shared/theme/colors';
import { format, differenceInHours, differenceInMinutes } from 'date-fns';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface Props {
  navigation: any;
  route: any;
}

export const WardenRequestDetailScreen: React.FC<Props> = ({ navigation, route }) => {
  const { request } = route.params;

  const getTypeIcon = (type: string): string => {
    const icons: Record<string, string> = {
      housekeeping: 'home',
      repair_maintenance: 'construct',
      leave: 'calendar',
      outpass: 'exit',
      guest_entry: 'people',
    };
    return icons[type] || 'document-text';
  };

  const getTypeColor = (type: string): string => {
    const typeColors: Record<string, string> = {
      housekeeping: colors.info,
      repair_maintenance: colors.warning,
      leave: colors.primary,
      outpass: colors.success,
      guest_entry: colors.accent,
    };
    return typeColors[type] || colors.textSecondary;
  };

  const getStatusColor = (status: string): string => {
    switch (status.toLowerCase()) {
      case 'pending':
        return colors.warning;
      case 'approved':
      case 'completed':
        return colors.success;
      case 'rejected':
      case 'cancelled':
        return colors.error;
      case 'in_progress':
        return colors.info;
      default:
        return colors.textSecondary;
    }
  };

  const formatDateTime = (dateString: string) => {
    try {
      return format(new Date(dateString), 'EEEE, MMMM dd, yyyy • hh:mm a');
    } catch {
      return dateString;
    }
  };

  const getElapsedTime = (dateString: string) => {
    try {
      const date = new Date(dateString);
      const now = new Date();
      const hours = differenceInHours(now, date);
      const minutes = differenceInMinutes(now, date) % 60;

      if (hours >= 24) {
        const days = Math.floor(hours / 24);
        return `${days}d ${hours % 24}h`;
      }
      return `${hours}h ${minutes}m`;
    } catch {
      return 'N/A';
    }
  };

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Request Details" />

      <ScrollView style={styles.content} showsVerticalScrollIndicator={false}>
        {/* Request Card */}
        <View style={styles.requestCard}>
          <View style={styles.cardHeader}>
            <View style={[styles.categoryBadge, { backgroundColor: getTypeColor(request.type) + '20' }]}>
              <Ionicons
                name={getTypeIcon(request.type) as any}
                size={16}
                color={getTypeColor(request.type)}
              />
              <Text style={[styles.categoryText, { color: getTypeColor(request.type) }]}>
                {request.type.replace('_', ' ').toUpperCase()}
              </Text>
            </View>
            <View style={[styles.statusBadge, { backgroundColor: getStatusColor(request.status) + '20' }]}>
              <Text style={[styles.statusText, { color: getStatusColor(request.status) }]}>
                {request.status.toUpperCase()}
              </Text>
            </View>
          </View>
          <Text style={styles.requestTitle}>{request.title}</Text>
        </View>

        {/* Student Card */}
        <View style={styles.sectionCard}>
          <View style={styles.sectionHeader}>
            <Ionicons name="person" size={20} color={colors.primary} />
            <Text style={styles.sectionTitle}>Student Details</Text>
          </View>
          <View style={styles.detailRow}>
            <Text style={styles.detailLabel}>Name</Text>
            <Text style={styles.detailValue}>{request.student_name || 'N/A'}</Text>
          </View>
          <View style={styles.divider} />
          <View style={styles.detailRow}>
            <Text style={styles.detailLabel}>Room Number</Text>
            <Text style={styles.detailValue}>{request.room_number || 'N/A'}</Text>
          </View>
        </View>

        {/* Submitted Card */}
        <View style={styles.sectionCard}>
          <View style={styles.sectionHeader}>
            <Ionicons name="calendar" size={20} color={colors.primary} />
            <Text style={styles.sectionTitle}>Submitted</Text>
          </View>
          <View style={styles.detailRow}>
            <Text style={styles.detailLabel}>Date & Time</Text>
            <Text style={styles.detailValue}>{formatDateTime(request.created_at)}</Text>
          </View>
        </View>

        {/* Time Elapsed Card */}
        <View style={styles.sectionCard}>
          <View style={styles.sectionHeader}>
            <Ionicons name="time" size={20} color={colors.warning} />
            <Text style={styles.sectionTitle}>Time Elapsed</Text>
          </View>
          <Text style={styles.elapsedTime}>{getElapsedTime(request.created_at)}</Text>
        </View>

        {/* Request Description Card */}
        <View style={styles.sectionCard}>
          <View style={styles.sectionHeader}>
            <Ionicons name="document-text" size={20} color={colors.primary} />
            <Text style={styles.sectionTitle}>Request Description</Text>
          </View>
          <Text style={styles.descriptionText}>
            {request.description || 'No description provided.'}
          </Text>
        </View>

        <View style={styles.bottomPadding} />
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  content: {
    flex: 1,
    padding: 16,
  },
  requestCard: {
    backgroundColor: colors.surface,
    borderRadius: 12,
    padding: 16,
    marginBottom: 16,
    borderWidth: 1,
    borderColor: colors.border,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  categoryBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 20,
  },
  categoryText: {
    fontSize: 12,
    fontWeight: '600',
  },
  statusBadge: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 12,
  },
  statusText: {
    fontSize: 11,
    fontWeight: '700',
  },
  requestTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: colors.textHeading,
  },
  sectionCard: {
    backgroundColor: colors.surface,
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: colors.border,
  },
  sectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 12,
  },
  sectionTitle: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textSecondary,
  },
  detailRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 8,
  },
  detailLabel: {
    fontSize: 14,
    color: colors.textSecondary,
  },
  detailValue: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.text,
    textAlign: 'right',
    flex: 1,
    marginLeft: 16,
  },
  divider: {
    height: 1,
    backgroundColor: colors.divider,
  },
  elapsedTime: {
    fontSize: 28,
    fontWeight: '700',
    color: colors.warning,
  },
  descriptionText: {
    fontSize: 15,
    color: colors.text,
    lineHeight: 22,
  },
  bottomPadding: {
    height: 40,
  },
});

export default WardenRequestDetailScreen;
