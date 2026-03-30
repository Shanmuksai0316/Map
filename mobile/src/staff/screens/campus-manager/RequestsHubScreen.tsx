import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  FlatList,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  ScrollView,
  TextInput,
  Modal,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { apiService } from '../../../shared/services/api.service';
import { theme } from '../../../shared/theme/theme';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';
import { GradientButton } from '../../../shared/components/GradientButton';

type RequestType = 'housekeeping' | 'maintenance' | 'outpass' | 'leave' | 'guest-entry' | 'sports' | 'laundry';
type StatusFilter = 'all' | 'pending' | 'in_progress' | 'resolved';

interface RequestItem {
  id: number;
  request_id?: string;
  student_name?: string;
  student_id?: string;
  student_uid?: string;
  user_name?: string;
  room?: string;
  hostel?: string;
  description?: string;
  reason?: string;
  status: string;
  created_at: string;
  type?: string;
  // Housekeeping
  request_option?: string;
  // R&M
  title?: string;
  // Outpass specific
  going_out_date?: string;
  return_date?: string;
  // Leave specific
  from_date?: string;
  to_date?: string;
  // Guest Entry specific
  guest_name?: string;
  guest_relation?: string;
  guest_id_proof?: string;
  guest_contact?: string;
  guest_arrival_date?: string;
  number_of_guests?: number;
  // Sports specific
  court_name?: string;
  booking_date?: string;
  slot_time?: string;
  // Laundry specific
  total_clothes?: number;
  total_weight?: string;
}

interface Props {
  navigation: any;
}

const TABS: { id: RequestType; label: string; icon: string; fullLabel: string }[] = [
  { id: 'housekeeping', label: 'HK', icon: 'broom', fullLabel: 'Housekeeping Requests' },
  { id: 'maintenance', label: 'R&M', icon: 'wrench', fullLabel: 'Repair & Maintenance Requests' },
  { id: 'outpass', label: 'Outpass', icon: 'exit-run', fullLabel: 'Out Pass Requests' },
  { id: 'leave', label: 'Leave', icon: 'calendar-remove', fullLabel: 'Leave Requests' },
  { id: 'guest-entry', label: 'Guest', icon: 'account-multiple', fullLabel: 'Guest Entry Requests' },
  { id: 'sports', label: 'Sports', icon: 'basketball', fullLabel: 'Sports Bookings' },
  { id: 'laundry', label: 'Laundry', icon: 'washing-machine', fullLabel: 'Laundry Requests' },
];

const STATUS_FILTERS: { id: StatusFilter; label: string }[] = [
  { id: 'all', label: 'All' },
  { id: 'pending', label: 'Pending' },
  { id: 'in_progress', label: 'In Progress' },
  { id: 'resolved', label: 'Resolved' },
];

const getStatusColor = (status: string): string => {
  const normalizedStatus = String(status ?? '').toLowerCase().replace(' ', '_');
  const colors: Record<string, string> = {
    pending: theme.colors.warning,
    approved: theme.colors.success,
    rejected: theme.colors.error,
    in_progress: theme.colors.info,
    completed: theme.colors.success,
    open: theme.colors.warning,
    resolved: theme.colors.success,
    closed: theme.colors.textSecondary,
  };
  return colors[normalizedStatus] || theme.colors.textSecondary;
};

const asText = (value: unknown): string => {
  if (typeof value === 'string') return value;
  if (typeof value === 'number') return String(value);
  if (value && typeof value === 'object') {
    const record = value as Record<string, unknown>;
    const nestedName = record.name ?? record.full_name ?? record.value;
    if (typeof nestedName === 'string') return nestedName;
  }
  return '';
};

export const RequestsHubScreen: React.FC<Props> = ({ navigation }) => {
  const [activeTab, setActiveTab] = useState<RequestType>('housekeeping');
  const [statusFilter, setStatusFilter] = useState<StatusFilter>('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [requests, setRequests] = useState<RequestItem[]>([]);
  const [filteredRequests, setFilteredRequests] = useState<RequestItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [selectedRequest, setSelectedRequest] = useState<RequestItem | null>(null);
  const [showPopup, setShowPopup] = useState(false);

  const fetchRequests = useCallback(async () => {
    setIsLoading(true);
    try {
      const response = await apiService.get<{ data?: RequestItem[] } | RequestItem[]>(`/mobile/campus-manager/requests/${activeTab}`);
      const payload = response as { data?: RequestItem[] } | RequestItem[];
      const requestList = Array.isArray(payload)
        ? payload
        : Array.isArray((payload as { data?: RequestItem[] }).data)
          ? (payload as { data?: RequestItem[] }).data ?? []
          : [];
      setRequests(requestList);
    } catch (error) {
      console.error('Failed to fetch requests:', error);
      setRequests([]);
    } finally {
      setIsLoading(false);
    }
  }, [activeTab]);

  useEffect(() => {
    fetchRequests();
  }, [fetchRequests]);

  // Filter requests based on search and status
  // For outpass, leave, guest-entry: Campus Manager sees only approved
  useEffect(() => {
    let filtered = requests;

    const statusNorm = (s: string) => s.toLowerCase().replace(' ', '_');

    // Campus Manager: only approved for outpass, leave, guest-entry
    if (activeTab === 'outpass' || activeTab === 'leave' || activeTab === 'guest-entry') {
      filtered = filtered.filter((req) => statusNorm(req.status) === 'approved');
    }

    // Apply status filter
    if (statusFilter !== 'all') {
      filtered = filtered.filter((req) => {
        const status = statusNorm(req.status);
        if (statusFilter === 'resolved') {
          return status === 'resolved' || status === 'completed' || status === 'closed';
        }
        return status === statusFilter;
      });
    }

    // Apply search filter
    if (searchQuery.trim()) {
      const query = searchQuery.toLowerCase();
      filtered = filtered.filter(
        (req) =>
          asText(req.student_name || req.user_name).toLowerCase().includes(query) ||
          asText(req.room).toLowerCase().includes(query) ||
          asText(req.request_id || String(req.id)).toLowerCase().includes(query)
      );
    }

    setFilteredRequests(filtered);
  }, [requests, statusFilter, searchQuery, activeTab]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await fetchRequests();
    setRefreshing(false);
  }, [fetchRequests]);

  const handleRequestPress = (request: RequestItem) => {
    setSelectedRequest(request);
    setShowPopup(true);
  };

  const formatDate = (dateString: string) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    if (Number.isNaN(date.getTime())) return 'N/A';
    return date.toLocaleDateString('en-US', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
  };

  const formatDateTime = (dateString: string) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    if (Number.isNaN(date.getTime())) return 'N/A';
    return date.toLocaleString('en-US', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const getTabFullLabel = () => {
    return TABS.find((t) => t.id === activeTab)?.fullLabel || '';
  };

  const getPopupTitle = () => {
    const titles: Record<RequestType, string> = {
      'housekeeping': 'Housekeeping',
      'maintenance': 'Repair & Maintenance',
      'outpass': 'Out Pass',
      'leave': 'Leave',
      'guest-entry': 'Guest Entry',
      'sports': 'Sports',
      'laundry': 'Laundry',
    };
    return titles[activeTab];
  };

  const renderPopupFields = () => {
    if (!selectedRequest) return null;

    const commonFields = [
      { label: 'Request ID', value: asText(selectedRequest.request_id) || `#${selectedRequest.id}` },
      { label: 'Student Name', value: asText(selectedRequest.student_name || selectedRequest.user_name) || 'N/A' },
      { label: 'Room Number', value: asText(selectedRequest.room) || 'N/A' },
    ];

    switch (activeTab) {
      case 'housekeeping':
      case 'maintenance':
        return [
          ...commonFields,
          { label: 'Submitted Date & Time', value: formatDateTime(selectedRequest.created_at) },
          { label: 'Status', value: selectedRequest.status, isStatus: true },
          { label: 'Description', value: selectedRequest.description || selectedRequest.reason || 'N/A' },
        ];

      case 'outpass':
        return [
          ...commonFields,
          { label: 'Going Out Date', value: selectedRequest.going_out_date ? formatDate(selectedRequest.going_out_date) : 'N/A' },
          { label: 'Submitted Date & Time', value: formatDateTime(selectedRequest.created_at) },
          { label: 'Status', value: selectedRequest.status, isStatus: true },
          { label: 'Description', value: selectedRequest.description || selectedRequest.reason || 'N/A' },
        ];

      case 'leave':
        return [
          ...commonFields,
          { label: 'From Date - To Date', value: selectedRequest.from_date && selectedRequest.to_date
            ? `${formatDate(selectedRequest.from_date)} - ${formatDate(selectedRequest.to_date)}`
            : 'N/A' },
          { label: 'Submitted Date & Time', value: formatDateTime(selectedRequest.created_at) },
          { label: 'Status', value: selectedRequest.status, isStatus: true },
          { label: 'Description', value: selectedRequest.description || selectedRequest.reason || 'N/A' },
        ];

      case 'guest-entry':
        return [
          ...commonFields,
          { label: 'Guest Name', value: selectedRequest.guest_name || 'N/A' },
          { label: 'Guest Relation', value: selectedRequest.guest_relation || 'N/A' },
          { label: 'Guest ID Proof', value: selectedRequest.guest_id_proof || 'N/A' },
          { label: 'Primary Contact Number', value: selectedRequest.guest_contact || 'N/A' },
          { label: 'Guest Arrival Date', value: selectedRequest.guest_arrival_date ? formatDate(selectedRequest.guest_arrival_date) : 'N/A' },
          { label: 'Submitted Date & Time', value: formatDateTime(selectedRequest.created_at) },
          { label: 'Status', value: selectedRequest.status, isStatus: true },
          { label: 'Description', value: selectedRequest.description || 'N/A' },
        ];

      case 'sports':
        return [
          { label: 'Request ID', value: selectedRequest.request_id || `#${selectedRequest.id}` },
          { label: 'Student Name', value: selectedRequest.student_name || selectedRequest.user_name },
          { label: 'Room', value: selectedRequest.room || 'N/A' },
          { label: 'Court Name', value: selectedRequest.court_name || 'N/A' },
          { label: 'Date', value: selectedRequest.booking_date ? formatDate(selectedRequest.booking_date) : 'N/A' },
          { label: 'Slot (Time)', value: selectedRequest.slot_time || 'N/A' },
        ];

      case 'laundry':
        return [
          { label: 'Request ID', value: selectedRequest.request_id || `#${selectedRequest.id}` },
          { label: 'Student Name', value: selectedRequest.student_name || selectedRequest.user_name },
          { label: 'Room', value: selectedRequest.room || 'N/A' },
          { label: 'Total Number of Clothes', value: selectedRequest.total_clothes?.toString() || 'N/A' },
          { label: 'Total Weight', value: selectedRequest.total_weight || 'N/A' },
          { label: 'Submitted Date & Time', value: formatDateTime(selectedRequest.created_at) },
          { label: 'Status', value: selectedRequest.status, isStatus: true },
        ];

      default:
        return commonFields;
    }
  };

  const renderRequestItem = ({ item }: { item: RequestItem }) => {
    const sid = asText(item.student_id ?? item.student_uid) || '—';
    const reqId = asText(item.request_id) || `#${item.id}`;
    const studentName = asText(item.student_name || item.user_name) || 'Unknown';
    const room = asText(item.room) || 'N/A';

    const renderPreviewLines = () => {
      switch (activeTab) {
        case 'housekeeping':
          return (
            <>
              <View style={styles.detailRow}><Icon name="format-list-bulleted" size={14} color={theme.colors.textSecondary} /><Text style={styles.detailText}>Request ID: {reqId}</Text></View>
              <View style={styles.detailRow}><Icon name="tag" size={14} color={theme.colors.textSecondary} /><Text style={styles.detailText}>Option: {item.request_option || item.type || '—'}</Text></View>
              <View style={styles.detailRow}><Icon name="account" size={14} color={theme.colors.textSecondary} /><Text style={styles.detailText}>{studentName}</Text></View>
              <View style={styles.detailRow}><Icon name="door" size={14} color={theme.colors.textSecondary} /><Text style={styles.detailText}>Room {room}</Text></View>
            </>
          );
        case 'maintenance':
          return (
            <>
              <View style={styles.detailRow}><Icon name="format-list-bulleted" size={14} color={theme.colors.textSecondary} /><Text style={styles.detailText}>Request ID: {reqId}</Text></View>
              <View style={styles.detailRow}><Icon name="text-box" size={14} color={theme.colors.textSecondary} /><Text style={styles.detailText}>Title: {item.title || item.description || item.reason || '—'}</Text></View>
              <View style={styles.detailRow}><Icon name="account" size={14} color={theme.colors.textSecondary} /><Text style={styles.detailText}>{studentName}</Text></View>
              <View style={styles.detailRow}><Icon name="door" size={14} color={theme.colors.textSecondary} /><Text style={styles.detailText}>Room {room}</Text></View>
            </>
          );
        case 'outpass':
          return (
            <>
              <View style={styles.detailRow}><Icon name="account" size={14} color={theme.colors.textSecondary} /><Text style={styles.detailText}>{studentName}</Text></View>
              <View style={styles.detailRow}><Icon name="identifier" size={14} color={theme.colors.textSecondary} /><Text style={styles.detailText}>Student ID: {sid}</Text></View>
              <View style={styles.detailRow}><Icon name="format-list-bulleted" size={14} color={theme.colors.textSecondary} /><Text style={styles.detailText}>Request ID: {reqId}</Text></View>
            </>
          );
        case 'leave':
          return (
            <>
              <View style={styles.detailRow}><Icon name="account" size={14} color={theme.colors.textSecondary} /><Text style={styles.detailText}>{studentName}</Text></View>
              <View style={styles.detailRow}><Icon name="identifier" size={14} color={theme.colors.textSecondary} /><Text style={styles.detailText}>Student ID: {sid}</Text></View>
              <View style={styles.detailRow}><Icon name="format-list-bulleted" size={14} color={theme.colors.textSecondary} /><Text style={styles.detailText}>Request ID: {reqId}</Text></View>
            </>
          );
        case 'guest-entry':
          return (
            <>
              <View style={styles.detailRow}><Icon name="account" size={14} color={theme.colors.textSecondary} /><Text style={styles.detailText}>{studentName}</Text></View>
              <View style={styles.detailRow}><Icon name="identifier" size={14} color={theme.colors.textSecondary} /><Text style={styles.detailText}>Student ID: {sid}</Text></View>
              <View style={styles.detailRow}><Icon name="format-list-bulleted" size={14} color={theme.colors.textSecondary} /><Text style={styles.detailText}>Request ID: {reqId}</Text></View>
              <View style={styles.detailRow}><Icon name="account-group" size={14} color={theme.colors.textSecondary} /><Text style={styles.detailText}>Guests: {item.number_of_guests ?? '—'}</Text></View>
              <View style={styles.detailRow}><Icon name="calendar" size={14} color={theme.colors.textSecondary} /><Text style={styles.detailText}>Arrival: {item.guest_arrival_date ? formatDate(item.guest_arrival_date) : '—'}</Text></View>
            </>
          );
        case 'sports':
          return (
            <>
              <View style={styles.detailRow}><Icon name="account" size={14} color={theme.colors.textSecondary} /><Text style={styles.detailText}>{studentName}</Text></View>
              <View style={styles.detailRow}><Icon name="identifier" size={14} color={theme.colors.textSecondary} /><Text style={styles.detailText}>Student ID: {sid}</Text></View>
              <View style={styles.detailRow}><Icon name="format-list-bulleted" size={14} color={theme.colors.textSecondary} /><Text style={styles.detailText}>Request ID: {reqId}</Text></View>
              <View style={styles.detailRow}><Icon name="basketball" size={14} color={theme.colors.textSecondary} /><Text style={styles.detailText}>Court: {item.court_name || '—'}</Text></View>
            </>
          );
        case 'laundry':
          return (
            <>
              <View style={styles.detailRow}><Icon name="account" size={14} color={theme.colors.textSecondary} /><Text style={styles.detailText}>{studentName}</Text></View>
              <View style={styles.detailRow}><Icon name="identifier" size={14} color={theme.colors.textSecondary} /><Text style={styles.detailText}>Student ID: {sid}</Text></View>
              <View style={styles.detailRow}><Icon name="format-list-bulleted" size={14} color={theme.colors.textSecondary} /><Text style={styles.detailText}>Request ID: {reqId}</Text></View>
              <View style={styles.detailRow}><Icon name="scale" size={14} color={theme.colors.textSecondary} /><Text style={styles.detailText}>Weight: {item.total_weight ?? '—'}</Text></View>
            </>
          );
        default:
          return (
            <>
              <View style={styles.detailRow}><Icon name="format-list-bulleted" size={14} color={theme.colors.textSecondary} /><Text style={styles.detailText}>Request ID: {reqId}</Text></View>
              <View style={styles.detailRow}><Icon name="account" size={14} color={theme.colors.textSecondary} /><Text style={styles.detailText}>{studentName}</Text></View>
              <View style={styles.detailRow}><Icon name="door" size={14} color={theme.colors.textSecondary} /><Text style={styles.detailText}>Room {room}</Text></View>
            </>
          );
      }
    };

    return (
      <TouchableOpacity
        style={styles.requestCard}
        onPress={() => handleRequestPress(item)}
        activeOpacity={0.7}
      >
        <View style={styles.requestHeader}>
          <View style={styles.requestIdContainer}>
            <Text style={styles.requestId}>{reqId}</Text>
          </View>
          <View style={[styles.statusBadge, { backgroundColor: getStatusColor(item.status) + '20' }]}>
            <Text style={[styles.statusText, { color: getStatusColor(item.status) }]}>
              {item.status}
            </Text>
          </View>
        </View>
        <View style={styles.requestDetails}>
          {renderPreviewLines()}
        </View>
        <View style={styles.requestFooter}>
          <View style={styles.timeContainer}>
            <Icon name="clock-outline" size={14} color={theme.colors.textMuted} />
            <Text style={styles.requestTime}>{formatDate(item.created_at)}</Text>
          </View>
          <GradientButton style={styles.viewButton}>
            <Text style={styles.viewButtonText}>View</Text>
            <Icon name="chevron-right" size={16} color={theme.colors.primary} />
          </GradientButton>
        </View>
      </TouchableOpacity>
    );
  };

  const renderEmptyState = () => (
    <View style={styles.emptyState}>
      <Icon name="clipboard-check-outline" size={64} color={theme.colors.border} />
      <Text style={styles.emptyTitle}>No Requests</Text>
      <Text style={styles.emptySubtitle}>
        No {activeTab.replace('-', ' ')} requests found
      </Text>
    </View>
  );

  return (
    <View style={styles.container}>
      <StaffScreenHeader
        onBack={() => navigation.goBack()}
        onNotificationsPress={() => navigation.navigate('Notifications')}  title="Requests Hub" />

      {/* Search Bar */}
      <View style={styles.searchContainer}>
        <View style={styles.searchInputContainer}>
          <Icon name="magnify" size={20} color={theme.colors.textMuted} />
          <TextInput
            style={styles.searchInput}
            placeholder="Search by name, room, or request ID..."
            value={searchQuery}
            onChangeText={setSearchQuery}
            placeholderTextColor={theme.colors.textMuted}
          />
          {searchQuery.length > 0 && (
            <TouchableOpacity onPress={() => setSearchQuery('')}>
              <Icon name="close-circle" size={18} color={theme.colors.textMuted} />
            </TouchableOpacity>
          )}
        </View>
      </View>

      {/* Tabs */}
      <View style={styles.tabsContainer}>
        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.tabsContent}
        >
          {TABS.map((tab) => (
            <TouchableOpacity
              key={tab.id}
              style={[styles.tab, activeTab === tab.id && styles.tabActive]}
              onPress={() => setActiveTab(tab.id)}
            >
              <Icon
                name={tab.icon}
                size={18}
                color={activeTab === tab.id ? theme.colors.white : theme.colors.textSecondary}
              />
              <Text style={[styles.tabText, activeTab === tab.id && styles.tabTextActive]}>
                {tab.label}
              </Text>
            </TouchableOpacity>
          ))}
        </ScrollView>
      </View>

      {/* Status Filters */}
      <View style={styles.filtersContainer}>
        <ScrollView horizontal showsHorizontalScrollIndicator={false}>
          {STATUS_FILTERS.map((filter) => (
            <TouchableOpacity
              key={filter.id}
              style={[styles.filterPill, statusFilter === filter.id && styles.filterPillActive]}
              onPress={() => setStatusFilter(filter.id)}
            >
              <Text
                style={[
                  styles.filterText,
                  statusFilter === filter.id && styles.filterTextActive,
                ]}
              >
                {filter.label}
              </Text>
            </TouchableOpacity>
          ))}
        </ScrollView>
      </View>

      {/* Section Title */}
      <View style={styles.sectionHeader}>
        <Text style={styles.sectionTitle}>{getTabFullLabel()}</Text>
        <Text style={styles.sectionCount}>{filteredRequests.length} requests</Text>
      </View>

      {/* Request List */}
      <FlatList
        data={filteredRequests}
        keyExtractor={(item) => String(item.id)}
        renderItem={renderRequestItem}
        ListEmptyComponent={!isLoading ? renderEmptyState : null}
        contentContainerStyle={styles.listContent}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
      />

      {/* Request Detail Popup */}
      <Modal
        visible={showPopup}
        animationType="slide"
        transparent
        onRequestClose={() => setShowPopup(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>{getPopupTitle()}</Text>
              <TouchableOpacity onPress={() => setShowPopup(false)}>
                <Icon name="close" size={24} color={theme.colors.text} />
              </TouchableOpacity>
            </View>

            <ScrollView style={styles.modalBody} showsVerticalScrollIndicator={false}>
              {renderPopupFields()?.map((field, index) => (
                <View key={index} style={styles.fieldRow}>
                  <Text style={styles.fieldLabel}>{field.label}</Text>
                  {field.isStatus ? (
                    <View style={[styles.modalStatusBadge, { backgroundColor: getStatusColor(field.value || '') + '20' }]}>
                      <Text style={[styles.modalStatusText, { color: getStatusColor(field.value || '') }]}>
                        {field.value}
                      </Text>
                    </View>
                  ) : (
                    <Text style={styles.fieldValue}>{field.value || 'N/A'}</Text>
                  )}
                </View>
              ))}
            </ScrollView>
          </View>
        </View>
      </Modal>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  header: {
    display: 'none',
  },
  headerRow: {
    display: 'none',
  },
  backButton: {
    display: 'none',
  },
  headerTitle: {
    display: 'none',
  },
  headerSubtitle: {
    display: 'none',
  },
  searchContainer: {
    padding: 16,
    paddingBottom: 8,
    backgroundColor: theme.colors.card,
  },
  searchInputContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: theme.colors.surfaceMuted,
    borderRadius: 12,
    paddingHorizontal: 14,
    paddingVertical: 10,
  },
  searchInput: {
    flex: 1,
    fontSize: 15,
    color: theme.colors.text,
    marginLeft: 10,
  },
  tabsContainer: {
    backgroundColor: theme.colors.card,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
  },
  tabsContent: {
    paddingHorizontal: 12,
    paddingVertical: 12,
    gap: 8,
  },
  tab: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: theme.colors.surfaceMuted,
    marginRight: 8,
  },
  tabActive: {
    backgroundColor: theme.colors.primary,
  },
  tabText: {
    fontSize: 13,
    fontWeight: '500',
    color: theme.colors.textSecondary,
    marginLeft: 6,
  },
  tabTextActive: {
    color: theme.colors.white,
  },
  filtersContainer: {
    paddingHorizontal: 16,
    paddingVertical: 10,
    backgroundColor: theme.colors.card,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
  },
  filterPill: {
    paddingHorizontal: 16,
    paddingVertical: 6,
    borderRadius: 16,
    backgroundColor: theme.colors.surfaceMuted,
    marginRight: 8,
  },
  filterPillActive: {
    backgroundColor: theme.colors.primary,
  },
  filterText: {
    fontSize: 13,
    fontWeight: '500',
    color: theme.colors.textSecondary,
  },
  filterTextActive: {
    color: theme.colors.white,
  },
  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: theme.colors.surfaceMuted,
  },
  sectionTitle: {
    fontSize: 15,
    fontWeight: '600',
    color: theme.colors.text,
  },
  sectionCount: {
    fontSize: 13,
    color: theme.colors.textSecondary,
  },
  listContent: {
    padding: 16,
    flexGrow: 1,
  },
  requestCard: {
    backgroundColor: theme.colors.card,
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  requestHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  requestIdContainer: {
    backgroundColor: theme.colors.surfaceMuted,
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 8,
  },
  requestId: {
    fontSize: 12,
    fontWeight: '600',
    color: theme.colors.textSecondary,
  },
  statusBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
  },
  statusText: {
    fontSize: 12,
    fontWeight: '600',
    textTransform: 'capitalize',
  },
  requestName: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.text,
    marginBottom: 8,
  },
  requestDetails: {
    flexDirection: 'row',
    gap: 16,
    marginBottom: 12,
  },
  detailRow: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  detailText: {
    fontSize: 13,
    color: theme.colors.textSecondary,
    marginLeft: 4,
  },
  requestFooter: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: theme.colors.border,
  },
  timeContainer: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  requestTime: {
    fontSize: 12,
    color: theme.colors.textMuted,
    marginLeft: 4,
  },
  viewButton: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 999,
  },
  viewButtonText: {
    fontSize: 13,
    fontWeight: '600',
    color: theme.colors.primary,
  },
  emptyState: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: 48,
  },
  emptyTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: theme.colors.textSecondary,
    marginTop: 16,
  },
  emptySubtitle: {
    fontSize: 14,
    color: theme.colors.textMuted,
    marginTop: 4,
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'flex-end',
  },
  modalContent: {
    backgroundColor: theme.colors.background,
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    maxHeight: '80%',
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 20,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: theme.colors.text,
  },
  modalBody: {
    padding: 20,
  },
  fieldRow: {
    marginBottom: 16,
  },
  fieldLabel: {
    fontSize: 13,
    fontWeight: '500',
    color: theme.colors.textSecondary,
    marginBottom: 4,
  },
  fieldValue: {
    fontSize: 15,
    fontWeight: '500',
    color: theme.colors.text,
    lineHeight: 22,
  },
  modalStatusBadge: {
    alignSelf: 'flex-start',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 12,
  },
  modalStatusText: {
    fontSize: 14,
    fontWeight: '600',
    textTransform: 'capitalize',
  },
});

export default RequestsHubScreen;
