import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  ActivityIndicator,
} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { apiService } from '../../../shared/services/api.service';
import { colors } from '../../../shared/theme/colors';
import { format } from 'date-fns';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface Request {
  id: number;
  type?: 'housekeeping' | 'repair_maintenance' | 'leave' | 'outpass' | 'guest_entry';
  category?: string; // Backend returns 'category' field
  title: string;
  description?: string;
  status: string;
  reporter_name?: string; // Backend returns 'reporter_name'
  student_name?: string;
  hostel_name?: string;
  room_number?: string;
  created_at: string;
}

type FilterType = 'all' | 'housekeeping' | 'repair_maintenance' | 'leave' | 'outpass' | 'guest_entry';

const FILTERS: { key: FilterType; label: string }[] = [
  { key: 'all', label: 'All' },
  { key: 'housekeeping', label: 'Housekeeping' },
  { key: 'repair_maintenance', label: 'Repair & Maintenance' },
  { key: 'leave', label: 'Leave' },
  { key: 'outpass', label: 'Out Pass' },
  { key: 'guest_entry', label: 'Guest Entry' },
];

interface Props {
  navigation: any;
}

export const WardenRequestsScreen: React.FC<Props> = ({ navigation }) => {
  const [requests, setRequests] = useState<Request[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [filter, setFilter] = useState<FilterType>('all');

  const fetchRequests = useCallback(async () => {
    try {
      const response = await apiService.get<any>('/mobile/warden/requests');
      const requestsData = response?.data || response || [];
      // Map backend response to frontend format
      const mappedRequests = requestsData.map((req: any) => ({
        ...req,
        type: req.category || req.type, // Use category if type is not present
        student_name: req.reporter_name || req.student_name, // Map reporter_name to student_name
      }));
      setRequests(mappedRequests);
    } catch (error) {
      console.error('Failed to fetch requests:', error);
      setRequests([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    fetchRequests();
  }, [fetchRequests]);

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    fetchRequests();
  }, [fetchRequests]);

  const filteredRequests = filter === 'all'
    ? requests
    : requests.filter(r => (r.type || r.category) === filter);

  const getTypeIcon = (type: string): string => {
    if (!type) return 'document-text';
    const icons: Record<string, string> = {
      housekeeping: 'home',
      repair_maintenance: 'construct',
      maintenance: 'construct', // Backend might return 'maintenance'
      leave: 'calendar',
      outpass: 'exit',
      guest_entry: 'people',
    };
    return icons[type] || 'document-text';
  };

  const getTypeColor = (type: string): string => {
    if (!type) return colors.textSecondary;
    const typeColors: Record<string, string> = {
      housekeeping: colors.info,
      repair_maintenance: colors.warning,
      maintenance: colors.warning, // Backend might return 'maintenance'
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

  const formatDate = (dateString: string) => {
    try {
      return format(new Date(dateString), 'MMM dd, yyyy • hh:mm a');
    } catch {
      return dateString;
    }
  };

  const handleRequestPress = (request: Request) => {
    navigation.navigate('WardenRequestDetail', { request });
  };

  return (
    <View style={styles.container}>
      <StaffScreenHeader
        title="Requests"
        variant="minimal"
        onBack={() => navigation.goBack()}
        showBell={false}
      />

      {/* Filter Pills */}
      <View style={styles.filterContainer}>
        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.filterScroll}
        >
          {FILTERS.map((f) => (
            <TouchableOpacity
              key={f.key}
              style={[
                styles.filterPill,
                filter === f.key && styles.filterPillActive,
              ]}
              onPress={() => setFilter(f.key)}
            >
              <Text
                style={[
                  styles.filterPillText,
                  filter === f.key && styles.filterPillTextActive,
                ]}
              >
                {f.label}
              </Text>
            </TouchableOpacity>
          ))}
        </ScrollView>
      </View>

      {/* Content */}
      {loading ? (
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color={colors.primary} />
        </View>
      ) : (
        <ScrollView
          style={styles.content}
          showsVerticalScrollIndicator={false}
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={onRefresh}
              tintColor={colors.primary}
            />
          }
        >
          {filteredRequests.length === 0 ? (
            <View style={styles.emptyState}>
              <Ionicons name="document-text-outline" size={64} color={colors.textMuted} />
              <Text style={styles.emptyTitle}>No Requests</Text>
              <Text style={styles.emptySubtitle}>
                {filter === 'all'
                  ? 'No requests found'
                  : `No ${filter.replace('_', ' ')} requests`}
              </Text>
            </View>
          ) : (
            filteredRequests.map((request) => (
              <TouchableOpacity
                key={request.id}
                style={styles.requestCard}
                onPress={() => handleRequestPress(request)}
                activeOpacity={0.7}
              >
                <Text style={styles.requestIdLabel}>Request #{request.id}</Text>
                {/* Category & Status */}
                <View style={styles.cardHeader}>
                  <View style={styles.categoryBadge}>
                    <Ionicons
                      name={getTypeIcon(request.type || request.category || '') as any}
                      size={14}
                      color={getTypeColor(request.type || request.category || '')}
                    />
                    <Text style={[styles.categoryText, { color: getTypeColor(request.type || request.category || '') }]}>
                      {(request.type || request.category || '').replace('_', ' ').toUpperCase()}
                    </Text>
                  </View>
                  <View style={[styles.statusBadge, { backgroundColor: getStatusColor(request.status) + '20' }]}>
                    <Text style={[styles.statusText, { color: getStatusColor(request.status) }]}>
                      {request.status.toUpperCase()}
                    </Text>
                  </View>
                </View>

                {/* Title */}
                <Text style={styles.requestTitle} numberOfLines={1}>
                  {request.title}
                </Text>

                {/* Description */}
                {request.description && (
                  <Text style={styles.requestDescription} numberOfLines={2}>
                    {request.description}
                  </Text>
                )}

                {/* Student Info & Date */}
                <View style={styles.cardFooter}>
                  <View style={styles.studentInfo}>
                    <Ionicons name="person-outline" size={14} color={colors.textMuted} />
                    <Text style={styles.footerText}>
                      {request.student_name || request.reporter_name || 'Unknown'}
                      {request.hostel_name && ` • ${request.hostel_name}`}
                    </Text>
                  </View>
                  <View style={styles.dateInfo}>
                    <Ionicons name="time-outline" size={14} color={colors.textMuted} />
                    <Text style={styles.footerText}>{formatDate(request.created_at)}</Text>
                  </View>
                </View>
              </TouchableOpacity>
            ))
          )}

          <View style={styles.bottomPadding} />
        </ScrollView>
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  filterContainer: {
    backgroundColor: colors.surface,
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  filterScroll: {
    paddingHorizontal: 16,
    gap: 8,
  },
  filterPill: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: colors.surfaceMuted,
    marginRight: 8,
  },
  filterPillActive: {
    backgroundColor: colors.primary,
  },
  filterPillText: {
    fontSize: 14,
    fontWeight: '500',
    color: colors.textSecondary,
  },
  filterPillTextActive: {
    color: colors.white,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  content: {
    flex: 1,
    padding: 16,
  },
  emptyState: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: 80,
  },
  emptyTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: colors.textSecondary,
    marginTop: 16,
  },
  emptySubtitle: {
    fontSize: 14,
    color: colors.textMuted,
    marginTop: 4,
  },
  requestIdLabel: {
    fontSize: 12,
    fontWeight: '600',
    color: colors.textSecondary,
    marginBottom: 8,
  },
  requestCard: {
    backgroundColor: colors.surface,
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: colors.border,
    shadowColor: colors.black,
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05,
    shadowRadius: 2,
    elevation: 1,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  categoryBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
  },
  categoryText: {
    fontSize: 11,
    fontWeight: '600',
  },
  statusBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
  },
  statusText: {
    fontSize: 10,
    fontWeight: '700',
  },
  requestTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.textHeading,
    marginBottom: 4,
  },
  requestDescription: {
    fontSize: 14,
    color: colors.textSecondary,
    lineHeight: 20,
    marginBottom: 12,
  },
  cardFooter: {
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: colors.divider,
  },
  studentInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginBottom: 4,
  },
  dateInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  footerText: {
    fontSize: 12,
    color: colors.textMuted,
  },
  bottomPadding: {
    height: 40,
  },
});

export default WardenRequestsScreen;
