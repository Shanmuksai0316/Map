import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  ActivityIndicator,
  Alert,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import { apiService } from '../../../shared/services/api.service';
import { APP_CONFIG } from '../../../shared/config/app.config';
import { format } from 'date-fns';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { colors } from '../../../shared/theme/colors';
import { errorHandler } from '../../../shared/utils/errorHandler';
import { ErrorState } from '../../../shared/components/shared/ErrorState';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface RoomAllocation {
  id: number;
  student_id: number;
  student_name: string;
  student_uid: string;
  hostel_name: string;
  room_number: string;
  bed_number?: string;
  status: 'active' | 'inactive' | 'pending';
  effective_from: string;
  effective_to?: string;
  created_at: string;
}

export const RoomAllocationScreen = ({ navigation }: any) => {
  const [allocations, setAllocations] = useState<RoomAllocation[]>([]);
  const [filteredAllocations, setFilteredAllocations] = useState<RoomAllocation[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<any>(null);
  const [filter, setFilter] = useState<'all' | 'active' | 'inactive' | 'pending'>('all');

  const fetchAllocations = async () => {
    try {
      setError(null);
      const response = await apiService.get<{ data: RoomAllocation[] }>(
        `${APP_CONFIG.ENDPOINTS.ADMIN_ALLOCATIONS}?limit=100`
      );
      const allocationList = response.data || [];
      setAllocations(allocationList);
      setFilteredAllocations(allocationList);
    } catch (err) {
      console.error('Failed to fetch room allocations:', err);
      const errorDetails = errorHandler.handleError(err);
      setError(errorDetails);
      setAllocations([]);
      setFilteredAllocations([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchAllocations();
  }, []);

  useEffect(() => {
    if (filter === 'all') {
      setFilteredAllocations(allocations);
    } else {
      setFilteredAllocations(allocations.filter(a => a.status === filter));
    }
  }, [filter, allocations]);

  const onRefresh = () => {
    setRefreshing(true);
    fetchAllocations();
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'active':
        return colors.success;
      case 'inactive':
        return colors.textMuted;
      case 'pending':
        return colors.warning;
      default:
        return colors.textSecondary;
    }
  };

  if (loading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color={colors.primary} />
        <Text style={styles.loadingText}>Loading room allocations...</Text>
      </View>
    );
  }

  if (error) {
    return (
      <View style={styles.container}>
        <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Room Allocation" />
        <ErrorState error={error} onRetry={fetchAllocations} />
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <StaffScreenHeader
        onBack={() => navigation.goBack()}
        showBell={false}
        rightSlot={
          <GradientButton
            style={styles.addButton}
            onPress={() => {
              Alert.alert('Info', 'Room allocation management is available in the web panel.');
            }}>
            <Ionicons name="add" size={22} color={colors.textOnPrimary} />
          </GradientButton>
        }  title="Room Allocation" />
      {/* Filter Tabs */}
      <View style={styles.filterContainer}>
        <ScrollView horizontal showsHorizontalScrollIndicator={false}>
          {(['all', 'active', 'inactive', 'pending'] as const).map((filterOption) => (
            <TouchableOpacity
              key={filterOption}
              style={[
                styles.filterChip,
                filter === filterOption && styles.filterChipActive,
              ]}
              onPress={() => setFilter(filterOption)}>
              <Text
                style={[
                  styles.filterChipText,
                  filter === filterOption && styles.filterChipTextActive,
                ]}>
                {filterOption.charAt(0).toUpperCase() + filterOption.slice(1)}
              </Text>
            </TouchableOpacity>
          ))}
        </ScrollView>
      </View>

      {/* Content */}
      <ScrollView
        style={styles.content}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>
        {filteredAllocations.length === 0 ? (
          <View style={styles.emptyState}>
            <Ionicons name="bed-outline" size={48} color={colors.textMuted} />
            <Text style={styles.emptyTitle}>No Allocations</Text>
            <Text style={styles.emptySubtitle}>
              {filter === 'all'
                ? 'No room allocations found.'
                : `No ${filter} allocations found.`}
            </Text>
          </View>
        ) : (
          <>
            <View style={styles.resultsHeader}>
              <Text style={styles.resultsCount}>
                {filteredAllocations.length} allocation{filteredAllocations.length !== 1 ? 's' : ''}
              </Text>
            </View>
            {filteredAllocations.map((allocation) => (
              <View key={allocation.id} style={styles.allocationCard}>
                <View style={styles.allocationHeader}>
                  <View style={styles.allocationInfo}>
                    <Text style={styles.studentName}>{allocation.student_name}</Text>
                    <Text style={styles.studentUid}>{allocation.student_uid}</Text>
                  </View>
                  <View style={[styles.statusBadge, { backgroundColor: getStatusColor(allocation.status) + '20' }]}>
                    <Text style={[styles.statusText, { color: getStatusColor(allocation.status) }]}>
                      {allocation.status}
                    </Text>
                  </View>
                </View>
                <View style={styles.allocationDetails}>
                  <View style={styles.detailRow}>
                    <Ionicons name="business-outline" size={16} color={colors.textMuted} />
                    <Text style={styles.detailText}>{allocation.hostel_name}</Text>
                  </View>
                  <View style={styles.detailRow}>
                    <Ionicons name="bed-outline" size={16} color={colors.textMuted} />
                    <Text style={styles.detailText}>
                      Room {allocation.room_number}
                      {allocation.bed_number && ` • Bed ${allocation.bed_number}`}
                    </Text>
                  </View>
                  <View style={styles.detailRow}>
                    <Ionicons name="calendar-outline" size={16} color={colors.textMuted} />
                    <Text style={styles.detailText}>
                      From {format(new Date(allocation.effective_from), 'MMM dd, yyyy')}
                      {allocation.effective_to && ` to ${format(new Date(allocation.effective_to), 'MMM dd, yyyy')}`}
                    </Text>
                  </View>
                </View>
              </View>
            ))}
          </>
        )}
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  subHeader: {
    paddingHorizontal: 16,
    paddingTop: 12,
    paddingBottom: 8,
  },
  subHeaderTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: colors.textHeading,
  },
  addButton: {
    padding: 6,
    backgroundColor: colors.primary,
    borderRadius: 8,
  },
  filterContainer: {
    backgroundColor: colors.white,
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  filterChip: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: colors.background,
    marginLeft: 8,
    borderWidth: 1,
    borderColor: colors.border,
  },
  filterChipActive: {
    backgroundColor: colors.primary,
    borderColor: colors.primary,
  },
  filterChipText: {
    fontSize: 14,
    fontWeight: '500',
    color: colors.text,
  },
  filterChipTextActive: {
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
  allocationCard: {
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
  allocationHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 12,
  },
  allocationInfo: {
    flex: 1,
    marginRight: 12,
  },
  studentName: {
    fontSize: 18,
    fontWeight: 'bold',
    color: colors.text,
    marginBottom: 4,
  },
  studentUid: {
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
    textTransform: 'capitalize',
  },
  allocationDetails: {
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: colors.border,
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
});
