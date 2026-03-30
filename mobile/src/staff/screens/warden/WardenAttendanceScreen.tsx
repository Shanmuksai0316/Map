import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  ActivityIndicator,
  Modal,
  TextInput,
  Alert,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { Calendar, DateData } from 'react-native-calendars';
import { apiService } from '../../../shared/services/api.service';
import { colors } from '../../../shared/theme/colors';
import { theme } from '../../../shared/theme/theme';
import { format } from 'date-fns';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface Room {
  id: number;
  room_no: string;
  total_students: number;
  unmarked: number;
  floor?: string;
}

interface Student {
  id: number;
  name: string;
  student_uid: string;
  on_leave?: boolean; // Flag indicating if student is on approved leave
}

interface AttendanceRecord {
  student_id: number;
  status: 'P' | 'A' | 'L' | null;
  comments?: string;
}

interface HistoryStats {
  hostel_name: string;
  status: string;
  present: number;
  absent: number;
  late: number;
  total: number;
}

type ViewMode = 'take' | 'history';

const TODAY_STR = format(new Date(), 'yyyy-MM-dd');

export const WardenAttendanceScreen = ({ navigation }: any) => {
  const [viewMode, setViewMode] = useState<ViewMode>('take');
  const [rooms, setRooms] = useState<Room[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [roomSearch, setRoomSearch] = useState('');

  const [selectedRoom, setSelectedRoom] = useState<Room | null>(null);
  const [roomStudents, setRoomStudents] = useState<Student[]>([]);
  const [attendance, setAttendance] = useState<Record<number, AttendanceRecord>>({});
  const [commentModalVisible, setCommentModalVisible] = useState(false);
  const [currentStudentId, setCurrentStudentId] = useState<number | null>(null);
  const [comment, setComment] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const [selectedDate, setSelectedDate] = useState(TODAY_STR);
  const [historyStats, setHistoryStats] = useState<HistoryStats | null>(null);
  const [datePickerVisible, setDatePickerVisible] = useState(false);

  const today = new Date();
  const minDate = format(new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000), 'yyyy-MM-dd');
  const maxDate = format(today, 'yyyy-MM-dd');

  const filteredRooms = roomSearch.trim()
    ? rooms.filter(r => (r.room_no || '').toLowerCase().includes(roomSearch.trim().toLowerCase()))
    : rooms;

  const fetchRooms = useCallback(async () => {
    try {
      setLoading(true);
      const res = await apiService.get<any>('/mobile/warden/rooms');
      setRooms(res?.data ?? []);
    } catch {
      setRooms([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  const fetchHistory = useCallback(async (date: string) => {
    try {
      const res = await apiService.get<any>(`/mobile/warden/attendance/history?date=${date}`);
      setHistoryStats(res?.data ?? null);
    } catch {
      setHistoryStats(null);
    }
  }, []);

  useEffect(() => {
    fetchRooms();
  }, [fetchRooms]);

  useEffect(() => {
    if (viewMode === 'history') {
      fetchHistory(selectedDate);
    }
  }, [viewMode, selectedDate, fetchHistory]);

  const handleRoomPress = async (room: Room) => {
    setSelectedRoom(room);
    setLoading(true);

    try {
      // Include date parameter when fetching students
      const res = await apiService.get<any>(`/mobile/warden/rooms/${room.id}/students?date=${selectedDate}`);
      const students: Student[] = (res?.data ?? []).map((s: any) => ({
        ...s,
        id: Number(s.id),
      }));

      const initial: Record<number, AttendanceRecord> = {};
      students.forEach(s => {
        // If student is on approved leave, automatically mark as 'L' (Leave)
        if (s.on_leave) {
          initial[s.id] = { student_id: s.id, status: 'L' };
        } else {
          initial[s.id] = { student_id: s.id, status: null };
        }
      });

      setRoomStudents(students);
      setAttendance(initial);
    } catch {
      setRoomStudents([]);
      setAttendance({});
    } finally {
      setLoading(false);
    }
  };

  const handleAttendanceSelect = (id: number, status: 'P' | 'A' | 'L') => {
    if (status !== 'P') {
      setCurrentStudentId(id);
      setComment(attendance[id]?.comments ?? '');
      setCommentModalVisible(true);
    }

    setAttendance(prev => ({
      ...prev,
      [id]: { ...prev[id], status },
    }));
  };

  const handleSaveComment = () => {
    if (currentStudentId) {
      setAttendance(prev => ({
        ...prev,
        [currentStudentId]: {
          ...prev[currentStudentId],
          comments: comment,
        },
      }));
    }
    setComment('');
    setCurrentStudentId(null);
    setCommentModalVisible(false);
  };

  const submitAttendance = async () => {
    if (Object.values(attendance).some(a => !a.status)) {
      Alert.alert('Incomplete', 'Please mark all students');
      return;
    }

    try {
      setSubmitting(true);
      const payload = {
        date: selectedDate, // Use selected date instead of today
        attendance: Object.values(attendance).map(a => ({
          student_id: Number(a.student_id),
          status: a.status,
          comments: a.comments || null,
        })),
      };
      
      await apiService.post(`/mobile/warden/rooms/${selectedRoom?.id}/attendance`, payload);

      Alert.alert('Success', 'Attendance submitted');
      setSelectedRoom(null);
      fetchRooms();
      if (viewMode === 'history') {
        fetchHistory(selectedDate);
      }
    } catch (error: any) {
      console.error('[WardenAttendance] Submit error:', error);
      const errorMessage = error?.response?.data?.message || error?.message || 'Failed to submit attendance';
      Alert.alert('Error', errorMessage);
    } finally {
      setSubmitting(false);
    }
  };

  if (selectedRoom) {
    return (
      <View style={styles.container}>
        <View style={[styles.dateSelector, { paddingTop: Math.max(insets.top, 12) + 12 }]}>
          <TouchableOpacity
            style={styles.dateButton}
            onPress={() => setDatePickerVisible(true)}
          >
            <Ionicons name="calendar-outline" size={20} color={colors.primary} />
            <Text style={styles.dateText}>
              {format(new Date(selectedDate), 'dd MMM yyyy')}
            </Text>
          </TouchableOpacity>
        </View>
        <ScrollView style={styles.content}>
          {roomStudents.map(s => (
            <View key={s.id} style={styles.studentCard}>
              <View style={styles.studentHeader}>
                <Text style={styles.studentName}>{s.name}</Text>
                {s.on_leave && (
                  <View style={styles.leaveBadge}>
                    <Text style={styles.leaveBadgeText}>On Leave</Text>
                  </View>
                )}
              </View>
              <View style={styles.attendanceButtons}>
                {(['P', 'A', 'L'] as const).map(st => (
                  <GradientButton
                    key={st}
                    style={[
                      styles.attendanceButton,
                      attendance[s.id]?.status === st && styles.attendanceActive,
                      s.on_leave && st !== 'L' && styles.attendanceDisabled, // Disable P and A if student is on leave
                    ]}
                    onPress={() => handleAttendanceSelect(s.id, st)}
                    disabled={s.on_leave && st !== 'L'} // Disable P and A if student is on leave
                  >
                    <Text>{st}</Text>
                  </GradientButton>
                ))}
              </View>
            </View>
          ))}
        </ScrollView>

        <GradientButton
          style={styles.submitButton}
          disabled={submitting}
          onPress={submitAttendance}
        >
          {submitting ? (
            <ActivityIndicator />
          ) : (
            <Text style={styles.submitButtonText}>Submit</Text>
          )}
        </GradientButton>

        <Modal visible={commentModalVisible} transparent>
          <View style={styles.modalOverlay}>
            <View style={styles.modalContent}>
              <TextInput
                value={comment}
                onChangeText={setComment}
                placeholder="Enter comment"
                style={styles.commentInput}
                multiline
              />
              <TouchableOpacity onPress={handleSaveComment}>
                <Text>Save</Text>
              </TouchableOpacity>
            </View>
          </View>
        </Modal>

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
                  // Refresh students list with new date
                  if (selectedRoom) {
                    handleRoomPress(selectedRoom);
                  }
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
  }

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Attendance" />
      <View style={[styles.dateSelector, { paddingTop: 12 }]}>
        <Text style={styles.dateLabel}>Today&apos;s attendance</Text>
        <TouchableOpacity
          style={styles.dateButton}
          onPress={() => setDatePickerVisible(true)}
        >
          <Ionicons name="calendar-outline" size={20} color={colors.primary} />
          <Text style={styles.dateText}>
            {format(new Date(selectedDate), 'EEEE, dd MMM yyyy')}
          </Text>
          <Ionicons name="chevron-down-outline" size={16} color={colors.primary} />
        </TouchableOpacity>
      </View>
      <View style={styles.searchContainer}>
        <Ionicons name="search-outline" size={20} color={colors.textMuted} style={styles.searchIcon} />
        <TextInput
          style={styles.searchInput}
          placeholder="Search by room number"
          placeholderTextColor={colors.textMuted}
          value={roomSearch}
          onChangeText={setRoomSearch}
        />
      </View>
      <ScrollView
        style={styles.roomsScroll}
        contentContainerStyle={styles.roomsGrid}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={fetchRooms} />
        }
      >
        {filteredRooms.map(room => (
          <TouchableOpacity
            key={room.id}
            style={styles.roomCard}
            onPress={() => handleRoomPress(room)}
          >
            <Text style={styles.roomCardTitle}>Room {room.room_no}</Text>
            <Text style={styles.roomCardSub}>Unmarked: {room.unmarked}</Text>
          </TouchableOpacity>
        ))}
      </ScrollView>

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
                // Refresh rooms list if needed
                fetchRooms();
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
  container: { flex: 1, backgroundColor: colors.background },
  content: { padding: 16 },
  dateSelector: { 
    padding: 16, 
    backgroundColor: colors.surface, 
    borderBottomWidth: 1, 
    borderBottomColor: '#E0E0E0',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  dateLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textSecondary,
    marginBottom: 8,
  },
  dateButton: { 
    flexDirection: 'row', 
    alignItems: 'center', 
    justifyContent: 'space-between', 
    gap: 8, 
    padding: 12, 
    backgroundColor: colors.background, 
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
  },
  dateText: { fontSize: 16, fontWeight: '600', color: colors.text, flex: 1 },
  searchContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.surface,
    marginHorizontal: 16,
    marginTop: 12,
    paddingHorizontal: 12,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
  },
  searchIcon: { marginRight: 8 },
  searchInput: {
    flex: 1,
    paddingVertical: 10,
    fontSize: 16,
    color: colors.text,
  },
  roomsScroll: { flex: 1 },
  roomsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    padding: 16,
    gap: 12,
    justifyContent: 'space-between',
  },
  roomCard: { 
    width: '48%',
    padding: 16, 
    borderRadius: 12, 
    backgroundColor: colors.surface, 
    borderWidth: 1,
    borderColor: colors.border,
  },
  roomCardTitle: { fontSize: 16, fontWeight: '600', color: colors.text },
  roomCardSub: { fontSize: 14, color: colors.textMuted, marginTop: 4 },
  studentCard: { padding: 16, marginBottom: 12, backgroundColor: colors.surface },
  studentHeader: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 8 },
  studentName: { fontSize: 16, fontWeight: '600', flex: 1 },
  leaveBadge: { backgroundColor: '#FFA500', paddingHorizontal: 8, paddingVertical: 4, borderRadius: 4 },
  leaveBadgeText: { color: '#FFF', fontSize: 12, fontWeight: '600' },
  attendanceButtons: { flexDirection: 'row', gap: 12 },
  attendanceButton: { flex: 1, padding: 12, borderWidth: 1, borderRadius: 8, alignItems: 'center' },
  attendanceActive: { backgroundColor: colors.primary },
  attendanceDisabled: { opacity: 0.5 },
  submitButton: {
    padding: 16,
    backgroundColor: '#D79F24',
    alignItems: 'center',
    borderRadius: 12,
    ...theme.shadows.medium,
  },
  submitButtonText: {
    color: colors.primary,
    fontSize: 16,
    fontWeight: '600',
  },
  modalOverlay: { flex: 1, justifyContent: 'center', backgroundColor: 'rgba(0,0,0,0.5)' },
  modalContent: { margin: 20, padding: 20, backgroundColor: colors.surface, borderRadius: 12 },
  modalTitle: { fontSize: 18, fontWeight: '600', marginBottom: 8 },
  modalSubtitle: { fontSize: 14, color: '#666', marginBottom: 16 },
  commentInput: { borderWidth: 1, borderRadius: 8, padding: 12 },
  closeButton: { marginTop: 16, padding: 12, backgroundColor: colors.primary, borderRadius: 8, alignItems: 'center' },
  closeButtonText: { color: '#FFF', fontWeight: '600' },
});

export default WardenAttendanceScreen;
