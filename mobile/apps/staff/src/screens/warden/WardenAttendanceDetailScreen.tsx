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
import { useAuthStore } from '../../store/auth.store';
import { apiService } from '../../services/api.service';
import { Student, Attendance } from '../../types';
import { APP_CONFIG } from '../../config/app.config';
import { colors } from '../../theme/colors';
import { errorHandler } from '../../utils/errorHandler';
import { ErrorState } from '../../components/shared/ErrorState';

type AttendanceStatus = 'present' | 'absent' | 'on_leave';

export const WardenAttendanceDetailScreen = ({ navigation, route }: any) => {
  const { user } = useAuthStore();
  const { roomId, room } = route.params;
  const [students, setStudents] = useState<Student[]>([]);
  const [attendanceData, setAttendanceData] = useState<Record<number, AttendanceStatus>>({});
  const [attendanceNotes, setAttendanceNotes] = useState<Record<number, string>>({});
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<any>(null);

  const fetchStudents = async () => {
    try {
      setError(null);
      const response = await apiService.get<{ data: Student[] }>(`${APP_CONFIG.ENDPOINTS.WARDEN_ROOMS}/${roomId}/students`);
      setStudents(response.data);

      // Initialize attendance data
      const initialAttendance: Record<number, AttendanceStatus> = {};
      response.data.forEach(student => {
        initialAttendance[student.id] = 'present'; // Default to present
      });
      setAttendanceData(initialAttendance);
    } catch (err) {
      console.error('Students fetch error:', err);
      const errorDetails = errorHandler.handleError(err);
      setError(errorDetails);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchStudents();
  }, [roomId]);

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
    const allMarked = students.every(student => attendanceData[student.id]);
    if (!allMarked) return false;

    // Check if notes are provided for absent/leave students
    const absentOrLeaveStudents = students.filter(
      s => attendanceData[s.id] === 'absent' || attendanceData[s.id] === 'on_leave'
    );
    const notesProvided = absentOrLeaveStudents.every(
      s => attendanceNotes[s.id]?.trim()?.length > 0
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
      const attendancePayload = Object.entries(attendanceData).map(([studentId, status]) => ({
        student_id: parseInt(studentId),
        status,
        date: new Date().toISOString().split('T')[0],
        marked_by: user?.name,
        notes: attendanceNotes[parseInt(studentId)] || null,
      }));

      await apiService.post(APP_CONFIG.ENDPOINTS.ATTENDANCE, {
        room_id: roomId,
        attendance: attendancePayload,
      });

      Alert.alert('Success', 'Attendance submitted successfully');
      navigation.goBack();
    } catch (error) {
      Alert.alert('Error', 'Failed to submit attendance. Please try again.');
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

  const StudentCard = ({ student }: { student: Student }) => {
    const isAbsentOrLeave = attendanceData[student.id] === 'absent' || attendanceData[student.id] === 'on_leave';
    
    return (
      <View style={styles.studentCard}>
        <View style={styles.studentHeader}>
          <View>
            <Text style={styles.studentName}>{student.name}</Text>
            <Text style={styles.studentDetails}>
              {student.email} • {student.phone}
            </Text>
          </View>
        </View>

        <View style={styles.radioGroup}>
          <AttendanceRadio studentId={student.id} status="present" />
          <AttendanceRadio studentId={student.id} status="absent" />
          <AttendanceRadio studentId={student.id} status="on_leave" />
        </View>

        {isAbsentOrLeave && (
          <View style={styles.notesContainer}>
            <Text style={styles.notesLabel}>
              {attendanceData[student.id] === 'absent' ? 'Reason for absence' : 'Leave details'} *
            </Text>
            <TextInput
              style={styles.notesInput}
              placeholder={
                attendanceData[student.id] === 'absent'
                  ? 'Enter reason for absence'
                  : 'Enter leave details'
              }
              value={attendanceNotes[student.id] || ''}
              onChangeText={(text) =>
                setAttendanceNotes((prev) => ({ ...prev, [student.id]: text }))
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
      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
          <Text style={styles.backIcon}>←</Text>
        </TouchableOpacity>
        <View style={styles.headerContent}>
          <Text style={styles.roomTitle}>{room.room_number}</Text>
          <Text style={styles.roomSubtitle}>{room.hostel_name} • {students.length} students</Text>
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
          <ActivityIndicator size="large" color={colors.primary} style={styles.loader} />
        ) : error ? (
          <ErrorState error={error} onRetry={fetchStudents} />
        ) : students.length === 0 ? (
          <View style={styles.emptyState}>
            <Text style={styles.emptyText}>No students found in this room</Text>
          </View>
        ) : (
          students.map((student) => (
            <StudentCard key={student.id} student={student} />
          ))
        )}
      </ScrollView>

      {/* Submit Button */}
      <View style={styles.footer}>
        <TouchableOpacity
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
        </TouchableOpacity>
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
    backgroundColor: colors.primary,
    padding: 20,
    paddingTop: 60,
    flexDirection: 'row',
    alignItems: 'center',
  },
  backButton: {
    marginRight: 16,
  },
  backIcon: {
    color: colors.surface,
    fontSize: 24,
  },
  headerContent: {
    flex: 1,
  },
  roomTitle: {
    color: colors.surface,
    fontSize: 20,
    fontWeight: 'bold',
  },
  roomSubtitle: {
    color: colors.surface,
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
    backgroundColor: colors.primary,
    paddingVertical: 16,
    borderRadius: 12,
    alignItems: 'center',
  },
  submitButtonDisabled: {
    backgroundColor: colors.textMuted,
  },
  submitButtonText: {
    color: colors.surface,
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
