/**
 * Emergency Exit Modal
 * 
 * Allows Guards to quickly record emergency exits for students.
 * Includes student search and reason selection.
 */

import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  Modal,
  TouchableOpacity,
  StyleSheet,
  TextInput,
  ScrollView,
  Alert,
  ActivityIndicator,
} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { apiService } from '../../services/api.service';
import { APP_CONFIG } from '../../config/app.config';
import { colors } from '../../theme/colors';
import { errorHandler } from '../../utils/errorHandler';

interface Student {
  id: number;
  name: string;
  roll_no: string;
  hostel_name: string;
}

interface EmergencyExitModalProps {
  visible: boolean;
  onClose: () => void;
  onEmergencyExit: (studentId: number, reason: string) => void;
}

const EMERGENCY_REASONS = [
  'Medical Emergency',
  'Family Emergency',
  'Security Threat',
  'Natural Disaster',
  'Fire Emergency',
  'Other Emergency',
];

export const EmergencyExitModal = ({
  visible,
  onClose,
  onEmergencyExit,
}: EmergencyExitModalProps) => {
  const [searchQuery, setSearchQuery] = useState('');
  const [students, setStudents] = useState<Student[]>([]);
  const [selectedStudent, setSelectedStudent] = useState<Student | null>(null);
  const [selectedReason, setSelectedReason] = useState('');
  const [customReason, setCustomReason] = useState('');
  const [loading, setLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  const fetchStudents = async () => {
    if (searchQuery.length < 2) {
      setStudents([]);
      return;
    }

    try {
      setLoading(true);
      const response = await apiService.get<{ data: Student[] }>(
        `${APP_CONFIG.ENDPOINTS.WARDEN_STUDENTS}?search=${encodeURIComponent(searchQuery)}&limit=10`
      );
      setStudents(response.data || []);
    } catch (error) {
      console.error('Error fetching students:', error);
      // Don't show error for search, just clear results
      setStudents([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    const timeoutId = setTimeout(() => {
      fetchStudents();
    }, 300);
    return () => clearTimeout(timeoutId);
  }, [searchQuery]);

  const handleStudentSelect = (student: Student) => {
    setSelectedStudent(student);
    setSearchQuery(student.name);
    setStudents([]);
  };

  const handleReasonSelect = (reason: string) => {
    setSelectedReason(reason);
    if (reason !== 'Other Emergency') {
      setCustomReason('');
    }
  };

  const handleSubmit = async () => {
    if (!selectedStudent) {
      Alert.alert('Error', 'Please select a student');
      return;
    }

    if (!selectedReason) {
      Alert.alert('Error', 'Please select a reason');
      return;
    }

    if (selectedReason === 'Other Emergency' && !customReason.trim()) {
      Alert.alert('Error', 'Please provide a custom reason');
      return;
    }

    setSubmitting(true);

    try {
      const reason = selectedReason === 'Other Emergency' ? customReason : selectedReason;
      await onEmergencyExit(selectedStudent.id, reason);
    } catch (error) {
      console.error('Emergency exit error:', error);
      Alert.alert('Error', 'Failed to record emergency exit');
    } finally {
      setSubmitting(false);
    }
  };

  const handleClose = () => {
    setSearchQuery('');
    setStudents([]);
    setSelectedStudent(null);
    setSelectedReason('');
    setCustomReason('');
    onClose();
  };

  return (
    <Modal
      visible={visible}
      animationType="slide"
      presentationStyle="pageSheet"
      onRequestClose={handleClose}>
      <View style={styles.container}>
        {/* Header */}
        <View style={styles.header}>
          <TouchableOpacity onPress={handleClose} style={styles.closeButton}>
            <Ionicons name="close" size={24} color={colors.text} />
          </TouchableOpacity>
          <Text style={styles.title}>Emergency Exit</Text>
          <View style={styles.placeholder} />
        </View>

        <ScrollView style={styles.content} showsVerticalScrollIndicator={false}>
          {/* Student Search */}
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>Select Student</Text>
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

            {/* Selected Student */}
            {selectedStudent && (
              <View style={styles.selectedStudentCard}>
                <View style={styles.studentInfo}>
                  <Text style={styles.studentName}>{selectedStudent.name}</Text>
                  <Text style={styles.studentRoll}>{selectedStudent.roll_no}</Text>
                  <Text style={styles.studentHostel}>{selectedStudent.hostel_name}</Text>
                </View>
                <TouchableOpacity
                  style={styles.changeButton}
                  onPress={() => {
                    setSelectedStudent(null);
                    setSearchQuery('');
                  }}>
                  <Text style={styles.changeButtonText}>Change</Text>
                </TouchableOpacity>
              </View>
            )}

            {/* Student List */}
            {searchQuery.length >= 2 && !selectedStudent && (
              <View style={styles.studentList}>
                {loading ? (
                  <ActivityIndicator size="small" color={colors.primary} style={styles.loader} />
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
          </View>

          {/* Emergency Reason */}
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>Emergency Reason</Text>
            <View style={styles.reasonsList}>
              {EMERGENCY_REASONS.map((reason) => (
                <TouchableOpacity
                  key={reason}
                  style={[
                    styles.reasonItem,
                    selectedReason === reason && styles.reasonItemSelected,
                  ]}
                  onPress={() => handleReasonSelect(reason)}>
                  <View style={styles.reasonContent}>
                    <View style={[
                      styles.reasonRadio,
                      selectedReason === reason && styles.reasonRadioSelected,
                    ]}>
                      {selectedReason === reason && (
                        <View style={styles.reasonRadioInner} />
                      )}
                    </View>
                    <Text style={[
                      styles.reasonText,
                      selectedReason === reason && styles.reasonTextSelected,
                    ]}>
                      {reason}
                    </Text>
                  </View>
                </TouchableOpacity>
              ))}
            </View>

            {/* Custom Reason Input */}
            {selectedReason === 'Other Emergency' && (
              <View style={styles.customReasonContainer}>
                <Text style={styles.customReasonLabel}>Please specify:</Text>
                <TextInput
                  style={styles.customReasonInput}
                  placeholder="Enter emergency reason..."
                  value={customReason}
                  onChangeText={setCustomReason}
                  multiline
                  numberOfLines={3}
                />
              </View>
            )}
          </View>

          {/* Warning */}
          <View style={styles.warningSection}>
            <Ionicons name="warning" size={24} color={colors.warning} />
            <Text style={styles.warningText}>
              This is an emergency exit. Please ensure this is a genuine emergency situation.
            </Text>
          </View>
        </ScrollView>

        {/* Submit Button */}
        <View style={styles.footer}>
          <TouchableOpacity
            style={[styles.submitButton, submitting && styles.submitButtonDisabled]}
            onPress={handleSubmit}
            disabled={submitting || !selectedStudent || !selectedReason}>
            {submitting ? (
              <ActivityIndicator size="small" color={colors.white} />
            ) : (
              <>
                <Ionicons name="alert-circle" size={20} color={colors.white} />
                <Text style={styles.submitButtonText}>Record Emergency Exit</Text>
              </>
            )}
          </TouchableOpacity>
        </View>
      </View>
    </Modal>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: colors.white,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  closeButton: {
    padding: 8,
  },
  title: {
    fontSize: 18,
    fontWeight: '600',
    color: colors.text,
  },
  placeholder: {
    width: 40,
  },
  content: {
    flex: 1,
    padding: 16,
  },
  section: {
    marginBottom: 24,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.text,
    marginBottom: 12,
  },
  searchContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.white,
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 8,
    marginBottom: 12,
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
    backgroundColor: colors.white,
    borderRadius: 8,
    padding: 16,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    shadowColor: colors.black,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  studentInfo: {
    flex: 1,
  },
  studentName: {
    fontSize: 16,
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
  studentList: {
    backgroundColor: colors.white,
    borderRadius: 8,
    shadowColor: colors.black,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
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
  reasonsList: {
    backgroundColor: colors.white,
    borderRadius: 8,
    shadowColor: colors.black,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  reasonItem: {
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  reasonItemSelected: {
    backgroundColor: colors.primary + '10',
  },
  reasonContent: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  reasonRadio: {
    width: 20,
    height: 20,
    borderRadius: 10,
    borderWidth: 2,
    borderColor: colors.gray,
    marginRight: 12,
    alignItems: 'center',
    justifyContent: 'center',
  },
  reasonRadioSelected: {
    borderColor: colors.primary,
  },
  reasonRadioInner: {
    width: 10,
    height: 10,
    borderRadius: 5,
    backgroundColor: colors.primary,
  },
  reasonText: {
    fontSize: 16,
    color: colors.text,
    flex: 1,
  },
  reasonTextSelected: {
    color: colors.primary,
    fontWeight: '500',
  },
  customReasonContainer: {
    marginTop: 12,
    backgroundColor: colors.white,
    borderRadius: 8,
    padding: 16,
    shadowColor: colors.black,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  customReasonLabel: {
    fontSize: 14,
    fontWeight: '500',
    color: colors.text,
    marginBottom: 8,
  },
  customReasonInput: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 8,
    padding: 12,
    fontSize: 16,
    color: colors.text,
    textAlignVertical: 'top',
  },
  warningSection: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    backgroundColor: colors.warning + '10',
    padding: 16,
    borderRadius: 8,
    borderLeftWidth: 4,
    borderLeftColor: colors.warning,
  },
  warningText: {
    fontSize: 14,
    color: colors.warning,
    marginLeft: 12,
    flex: 1,
    lineHeight: 20,
  },
  footer: {
    padding: 16,
    backgroundColor: colors.white,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  submitButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.error,
    paddingVertical: 16,
    paddingHorizontal: 24,
    borderRadius: 8,
    shadowColor: colors.black,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.2,
    shadowRadius: 4,
    elevation: 3,
  },
  submitButtonDisabled: {
    backgroundColor: colors.gray,
  },
  submitButtonText: {
    color: colors.white,
    fontSize: 16,
    fontWeight: '600',
    marginLeft: 8,
  },
});