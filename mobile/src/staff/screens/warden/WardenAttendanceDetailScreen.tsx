import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  Alert,
  ActivityIndicator,
  TextInput,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import { format } from 'date-fns';
import { apiService } from '../../../shared/services/api.service';
import { APP_CONFIG } from '../../../shared/config/app.config';
import { colors } from '../../../shared/theme/colors';
import { errorHandler } from '../../../shared/utils/errorHandler';
import { ErrorState } from '../../../shared/components/shared/ErrorState';
import { useOfflineQueue } from '../../../shared/hooks/useOfflineQueue';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

type AttendanceStatus = 'present' | 'absent' | 'on_leave';
type RosterStudent = {
  student_id: number;
  name: string;
  roll_no?: string;
  mark?: 'present' | 'absent';
  leave?: boolean;
  uid_masked?: string;
};

export const WardenAttendanceDetailScreen = ({ navigation, route }: any) => {
  const { roomId, room, sessionId, date } = route.params || {};
  
  const [students, setStudents] = useState<RosterStudent[]>([]);
  const [attendanceData, setAttendanceData] = useState<Record<number, AttendanceStatus>>({});
  const [attendanceNotes, setAttendanceNotes] = useState<Record<number, string>>({});
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<any>(null);
  const { addAction, isOnline } = useOfflineQueue();

  const generateIdempotencyKey = (studentId: number) =>
    `mark-${sessionId || 'no-session'}-${roomId}-${studentId}-${Date.now()}`;

  const fetchStudents = async () => {
    try {
      setError(null);
      setLoading(true);

      // If sessionId exists, use the V2 roster endpoint
      // Otherwise, use the legacy endpoint with date parameter
      let studentsList: RosterStudent[] = [];
      
      if (sessionId) {
        const response = await apiService.get<{ data: { room: string; students: RosterStudent[] } }>(
          `${APP_CONFIG.ENDPOINTS.ATTENDANCE_SESSIONS}/${sessionId}/rooms/${roomId}/roster`,
        );
        studentsList = response.data.students || [];
      } else {
        // Use legacy endpoint with date parameter
        const dateParam = date || format(new Date(), 'yyyy-MM-dd');
        try {
          const endpoint = `${APP_CONFIG.ENDPOINTS.WARDEN_ROOMS}/${roomId}/students?date=${dateParam}`;
          
          const legacyResponse = await apiService.get<{ data: any[]; room?: any }>(
            endpoint,
          );
          
          // apiService.get returns response.data from axios, so legacyResponse is already { data: [...], room: {...} }
          const studentsData = Array.isArray(legacyResponse?.data) ? legacyResponse.data : [];
          
          if (studentsData.length === 0) {
            setStudents([]);
            setAttendanceData({});
            setLoading(false);
            setRefreshing(false);
            return;
          }
          
          // Transform legacy response to match RosterStudent format
          studentsList = studentsData.map((s: any) => {
            // Map attendance_status to mark format
            let mark: 'present' | 'absent' | undefined = undefined;
            if (s.attendance_status === 'P') {
              mark = 'present';
            } else if (s.attendance_status === 'A') {
              mark = 'absent';
            }
            
            const studentId = parseInt(String(s.id), 10);
            if (isNaN(studentId)) {
              console.warn('[WardenAttendanceDetail] Invalid student ID:', s.id);
              return null;
            }
            
            return {
              student_id: studentId,
              name: s.name || 'Unknown',
              roll_no: s.roll_no || '',
              mark: mark,
              leave: s.on_leave || false,
            };
          }).filter((s): s is RosterStudent => s !== null);
          
          // Also set notes from pre-recorded attendance
          const notesMap: Record<number, string> = {};
          studentsData.forEach((s: any) => {
            if (s.attendance_notes && s.id) {
              const studentId = parseInt(String(s.id), 10);
              if (!isNaN(studentId)) {
                notesMap[studentId] = s.attendance_notes;
              }
            }
          });
          if (Object.keys(notesMap).length > 0) {
            setAttendanceNotes((prev) => ({ ...prev, ...notesMap }));
          }
        } catch (legacyErr) {
          console.error('[WardenAttendanceDetail] Legacy endpoint error:', legacyErr);
          setError({
            message: 'Failed to fetch students. Please check your connection and try again.',
            details: (legacyErr as any)?.response?.data || legacyErr,
          });
          setStudents([]);
          setAttendanceData({});
          setLoading(false);
          setRefreshing(false);
          return;
        }
      }

      setStudents(studentsList);

      // Initialize attendance data with pre-recorded values
      const initialAttendance: Record<number, AttendanceStatus> = {};
      studentsList.forEach(student => {
        if (student.leave) {
          initialAttendance[student.student_id] = 'on_leave';
          return;
        }
        // Use pre-recorded mark if available, otherwise default to present
        if (student.mark === 'present' || student.mark === 'absent') {
          initialAttendance[student.student_id] = student.mark;
        } else {
          initialAttendance[student.student_id] = 'present'; // Default to present
        }
      });
      
      setAttendanceData(initialAttendance);
    } catch (err) {
      console.error('[WardenAttendanceDetail] Students fetch error:', err);
      const errorDetails = errorHandler.handleError(err);
      setError(errorDetails);
      setStudents([]);
      setAttendanceData({});
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    if (roomId) {
      fetchStudents();
    }
  }, [roomId, sessionId, date]);

  const onRefresh = () => {
    setRefreshing(true);
    fetchStudents();
  };

  const handleAttendanceChange = (studentId: number, status: AttendanceStatus) => {
    setAttendanceData(prev => ({
      ...prev,
      [studentId]: status,
    }));
  };

  const canSubmit = () => {
    // Check if all students are marked
    const allMarked = students.every(student => attendanceData[student.student_id]);
    if (!allMarked) return false;

    // Check if notes are provided for absent/leave students
    const absentOrLeaveStudents = students.filter(
      s => attendanceData[s.student_id] === 'absent' || attendanceData[s.student_id] === 'on_leave'
    );
    const notesProvided = absentOrLeaveStudents.every(
      s => attendanceNotes[s.student_id]?.trim()?.length > 0
    );
    
    return notesProvided;
  };

  const handleSubmit = async () => {
    if (!canSubmit()) {
      Alert.alert(
        'Validation Error',
        'Please mark all students and provide reasons for absent/leave students.',
      );
      return;
    }

    Alert.alert(
      'Submit Attendance',
      `Mark attendance for ${Object.keys(attendanceData).length} students?`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Submit',
          onPress: submitAttendance,
        },
      ]
    );
  };

  const submitAttendance = async () => {
    setSubmitting(true);
    try {
      const marks = Object.entries(attendanceData);
      const selectedDateValue = date || format(new Date(), 'yyyy-MM-dd');

      // If no sessionId, use legacy endpoint
      if (!sessionId) {
        const payload = {
          date: selectedDateValue,
          attendance: marks
            .map(([studentId, status]) => ({
              student_id: parseInt(studentId, 10),
              status: status === 'present' ? 'P' : status === 'on_leave' ? 'L' : 'A',
              comments: attendanceNotes[parseInt(studentId, 10)] || null,
            })),
        };

        await apiService.post(
          `${APP_CONFIG.ENDPOINTS.WARDEN_ROOMS}/${roomId}/attendance`,
          payload,
        );

        Alert.alert('Success', 'Attendance submitted successfully');
        navigation.goBack();
        return;
      }

      // If offline, queue marks and exit early
      if (!isOnline) {
        await Promise.all(
          marks
            .filter(([, status]) => status !== 'on_leave')
            .map(async ([studentId, status]) => {
              const markPayload = {
                session_id: sessionId,
                room_id: roomId,
                student_id: parseInt(studentId, 10),
                status: status === 'present' ? 'present' : 'absent',
                note: attendanceNotes[parseInt(studentId, 10)] || null,
                marked_at: new Date().toISOString(),
              };

              await addAction('attendance_mark', markPayload);
            }),
        );

        Alert.alert(
          'Queued Offline',
          'Marks queued for sync. Submit will complete when back online.'
        );
        navigation.goBack();
        return;
      }

      // Push marks first (skip on_leave to avoid sending invalid payload)
      await Promise.all(
        marks
          .filter(([, status]) => status !== 'on_leave')
          .map(async ([studentId, status]) => {
            const markPayload = {
              student_id: parseInt(studentId, 10),
              mark: status === 'present' ? 'present' : 'absent',
              idempotency_key: generateIdempotencyKey(parseInt(studentId, 10)),
            };

            await apiService.post(
              `${APP_CONFIG.ENDPOINTS.ATTENDANCE_SESSIONS}/${sessionId}/rooms/${roomId}/mark`,
              markPayload,
            );
          }),
      );

      // Submit the room once all marks are sent
      await apiService.post(
        `${APP_CONFIG.ENDPOINTS.ATTENDANCE_SESSIONS}/${sessionId}/rooms/${roomId}/submit`,
        {},
      );

      Alert.alert('Success', 'Attendance submitted successfully');
      navigation.goBack();
    } catch (error) {
      console.error('Submit attendance error:', error);
      const errorMessage = (error as any)?.response?.data?.message || 'Failed to submit attendance. Please try again.';
      Alert.alert('Error', errorMessage);
    } finally {
      setSubmitting(false);
    }
  };

  const AttendanceRadio = ({ studentId, status }: { studentId: number; status: AttendanceStatus }) => (
    <TouchableOpacity
      style={[
        styles.radioOption,
        attendanceData[studentId] === status && styles.radioSelected,
      ]}
      onPress={() => handleAttendanceChange(studentId, status)}>
      <View style={styles.radioCircle}>
        {attendanceData[studentId] === status && <View style={styles.radioDot} />}
      </View>
      <Text
        style={[
          styles.radioLabel,
          attendanceData[studentId] === status && styles.radioLabelSelected,
        ]}>
        {status === 'present' ? 'Present (P)' : status === 'absent' ? 'Absent (A)' : 'Leave (L)'}
      </Text>
    </TouchableOpacity>
  );

  const StudentCard = ({ student }: { student: RosterStudent }) => {
    const isAbsentOrLeave =
      attendanceData[student.student_id] === 'absent' ||
      attendanceData[student.student_id] === 'on_leave';
    
    return (
      <View style={styles.studentCard}>
        <View style={styles.studentHeader}>
          <View>
            <Text style={styles.studentName}>{student.name}</Text>
            <Text style={styles.studentDetails}>
              UID: {student.uid_masked || '***'}
            </Text>
          </View>
        </View>

        <View style={styles.radioGroup}>
          <AttendanceRadio studentId={student.student_id} status="present" />
          <AttendanceRadio studentId={student.student_id} status="absent" />
          <AttendanceRadio studentId={student.student_id} status="on_leave" />
        </View>

        {isAbsentOrLeave && (
          <View style={styles.notesContainer}>
            <Text style={styles.notesLabel}>
              {attendanceData[student.student_id] === 'absent' ? 'Reason for absence' : 'Leave details'} *
            </Text>
            <TextInput
              style={styles.notesInput}
              placeholder={
                attendanceData[student.student_id] === 'absent'
                  ? 'Enter reason for absence'
                  : 'Enter leave details'
              }
              value={attendanceNotes[student.student_id] || ''}
              onChangeText={(text) =>
                setAttendanceNotes((prev) => ({ ...prev, [student.student_id]: text }))
              }
              multiline
              numberOfLines={3}
              placeholderTextColor={colors.textMuted}
            />
          </View>
        )}
      </View>
    );
  };

  const presentCount = Object.values(attendanceData).filter(status => status === 'present').length;
  const absentCount = Object.values(attendanceData).filter(status => status === 'absent').length;
  const leaveCount = Object.values(attendanceData).filter(status => status === 'on_leave').length;

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Attendance Details" />
      <View style={styles.header}>
        <View style={styles.headerContent}>
          <Text style={styles.roomTitle}>{room.room || room.room_number || 'Room'}</Text>
          <Text style={styles.roomSubtitle}>{students.length} students</Text>
        </View>
      </View>

      {/* Summary */}
      <View style={styles.summary}>
        <View style={styles.summaryItem}>
          <Text style={[styles.summaryNumber, { color: colors.success }]}>{presentCount}</Text>
          <Text style={styles.summaryLabel}>Present</Text>
        </View>
        <View style={styles.summaryItem}>
          <Text style={[styles.summaryNumber, { color: colors.error }]}>{absentCount}</Text>
          <Text style={styles.summaryLabel}>Absent</Text>
        </View>
        <View style={styles.summaryItem}>
          <Text style={[styles.summaryNumber, { color: colors.warning }]}>{leaveCount}</Text>
          <Text style={styles.summaryLabel}>Leave</Text>
        </View>
      </View>

      {/* Students List */}
      <ScrollView
        style={styles.studentsList}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>
        {loading ? (
          <View style={styles.loaderContainer}>
            <ActivityIndicator size="large" color={colors.primary} />
            <Text style={styles.loaderText}>Loading students...</Text>
          </View>
        ) : error ? (
          <View style={styles.emptyState}>
            <Text style={styles.emptyText}>Error loading students</Text>
            <Text style={styles.errorDetails}>{error?.message || 'Unknown error'}</Text>
            <GradientButton style={styles.retryButton} onPress={fetchStudents}>
              <Text style={styles.retryButtonText}>Retry</Text>
            </GradientButton>
          </View>
        ) : students.length === 0 ? (
          <View style={styles.emptyState}>
            <Text style={styles.emptyText}>No students found in this room</Text>
            <Text style={styles.emptySubtext}>
              {date ? `Date: ${format(new Date(date), 'dd MMM yyyy')}` : 'Please select a date'}
            </Text>
            <Text style={styles.emptySubtext}>
              {sessionId ? `Session ID: ${sessionId}` : 'No session for this date'}
            </Text>
            <Text style={styles.emptySubtext}>
              Room ID: {roomId}
            </Text>
            {error && (
              <Text style={styles.errorDetails}>
                Error: {error?.message || 'Unknown error'}
              </Text>
            )}
            <GradientButton style={styles.retryButton} onPress={fetchStudents}>
              <Text style={styles.retryButtonText}>Retry</Text>
            </GradientButton>
          </View>
        ) : (
          students.map((student) => (
            <StudentCard key={student.student_id} student={student} />
          ))
        )}
      </ScrollView>

      {/* Submit Button */}
      <View style={styles.footer}>
        <GradientButton
          style={[
            styles.submitButton,
            (!canSubmit() || submitting) && styles.submitButtonDisabled,
          ]}
          onPress={handleSubmit}
          disabled={!canSubmit() || submitting}>
          {submitting ? (
            <ActivityIndicator size="small" color={colors.surface} />
          ) : (
            <Text style={styles.submitButtonText}>Submit Attendance</Text>
          )}
        </GradientButton>
      </View>
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
    padding: 20,
    flexDirection: 'row',
    alignItems: 'center',
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  headerContent: {
    flex: 1,
  },
  roomTitle: {
    color: colors.primary,
    fontSize: 20,
    fontWeight: 'bold',
  },
  roomSubtitle: {
    color: colors.textSecondary,
    fontSize: 14,
    opacity: 0.8,
    marginTop: 2,
  },
  summary: {
    backgroundColor: colors.surface,
    padding: 16,
    flexDirection: 'row',
    justifyContent: 'space-around',
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  summaryItem: {
    alignItems: 'center',
  },
  summaryNumber: {
    fontSize: 24,
    fontWeight: 'bold',
  },
  summaryLabel: {
    fontSize: 12,
    color: colors.textMuted,
    marginTop: 2,
  },
  studentsList: {
    flex: 1,
  },
  loader: {
    marginTop: 40,
  },
  loaderContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
  },
  loaderText: {
    marginTop: 16,
    fontSize: 16,
    color: colors.textSecondary,
  },
  studentCard: {
    backgroundColor: colors.surface,
    padding: 16,
    marginHorizontal: 20,
    marginVertical: 6,
    borderRadius: 12,
    elevation: 1,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.1,
    shadowRadius: 2,
  },
  studentHeader: {
    marginBottom: 12,
  },
  studentName: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.textPrimary,
  },
  studentDetails: {
    fontSize: 12,
    color: colors.textMuted,
    marginTop: 2,
  },
  radioGroup: {
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  radioOption: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
    paddingVertical: 8,
    paddingHorizontal: 4,
  },
  radioSelected: {
    backgroundColor: colors.surfaceMuted,
    borderRadius: 8,
  },
  radioCircle: {
    width: 20,
    height: 20,
    borderRadius: 10,
    borderWidth: 2,
    borderColor: colors.border,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 8,
  },
  radioDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: colors.primary,
  },
  radioLabel: {
    fontSize: 12,
    color: colors.textMuted,
  },
  radioLabelSelected: {
    color: colors.primary,
    fontWeight: '600',
  },
  footer: {
    backgroundColor: colors.surface,
    padding: 20,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  submitButton: {
    backgroundColor: '#D79F24',
    paddingVertical: 16,
    borderRadius: 12,
    alignItems: 'center',
  },
  submitButtonDisabled: {
    opacity: 0.6,
  },
  submitButtonText: {
    color: colors.primary,
    fontSize: 16,
    fontWeight: '600',
  },
  notesContainer: {
    marginTop: 12,
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  notesLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: 8,
  },
  notesInput: {
    backgroundColor: colors.surfaceMuted,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 8,
    padding: 12,
    fontSize: 14,
    color: colors.textPrimary,
    minHeight: 80,
    textAlignVertical: 'top',
  },
  emptyState: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
    marginTop: 100,
  },
  emptyText: {
    fontSize: 16,
    color: colors.textMuted,
    textAlign: 'center',
  },
});
