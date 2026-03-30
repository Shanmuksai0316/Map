import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  FlatList,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  TextInput,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { api } from '../../../services/api';
import type { LaundryRequest } from '../../../shared/types';
import { theme } from '../../../shared/theme/theme';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface Props {
  navigation: any;
}

type FilterStatus =
  | 'all'
  | 'pending'
  | 'scheduled'
  | 'collected'
  | 'washing'
  | 'drying'
  | 'ready'
  | 'delivered'
  | 'completed'
  | 'cancelled'
  | 'lost'
  | 'damaged';

export const RequestListScreen: React.FC<Props> = ({ navigation }) => {
  const [requests, setRequests] = useState<LaundryRequest[]>([]);
  const [filteredRequests, setFilteredRequests] = useState<LaundryRequest[]>([]);
  const [_isLoading, setIsLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [filterStatus, setFilterStatus] = useState<FilterStatus>('all');

  const fetchRequests = useCallback(async () => {
    try {
      const response = await api.get('/laundry/requests');
      setRequests(response.data.data || []);
    } catch (error) {
      console.error('Failed to fetch laundry requests:', error);
      // Show empty state - no mock data in production
      setRequests([]);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchRequests();
  }, [fetchRequests]);

  useEffect(() => {
    let filtered = requests;

    // Filter by status
    if (filterStatus !== 'all') {
      filtered = filtered.filter(r => r.status === filterStatus);
    }

    // Filter by search query
    if (searchQuery.trim()) {
      const query = searchQuery.toLowerCase();
      filtered = filtered.filter(
        r =>
          r.student_name?.toLowerCase().includes(query) ||
          r.room_number?.toLowerCase().includes(query)
      );
    }

    setFilteredRequests(filtered);
  }, [requests, filterStatus, searchQuery]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await fetchRequests();
    setRefreshing(false);
  }, [fetchRequests]);

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'pending':
        return theme.colors.warning;
      case 'scheduled':
        return theme.colors.primary;
      case 'collected':
        return theme.colors.primaryLight;
      case 'washing':
        return theme.colors.info;
      case 'drying':
        return theme.colors.primaryLight;
      case 'ready':
        return theme.colors.success;
      case 'delivered':
        return theme.colors.success;
      case 'completed':
        return theme.colors.success;
      case 'cancelled':
        return theme.colors.error;
      case 'lost':
        return theme.colors.error;
      case 'damaged':
        return theme.colors.warning;
      default:
        return theme.colors.textSecondary;
    }
  };

  const getStatusLabel = (status: string) => {
    switch (status) {
      case 'pending':
        return 'Pending';
      case 'scheduled':
        return 'Scheduled';
      case 'collected':
        return 'Collected';
      case 'washing':
        return 'Washing';
      case 'drying':
        return 'Drying';
      case 'ready':
        return 'Ready for Pickup';
      case 'delivered':
        return 'Delivered';
      case 'completed':
        return 'Completed';
      case 'cancelled':
        return 'Cancelled';
      case 'lost':
        return 'Lost';
      case 'damaged':
        return 'Damaged';
      default:
        return status;
    }
  };

  const handleUpdateStatus = async (requestId: number, newStatus: string) => {
    try {
      await api.put(`/laundry/requests/${requestId}/status`, {
        status: newStatus,
      });
      fetchRequests();
    } catch (error) {
      console.error('Failed to update status:', error);
    }
  };

  const renderFilterButton = (status: FilterStatus, label: string) => (
    <TouchableOpacity
      style={[
        styles.filterButton,
        filterStatus === status && styles.filterButtonActive,
      ]}
      onPress={() => setFilterStatus(status)}
    >
      <Text
        style={[
          styles.filterButtonText,
          filterStatus === status && styles.filterButtonTextActive,
        ]}
      >
        {label}
      </Text>
    </TouchableOpacity>
  );

  const renderRequest = ({ item }: { item: LaundryRequest }) => (
    <TouchableOpacity
      style={styles.requestCard}
      onPress={() => navigation.navigate('LaundryRequestDetail', { request: item })}
    >
      <View style={styles.requestHeader}>
        <View style={styles.studentInfo}>
          <Icon name="account" size={20} color={theme.colors.textSecondary} />
          <Text style={styles.studentName}>{item.student_name}</Text>
        </View>
        <View
          style={[
            styles.statusBadge,
            { backgroundColor: getStatusColor(item.status) + '20' },
          ]}
        >
          <View
            style={[
              styles.statusDot,
              { backgroundColor: getStatusColor(item.status) },
            ]}
          />
          <Text
            style={[styles.statusText, { color: getStatusColor(item.status) }]}
          >
            {getStatusLabel(item.status)}
          </Text>
        </View>
      </View>

      <View style={styles.requestDetails}>
        <View style={styles.detailItem}>
          <Icon name="door" size={16} color={theme.colors.textMuted} />
          <Text style={styles.detailText}>Room {item.room_number}</Text>
        </View>
        <View style={styles.detailItem}>
          <Icon name="hanger" size={16} color={theme.colors.textMuted} />
          <Text style={styles.detailText}>{item.item_count} items</Text>
        </View>
      </View>

      {!['completed', 'cancelled', 'lost', 'damaged'].includes(item.status) && (
        <View style={styles.actionButtons}>
          {['pending', 'scheduled'].includes(item.status) && (
            <TouchableOpacity
              style={[styles.actionButton, { backgroundColor: theme.colors.primary }]}
              onPress={() => handleUpdateStatus(item.id, 'collected')}
            >
              <Icon name="play" size={14} color={theme.colors.white} />
              <Text style={styles.actionButtonText}>Collect</Text>
            </TouchableOpacity>
          )}
          {['collected', 'washing', 'drying'].includes(item.status) && (
            <GradientButton
              style={[styles.actionButton, { backgroundColor: theme.colors.primary }]}
              onPress={() => handleUpdateStatus(item.id, 'ready')}
            >
              <Icon name="check" size={14} color={theme.colors.white} />
              <Text style={styles.actionButtonText}>Ready</Text>
            </GradientButton>
          )}
          {item.status === 'ready' && (
            <GradientButton
              style={[styles.actionButton, { backgroundColor: theme.colors.success }]}
              onPress={() => handleUpdateStatus(item.id, 'delivered')}
            >
              <Icon name="check-all" size={14} color={theme.colors.white} />
              <Text style={styles.actionButtonText}>Deliver</Text>
            </GradientButton>
          )}
          {item.status === 'delivered' && (
            <GradientButton
              style={[styles.actionButton, { backgroundColor: theme.colors.info }]}
              onPress={() => handleUpdateStatus(item.id, 'completed')}
            >
              <Icon name="flag" size={14} color={theme.colors.white} />
              <Text style={styles.actionButtonText}>Complete</Text>
            </GradientButton>
          )}
        </View>
      )}
    </TouchableOpacity>
  );

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Requests" />

      {/* Search */}
      <View style={styles.searchContainer}>
        <View style={styles.searchBox}>
          <Icon name="magnify" size={20} color={theme.colors.textMuted} />
          <TextInput
            style={styles.searchInput}
            value={searchQuery}
            onChangeText={setSearchQuery}
            placeholder="Search by name or room..."
            placeholderTextColor={theme.colors.textMuted}
          />
        </View>
      </View>

      {/* Filters */}
      <View style={styles.filtersContainer}>
        {renderFilterButton('all', 'All')}
        {renderFilterButton('pending', 'Pending')}
        {renderFilterButton('scheduled', 'Scheduled')}
        {renderFilterButton('collected', 'Collected')}
        {renderFilterButton('washing', 'Washing')}
        {renderFilterButton('drying', 'Drying')}
        {renderFilterButton('ready', 'Ready')}
        {renderFilterButton('delivered', 'Delivered')}
        {renderFilterButton('completed', 'Completed')}
        {renderFilterButton('cancelled', 'Cancelled')}
        {renderFilterButton('lost', 'Lost')}
        {renderFilterButton('damaged', 'Damaged')}
      </View>

      {/* List */}
      <FlatList
        data={filteredRequests}
        renderItem={renderRequest}
        keyExtractor={item => item.id.toString()}
        contentContainerStyle={styles.listContent}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
        ListEmptyComponent={
          <View style={styles.emptyState}>
            <Icon name="washing-machine" size={64} color={theme.colors.border} />
            <Text style={styles.emptyText}>No laundry requests found</Text>
          </View>
        }
      />
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  searchContainer: {
    padding: 16,
    paddingBottom: 8,
  },
  searchBox: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: theme.colors.white,
    borderRadius: 12,
    paddingHorizontal: 12,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  searchInput: {
    flex: 1,
    paddingVertical: 12,
    paddingHorizontal: 8,
    fontSize: 15,
    color: theme.colors.text,
  },
  filtersContainer: {
    flexDirection: 'row',
    paddingHorizontal: 16,
    paddingBottom: 8,
    gap: 8,
  },
  filterButton: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: theme.colors.white,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  filterButtonActive: {
    backgroundColor: theme.colors.primary,
    borderColor: theme.colors.primary,
  },
  filterButtonText: {
    fontSize: 13,
    color: theme.colors.textSecondary,
    fontWeight: '500',
  },
  filterButtonTextActive: {
    color: theme.colors.white,
  },
  listContent: {
    padding: 16,
    paddingTop: 8,
  },
  requestCard: {
    backgroundColor: theme.colors.white,
    borderRadius: 16,
    padding: 16,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  requestHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  studentInfo: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  studentName: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.text,
    marginLeft: 8,
  },
  statusBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
  },
  statusDot: {
    width: 6,
    height: 6,
    borderRadius: 3,
    marginRight: 6,
  },
  statusText: {
    fontSize: 12,
    fontWeight: '600',
  },
  requestDetails: {
    flexDirection: 'row',
    gap: 16,
    marginBottom: 12,
  },
  detailItem: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  detailText: {
    fontSize: 13,
    color: theme.colors.textSecondary,
    marginLeft: 4,
  },
  actionButtons: {
    flexDirection: 'row',
    justifyContent: 'flex-end',
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: theme.colors.divider,
  },
  actionButton: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 8,
  },
  actionButtonText: {
    color: theme.colors.white,
    fontSize: 13,
    fontWeight: '600',
    marginLeft: 4,
  },
  emptyState: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 60,
  },
  emptyText: {
    fontSize: 16,
    color: theme.colors.textMuted,
    marginTop: 16,
  },
});

export default RequestListScreen;
