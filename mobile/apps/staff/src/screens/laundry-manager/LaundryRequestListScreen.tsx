import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  ActivityIndicator,
  TextInput,
  Alert,
} from 'react-native';
import { useAuthStore } from '../../store/auth.store';
import { apiService } from '../../services/api.service';
import { APP_CONFIG } from '../../config/app.config';
import { format } from 'date-fns';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { colors } from '../../theme/colors';
import { errorHandler } from '../../utils/errorHandler';
import { ErrorState } from '../../components/shared/ErrorState';
import { SLACountdownBadge } from '../../components/SLACountdownBadge';

interface LaundryRequest {
  id: number;
  status: {
    value: string;
    label: string;
    color: string;
    is_active: boolean;
    is_in_progress: boolean;
    is_completed: boolean;
  };
  service_type: {
    value: string;
    label: string;
  };
  bag_count: number;
  weight_kg?: number;
  student?: {
    name: string;
    student_uid: string;
    hostel_name?: string;
    room_no?: string;
  };
  hostel?: {
    name: string;
  };
  requested_at?: string;
  estimated_completion_at?: string;
  special_instructions?: string;
  collection_notes?: string;
  delivery_notes?: string;
}

export const LaundryRequestListScreen = ({ navigation }: any) => {
  const { user } = useAuthStore();
  const [requests, setRequests] = useState<LaundryRequest[]>([]);
  const [filteredRequests, setFilteredRequests] = useState<LaundryRequest[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<any>(null);
  const [filter, setFilter] = useState<'all' | 'pending' | 'collected' | 'ready' | 'delivered' | 'completed'>('all');
  const [searchQuery, setSearchQuery] = useState('');

  const fetchRequests = async () => {
    try {
      setError(null);
      const statusParam = filter !== 'all' ? `&status=${filter}` : '';
      const response = await apiService.get<{ data: LaundryRequest[] }>(
        `${APP_CONFIG.ENDPOINTS.LAUNDRY_REQUESTS}?limit=100${statusParam}`
      );
      
      const requestList = response.data || [];
      setRequests(requestList);
      setFilteredRequests(requestList);
    } catch (err) {
      console.error('Failed to fetch laundry requests:', err);
      const errorDetails = errorHandler.handleError(err);
      setError(errorDetails);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchRequests();
  }, [filter]);

  useEffect(() => {
    // Filter requests by search query
    if (!searchQuery.trim()) {
      setFilteredRequests(requests);
      return;
    }

    const query = searchQuery.toLowerCase();
    const filtered = requests.filter((request) => {
      return (
        request.student?.name?.toLowerCase().includes(query) ||
        request.student?.student_uid?.toLowerCase().includes(query) ||
        request.student?.hostel_name?.toLowerCase().includes(query) ||
        request.student?.room_no?.toLowerCase().includes(query) ||
        request.service_type?.label?.toLowerCase().includes(query)
      );
    });
    setFilteredRequests(filtered);
  }, [searchQuery, requests]);

  const onRefresh = () => {
    setRefreshing(true);
    fetchRequests();
  };

  const getStatusIcon = (statusValue: string) => {
    switch (statusValue) {
      case 'pending':
        return 'time-outline';
      case 'scheduled':
        return 'calendar-outline';
      case 'collected':
        return 'bag-outline';
      case 'processing':
        return 'refresh-outline';
      case 'ready':
        return 'checkmark-circle-outline';
      case 'delivered':
        return 'checkmark-done-outline';
      case 'completed':
        return 'flag-outline';
      case 'cancelled':
        return 'close-circle-outline';
      default:
        return 'document-text-outline';
    }
  };

  const RequestCard = ({ request }: { request: LaundryRequest }) => (
    <TouchableOpacity
      style={styles.requestCard}
      onPress={() => navigation.navigate('LaundryRequestDetail', { requestId: request.id })}>
      <View style={styles.requestHeader}>
        <View style={styles.requestInfo}>
          <View style={styles.studentNameRow}>
            <Text style={styles.studentName}>{request.student?.name || 'Unknown Student'}</Text>
            {request.requested_at && (
              <SLACountdownBadge
                createdAt={request.requested_at}
                status={request.status.is_completed ? 'completed' : request.status.is_in_progress ? 'in_progress' : 'open'}
                category="laundry"
                size="small"
              />
            )}
          </View>
          <Text style={styles.studentDetails}>
            {request.student?.student_uid} • {request.student?.hostel_name || request.hostel?.name}
            {request.student?.room_no && ` • Room ${request.student.room_no}`}
          </Text>
        </View>
        <View style={[styles.statusBadge, { backgroundColor: request.status.color + '20' }]}>
          <Ionicons name={getStatusIcon(request.status.value)} size={16} color={request.status.color} />
          <Text style={[styles.statusText, { color: request.status.color }]}>
            {request.status.label}
          </Text>
        </View>
      </View>

      <View style={styles.requestDetails}>
        <View style={styles.detailRow}>
          <Ionicons name="shirt-outline" size={16} color={colors.textSecondary} />
          <Text style={styles.detailText}>{request.service_type?.label || 'Unknown'}</Text>
        </View>
        <View style={styles.detailRow}>
          <Ionicons name="cube-outline" size={16} color={colors.textSecondary} />
          <Text style={styles.detailText}>
            {request.bag_count} bag{request.bag_count !== 1 ? 's' : ''}
            {request.weight_kg && ` • ${request.weight_kg} kg`}
          </Text>
        </View>
        {request.requested_at && (
          <View style={styles.detailRow}>
            <Ionicons name="calendar-outline" size={16} color={colors.textSecondary} />
            <Text style={styles.detailText}>
              Requested: {format(new Date(request.requested_at), 'MMM dd, HH:mm')}
            </Text>
          </View>
        )}
        {request.estimated_completion_at && (
          <View style={styles.detailRow}>
            <Ionicons name="time-outline" size={16} color={colors.textSecondary} />
            <Text style={styles.detailText}>
              Est. Completion: {format(new Date(request.estimated_completion_at), 'MMM dd, HH:mm')}
            </Text>
          </View>
        )}
      </View>

      {request.special_instructions && (
        <View style={styles.instructionsContainer}>
          <Text style={styles.instructionsLabel}>Special Instructions:</Text>
          <Text style={styles.instructionsText} numberOfLines={2}>
            {request.special_instructions}
          </Text>
        </View>
      )}
    </TouchableOpacity>
  );

  return (
    <View style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity
          style={styles.backButton}
          onPress={() => navigation.goBack()}>
          <Ionicons name="arrow-back" size={24} color={colors.white} />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Laundry Requests</Text>
        <View style={styles.headerSpacer} />
      </View>

      {/* Search Bar */}
      <View style={styles.searchContainer}>
        <Ionicons name="search-outline" size={20} color={colors.textSecondary} style={styles.searchIcon} />
        <TextInput
          style={styles.searchInput}
          placeholder="Search by name, UID, hostel..."
          value={searchQuery}
          onChangeText={setSearchQuery}
          placeholderTextColor={colors.textMuted}
        />
        {searchQuery.length > 0 && (
          <TouchableOpacity onPress={() => setSearchQuery('')} style={styles.clearButton}>
            <Ionicons name="close-circle" size={20} color={colors.textSecondary} />
          </TouchableOpacity>
        )}
      </View>

      {/* Filter Tabs */}
      <View style={styles.filterContainer}>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.filterScroll}>
          {[
            { key: 'all', label: 'All' },
            { key: 'pending', label: 'Pending' },
            { key: 'collected', label: 'Collected' },
            { key: 'ready', label: 'Ready' },
            { key: 'delivered', label: 'Delivered' },
            { key: 'completed', label: 'Completed' },
          ].map((filterOption) => (
            <TouchableOpacity
              key={filterOption.key}
              style={[
                styles.filterTab,
                filter === filterOption.key && styles.filterTabActive,
              ]}
              onPress={() => setFilter(filterOption.key as any)}>
              <Text
                style={[
                  styles.filterTabText,
                  filter === filterOption.key && styles.filterTabTextActive,
                ]}>
                {filterOption.label}
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
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
          }>
          {filteredRequests.length === 0 ? (
            <View style={styles.emptyState}>
              <Ionicons name="shirt-outline" size={48} color={colors.textMuted} />
              <Text style={styles.emptyTitle}>No Requests Found</Text>
              <Text style={styles.emptySubtitle}>
                {searchQuery ? 'Try a different search term' : `No ${filter !== 'all' ? filter : ''} requests available`}
              </Text>
            </View>
          ) : (
            <>
              <View style={styles.resultsHeader}>
                <Text style={styles.resultsCount}>
                  {filteredRequests.length} request{filteredRequests.length !== 1 ? 's' : ''} found
                </Text>
              </View>
              {filteredRequests.map((request) => (
                <RequestCard key={request.id} request={request} />
              ))}
            </>
          )}
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
  header: {
    backgroundColor: colors.primary,
    paddingTop: 60,
    paddingBottom: 16,
    paddingHorizontal: 16,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  backButton: {
    padding: 8,
  },
  headerTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: colors.white,
    flex: 1,
    textAlign: 'center',
  },
  headerSpacer: {
    width: 40,
  },
  searchContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.white,
    marginHorizontal: 16,
    marginTop: 16,
    marginBottom: 8,
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
    fontSize: 16,
    color: colors.text,
    paddingVertical: 12,
  },
  clearButton: {
    padding: 4,
  },
  filterContainer: {
    backgroundColor: colors.white,
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  filterScroll: {
    paddingHorizontal: 16,
  },
  filterTab: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    marginRight: 8,
    borderRadius: 20,
    backgroundColor: colors.background,
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
  resultsHeader: {
    paddingHorizontal: 16,
    paddingTop: 16,
    paddingBottom: 8,
  },
  resultsCount: {
    fontSize: 14,
    color: colors.textSecondary,
    fontWeight: '500',
  },
  emptyState: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
    marginTop: 100,
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
    backgroundColor: colors.white,
    marginHorizontal: 16,
    marginBottom: 16,
    borderRadius: 12,
    padding: 16,
    elevation: 2,
    shadowColor: colors.black,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  requestHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 12,
  },
  requestInfo: {
    flex: 1,
    marginRight: 12,
  },
  studentNameRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 4,
    gap: 8,
  },
  studentName: {
    fontSize: 18,
    fontWeight: 'bold',
    color: colors.text,
    flex: 1,
  },
  studentDetails: {
    fontSize: 14,
    color: colors.textSecondary,
  },
  statusBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 16,
    gap: 6,
  },
  statusText: {
    fontSize: 12,
    fontWeight: '600',
  },
  requestDetails: {
    marginBottom: 12,
  },
  detailRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 8,
  },
  detailText: {
    fontSize: 14,
    color: colors.textSecondary,
    marginLeft: 8,
  },
  instructionsContainer: {
    marginTop: 12,
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  instructionsLabel: {
    fontSize: 12,
    fontWeight: '600',
    color: colors.textSecondary,
    marginBottom: 4,
  },
  instructionsText: {
    fontSize: 14,
    color: colors.text,
    fontStyle: 'italic',
  },
});

