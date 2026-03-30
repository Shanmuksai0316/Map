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
import { GradientButton } from '../../../shared/components/GradientButton';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { Calendar, DateData } from 'react-native-calendars';
import { apiService } from '../../../shared/services/api.service';
import { APP_CONFIG } from '../../../shared/config/app.config';
import { colors } from '../../../shared/theme/colors';
import { OfflineIndicator } from '../../../shared/components/shared/OfflineIndicator';
import { format } from 'date-fns';
import { useOfflineQueue } from '../../../shared/hooks/useOfflineQueue';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

type AttendanceSessionSummary = {
  id: number;
  hostel_id: number;
  date: string;
  status: string;
  counts: {
    total: number;
    present: number;
    absent: number;
    unmarked: number;
  };
};

type AttendanceRoomProgress = {
  room_id: number;
  room: string;
  counts: {
    total: number;
    present: number;
    absent: number;
    unmarked: number;
  };
  percent_complete: number;
};

export const WardenAttendanceRoomsScreen = ({ navigation }: any) => {
  const { isOnline } = useOfflineQueue();
  const [session, setSession] = useState<AttendanceSessionSummary | null>(null);
  const [rooms, setRooms] = useState<AttendanceRoomProgress[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedDate, setSelectedDate] = useState(format(new Date(), 'yyyy-MM-dd'));
  const [datePickerVisible, setDatePickerVisible] = useState(false);

  const today = new Date();
  const minDate = format(new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000), 'yyyy-MM-dd'); // 7 days ago
  const maxDate = format(today, 'yyyy-MM-dd'); // Today

  const fetchSessionAndRooms = async () => {
    try {
      setError(null);
      
      // Use selected date instead of always fetching today
      const isToday = selectedDate === format(new Date(), 'yyyy-MM-dd');
      const endpoint = isToday 
        ? APP_CONFIG.ENDPOINTS.ATTENDANCE_SESSION_TODAY
        : `${APP_CONFIG.ENDPOINTS.ATTENDANCE_SESSIONS}?date=${selectedDate}`;
      
      const todayResponse = await apiService.get<{ data: AttendanceSessionSummary | null }>(
        endpoint,
      );

      const sessionData = todayResponse.data;
      setSession(sessionData);

      if (sessionData?.id) {
        const roomsResponse = await apiService.get<{ data: AttendanceRoomProgress[] }>(
          `${APP_CONFIG.ENDPOINTS.ATTENDANCE_SESSIONS}/${sessionData.id}/rooms`,
        );
        setRooms(roomsResponse.data);
      } else {
        setRooms([]);
      }
    } catch (error) {
      console.error('Rooms fetch error:', error);
      setError('Unable to load attendance sessions right now.');
      setRooms([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchSessionAndRooms();
  }, [selectedDate]);

  const onRefresh = () => {
    setRefreshing(true);
    fetchSessionAndRooms();
  };

  const handleRoomPress = (room: AttendanceRoomProgress) => {
    // Pass sessionId if available, and always pass the selected date
    navigation.navigate('WardenAttendanceDetail', { 
      sessionId: session?.id || null, 
      roomId: room.room_id, 
      room,
      date: selectedDate, // Pass the selected date
    });
  };

  const RoomCard = ({ room }: { room: AttendanceRoomProgress }) => {
    const occupancyRate = Math.round(room.percent_complete);

    return (
      <TouchableOpacity
        style={styles.roomCard}
        onPress={() => handleRoomPress(room)}>
        <View style={styles.roomHeader}>
          <View>
            <Text style={styles.roomNumber}>{room.room}</Text>
            <Text style={styles.hostelName}>Students: {room.counts.total}</Text>
          </View>
          <View style={styles.occupancyBadge}>
            <Text style={styles.occupancyText}>{occupancyRate}%</Text>
          </View>
        </View>

        <View style={styles.roomStats}>
          <View style={styles.statItem}>
            <Text style={styles.statValue}>{room.counts.present}</Text>
            <Text style={styles.statLabel}>Present</Text>
          </View>
          <View style={styles.statItem}>
            <Text style={styles.statValue}>{room.counts.absent}</Text>
            <Text style={styles.statLabel}>Absent</Text>
          </View>
          <View style={styles.statItem}>
            <Text style={styles.statValue}>{room.counts.unmarked}</Text>
            <Text style={styles.statLabel}>Unmarked</Text>
          </View>
        </View>

        <View style={styles.progressBar}>
          <View
            style={[
              styles.progressFill,
              {
                width: `${occupancyRate}%`,
                backgroundColor: occupancyRate >= 90 ? colors.warning : colors.success,
              },
            ]}
          />
        </View>
      </TouchableOpacity>
    );
  };

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Attendance" />
      <View style={styles.header}>
        <View style={styles.headerContent}>
          <TouchableOpacity
            style={styles.dateSelector}
            onPress={() => setDatePickerVisible(true)}
          >
            <Ionicons name="calendar-outline" size={18} color={colors.primary} />
            <Text style={styles.date}>{format(new Date(selectedDate), 'EEEE, MMMM dd, yyyy')}</Text>
            <Ionicons name="chevron-down-outline" size={16} color={colors.primary} />
          </TouchableOpacity>
          <Text style={styles.subGreeting}>
            {session ? `Status: ${session.status}${isOnline ? '' : ' • Offline'}` : 'No active session found'}
          </Text>
        </View>
      </View>

      {/* Scrollable Content */}
      <ScrollView
        style={styles.scrollView}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>
        <OfflineIndicator />
        
        {/* Room List */}
        <View style={styles.section}>
          {error && (
            <Text style={[styles.sectionTitle, { color: colors.error }]}>{error}</Text>
          )}
          {!loading && !session && !error && (
            <Text style={styles.sectionTitle}>No session for today</Text>
          )}
          {session && (
            <>
              <Text style={styles.sectionTitle}>Rooms ({rooms.length})</Text>
              {rooms.map((room) => (
                <RoomCard key={room.room_id} room={room} />
              ))}
            </>
          )}
        </View>
      </ScrollView>

      {/* Date Picker Modal */}
      <Modal visible={datePickerVisible} transparent animationType="slide">
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <Text style={styles.modalTitle}>Select Attendance Date</Text>
            <Text style={styles.modalSubtitle}>You can mark attendance up to 7 days back</Text>
            <Calendar
              current={selectedDate}
              minDate={minDate}
              maxDate={maxDate}
              onDayPress={(day: DateData) => {
                setSelectedDate(day.dateString);
                setDatePickerVisible(false);
                // Refresh session and rooms with new date
                fetchSessionAndRooms();
              }}
              markedDates={{
                [selectedDate]: { selected: true, selectedColor: colors.primary },
              }}
            />
            <GradientButton
              style={styles.closeButton}
              onPress={() => setDatePickerVisible(false)}
            >
              <Text style={styles.closeButtonText}>Close</Text>
            </GradientButton>
          </View>
        </View>
      </Modal>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  header: {
    backgroundColor: colors.white,
    paddingBottom: 16,
    paddingHorizontal: 20,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  headerContent: {
    width: '100%',
  },
  headerTitle: {
    color: colors.primary,
    fontSize: 24,
    fontWeight: '700',
    marginBottom: 12,
  },
  dateSelector: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 10,
    paddingHorizontal: 12,
    backgroundColor: colors.surface,
    borderRadius: 8,
    alignSelf: 'flex-start',
    marginBottom: 8,
    borderWidth: 1,
    borderColor: colors.border,
  },
  date: {
    color: colors.primary,
    fontSize: 16,
    fontWeight: '500',
  },
  subGreeting: {
    color: colors.textSecondary,
    fontSize: 14,
    marginTop: 4,
  },
  scrollView: {
    flex: 1,
  },
  section: {
    padding: 20,
  },
  sectionTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: colors.textPrimary,
    marginBottom: 16,
  },
  roomCard: {
    backgroundColor: colors.surface,
    padding: 16,
    borderRadius: 12,
    marginBottom: 12,
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  roomHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  roomNumber: {
    fontSize: 18,
    fontWeight: 'bold',
    color: colors.textPrimary,
  },
  hostelName: {
    fontSize: 14,
    color: colors.textMuted,
    marginTop: 2,
  },
  occupancyBadge: {
    backgroundColor: colors.info,
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
  },
  occupancyText: {
    color: colors.surface,
    fontSize: 12,
    fontWeight: '600',
  },
  roomStats: {
    flexDirection: 'row',
    marginBottom: 12,
  },
  statItem: {
    flex: 1,
    alignItems: 'center',
  },
  statValue: {
    fontSize: 18,
    fontWeight: 'bold',
    color: colors.textPrimary,
  },
  statLabel: {
    fontSize: 12,
    color: colors.textMuted,
    marginTop: 2,
  },
  progressBar: {
    height: 4,
    backgroundColor: colors.border,
    borderRadius: 2,
    overflow: 'hidden',
  },
  progressFill: {
    height: '100%',
    borderRadius: 2,
  },
  modalOverlay: {
    flex: 1,
    justifyContent: 'center',
    backgroundColor: 'rgba(0,0,0,0.5)',
  },
  modalContent: {
    margin: 20,
    padding: 20,
    backgroundColor: colors.surface,
    borderRadius: 12,
  },
  modalTitle: {
    fontSize: 18,
    fontWeight: '600',
    marginBottom: 8,
    color: colors.textPrimary,
  },
  modalSubtitle: {
    fontSize: 14,
    color: colors.textSecondary,
    marginBottom: 16,
  },
  closeButton: {
    marginTop: 16,
    padding: 12,
    backgroundColor: colors.primary,
    borderRadius: 8,
    alignItems: 'center',
  },
  closeButtonText: {
    color: colors.surface,
    fontWeight: '600',
  },
});
