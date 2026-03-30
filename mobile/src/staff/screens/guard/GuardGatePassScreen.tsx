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
import { useAuthStore } from '../../../shared/store/auth.store';
import { apiService } from '../../../shared/services/api.service';
import { APP_CONFIG } from '../../../shared/config/app.config';
import { colors } from '../../../shared/theme/colors';
import { format } from 'date-fns';
import { GuardOutpassDetailScreen } from './GuardOutpassDetailScreen';
import { GuardLeaveDetailScreen } from './GuardLeaveDetailScreen';
import { GuardGuestEntryDetailScreen } from './GuardGuestEntryDetailScreen';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

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
  student_id?: string;
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
  student_id?: string;
  room_number?: string;
  visitor_name: string;
  status: string;
  time: string;
  reason?: string;
  guest_relationship?: string;
  guest_phone?: string;
  number_of_guests?: number;
  visit_date?: string;
}

export const GuardGatePassScreen = ({ navigation, route }: any) => {
  const initialTab: 'outpass' | 'leave' | 'guest' =
    route?.params?.initialTab ?? 'outpass';
  const [activeTab, setActiveTab] = useState<'outpass' | 'leave' | 'guest'>(initialTab);
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
  const [error, setError] = useState<string | null>(null);

  const fetchData = async () => {
    try {
      setError(null);
      const [outpassData, leaveData, guestData] = await Promise.all([
        apiService.get<{ data: Outpass[] }>('/guard/outpasses/active').catch((e) => {
          if (e?.response?.status === 401) throw e;
          return { data: [] };
        }),
        apiService.get<{ data: Leave[] }>('/guard/leaves/active').catch((e) => {
          if (e?.response?.status === 401) throw e;
          return { data: [] };
        }),
        apiService.get<{ data: GuestEntry[] }>('/guard/guest-entries/active').catch((e) => {
          if (e?.response?.status === 401) throw e;
          return { data: [] };
        }),
      ]);
      setOutpasses(outpassData?.data ?? []);
      setLeaves(leaveData?.data ?? []);
      setGuestEntries(guestData?.data ?? []);
    } catch (err: any) {
      console.error('Error fetching gate passes:', err);
      const msg = err?.response?.status === 401
        ? 'Session expired. Please log in again.'
        : 'Could not load requests. Pull down to refresh.';
      setError(msg);
      setOutpasses([]);
      setLeaves([]);
      setGuestEntries([]);
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

  const handleDetailClosed = () => {
    setShowOutpassDetail(false);
    setShowLeaveDetail(false);
    setShowGuestDetail(false);
    setSelectedOutpass(null);
    setSelectedLeave(null);
    setSelectedGuest(null);
    fetchData();
  };

  const todayStr = format(new Date(), 'yyyy-MM-dd');
  const sortedLeaves = [...leaves].sort((a, b) => {
    const aToday = a.from_date === todayStr ? 1 : 0;
    const bToday = b.from_date === todayStr ? 1 : 0;
    if (bToday !== aToday) return bToday - aToday;
    return new Date(b.from_date).getTime() - new Date(a.from_date).getTime();
  });

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
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Gate Pass" />
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

      {/* Error banner */}
      {error && (
        <View style={styles.errorBanner}>
          <Text style={styles.errorText}>{error}</Text>
        </View>
      )}

      {/* Content */}
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
                <Text style={styles.cardIdLabel}>Outpass #{outpass.id}</Text>
                <View style={styles.cardHeader}>
                  <View>
                    <Text style={styles.cardTitle}>{outpass.student_name}</Text>
                    <Text style={styles.cardSubtitle}>Room {outpass.room_number || 'N/A'}</Text>
                  </View>
                  <View style={[styles.statusBadge, { backgroundColor: getStatusBadge(outpass.status) + '30' }]}>
                    <Text style={[styles.statusText, { color: getStatusBadge(outpass.status) }]}>{outpass.status.toUpperCase()}</Text>
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
                  <Text style={styles.viewButtonText}>View details</Text>
                </TouchableOpacity>
              </TouchableOpacity>
            ))}
          </View>
        )}

        {activeTab === 'leave' && (
          <View style={styles.tabContent}>
            {sortedLeaves.map((leave) => (
              <TouchableOpacity
                key={leave.id}
                style={styles.card}
                onPress={() => {
                  setSelectedLeave(leave);
                  setShowLeaveDetail(true);
                }}>
                <Text style={styles.cardIdLabel}>Leave #{leave.id}</Text>
                <View style={styles.cardHeader}>
                  <View>
                    <Text style={styles.cardTitle}>{leave.student_name}</Text>
                    <Text style={styles.cardSubtitle}>Room {leave.room_number || 'N/A'}</Text>
                  </View>
                  <View style={[styles.statusBadge, { backgroundColor: getStatusBadge(leave.status) + '30' }]}>
                    <Text style={[styles.statusText, { color: getStatusBadge(leave.status) }]}>{leave.status.toUpperCase()}</Text>
                  </View>
                </View>
                <Text style={styles.cardReason}>Leave Type: {leave.leave_type}</Text>
                <View style={styles.cardFooter}>
                  <Text style={styles.cardTime}>
                    {format(new Date(leave.from_date), 'MMM dd')} - {format(new Date(leave.to_date), 'MMM dd')}
                  </Text>
                </View>
                <TouchableOpacity
                  style={styles.viewButton}
                  onPress={() => {
                    setSelectedLeave(leave);
                    setShowLeaveDetail(true);
                  }}>
                  <Text style={styles.viewButtonText}>View details</Text>
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
                <Text style={styles.cardIdLabel}>Request #{guest.id}</Text>
                <View style={styles.cardHeader}>
                  <View>
                    <Text style={styles.cardTitle}>{guest.student_name}</Text>
                    <Text style={styles.cardSubtitle}>
                      {guest.student_id ? `ID: ${guest.student_id} • ` : ''}Room {guest.room_number || 'N/A'}
                    </Text>
                  </View>
                  <View style={[styles.statusBadge, { backgroundColor: getStatusBadge(guest.status) + '30' }]}>
                    <Text style={[styles.statusText, { color: getStatusBadge(guest.status) }]}>{guest.status.toUpperCase()}</Text>
                  </View>
                </View>
                <Text style={styles.cardReason}>Guests: {guest.number_of_guests ?? 1} • {guest.visitor_name || '—'}</Text>
                <Text style={styles.cardTime}>Date of arrival: {guest.visit_date ? format(new Date(guest.visit_date), 'MMM dd, yyyy') : guest.time || '—'}</Text>
                <TouchableOpacity
                  style={styles.viewButton}
                  onPress={() => {
                    setSelectedGuest(guest);
                    setShowGuestDetail(true);
                  }}>
                  <Text style={styles.viewButtonText}>View details</Text>
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
          onClose={handleDetailClosed}
          onMarkedComplete={handleDetailClosed}
        />
      )}
      {selectedLeave && (
        <GuardLeaveDetailScreen
          visible={showLeaveDetail}
          leave={selectedLeave}
          onClose={handleDetailClosed}
          onMarkedComplete={handleDetailClosed}
        />
      )}
      {selectedGuest && (
        <GuardGuestEntryDetailScreen
          visible={showGuestDetail}
          guest={selectedGuest}
          onClose={handleDetailClosed}
          onMarkEntryComplete={handleDetailClosed}
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
  },
  headerTitle: {
    color: colors.surface,
    fontSize: 24,
    fontWeight: 'bold',
  },
  errorBanner: {
    backgroundColor: '#FEE2E2',
    marginHorizontal: 16,
    marginVertical: 8,
    padding: 12,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#DC2626',
  },
  errorText: {
    color: '#DC2626',
    fontSize: 14,
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
  cardIdLabel: {
    fontSize: 12,
    fontWeight: '600',
    color: colors.textMuted,
    marginBottom: 8,
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
