/**
 * WardenStudentDetailScreen
 * 
 * Shows detailed student information with dropdown tabs
 */

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
} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useAuthStore } from '../../store/auth.store';
import { apiService } from '../../services/api.service';
import { Student } from '../../types';
import { APP_CONFIG } from '../../config/app.config';
import { colors } from '../../theme/colors';
import { format } from 'date-fns';

interface StudentDetail extends Student {
  gender?: string;
  dob?: string;
  blood_group?: string;
  nationality?: string;
  permanent_address?: string;
  correspondence_address?: string;
  roll_no?: string;
  course?: string;
  department?: string;
  year_of_study?: string;
  admission_year?: string;
  college_id?: string;
  admission_mode?: string;
  father_name?: string;
  mother_name?: string;
  guardian_name?: string;
  relationship?: string;
  parent_contact?: string;
  parent_email?: string;
  parent_occupation?: string;
  parent_address?: string;
  local_guardian_name?: string;
  local_guardian_relationship?: string;
  local_guardian_address?: string;
  local_guardian_contact?: string;
  medical_conditions?: string;
  disabilities?: string;
  allergies?: string;
  medications?: string;
  insurance?: string;
  emergency_name?: string;
  emergency_relationship?: string;
  emergency_address?: string;
  emergency_contact?: string;
}

interface Section {
  key: string;
  title: string;
  fields: { key: string; label: string }[];
}

export const WardenStudentDetailScreen = ({ navigation, route }: any) => {
  const { user } = useAuthStore();
  const { studentId } = route.params;
  const [student, setStudent] = useState<StudentDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [expandedSections, setExpandedSections] = useState<Record<string, boolean>>({});

  const sections: Section[] = [
    {
      key: 'personal',
      title: 'Personal Information',
      fields: [
        { key: 'name', label: 'Full Name (as per ID)' },
        { key: 'gender', label: 'Gender' },
        { key: 'dob', label: 'Date of Birth' },
        { key: 'blood_group', label: 'Blood Group' },
        { key: 'nationality', label: 'Nationality' },
        { key: 'phone', label: 'Mobile Number' },
        { key: 'email', label: 'Email Address' },
        { key: 'permanent_address', label: 'Permanent Address' },
        { key: 'correspondence_address', label: 'Correspondence Address (if different)' },
      ],
    },
    {
      key: 'academic',
      title: 'Academic Details',
      fields: [
        { key: 'roll_no', label: 'College Roll Number / Enrollment Number' },
        { key: 'course', label: 'Course / Program Name' },
        { key: 'department', label: 'Department / Faculty' },
        { key: 'year_of_study', label: 'Year of Study' },
        { key: 'admission_year', label: 'Admission Year' },
        { key: 'college_id', label: 'College ID Number' },
        { key: 'admission_mode', label: 'Mode of Admission' },
      ],
    },
    {
      key: 'parent',
      title: 'Parent/Guardian Information',
      fields: [
        { key: 'father_name', label: "Father's Name" },
        { key: 'mother_name', label: "Mother's Name" },
        { key: 'guardian_name', label: "Guardian's Name (if different)" },
        { key: 'relationship', label: 'Relationship with Student' },
        { key: 'parent_contact', label: 'Parent/Guardian Contact Number(s)' },
        { key: 'parent_email', label: 'Parent/Guardian Email (if available)' },
        { key: 'parent_occupation', label: 'Occupation and Address of Parent/Guardian' },
      ],
    },
    {
      key: 'local_guardian',
      title: 'Local Guardian Details',
      fields: [
        { key: 'local_guardian_name', label: 'Name' },
        { key: 'local_guardian_relationship', label: 'Relationship' },
        { key: 'local_guardian_address', label: 'Address' },
        { key: 'local_guardian_contact', label: 'Contact Number(s)' },
      ],
    },
    {
      key: 'medical',
      title: 'Medical and Health Information',
      fields: [
        { key: 'medical_conditions', label: 'Known Medical Conditions / Chronic Illness' },
        { key: 'disabilities', label: 'Any Disabilities' },
        { key: 'allergies', label: 'Allergies (if any)' },
        { key: 'medications', label: 'Regular Medications' },
        { key: 'insurance', label: 'Medical Insurance Details (if applicable)' },
      ],
    },
    {
      key: 'emergency',
      title: 'Emergency Contact Details',
      fields: [
        { key: 'emergency_name', label: 'Name' },
        { key: 'emergency_relationship', label: 'Relationship' },
        { key: 'emergency_address', label: 'Address' },
        { key: 'emergency_contact', label: 'Contact Number(s)' },
      ],
    },
  ];

  const fetchStudent = async () => {
    try {
      const response = await apiService.get<{ data: StudentDetail }>(
        `${APP_CONFIG.ENDPOINTS.WARDEN_STUDENTS}/${studentId}`
      );
      setStudent(response.data);
    } catch (error) {
      console.error('Student fetch error:', error);
      // Mock data for demo
      const mockStudent: StudentDetail = {
        id: studentId,
        name: 'John Doe',
        email: 'john.doe@student.com',
        phone: '+1234567890',
        room_id: 1,
        room_number: '101',
        hostel_id: 1,
        hostel_name: 'Hostel A',
        tenant_id: 'tenant_1',
        created_at: '2025-01-01T00:00:00Z',
        updated_at: '2025-01-01T00:00:00Z',
        gender: 'Male',
        dob: '2000-01-15',
        blood_group: 'O+',
        nationality: 'Indian',
        permanent_address: '123 Main Street, City, State, PIN',
        correspondence_address: 'Same as permanent address',
        roll_no: '2020-001',
        course: 'B.Tech Computer Science',
        department: 'Computer Science',
        year_of_study: '4th Year',
        admission_year: '2020',
        college_id: 'CLG-2020-001',
        admission_mode: 'Merit',
        father_name: 'Father Name',
        mother_name: 'Mother Name',
        guardian_name: 'Guardian Name',
        relationship: 'Father',
        parent_contact: '+9876543210',
        parent_email: 'parent@email.com',
        parent_occupation: 'Engineer',
        parent_address: '123 Main Street, City, State',
        local_guardian_name: 'Local Guardian Name',
        local_guardian_relationship: 'Uncle',
        local_guardian_address: '456 Local Street, City',
        local_guardian_contact: '+1112223334',
        medical_conditions: 'None',
        disabilities: 'None',
        allergies: 'None',
        medications: 'None',
        insurance: 'Insurance Provider Name',
        emergency_name: 'Emergency Contact Name',
        emergency_relationship: 'Brother',
        emergency_address: '789 Emergency Street',
        emergency_contact: '+5556667778',
      };
      setStudent(mockStudent);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    if (studentId) {
      fetchStudent();
    } else {
      Alert.alert('Error', 'Invalid student ID');
      navigation.goBack();
    }
  }, [studentId]);

  const onRefresh = () => {
    setRefreshing(true);
    fetchStudent();
  };

  const toggleSection = (sectionKey: string) => {
    setExpandedSections(prev => ({
      ...prev,
      [sectionKey]: !prev[sectionKey],
    }));
  };

  const getFieldValue = (fieldKey: string): string => {
    if (!student) return 'N/A';
    const value = (student as any)[fieldKey];
    if (value === null || value === undefined || value === '') return 'N/A';
    if (fieldKey === 'dob' && value) {
      try {
        return format(new Date(value), 'MMM dd, yyyy');
      } catch {
        return value;
      }
    }
    return String(value);
  };

  if (loading || !student) {
    return (
      <View style={styles.container}>
        <View style={styles.header}>
          <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
            <Ionicons name="arrow-back" size={24} color={colors.surface} />
          </TouchableOpacity>
          <Text style={styles.headerTitle}>Student Details</Text>
          <View style={styles.headerSpacer} />
        </View>
        <View style={styles.loaderContainer}>
          <ActivityIndicator size="large" color={colors.primary} />
        </View>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
          <Ionicons name="arrow-back" size={24} color={colors.surface} />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Student Details</Text>
        <View style={styles.headerSpacer} />
      </View>

      {/* Student Name */}
      <View style={styles.studentHeader}>
        <Text style={styles.studentName}>{student.name}</Text>
        <Text style={styles.studentRoom}>Room {student.room_number} • {student.hostel_name}</Text>
      </View>

      {/* Sections */}
      <ScrollView
        style={styles.content}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>
        {sections.map((section) => {
          const isExpanded = expandedSections[section.key];

          return (
            <View key={section.key} style={styles.section}>
              <TouchableOpacity
                style={styles.sectionHeader}
                onPress={() => toggleSection(section.key)}>
                <Text style={styles.sectionTitle}>{section.title}</Text>
                <Ionicons
                  name={isExpanded ? 'chevron-up' : 'chevron-down'}
                  size={24}
                  color={colors.primary}
                />
              </TouchableOpacity>

              {isExpanded && (
                <View style={styles.sectionContent}>
                  {section.fields.map((field) => (
                    <View key={field.key} style={styles.fieldRow}>
                      <Text style={styles.fieldLabel}>{field.label}</Text>
                      <Text style={styles.fieldValue}>{getFieldValue(field.key)}</Text>
                    </View>
                  ))}
                </View>
              )}
            </View>
          );
        })}
      </ScrollView>
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
    justifyContent: 'space-between',
  },
  backButton: {
    padding: 8,
  },
  headerTitle: {
    color: colors.surface,
    fontSize: 20,
    fontWeight: 'bold',
    flex: 1,
    textAlign: 'center',
  },
  headerSpacer: {
    width: 40,
  },
  loaderContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  studentHeader: {
    backgroundColor: colors.surface,
    padding: 20,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  studentName: {
    fontSize: 24,
    fontWeight: 'bold',
    color: colors.textPrimary,
    marginBottom: 4,
  },
  studentRoom: {
    fontSize: 16,
    color: colors.textMuted,
  },
  content: {
    flex: 1,
  },
  section: {
    backgroundColor: colors.surface,
    marginBottom: 8,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 20,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: colors.textPrimary,
  },
  sectionContent: {
    paddingHorizontal: 20,
    paddingBottom: 20,
  },
  fieldRow: {
    marginBottom: 16,
  },
  fieldLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textMuted,
    marginBottom: 4,
  },
  fieldValue: {
    fontSize: 16,
    color: colors.textPrimary,
    lineHeight: 22,
  },
});

