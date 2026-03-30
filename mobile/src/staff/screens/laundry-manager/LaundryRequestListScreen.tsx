/**
 * Laundry Requests List Screen (Active Requests)
 * Requirements:
 * - Header: "Laundry Requests" center aligned, back arrow left aligned
 * - Search bar: search by name, request ID, room number
 * - Status Filter: All Requests, In Progress, Ready for Pickup, Completed
 * - Laundry cards with: Student name, Room number, Status, Item count, Weight, Date, View Detail
 */

import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  ActivityIndicator,
  TextInput,
} from 'react-native';
import { apiService } from '../../../shared/services/api.service';
import { APP_CONFIG } from '../../../shared/config/app.config';
import { format } from 'date-fns';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { colors } from '../../../shared/theme/colors';
import { SLA_CONFIGS } from '../../../shared/utils/sla.util';
import { errorHandler } from '../../../shared/utils/errorHandler';
import { ErrorState } from '../../../shared/components/shared/ErrorState';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';
import { GradientButton } from '../../../shared/components/GradientButton';

interface LaundryRequest {
  id: number;
  status: {
    value: string;
    label: string;
    color: string;
    is_active?: boolean;
    is_in_progress?: boolean;
    is_completed?: boolean;
  } | string;
  service_type?: {
    value: string;
    label: string;
  };
  bag_count: number;
  weight_kg?: number;
  student?: {
    name: string;
    student_uid?: string;
    hostel_name?: string;
    room_no?: string;
  };
  student_name?: string;
  student_id?: number;
  hostel?: { name: string };
  requested_at?: string;
  estimated_completion_at?: string;
  special_instructions?: string;
  is_delayed?: boolean;
}

type FilterType = 'all' | 'in_progress' | 'ready' | 'completed';

export const LaundryRequestListScreen = ({ navigation }: any) => {
  const [requests, setRequests] = useState<LaundryRequest[]>([]);
  const [filteredRequests, setFilteredRequests] = useState<LaundryRequest[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<any>(null);
  const [filter, setFilter] = useState<FilterType>('all');
  const [searchQuery, setSearchQuery] = useState('');

  const filterOptions: { key: FilterType; label: string }[] = [
    { key: 'all', label: 'All Requests' },
    { key: 'in_progress', label: 'In Progress' },
    { key: 'ready', label: 'Ready for Pickup' },
    { key: 'completed', label: 'Completed' },
  ];

  const fetchRequests = useCallback(async () => {
    try {
      setError(null);
      let statusParam = '';
      
      // Map filter to backend status values
      switch (filter) {
        case 'in_progress':
          // Include all active statuses except ready and completed
          statusParam = '&status=pending,scheduled,collected,washing,drying';
          break;
        case 'ready':
          statusParam = '&status=ready';
          break;
        case 'completed':
          statusParam = '&status=completed,delivered';
          break;
        default:
          statusParam = '';
      }

      const response = await apiService.get<{ data: LaundryRequest[] }>(
        `${APP_CONFIG.ENDPOINTS.LAUNDRY_REQUESTS}?limit=100${statusParam}`
      );
      
      const requestList = response.data || [];
      setRequests(requestList);
      applySearchFilter(requestList, searchQuery);
    } catch (err) {
      console.error('Failed to fetch laundry requests:', err);
      const errorDetails = errorHandler.handleError(err);
      setError(errorDetails);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [filter]);

  const applySearchFilter = useCallback((requestList: LaundryRequest[], query: string) => {
    if (!query.trim()) {
      setFilteredRequests(requestList);
      return;
    }

    const lowerQuery = query.toLowerCase();
    const filtered = requestList.filter((request) => {
      const name = request.student?.name ?? request.student_name ?? '';
      const uid = request.student?.student_uid ?? (request.student_id != null ? String(request.student_id) : '');
      const room = request.student?.room_no ?? '';
      return (
        name.toLowerCase().includes(lowerQuery) ||
        uid.toLowerCase().includes(lowerQuery) ||
        room.toLowerCase().includes(lowerQuery) ||
        String(request.id).includes(lowerQuery)
      );
    });
    setFilteredRequests(filtered);
  }, []);

  useEffect(() => {
    fetchRequests();
  }, [fetchRequests]);

  useEffect(() => {
    applySearchFilter(requests, searchQuery);
  }, [searchQuery, requests, applySearchFilter]);

  const onRefresh = () => {
    setRefreshing(true);
    fetchRequests();
  };

  const getStatusColor = (status: string): string => {
    switch (status) {
      case 'pending':
        return colors.warning;
      case 'collected':
      case 'washing':
      case 'drying':
        return colors.info;
      case 'ready':
        return colors.accent;
      case 'delivered':
      case 'completed':
        return colors.success;
      case 'cancelled':
        return colors.error;
      default:
        return colors.textSecondary;
    }
  };

  type SLAPill = 'within' | 'near' | 'breached' | null;
  const getSLAPill = (request: LaundryRequest): SLAPill => {
    const statusValue = typeof request.status === 'string' ? request.status : request.status?.value ?? '';
    if (['completed', 'delivered', 'cancelled'].includes(statusValue)) return null;
    const requestedAt = request.requested_at ? new Date(request.requested_at).getTime() : 0;
    if (!requestedAt) return null;
    const slaHours = SLA_CONFIGS.laundry.hours;
    const deadline = requestedAt + slaHours * 60 * 60 * 1000;
    const now = Date.now();
    if (request.is_delayed === true || now > deadline) return 'breached';
    const remainingHours = (deadline - now) / (60 * 60 * 1000);
    if (remainingHours <= slaHours * 0.25) return 'near';
    return 'within';
  };

  const RequestCard = ({ request }: { request: LaundryRequest }) => {
    const statusVal = typeof request.status === 'string' ? request.status : request.status?.value ?? 'pending';
    const statusColor = typeof request.status === 'object' && request.status?.color
      ? request.status.color
      : getStatusColor(statusVal);
    const statusLabel = typeof request.status === 'object' && request.status?.label
      ? request.status.label
      : statusVal.charAt(0).toUpperCase() + statusVal.slice(1).replace('_', ' ');
    const studentName = request.student?.name ?? request.student_name ?? 'Unknown Student';
    const studentId = request.student?.student_uid ?? (request.student_id != null ? String(request.student_id) : null);
    const roomNo = request.student?.room_no ?? 'N/A';
    const slaPill = getSLAPill(request);

    return (
      <TouchableOpacity
        style={styles.requestCard}
        onPress={() => navigation.navigate('LaundryRequestDetail', { requestId: request.id })}
        activeOpacity={0.7}
      >
        {/* Request ID + SLA pill */}
        <View style={styles.cardTopRow}>
          <Text style={styles.requestIdText}>Request #{request.id}</Text>
          {slaPill !== null && (
            <View style={[styles.slaPill, slaPill === 'breached' && styles.slaPillBreached, slaPill === 'near' && styles.slaPillNear]}>
              <Text style={[styles.slaPillText, slaPill === 'breached' && styles.slaPillTextBreached]}>
                {slaPill === 'breached' ? 'Breached' : slaPill === 'near' ? 'Near SLA' : 'Within SLA'}
              </Text>
            </View>
          )}
        </View>

        {/* Header Row */}
        <View style={styles.cardHeader}>
          <View style={styles.studentInfo}>
            <Text style={styles.studentName}>{studentName}</Text>
            <Text style={styles.roomNumber}>Room {roomNo}</Text>
            {studentId != null && (
              <Text style={styles.studentIdText}>Student ID: {studentId}</Text>
            )}
          </View>
          <View style={[styles.statusBadge, { backgroundColor: statusColor + '20' }]}>
            <Text style={[styles.statusText, { color: statusColor }]}>{statusLabel}</Text>
          </View>
        </View>

        {/* Details Row */}
        <View style={styles.detailsRow}>
          <View style={styles.detailItem}>
            <Ionicons name="shirt-outline" size={16} color={colors.textSecondary} />
            <Text style={styles.detailText}>
              {request.bag_count} item{request.bag_count !== 1 ? 's' : ''}
            </Text>
          </View>
          {request.weight_kg && (
            <View style={styles.detailItem}>
              <Ionicons name="scale-outline" size={16} color={colors.textSecondary} />
              <Text style={styles.detailText}>{request.weight_kg} kg</Text>
            </View>
          )}
          {request.requested_at && (
            <View style={styles.detailItem}>
              <Ionicons name="calendar-outline" size={16} color={colors.textSecondary} />
              <Text style={styles.detailText}>
                {format(new Date(request.requested_at), 'MMM dd, yyyy')}
              </Text>
            </View>
          )}
        </View>

        {/* View Detail Button */}
        <GradientButton
          style={styles.viewDetailButton}
          onPress={() => navigation.navigate('LaundryRequestDetail', { requestId: request.id })}
        >
          <Text style={styles.viewDetailText}>View Detail</Text>
          <Ionicons name="chevron-forward" size={16} color={colors.primary} />
        </GradientButton>
      </TouchableOpacity>
    );
  };

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Requests" />

      {/* Search Bar */}
      <View style={styles.searchContainer}>
        <Ionicons name="search-outline" size={20} color={colors.textSecondary} style={styles.searchIcon} />
        <TextInput
          style={styles.searchInput}
          placeholder="Search by name, request ID, room number..."
          value={searchQuery}
          onChangeText={setSearchQuery}
          placeholderTextColor={colors.textMuted}
        />
        {searchQuery.length > 0 && (
          <GradientButton onPress={() => setSearchQuery('')} style={styles.clearButton}>
            <Ionicons name="close-circle" size={20} color={colors.textSecondary} />
          </GradientButton>
        )}
      </View>

      {/* Status Filter */}
      <View style={styles.filterContainer}>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.filterScroll}>
          {filterOptions.map((option) => (
            <TouchableOpacity
              key={option.key}
              style={[
                styles.filterTab,
                filter === option.key && styles.filterTabActive,
              ]}
              onPress={() => setFilter(option.key)}
            >
              <Text
                style={[
                  styles.filterTabText,
                  filter === option.key && styles.filterTabTextActive,
                ]}
              >
                {option.label}
              </Text>
            </TouchableOpacity>
          ))}
        </ScrollView>
      </View>

      {/* Content */}
      {loading && !error ? (
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color={colors.primary} />
          <Text style={styles.loadingText}>Loading requests...</Text>
        </View>
      ) : error ? (
        <ErrorState error={error} onRetry={fetchRequests} />
      ) : (
        <ScrollView
          style={styles.content}
          contentContainerStyle={styles.contentContainer}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={colors.primary} />
          }
        >
          {filteredRequests.length === 0 ? (
            <View style={styles.emptyState}>
              <Ionicons name="shirt-outline" size={48} color={colors.textMuted} />
              <Text style={styles.emptyTitle}>No Requests Found</Text>
              <Text style={styles.emptySubtitle}>
                {searchQuery ? 'Try a different search term' : `No ${filter !== 'all' ? filter.replace('_', ' ') : ''} requests available`}
              </Text>
            </View>
          ) : (
            <>
              <Text style={styles.resultsCount}>
                {filteredRequests.length} request{filteredRequests.length !== 1 ? 's' : ''} found
              </Text>
              {filteredRequests.map((request) => (
                <RequestCard key={request.id} request={request} />
              ))}
            </>
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
  searchContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.surface,
    marginHorizontal: 16,
    marginTop: 16,
    marginBottom: 12,
    paddingHorizontal: 16,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: colors.border,
  },
  searchIcon: {
    marginRight: 12,
  },
  searchInput: {
    flex: 1,
    fontSize: 15,
    color: colors.text,
    paddingVertical: 14,
  },
  clearButton: {
    padding: 6,
    borderRadius: 999,
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
  filterTab: {
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: 20,
    backgroundColor: colors.surfaceMuted,
    marginRight: 8,
  },
  filterTabActive: {
    backgroundColor: colors.primary,
  },
  filterTabText: {
    fontSize: 14,
    fontWeight: '500',
    color: colors.textSecondary,
  },
  filterTabTextActive: {
    color: colors.white,
    fontWeight: '600',
  },
  content: {
    flex: 1,
  },
  contentContainer: {
    padding: 16,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
  },
  loadingText: {
    marginTop: 16,
    fontSize: 16,
    color: colors.textSecondary,
  },
  resultsCount: {
    fontSize: 14,
    color: colors.textSecondary,
    fontWeight: '500',
    marginBottom: 12,
  },
  emptyState: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: 80,
  },
  emptyTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: colors.text,
    marginTop: 16,
    marginBottom: 8,
  },
  emptySubtitle: {
    fontSize: 14,
    color: colors.textSecondary,
    textAlign: 'center',
  },
  requestCard: {
    backgroundColor: colors.surface,
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: colors.border,
    shadowColor: colors.black,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.06,
    shadowRadius: 4,
    elevation: 2,
  },
  cardTopRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  requestIdText: {
    fontSize: 12,
    fontWeight: '600',
    color: colors.textSecondary,
  },
  slaPill: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
    backgroundColor: colors.success + '22',
  },
  slaPillNear: {
    backgroundColor: colors.warning + '22',
  },
  slaPillBreached: {
    backgroundColor: colors.error + '22',
  },
  slaPillText: {
    fontSize: 11,
    fontWeight: '600',
    color: colors.success,
  },
  slaPillTextBreached: {
    color: colors.error,
  },
  studentIdText: {
    fontSize: 12,
    color: colors.textSecondary,
    marginTop: 2,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 12,
  },
  studentInfo: {
    flex: 1,
    marginRight: 12,
  },
  studentName: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.text,
    marginBottom: 4,
  },
  roomNumber: {
    fontSize: 14,
    color: colors.textSecondary,
  },
  statusBadge: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 16,
  },
  statusText: {
    fontSize: 12,
    fontWeight: '600',
  },
  detailsRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 16,
    marginBottom: 12,
  },
  detailItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  detailText: {
    fontSize: 13,
    color: colors.textSecondary,
  },
  viewDetailButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 10,
    paddingHorizontal: 12,
    borderRadius: 12,
    marginTop: 8,
  },
  viewDetailText: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.primary,
    marginRight: 4,
  },
  bottomPadding: {
    height: 40,
  },
});

export default LaundryRequestListScreen;
