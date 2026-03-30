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
import { useAuthStore } from '../../../shared/store/auth.store';
import { apiService } from '../../../shared/services/api.service';
import { APP_CONFIG } from '../../../shared/config/app.config';
import { format } from 'date-fns';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { colors } from '../../../shared/theme/colors';
import { errorHandler } from '../../../shared/utils/errorHandler';
import { ErrorState } from '../../../shared/components/shared/ErrorState';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface SportsFacility {
  id: number;
  name: string;
  type: string;
  capacity: number;
  hostel_name?: string;
}

interface FacilityOccupancy {
  facility_id: number;
  facility_name: string;
  capacity: number;
  current_bookings_count: number;
  upcoming_bookings_count: number;
  total_active_bookings: number;
  occupancy_percentage: number;
  active_blockouts_count: number;
  current_bookings: Array<{
    id: number;
    student_name: string;
    student_uid?: string;
    start_at: string;
    end_at: string;
    purpose?: string;
  }>;
  upcoming_bookings: Array<{
    id: number;
    student_name: string;
    student_uid?: string;
    start_at: string;
    end_at: string;
    purpose?: string;
  }>;
  active_blockouts: Array<{
    id: number;
    start_at: string;
    end_at: string;
    reason?: string;
  }>;
}

interface NoShowAlert {
  id: number;
  student_name: string;
  student_uid?: string;
  start_at: string;
  minutes_late: number;
  status: 'no_show' | 'approaching';
}

interface EventWaitlist {
  event_id: number;
  event_name: string;
  capacity: number;
  registered_count: number;
  available_spots: number;
  count: number;
  data: Array<{
    id: number;
    student_name: string;
    student_uid?: string;
    waitlist_position: number;
    enrolled_at: string;
    notes?: string;
  }>;
}

export const SportsFacilityMonitoringScreen = ({ navigation }: any) => {
  const { user } = useAuthStore();
  const [facilities, setFacilities] = useState<SportsFacility[]>([]);
  const [selectedFacility, setSelectedFacility] = useState<number | null>(null);
  const [occupancy, setOccupancy] = useState<FacilityOccupancy | null>(null);
  const [noShowAlerts, setNoShowAlerts] = useState<NoShowAlert[]>([]);
  const [events, setEvents] = useState<any[]>([]);
  const [waitlists, setWaitlists] = useState<EventWaitlist[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<any>(null);
  const [tab, setTab] = useState<'facilities' | 'events' | 'alerts'>('facilities');

  const fetchFacilities = async () => {
    try {
      setError(null);
      const response = await apiService.get<{ data: SportsFacility[] }>(
        APP_CONFIG.ENDPOINTS.SPORTS_FACILITIES
      );
      setFacilities(response.data || []);
      if (response.data && response.data.length > 0 && !selectedFacility) {
        setSelectedFacility(response.data[0].id);
      }
    } catch (err) {
      console.error('Failed to fetch facilities:', err);
      const errorDetails = errorHandler.handleError(err);
      setError(errorDetails);
      setFacilities([]);
    }
  };

  const fetchOccupancy = async (facilityId: number) => {
    try {
      const response = await apiService.get<{ data: FacilityOccupancy }>(
        `${APP_CONFIG.ENDPOINTS.SPORTS_FACILITIES}/${facilityId}/occupancy`
      );
      setOccupancy(response.data);
    } catch (err) {
      console.error('Failed to fetch occupancy:', err);
      // Don't set error for occupancy, just set to null
      setOccupancy(null);
    }
  };

  const fetchNoShowAlerts = async (facilityId: number) => {
    try {
      const response = await apiService.get<{ data: NoShowAlert[], count: number }>(
        `${APP_CONFIG.ENDPOINTS.SPORTS_FACILITIES}/${facilityId}/no-show-alerts`
      );
      setNoShowAlerts(response.data || []);
    } catch (err) {
      console.error('Failed to fetch no-show alerts:', err);
      // Don't set error for alerts, just set to empty
      setNoShowAlerts([]);
    }
  };

  const fetchEvents = async () => {
    try {
      const response = await apiService.get<{ data: any[] } | { data: { data: any[]; links: any; meta: any } }>(
        `${APP_CONFIG.ENDPOINTS.SPORTS_EVENTS || APP_CONFIG.ENDPOINTS.SPORTS}/events?status=active&per_page=20`
      );
      // Handle Laravel pagination: response.data may be { data: [...], links: {...}, meta: {...} }
      // Or direct array: { data: [...] }
      const eventsData = Array.isArray(response.data) 
        ? response.data 
        : (response.data?.data || []);
      setEvents(eventsData);
    } catch (error) {
      console.error('Failed to fetch events:', error);
      setEvents([]);
    }
  };

  const fetchEventWaitlists = async () => {
    try {
      const eventPromises = events.map(event =>
        apiService.get<EventWaitlist>(
          `${APP_CONFIG.ENDPOINTS.SPORTS_EVENTS || APP_CONFIG.ENDPOINTS.SPORTS}/events/${event.id}/waitlist`
        ).then(res => ({ ...res, event_id: event.id, event_name: event.name }))
          .catch(() => null)
      );
      const waitlistResults = await Promise.all(eventPromises);
      setWaitlists(waitlistResults.filter(Boolean) as EventWaitlist[]);
    } catch (error) {
      console.error('Failed to fetch waitlists:', error);
      setWaitlists([]);
    }
  };

  useEffect(() => {
    fetchFacilities();
    fetchEvents();
  }, []);

  useEffect(() => {
    if (selectedFacility) {
      setLoading(true);
      Promise.all([
        fetchOccupancy(selectedFacility),
        fetchNoShowAlerts(selectedFacility),
      ]).finally(() => setLoading(false));
    }
  }, [selectedFacility]);

  useEffect(() => {
    if (tab === 'events' && events.length > 0) {
      fetchEventWaitlists();
    }
  }, [tab, events]);

  const onRefresh = () => {
    setRefreshing(true);
    Promise.all([
      fetchFacilities(),
      fetchEvents(),
      selectedFacility ? Promise.all([
        fetchOccupancy(selectedFacility),
        fetchNoShowAlerts(selectedFacility),
      ]) : Promise.resolve(),
    ]).finally(() => setRefreshing(false));
  };

  const getOccupancyColor = (percentage: number) => {
    if (percentage >= 90) return colors.error;
    if (percentage >= 70) return colors.warning;
    return colors.success;
  };

  const FacilityOccupancyCard = ({ occupancy }: { occupancy: FacilityOccupancy }) => (
    <View style={styles.occupancyCard}>
      <View style={styles.occupancyHeader}>
        <Text style={styles.facilityName}>{occupancy.facility_name}</Text>
        <View style={[styles.occupancyBadge, { backgroundColor: getOccupancyColor(occupancy.occupancy_percentage) + '20' }]}>
          <Ionicons name="people-outline" size={16} color={getOccupancyColor(occupancy.occupancy_percentage)} />
          <Text style={[styles.occupancyText, { color: getOccupancyColor(occupancy.occupancy_percentage) }]}>
            {occupancy.occupancy_percentage.toFixed(0)}%
          </Text>
        </View>
      </View>

      <View style={styles.statsRow}>
        <View style={styles.statItem}>
          <Ionicons name="fitness-outline" size={20} color={colors.primary} />
          <Text style={styles.statValue}>{occupancy.current_bookings_count}</Text>
          <Text style={styles.statLabel}>In Use</Text>
        </View>
        <View style={styles.statItem}>
          <Ionicons name="time-outline" size={20} color={colors.warning} />
          <Text style={styles.statValue}>{occupancy.upcoming_bookings_count}</Text>
          <Text style={styles.statLabel}>Upcoming</Text>
        </View>
        <View style={styles.statItem}>
          <Ionicons name="ban-outline" size={20} color={colors.error} />
          <Text style={styles.statValue}>{occupancy.active_blockouts_count}</Text>
          <Text style={styles.statLabel}>Blocked</Text>
        </View>
      </View>

      {occupancy.current_bookings.length > 0 && (
        <View style={styles.bookingsSection}>
          <Text style={styles.sectionTitle}>Currently In Use</Text>
          {occupancy.current_bookings.map((booking) => (
            <View key={booking.id} style={styles.bookingItem}>
              <Ionicons name="person-outline" size={16} color={colors.primary} />
              <View style={styles.bookingInfo}>
                <Text style={styles.bookingStudent}>{booking.student_name}</Text>
                <Text style={styles.bookingTime}>
                  Until {format(new Date(booking.end_at), 'HH:mm')}
                </Text>
              </View>
            </View>
          ))}
        </View>
      )}

      {occupancy.upcoming_bookings.length > 0 && (
        <View style={styles.bookingsSection}>
          <Text style={styles.sectionTitle}>Upcoming</Text>
          {occupancy.upcoming_bookings.slice(0, 3).map((booking) => (
            <View key={booking.id} style={styles.bookingItem}>
              <Ionicons name="calendar-outline" size={16} color={colors.info} />
              <View style={styles.bookingInfo}>
                <Text style={styles.bookingStudent}>{booking.student_name}</Text>
                <Text style={styles.bookingTime}>
                  {format(new Date(booking.start_at), 'MMM dd, HH:mm')}
                </Text>
              </View>
            </View>
          ))}
        </View>
      )}
    </View>
  );

  const NoShowAlertCard = ({ alert }: { alert: NoShowAlert }) => (
    <View style={styles.alertCard}>
      <View style={styles.alertHeader}>
        <View style={styles.alertInfo}>
          <Text style={styles.alertStudent}>{alert.student_name}</Text>
          <Text style={styles.alertTime}>
            Started: {format(new Date(alert.start_at), 'MMM dd, HH:mm')}
          </Text>
        </View>
        <View style={[styles.alertBadge, alert.status === 'no_show' ? styles.noShowBadge : styles.approachingBadge]}>
          <Ionicons
            name={alert.status === 'no_show' ? 'alert-circle' : 'time-outline'}
            size={16}
            color={alert.status === 'no_show' ? colors.error : colors.warning}
          />
          <Text style={[styles.alertBadgeText, { color: alert.status === 'no_show' ? colors.error : colors.warning }]}>
            {alert.minutes_late} min late
          </Text>
        </View>
      </View>
    </View>
  );

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Facility Monitoring" />

      {/* Tabs */}
      <View style={styles.tabs}>
        <TouchableOpacity
          style={[styles.tab, tab === 'facilities' && styles.tabActive]}
          onPress={() => setTab('facilities')}>
          <Text style={[styles.tabText, tab === 'facilities' && styles.tabTextActive]}>
            Facilities
          </Text>
        </TouchableOpacity>
        <TouchableOpacity
          style={[styles.tab, tab === 'events' && styles.tabActive]}
          onPress={() => setTab('events')}>
          <Text style={[styles.tabText, tab === 'events' && styles.tabTextActive]}>
            Events
          </Text>
        </TouchableOpacity>
        <TouchableOpacity
          style={[styles.tab, tab === 'alerts' && styles.tabActive]}
          onPress={() => setTab('alerts')}>
          <View style={styles.tabWithBadge}>
            <Text style={[styles.tabText, tab === 'alerts' && styles.tabTextActive]}>
              Alerts
            </Text>
            {noShowAlerts.length > 0 && (
              <View style={styles.badge}>
                <Text style={styles.badgeText}>{noShowAlerts.length}</Text>
              </View>
            )}
          </View>
        </TouchableOpacity>
      </View>

      {/* Content */}
      {loading ? (
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color={colors.primary} />
          <Text style={styles.loadingText}>Loading...</Text>
        </View>
      ) : error && tab === 'facilities' ? (
        <ErrorState error={error} onRetry={fetchFacilities} />
      ) : (
        <ScrollView
          style={styles.content}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
          }>
          {tab === 'facilities' && (
            <>
              {/* Facility Selector */}
              <View style={styles.facilitySelector}>
                <Text style={styles.selectorLabel}>Select Facility:</Text>
                <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.facilityScroll}>
                  {facilities.map((facility) => (
                    <TouchableOpacity
                      key={facility.id}
                      style={[
                        styles.facilityChip,
                        selectedFacility === facility.id && styles.facilityChipActive,
                      ]}
                      onPress={() => setSelectedFacility(facility.id)}>
                      <Text
                        style={[
                          styles.facilityChipText,
                          selectedFacility === facility.id && styles.facilityChipTextActive,
                        ]}>
                        {facility.name}
                      </Text>
                    </TouchableOpacity>
                  ))}
                </ScrollView>
              </View>

              {/* Occupancy Data */}
              {occupancy && <FacilityOccupancyCard occupancy={occupancy} />}
            </>
          )}

          {tab === 'events' && (
            <View style={styles.section}>
              <Text style={styles.sectionTitle}>Event Waitlists</Text>
              {waitlists.length === 0 ? (
                <View style={styles.emptyState}>
                  <Ionicons name="calendar-outline" size={48} color={colors.textMuted} />
                  <Text style={styles.emptyTitle}>No Waitlists</Text>
                  <Text style={styles.emptySubtitle}>No events with waitlists found</Text>
                </View>
              ) : (
                <>
                  {waitlists.map((waitlist) => (
                    <View key={waitlist.event_id} style={styles.waitlistCard}>
                      <View style={styles.waitlistHeader}>
                        <Text style={styles.waitlistEventName}>{waitlist.event_name || 'Unknown Event'}</Text>
                        <View style={styles.waitlistStats}>
                          <Text style={styles.waitlistStatText}>
                            {waitlist.registered_count || 0}/{waitlist.capacity || 0} registered
                          </Text>
                          <Text style={styles.waitlistStatText}>
                            {waitlist.available_spots || 0} spots available
                          </Text>
                        </View>
                      </View>
                      {(waitlist.count || 0) > 0 ? (
                        <View style={styles.waitlistItems}>
                          <Text style={styles.waitlistTitle}>
                            Waitlist ({waitlist.count} {waitlist.count === 1 ? 'student' : 'students'})
                          </Text>
                          {waitlist.data?.slice(0, 5).map((item) => (
                            <View key={item.id} style={styles.waitlistItem}>
                              <View style={styles.waitlistPosition}>
                                <Text style={styles.waitlistPositionText}>#{item.waitlist_position}</Text>
                              </View>
                              <View style={styles.waitlistItemInfo}>
                                <Text style={styles.waitlistStudentName}>{item.student_name}</Text>
                                {item.student_uid && (
                                  <Text style={styles.waitlistStudentUid}>{item.student_uid}</Text>
                                )}
                              </View>
                            </View>
                          ))}
                          {waitlist.count > 5 && (
                            <Text style={styles.waitlistMore}>
                              +{waitlist.count - 5} more
                            </Text>
                          )}
                        </View>
                      ) : (
                        <Text style={styles.waitlistEmpty}>No waitlist</Text>
                      )}
                    </View>
                  ))}
                </>
              )}
            </View>
          )}

          {tab === 'alerts' && (
            <View style={styles.section}>
              <Text style={styles.sectionTitle}>No-Show Alerts</Text>
              {!selectedFacility ? (
                <View style={styles.emptyState}>
                  <Ionicons name="alert-circle-outline" size={48} color={colors.textMuted} />
                  <Text style={styles.emptyTitle}>Select a Facility</Text>
                  <Text style={styles.emptySubtitle}>
                    Choose a facility to view no-show alerts
                  </Text>
                </View>
              ) : noShowAlerts.length === 0 ? (
                <View style={styles.emptyState}>
                  <Ionicons name="checkmark-circle-outline" size={48} color={colors.success} />
                  <Text style={styles.emptyTitle}>All Clear</Text>
                  <Text style={styles.emptySubtitle}>No no-show alerts</Text>
                </View>
              ) : (
                <>
                  {noShowAlerts.map((alert) => (
                    <NoShowAlertCard key={alert.id} alert={alert} />
                  ))}
                </>
              )}
            </View>
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
  tabs: {
    flexDirection: 'row',
    backgroundColor: colors.white,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  tab: {
    flex: 1,
    paddingVertical: 16,
    alignItems: 'center',
    borderBottomWidth: 2,
    borderBottomColor: 'transparent',
  },
  tabActive: {
    borderBottomColor: colors.primary,
  },
  tabText: {
    fontSize: 14,
    fontWeight: '500',
    color: colors.textSecondary,
  },
  tabTextActive: {
    color: colors.primary,
    fontWeight: '600',
  },
  tabWithBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  badge: {
    backgroundColor: colors.error,
    borderRadius: 10,
    paddingHorizontal: 6,
    paddingVertical: 2,
    minWidth: 20,
    alignItems: 'center',
  },
  badgeText: {
    color: colors.white,
    fontSize: 10,
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
  facilitySelector: {
    backgroundColor: colors.white,
    paddingVertical: 16,
    paddingHorizontal: 16,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  selectorLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.text,
    marginBottom: 12,
  },
  facilityScroll: {
    maxHeight: 50,
  },
  facilityChip: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: colors.background,
    marginRight: 8,
    borderWidth: 1,
    borderColor: colors.border,
  },
  facilityChipActive: {
    backgroundColor: colors.primary,
    borderColor: colors.primary,
  },
  facilityChipText: {
    fontSize: 14,
    fontWeight: '500',
    color: colors.text,
  },
  facilityChipTextActive: {
    color: colors.white,
    fontWeight: '600',
  },
  section: {
    padding: 16,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: colors.text,
    marginBottom: 16,
  },
  occupancyCard: {
    backgroundColor: colors.white,
    marginHorizontal: 16,
    marginTop: 16,
    marginBottom: 16,
    borderRadius: 12,
    padding: 16,
    elevation: 2,
    shadowColor: colors.black,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  occupancyHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 16,
  },
  facilityName: {
    fontSize: 20,
    fontWeight: 'bold',
    color: colors.text,
    flex: 1,
  },
  occupancyBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 16,
    gap: 6,
  },
  occupancyText: {
    fontSize: 14,
    fontWeight: '600',
  },
  statsRow: {
    flexDirection: 'row',
    justifyContent: 'space-around',
    marginBottom: 16,
    paddingTop: 16,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  statItem: {
    alignItems: 'center',
  },
  statValue: {
    fontSize: 24,
    fontWeight: 'bold',
    color: colors.text,
    marginTop: 8,
    marginBottom: 4,
  },
  statLabel: {
    fontSize: 12,
    color: colors.textSecondary,
  },
  bookingsSection: {
    marginTop: 16,
    paddingTop: 16,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  bookingItem: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 12,
    paddingVertical: 8,
  },
  bookingInfo: {
    flex: 1,
    marginLeft: 12,
  },
  bookingStudent: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.text,
    marginBottom: 2,
  },
  bookingTime: {
    fontSize: 12,
    color: colors.textSecondary,
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
  alertCard: {
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
  alertHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
  },
  alertInfo: {
    flex: 1,
    marginRight: 12,
  },
  alertStudent: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.text,
    marginBottom: 4,
  },
  alertTime: {
    fontSize: 12,
    color: colors.textSecondary,
  },
  alertBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 16,
    gap: 6,
  },
  noShowBadge: {
    backgroundColor: colors.error + '20',
  },
  approachingBadge: {
    backgroundColor: colors.warning + '20',
  },
  alertBadgeText: {
    fontSize: 12,
    fontWeight: '600',
  },
  waitlistCard: {
    backgroundColor: colors.white,
    marginBottom: 16,
    borderRadius: 12,
    padding: 16,
    elevation: 2,
    shadowColor: colors.black,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  waitlistHeader: {
    marginBottom: 12,
  },
  waitlistEventName: {
    fontSize: 18,
    fontWeight: 'bold',
    color: colors.text,
    marginBottom: 8,
  },
  waitlistStats: {
    flexDirection: 'row',
    gap: 16,
  },
  waitlistStatText: {
    fontSize: 12,
    color: colors.textSecondary,
  },
  waitlistItems: {
    marginTop: 12,
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  waitlistTitle: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.text,
    marginBottom: 12,
  },
  waitlistItem: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 12,
    paddingVertical: 8,
  },
  waitlistPosition: {
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: colors.primary + '20',
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
  },
  waitlistPositionText: {
    fontSize: 12,
    fontWeight: '600',
    color: colors.primary,
  },
  waitlistItemInfo: {
    flex: 1,
  },
  waitlistStudentName: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.text,
    marginBottom: 2,
  },
  waitlistStudentUid: {
    fontSize: 12,
    color: colors.textSecondary,
  },
  waitlistMore: {
    fontSize: 12,
    color: colors.textSecondary,
    fontStyle: 'italic',
    marginTop: 4,
  },
  waitlistEmpty: {
    fontSize: 14,
    color: colors.textSecondary,
    fontStyle: 'italic',
    textAlign: 'center',
    paddingVertical: 20,
  },
});
