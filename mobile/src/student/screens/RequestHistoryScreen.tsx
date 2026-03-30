import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  Alert,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { theme } from '../../shared/theme/theme';
import { apiService } from '../../shared/services/api.service';
import { APP_CONFIG } from '../../shared/config/app.config';
import { EmptyState } from '../../shared/components';

interface RequestItem {
  id: string;
  type: 'gate_pass' | 'leave' | 'sick_leave' | 'guest_entry' | 'room_change' | 'ticket';
  title: string;
  status: 'pending' | 'approved' | 'rejected' | 'active' | 'done' | 'in_progress' | 'resolved' | 'closed';
  created_at: string;
  unique_id?: string;
}

const HISTORY_FILTERS = [
  { id: 'all', label: 'All' },
  { id: 'gate_pass', label: 'Gate Pass' },
  { id: 'leave', label: 'Leave' },
  { id: 'sick_leave', label: 'Sick Leave' },
  { id: 'guest_entry', label: 'Guest Entry' },
  { id: 'room_change', label: 'Room Change' },
  { id: 'ticket', label: 'Tickets' },
];

export const RequestHistoryScreen = ({ navigation, route }: any) => {
  const insets = useSafeAreaInsets();
  const { filter: initialFilter = 'all' } = route?.params || {};
  const [activeFilter, setActiveFilter] = useState(initialFilter);
  const [requests, setRequests] = useState<RequestItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const fetchAllRequests = async () => {
    try {
      setLoading(true);
      const results = await Promise.allSettled([
        apiService.get<{ data: any[] }>(APP_CONFIG.ENDPOINTS.GATE_PASSES),
        apiService.get<{ data: any[] }>(APP_CONFIG.ENDPOINTS.LEAVES),
        apiService.get<{ data: any[] }>(APP_CONFIG.ENDPOINTS.SICK_LEAVES),
        apiService.get<{ data: any[] }>(APP_CONFIG.ENDPOINTS.GUEST_ENTRIES),
        apiService.get<{ data: any[] }>(APP_CONFIG.ENDPOINTS.ROOM_CHANGES),
        apiService.get<{ data: any[] }>(APP_CONFIG.ENDPOINTS.TICKETS),
      ]);

      const allRequests: RequestItem[] = [];

      // Process gate passes
      if (results[0].status === 'fulfilled') {
        results[0].value.data?.forEach((item: any) => {
          allRequests.push({
            id: item.id,
            type: 'gate_pass',
            title: item.purpose || 'Gate Pass Request',
            status: item.status,
            created_at: item.created_at,
            unique_id: item.unique_id,
          });
        });
      }

      // Process leaves
      if (results[1].status === 'fulfilled') {
        results[1].value.data?.forEach((item: any) => {
          allRequests.push({
            id: item.id,
            type: 'leave',
            title: item.reason_for_leave || 'Leave Request',
            status: item.status,
            created_at: item.created_at,
            unique_id: item.unique_id,
          });
        });
      }

      // Process sick leaves
      if (results[2].status === 'fulfilled') {
        results[2].value.data?.forEach((item: any) => {
          allRequests.push({
            id: item.id,
            type: 'sick_leave',
            title: item.illness || 'Sick Leave Request',
            status: item.status,
            created_at: item.created_at,
            unique_id: item.unique_id,
          });
        });
      }

      // Process guest entries
      if (results[3].status === 'fulfilled') {
        results[3].value.data?.forEach((item: any) => {
          allRequests.push({
            id: item.id,
            type: 'guest_entry',
            title: 'Parents Visit',
            status: item.status,
            created_at: item.created_at,
            unique_id: item.unique_id,
          });
        });
      }

      // Process room changes
      if (results[4].status === 'fulfilled') {
        results[4].value.data?.forEach((item: any) => {
          allRequests.push({
            id: item.id,
            type: 'room_change',
            title: 'Room Change Request',
            status: item.status,
            created_at: item.created_at,
            unique_id: item.unique_id,
          });
        });
      }

      // Process tickets
      if (results[5].status === 'fulfilled') {
        results[5].value.data?.forEach((item: any) => {
          allRequests.push({
            id: item.id,
            type: 'ticket',
            title: item.title || item.issue || 'Ticket',
            status: item.status,
            created_at: item.created_at,
          });
        });
      }

      // Sort by creation date (newest first)
      allRequests.sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime());

      setRequests(allRequests);
    } catch (error) {
      console.error('Error fetching request history:', error);
      Alert.alert('Error', 'Failed to load request history');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchAllRequests();
  }, []);

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    fetchAllRequests();
  }, []);

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'approved':
      case 'active':
      case 'done':
      case 'resolved':
        return theme.colors.success;
      case 'pending':
      case 'in_progress':
        return theme.colors.warning;
      case 'rejected':
      case 'closed':
        return theme.colors.error;
      default:
        return theme.colors.textMuted;
    }
  };

  const getStatusLabel = (status: string) => {
    switch (status) {
      case 'approved':
        return 'APPROVED';
      case 'active':
        return 'ACTIVE';
      case 'done':
        return 'DONE';
      case 'resolved':
        return 'RESOLVED';
      case 'pending':
        return 'PENDING';
      case 'in_progress':
        return 'IN PROGRESS';
      case 'rejected':
        return 'REJECTED';
      case 'closed':
        return 'CLOSED';
      default:
        return status.toUpperCase();
    }
  };

  const getTypeIcon = (type: string) => {
    switch (type) {
      case 'gate_pass':
        return 'exit-outline';
      case 'leave':
        return 'calendar-outline';
      case 'sick_leave':
        return 'medical-outline';
      case 'guest_entry':
        return 'people-outline';
      case 'room_change':
        return 'swap-horizontal-outline';
      case 'ticket':
        return 'construct-outline';
      default:
        return 'document-outline';
    }
  };

  const getTypeLabel = (type: string) => {
    switch (type) {
      case 'gate_pass':
        return 'Gate Pass';
      case 'leave':
        return 'Leave';
      case 'sick_leave':
        return 'Sick Leave';
      case 'guest_entry':
        return 'Guest Entry';
      case 'room_change':
        return 'Room Change';
      case 'ticket':
        return 'Ticket';
      default:
        return 'Request';
    }
  };

  const filteredRequests = activeFilter === 'all'
    ? requests
    : requests.filter(request => request.type === activeFilter);

  const HEADER_ROW_HEIGHT = 52;
  const HEADER_PADDING_TOP = Math.max(insets.top, 10);
  const HEADER_PADDING_BOTTOM = 6;

  return (
    <View style={styles.container}>
      {/* Header */}
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
            onPress={() => (navigation?.canGoBack?.() ? navigation.goBack() : navigation.navigate('Home'))}
            accessibilityLabel="Go back">
            <Ionicons name="arrow-back" size={24} color={theme.colors.primary} />
          </TouchableOpacity>
          <Text style={styles.headerTitle}>Request History</Text>
          <View style={styles.headerSpacer} />
        </View>
      </View>

      {/* Filter Pills */}
      <View style={styles.filterContainer}>
        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.filterScroll}>
          {HISTORY_FILTERS.map((filter) => (
            <TouchableOpacity
              key={filter.id}
              style={[
                styles.filterPill,
                activeFilter === filter.id && styles.filterPillActive,
              ]}
              onPress={() => setActiveFilter(filter.id)}>
              <Text style={[
                styles.filterPillText,
                activeFilter === filter.id && styles.filterPillTextActive,
              ]}>
                {filter.label}
              </Text>
            </TouchableOpacity>
          ))}
        </ScrollView>
      </View>

      <ScrollView
        style={styles.content}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>
        {loading ? (
          <View style={styles.loadingContainer}>
            <Text>Loading request history...</Text>
          </View>
        ) : filteredRequests.length === 0 ? (
          <EmptyState
            variant="no-data"
            title={`No ${activeFilter === 'all' ? '' : HISTORY_FILTERS.find(f => f.id === activeFilter)?.label + ' '}Requests`}
            subtitle={activeFilter === 'all' ? "You haven't made any requests yet" : `No ${HISTORY_FILTERS.find(f => f.id === activeFilter)?.label.toLowerCase()} requests found`}
          />
        ) : (
          filteredRequests.map((request) => (
            <TouchableOpacity
              key={`${request.type}-${request.id}`}
              style={styles.requestCard}
              onPress={() => {
                // Navigate to respective detail screen
                const screenMap: Record<string, { screen: string; paramKey: string; tab?: string }> = {
                  gate_pass: { screen: 'GatePass', paramKey: 'id' },
                  leave: { screen: 'LeaveDetail', paramKey: 'leaveId' },
                  sick_leave: { screen: 'SickLeaveDetail', paramKey: 'sickLeaveId' },
                  guest_entry: { screen: 'GuestEntryDetail', paramKey: 'guestEntryId' },
                  room_change: { tab: 'Profile', screen: 'RoomChangeDetail', paramKey: 'roomChangeId' },
                  ticket: { screen: 'TicketDetail', paramKey: 'ticketId' },
                };

                const target = screenMap[request.type];
                if (!target) {
                  return;
                }

                const params = { [target.paramKey]: request.id };

                if (target.tab) {
                  navigation.navigate(target.tab, {
                    screen: target.screen,
                    params,
                  });
                  return;
                }

                navigation.navigate(target.screen, params);
              }}>
              <View style={styles.requestHeader}>
                <View style={styles.requestInfo}>
                  <View style={styles.typeBadge}>
                    <Ionicons name={getTypeIcon(request.type)} size={14} color={theme.colors.primary} />
                    <Text style={styles.typeText}>{getTypeLabel(request.type)}</Text>
                  </View>
                  <Text style={styles.requestTitle}>{request.title}</Text>
                </View>
                <View
                  style={[
                    styles.statusBadge,
                    { backgroundColor: `${getStatusColor(request.status)}20` },
                  ]}>
                  <Text
                    style={[
                      styles.statusText,
                      { color: getStatusColor(request.status) },
                    ]}>
                    {getStatusLabel(request.status)}
                  </Text>
                </View>
              </View>

              <View style={styles.requestFooter}>
                {request.unique_id && (
                  <Text style={styles.requestId}>ID: {request.unique_id}</Text>
                )}
                <Text style={styles.requestDate}>
                  {new Date(request.created_at).toLocaleDateString()}
                </Text>
              </View>
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
    backgroundColor: theme.colors.white,
  },
  header: {
    backgroundColor: theme.colors.white,
    paddingHorizontal: 16,
    ...theme.shadows.medium,
  },
  headerRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  backButton: {
    padding: 8,
  },
  headerTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: theme.colors.primary,
  },
  headerSpacer: {
    width: 40,
  },
  filterContainer: {
    backgroundColor: theme.colors.surface,
    paddingVertical: 12,
    paddingHorizontal: 16,
  },
  filterScroll: {
    gap: 8,
  },
  filterPill: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: theme.colors.white,
    borderWidth: 1,
    borderColor: theme.colors.border,
    marginRight: 8,
  },
  filterPillActive: {
    backgroundColor: theme.colors.primary,
    borderColor: theme.colors.primary,
  },
  filterPillText: {
    fontSize: 14,
    fontWeight: '600',
    color: theme.colors.textSecondary,
  },
  filterPillTextActive: {
    color: theme.colors.white,
  },
  content: {
    flex: 1,
    padding: 16,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingTop: 40,
  },
  requestCard: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.borderRadius.lg,
    padding: 16,
    marginBottom: 12,
    ...theme.shadows.small,
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
  typeBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    marginBottom: 4,
  },
  typeText: {
    fontSize: 12,
    fontWeight: '600',
    color: theme.colors.primary,
    textTransform: 'uppercase',
  },
  requestTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.text,
    lineHeight: 20,
  },
  statusBadge: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 12,
  },
  statusText: {
    fontSize: 12,
    fontWeight: '700',
    textAlign: 'center',
  },
  requestFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingTop: 8,
    borderTopWidth: 1,
    borderTopColor: theme.colors.divider,
  },
  requestId: {
    fontSize: 12,
    color: theme.colors.textMuted,
    fontWeight: '500',
  },
  requestDate: {
    fontSize: 12,
    color: theme.colors.textMuted,
  },
});
