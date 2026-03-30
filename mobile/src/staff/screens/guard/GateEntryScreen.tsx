/**
 * Gate Entry Screen
 * 
 * Allows Guards to record student entry (IN) at the gate.
 * Supports QR scanning, manual search, and offline mode.
 */

import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  TextInput,
  Alert,
  RefreshControl,
  ActivityIndicator,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useAuthStore } from '../../../shared/store/auth.store';
import { apiService } from '../../../shared/services/api.service';
import { useOfflineQueue } from '../../../shared/hooks/useOfflineQueue';
import { OfflineIndicator } from '../../../shared/components/shared/OfflineIndicator';
import { APP_CONFIG } from '../../../shared/config/app.config';
import { colors } from '../../../shared/theme/colors';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface Student {
  id: number;
  name: string;
  roll_no: string;
  hostel_name: string;
  photo_url?: string;
}

interface GatePass {
  id: number;
  student_id: number;
  student_name: string;
  reason: string;
  status: string;
  valid_until: string;
}

export const GateEntryScreen = ({ navigation }: any) => {
  const { user } = useAuthStore();
  const { addAction, isOnline } = useOfflineQueue();
  const [searchQuery, setSearchQuery] = useState('');
  const [students, setStudents] = useState<Student[]>([]);
  const [loading, setLoading] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [selectedStudent, setSelectedStudent] = useState<Student | null>(null);
  const [gatePass, setGatePass] = useState<GatePass | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const fetchStudents = async () => {
    try {
      setLoading(true);
      const response = await apiService.get<{ data: Student[] }>('/students/search', {
        params: { q: searchQuery, limit: 20 }
      });
      setStudents(response.data);
    } catch (error) {
      console.error('Error fetching students:', error);
      // Show empty state - no mock data in production
      setStudents([]);
    } finally {
      setLoading(false);
    }
  };

  const fetchGatePass = async (studentId: number) => {
    try {
      const response = await apiService.get<{ data: GatePass }>(`/outpasses/student/${studentId}/active`);
      setGatePass(response.data);
    } catch (error) {
      console.error('Error fetching gate pass:', error);
      setGatePass(null);
    }
  };

  useEffect(() => {
    if (searchQuery.length >= 2) {
      const timeoutId = setTimeout(() => {
        fetchStudents();
      }, 300);
      return () => clearTimeout(timeoutId);
    } else {
      setStudents([]);
    }
  }, [searchQuery]);

  const handleStudentSelect = async (student: Student) => {
    setSelectedStudent(student);
    await fetchGatePass(student.id);
  };

  const handleGateEntry = async () => {
    if (!selectedStudent) {
      Alert.alert('Error', 'Please select a student');
      return;
    }

    if (!gatePass) {
      Alert.alert('Error', 'No active gate pass found for this student');
      return;
    }

    setSubmitting(true);

    try {
      const entryData = {
        student_id: selectedStudent.id,
        outpass_id: gatePass.id,
        direction: 'in',
        in_time: new Date().toISOString(),
      };

      if (isOnline) {
        // Direct API call
        await apiService.post('/gate/entries', entryData);
        Alert.alert('Success', 'Student entry recorded successfully');
        setSelectedStudent(null);
        setGatePass(null);
        setSearchQuery('');
      } else {
        // Queue for offline sync
        await addAction('gate_in', entryData);
        Alert.alert('Offline', 'Entry queued for sync when online');
        setSelectedStudent(null);
        setGatePass(null);
        setSearchQuery('');
      }
    } catch (error) {
      console.error('Error recording entry:', error);
      Alert.alert('Error', 'Failed to record entry. Please try again.');
    } finally {
      setSubmitting(false);
    }
  };

  const onRefresh = () => {
    setRefreshing(true);
    if (searchQuery.length >= 2) {
      fetchStudents();
    }
    setRefreshing(false);
  };

  return (
    <ScrollView
      style={styles.container}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
      }>
      <OfflineIndicator />

      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Gate Entry" />

      {/* Search Section */}
      <View style={styles.searchSection}>
        <View style={styles.searchContainer}>
          <Ionicons name="search" size={20} color={colors.gray} style={styles.searchIcon} />
          <TextInput
            style={styles.searchInput}
            placeholder="Search by name or roll number..."
            value={searchQuery}
            onChangeText={setSearchQuery}
            autoCapitalize="none"
            autoCorrect={false}
          />
        </View>
      </View>

      {/* Selected Student */}
      {selectedStudent && (
        <View style={styles.selectedStudentCard}>
          <Text style={styles.cardTitle}>Selected Student</Text>
          <View style={styles.studentInfo}>
            <View style={styles.studentDetails}>
              <Text style={styles.studentName}>{selectedStudent.name}</Text>
              <Text style={styles.studentRoll}>{selectedStudent.roll_no}</Text>
              <Text style={styles.studentHostel}>{selectedStudent.hostel_name}</Text>
            </View>
            <GradientButton
              style={styles.changeButton}
              onPress={() => {
                setSelectedStudent(null);
                setGatePass(null);
              }}>
              <Text style={styles.changeButtonText}>Change</Text>
            </GradientButton>
          </View>

          {/* Gate Pass Info */}
          {gatePass ? (
            <View style={styles.gatePassInfo}>
              <Text style={styles.gatePassTitle}>Active Gate Pass</Text>
              <Text style={styles.gatePassReason}>{gatePass.reason}</Text>
              <Text style={styles.gatePassValid}>
                Valid until: {new Date(gatePass.valid_until).toLocaleString()}
              </Text>
            </View>
          ) : (
            <View style={styles.noGatePass}>
              <Ionicons name="warning" size={20} color={colors.error} />
              <Text style={styles.noGatePassText}>No active gate pass found</Text>
            </View>
          )}
        </View>
      )}

      {/* Student List */}
      {searchQuery.length >= 2 && !selectedStudent && (
        <View style={styles.studentList}>
          <Text style={styles.listTitle}>Search Results</Text>
          {loading ? (
            <ActivityIndicator size="large" color={colors.primary} style={styles.loader} />
          ) : students.length > 0 ? (
            students.map((student) => (
              <TouchableOpacity
                key={student.id}
                style={styles.studentItem}
                onPress={() => handleStudentSelect(student)}>
                <View style={styles.studentItemContent}>
                  <View style={styles.studentItemDetails}>
                    <Text style={styles.studentItemName}>{student.name}</Text>
                    <Text style={styles.studentItemRoll}>{student.roll_no}</Text>
                    <Text style={styles.studentItemHostel}>{student.hostel_name}</Text>
                  </View>
                  <Ionicons name="chevron-forward" size={20} color={colors.gray} />
                </View>
              </TouchableOpacity>
            ))
          ) : (
            <Text style={styles.noResults}>No students found</Text>
          )}
        </View>
      )}

      {/* Entry Button */}
      {selectedStudent && gatePass && (
        <View style={styles.actionSection}>
          <GradientButton
            style={[styles.entryButton, submitting && styles.entryButtonDisabled]}
            onPress={handleGateEntry}
            disabled={submitting}>
            {submitting ? (
              <ActivityIndicator size="small" color={colors.white} />
            ) : (
              <>
                <Ionicons name="log-in" size={20} color={colors.white} />
                <Text style={styles.entryButtonText}>Record Entry</Text>
              </>
            )}
          </GradientButton>
        </View>
      )}
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  searchSection: {
    padding: 16,
    backgroundColor: colors.white,
  },
  searchContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.background,
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  searchIcon: {
    marginRight: 8,
  },
  searchInput: {
    flex: 1,
    fontSize: 16,
    color: colors.text,
  },
  selectedStudentCard: {
    margin: 16,
    padding: 16,
    backgroundColor: colors.white,
    borderRadius: 8,
    shadowColor: colors.black,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  cardTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.text,
    marginBottom: 12,
  },
  studentInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  studentDetails: {
    flex: 1,
  },
  studentName: {
    fontSize: 18,
    fontWeight: '600',
    color: colors.text,
  },
  studentRoll: {
    fontSize: 14,
    color: colors.gray,
    marginTop: 2,
  },
  studentHostel: {
    fontSize: 14,
    color: colors.gray,
    marginTop: 2,
  },
  changeButton: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    backgroundColor: colors.primary,
    borderRadius: 4,
  },
  changeButtonText: {
    color: colors.white,
    fontSize: 14,
    fontWeight: '500',
  },
  gatePassInfo: {
    marginTop: 12,
    padding: 12,
    backgroundColor: colors.success + '10',
    borderRadius: 6,
    borderLeftWidth: 3,
    borderLeftColor: colors.success,
  },
  gatePassTitle: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.success,
  },
  gatePassReason: {
    fontSize: 14,
    color: colors.text,
    marginTop: 4,
  },
  gatePassValid: {
    fontSize: 12,
    color: colors.gray,
    marginTop: 4,
  },
  noGatePass: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: 12,
    padding: 12,
    backgroundColor: colors.error + '10',
    borderRadius: 6,
    borderLeftWidth: 3,
    borderLeftColor: colors.error,
  },
  noGatePassText: {
    fontSize: 14,
    color: colors.error,
    marginLeft: 8,
  },
  studentList: {
    margin: 16,
    backgroundColor: colors.white,
    borderRadius: 8,
    shadowColor: colors.black,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  listTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.text,
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  studentItem: {
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  studentItemContent: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  studentItemDetails: {
    flex: 1,
  },
  studentItemName: {
    fontSize: 16,
    fontWeight: '500',
    color: colors.text,
  },
  studentItemRoll: {
    fontSize: 14,
    color: colors.gray,
    marginTop: 2,
  },
  studentItemHostel: {
    fontSize: 14,
    color: colors.gray,
    marginTop: 2,
  },
  noResults: {
    textAlign: 'center',
    color: colors.gray,
    padding: 20,
    fontSize: 16,
  },
  loader: {
    padding: 20,
  },
  actionSection: {
    padding: 16,
  },
  entryButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.success,
    paddingVertical: 16,
    paddingHorizontal: 24,
    borderRadius: 8,
    shadowColor: colors.black,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.2,
    shadowRadius: 4,
    elevation: 3,
  },
  entryButtonDisabled: {
    backgroundColor: colors.gray,
  },
  entryButtonText: {
    color: colors.white,
    fontSize: 16,
    fontWeight: '600',
    marginLeft: 8,
  },
});
