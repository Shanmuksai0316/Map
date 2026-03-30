import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  Modal,
} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useAuthStore } from '../../store/auth.store';
import { apiService } from '../../services/api.service';
import { APP_CONFIG } from '../../config/app.config';
import { colors } from '../../theme/colors';
import { format } from 'date-fns';
import { GuardOutpassDetailScreen } from './GuardOutpassDetailScreen';
import { GuardLeaveDetailScreen } from './GuardLeaveDetailScreen';
import { GuardGuestEntryDetailScreen } from './GuardGuestEntryDetailScreen';

interface Outpass {
  id: number;
  student_name: string;
  room_number?: string;
  reason: string;
  status: string;
  out_date: string;
  out_time: string;
  expected_in_date: string;
  expected_in_time: string;
}

interface Leave {
  id: number;
  student_name: string;
  room_number?: string;
  leave_type: string;
  status: string;
  from_date: string;
  to_date: string;
  time: string;
  emergency_contact?: string;
}

interface GuestEntry {
  id: number;
  student_name: string;
  room_number?: string;
  visitor_name: string;
  status: string;
  time: string;
  reason?: string;
  guest_relationship?: string;
  guest_phone?: string;
}

export const LaundryManagerGatePassScreen = ({ navigation }: any) => {
  const [activeTab, setActiveTab] = useState<'outpass' | 'leave' | 'guest'>('outpass');
  const [outpasses, setOutpasses] = useState<Outpass[]>([]);
  const [leaves, setLeaves] = useState<Leave[]>([]);
  const [guestEntries, setGuestEntries] = useState<GuestEntry[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [selectedOutpass, setSelectedOutpass] = useState<Outpass | null>(null);
  const [selectedLeave, setSelectedLeave] = useState<Leave | null>(null);
  const [selectedGuest, setSelectedGuest] = useState<GuestEntry | null>(null);
  const [showOutpassDetail, setShowOutpassDetail] = useState(false);
  const [showLeaveDetail, setShowLeaveDetail] = useState(false);
  const [showGuestDetail, setShowGuestDetail] = useState(false);

  const fetchData = async () => {
    try {
      const [outpassData, leaveData, guestData] = await Promise.all([
        apiService.get<{ data: Outpass[] }>(`${APP_CONFIG.ENDPOINTS.OUTPASSES}?status=active`).catch(() => ({ data: [] })),
        apiService.get<{ data: Leave[] }>(`${APP_CONFIG.ENDPOINTS.LEAVES}?status=active`).catch(() => ({ data: [] })),
        apiService.get<{ data: GuestEntry[] }>(`${APP_CONFIG.ENDPOINTS.GUEST_ENTRIES}?status=active`).catch(() => ({ data: [] })),
      ]);
      setOutpasses(outpassData.data);
      setLeaves(leaveData.data);
      setGuestEntries(guestData.data);
    } catch (error) {
      console.error('Error fetching gate passes:', error);
    } finally {
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, []);

  const onRefresh = () => {
    setRefreshing(true);
    fetchData();
  };

  const getStatusBadge = (status: string) => {
    const statusColors: Record<string, string> = {
      active: '#4CAF50',
      pending: '#FF9800',
      expired: '#F44336',
      approved: '#2196F3',
    };
    return statusColors[status] || colors.textMuted;
  };

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.headerTitle}>Gate Pass</Text>
      </View>

      {/* Tabs */}
      <View style={styles.tabs}>
        <TouchableOpacity
          style={[styles.tab, activeTab === 'outpass' && styles.tabActive]}
          onPress={() => setActiveTab('outpass')}>
          <Text style={[styles.tabText, activeTab === 'outpass' && styles.tabTextActive]}>
            Outpass
          </Text>
          {outpasses.length > 0 && (
            <View style={styles.badge}>
              <Text style={styles.badgeText}>{outpasses.length}</Text>
            </View>
          )}
        </TouchableOpacity>
        <TouchableOpacity
          style={[styles.tab, activeTab === 'leave' && styles.tabActive]}
          onPress={() => setActiveTab('leave')}>
          <Text style={[styles.tabText, activeTab === 'leave' && styles.tabTextActive]}>
            Leave
          </Text>
          {leaves.length > 0 && (
            <View style={styles.badge}>
              <Text style={styles.badgeText}>{leaves.length}</Text>
            </View>
          )}
        </TouchableOpacity>
        <TouchableOpacity
          style={[styles.tab, activeTab === 'guest' && styles.tabActive]}
          onPress={() => setActiveTab('guest')}>
          <Text style={[styles.tabText, activeTab === 'guest' && styles.tabTextActive]}>
            Guest Entry
          </Text>
          {guestEntries.length > 0 && (
            <View style={styles.badge}>
              <Text style={styles.badgeText}>{guestEntries.length}</Text>
            </View>
          )}
        </TouchableOpacity>
      </View>

      {/* Content - Same as GuardGatePassScreen */}
      <ScrollView
        style={styles.content}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}>
        {activeTab === 'outpass' && (
          <View style={styles.tabContent}>
            {outpasses.map((outpass) => (
              <TouchableOpacity
                key={outpass.id}
                style={styles.card}
                onPress={() => {
                  setSelectedOutpass(outpass);
                  setShowOutpassDetail(true);
                }}>
                <View style={styles.cardHeader}>
                  <View>
                    <Text style={styles.cardTitle}>{outpass.student_name}</Text>
                    <Text style={styles.cardSubtitle}>Room {outpass.room_number || 'N/A'}</Text>
                  </View>
                  <View style={[styles.statusBadge, { backgroundColor: getStatusBadge(outpass.status) }]}>
                    <Text style={styles.statusText}>{outpass.status.toUpperCase()}</Text>
                  </View>
                </View>
                <Text style={styles.cardReason}>{outpass.reason}</Text>
                <View style={styles.cardFooter}>
                  <Text style={styles.cardTime}>
                    Exit: {format(new Date(outpass.out_date), 'MMM dd')} {outpass.out_time}
                  </Text>
                  <Text style={styles.cardTime}>
                    Entry: {format(new Date(outpass.expected_in_date), 'MMM dd')} {outpass.expected_in_time}
                  </Text>
                </View>
                <TouchableOpacity
                  style={styles.viewButton}
                  onPress={() => {
                    setSelectedOutpass(outpass);
                    setShowOutpassDetail(true);
                  }}>
                  <Text style={styles.viewButtonText}>View</Text>
                </TouchableOpacity>
              </TouchableOpacity>
            ))}
          </View>
        )}

        {activeTab === 'leave' && (
          <View style={styles.tabContent}>
            {leaves.map((leave) => (
              <TouchableOpacity
                key={leave.id}
                style={styles.card}
                onPress={() => {
                  setSelectedLeave(leave);
                  setShowLeaveDetail(true);
                }}>
                <View style={styles.cardHeader}>
                  <View>
                    <Text style={styles.cardTitle}>{leave.student_name}</Text>
                    <Text style={styles.cardSubtitle}>Room {leave.room_number || 'N/A'}</Text>
                  </View>
                  <View style={[styles.statusBadge, { backgroundColor: getStatusBadge(leave.status) }]}>
                    <Text style={styles.statusText}>{leave.status.toUpperCase()}</Text>
                  </View>
                </View>
                <Text style={styles.cardReason}>Leave Type: {leave.leave_type}</Text>
                <View style={styles.cardFooter}>
                  <Text style={styles.cardTime}>
                    {format(new Date(leave.from_date), 'MMM dd')} - {format(new Date(leave.to_date), 'MMM dd')}
                  </Text>
                  <Text style={styles.cardTime}>Time: {leave.time}</Text>
                </View>
                <TouchableOpacity
                  style={styles.viewButton}
                  onPress={() => {
                    setSelectedLeave(leave);
                    setShowLeaveDetail(true);
                  }}>
                  <Text style={styles.viewButtonText}>View</Text>
                </TouchableOpacity>
              </TouchableOpacity>
            ))}
          </View>
        )}

        {activeTab === 'guest' && (
          <View style={styles.tabContent}>
            {guestEntries.map((guest) => (
              <TouchableOpacity
                key={guest.id}
                style={styles.card}
                onPress={() => {
                  setSelectedGuest(guest);
                  setShowGuestDetail(true);
                }}>
                <View style={styles.cardHeader}>
                  <View>
                    <Text style={styles.cardTitle}>{guest.student_name}</Text>
                    <Text style={styles.cardSubtitle}>Room {guest.room_number || 'N/A'}</Text>
                  </View>
                  <View style={[styles.statusBadge, { backgroundColor: getStatusBadge(guest.status) }]}>
                    <Text style={styles.statusText}>{guest.status.toUpperCase()}</Text>
                  </View>
                </View>
                <Text style={styles.cardReason}>Visitor: {guest.visitor_name}</Text>
                <Text style={styles.cardTime}>Time: {guest.time}</Text>
                <TouchableOpacity
                  style={styles.viewButton}
                  onPress={() => {
                    setSelectedGuest(guest);
                    setShowGuestDetail(true);
                  }}>
                  <Text style={styles.viewButtonText}>View</Text>
                </TouchableOpacity>
              </TouchableOpacity>
            ))}
          </View>
        )}
      </ScrollView>

      {/* Detail Modals */}
      {selectedOutpass && (
        <GuardOutpassDetailScreen
          visible={showOutpassDetail}
          outpass={selectedOutpass}
          onClose={() => setShowOutpassDetail(false)}
        />
      )}
      {selectedLeave && (
        <GuardLeaveDetailScreen
          visible={showLeaveDetail}
          leave={selectedLeave}
          onClose={() => setShowLeaveDetail(false)}
        />
      )}
      {selectedGuest && (
        <GuardGuestEntryDetailScreen
          visible={showGuestDetail}
          guest={selectedGuest}
          onClose={() => setShowGuestDetail(false)}
        />
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
    padding: 20,
    paddingTop: 60,
  },
  headerTitle: {
    color: colors.surface,
    fontSize: 24,
    fontWeight: 'bold',
  },
  tabs: {
    flexDirection: 'row',
    backgroundColor: colors.surface,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  tab: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 16,
    borderBottomWidth: 2,
    borderBottomColor: 'transparent',
  },
  tabActive: {
    borderBottomColor: colors.primary,
  },
  tabText: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textMuted,
  },
  tabTextActive: {
    color: colors.primary,
  },
  badge: {
    backgroundColor: colors.primary,
    borderRadius: 10,
    paddingHorizontal: 6,
    paddingVertical: 2,
    marginLeft: 6,
  },
  badgeText: {
    color: colors.surface,
    fontSize: 10,
    fontWeight: '600',
  },
  content: {
    flex: 1,
  },
  tabContent: {
    padding: 20,
  },
  card: {
    backgroundColor: colors.surface,
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 12,
  },
  cardTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: 4,
  },
  cardSubtitle: {
    fontSize: 14,
    color: colors.textMuted,
  },
  statusBadge: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
  },
  statusText: {
    color: colors.surface,
    fontSize: 10,
    fontWeight: '600',
  },
  cardReason: {
    fontSize: 14,
    color: colors.textPrimary,
    marginBottom: 12,
  },
  cardFooter: {
    marginBottom: 12,
  },
  cardTime: {
    fontSize: 12,
    color: colors.textMuted,
    marginBottom: 4,
  },
  viewButton: {
    backgroundColor: colors.primary,
    paddingVertical: 10,
    borderRadius: 8,
    alignItems: 'center',
  },
  viewButtonText: {
    color: colors.surface,
    fontSize: 14,
    fontWeight: '600',
  },
});

