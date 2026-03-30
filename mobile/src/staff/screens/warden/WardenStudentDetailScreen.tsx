import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  ActivityIndicator,
} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { apiService } from '../../../shared/services/api.service';
import { colors } from '../../../shared/theme/colors';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface StudentDetail {
  id: string;
  map_student_id?: string;
  name: string;
  phone?: string;
  email?: string;
  gender?: string;
  date_of_birth?: string;
  // Academic
  roll_no?: string;
  erp_number?: string;
  program?: string;
  department?: string;
  year_of_study?: number;
  // Hostel
  hostel_name?: string;
  room_no?: string;
  room_capacity?: number;
  current_status?: string;
  // Parent Info
  father_name?: string;
  father_phone?: string;
  mother_name?: string;
  mother_phone?: string;
  // Guardian
  guardian_name?: string;
  guardian_phone?: string;
  guardian_relationship?: string;
  guardian_address?: string;
  // Medical
  blood_group?: string;
  medical_conditions?: string;
  allergies?: string;
  // Emergency Contact
  emergency_contact_name?: string;
  emergency_contact_relationship?: string;
  emergency_contact_phone?: string;
  emergency_contact_address?: string;
}

interface Props {
  navigation: any;
  route: any;
}

export const WardenStudentDetailScreen: React.FC<Props> = ({ navigation, route }) => {
  const { student: initialStudent } = route.params;
  const [student, setStudent] = useState<StudentDetail>(initialStudent);
  const [loading, setLoading] = useState(false);
  const [refreshing, setRefreshing] = useState(false);

  const fetchStudentDetail = useCallback(async () => {
    setLoading(true);
    try {
      const response = await apiService.get<any>(`/mobile/warden/students/${initialStudent.id}`);
      const payload = response?.data?.data || response?.data || response;
      setStudent(payload || initialStudent);
    } catch (error) {
      console.error('Failed to fetch student detail:', error);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [initialStudent]);

  useEffect(() => {
    fetchStudentDetail();
  }, [fetchStudentDetail]);

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    fetchStudentDetail();
  }, [fetchStudentDetail]);

  const renderSection = (title: string, icon: string, children: React.ReactNode) => (
    <View style={styles.section}>
      <View style={styles.sectionHeader}>
        <Ionicons name={icon as any} size={20} color={colors.primary} />
        <Text style={styles.sectionTitle}>{title}</Text>
      </View>
      <View style={styles.sectionContent}>{children}</View>
    </View>
  );

  const renderDetailRow = (label: string, value?: string | number | null) => (
    <View style={styles.detailRow}>
      <Text style={styles.detailLabel}>{label}</Text>
      <Text style={styles.detailValue}>{value || 'N/A'}</Text>
    </View>
  );

  if (loading) {
    return (
      <View style={[styles.container, styles.loadingContainer]}>
        <ActivityIndicator size="large" color={colors.primary} />
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Student Details" />

      <ScrollView
        style={styles.content}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={onRefresh}
            tintColor={colors.primary}
          />
        }
      >
        {/* Name Card */}
        <View style={styles.nameCard}>
          <View style={styles.avatarContainer}>
            <Text style={styles.avatarText}>
              {student.name?.charAt(0).toUpperCase() || 'S'}
            </Text>
          </View>
          <Text style={styles.studentName}>{student.name}</Text>
          <Text style={styles.studentRoom}>
            Room {student.room_no || 'N/A'} • {student.hostel_name || 'N/A'}
          </Text>
        </View>

        {/* Basic Information */}
        {renderSection('Basic Information', 'person-outline', (
          <>
            {renderDetailRow('Full Name', student.name)}
            {renderDetailRow('Email', student.email)}
            {renderDetailRow('Mobile Number', student.phone)}
            {renderDetailRow('Gender', student.gender)}
            {renderDetailRow('Date of Birth', student.date_of_birth)}
          </>
        ))}

        {/* Academic Details */}
        {renderSection('Academic Details', 'school-outline', (
          <>
            {renderDetailRow('MAP ID', student.map_student_id)}
            {renderDetailRow('ERP Number', student.erp_number || student.roll_no)}
            {renderDetailRow('Department', student.department)}
            {renderDetailRow('Year', student.year_of_study ? `Year ${student.year_of_study}` : undefined)}
          </>
        ))}

        {/* Hostel Allocation */}
        {renderSection('Hostel Allocation', 'bed-outline', (
          <>
            {renderDetailRow('Assigned Hostel', student.hostel_name)}
            {renderDetailRow('Room Number', student.room_no)}
            {renderDetailRow('Room Capacity', student.room_capacity)}
            {renderDetailRow('Current Status', student.current_status || 'Active')}
          </>
        ))}

        {/* Parent Information */}
        {renderSection('Parent Information', 'people-outline', (
          <>
            <Text style={styles.subSectionTitle}>Father</Text>
            {renderDetailRow('Name', student.father_name)}
            {renderDetailRow('Phone', student.father_phone)}
            <View style={styles.subDivider} />
            <Text style={styles.subSectionTitle}>Mother</Text>
            {renderDetailRow('Name', student.mother_name)}
            {renderDetailRow('Phone', student.mother_phone)}
          </>
        ))}

        {/* Local Guardian */}
        {renderSection('Local Guardian', 'shield-outline', (
          <>
            {renderDetailRow('Name', student.guardian_name)}
            {renderDetailRow('Contact Number', student.guardian_phone)}
            {renderDetailRow('Relationship', student.guardian_relationship)}
            {renderDetailRow('Local Address', student.guardian_address)}
          </>
        ))}

        {/* Medical Information */}
        {renderSection('Medical Information', 'medkit-outline', (
          <>
            {renderDetailRow('Blood Group', student.blood_group)}
            {renderDetailRow('Medical Conditions', student.medical_conditions)}
            {renderDetailRow('Allergies', student.allergies)}
          </>
        ))}

        {/* Emergency Contact */}
        {renderSection('Emergency Contact', 'call-outline', (
          <>
            {renderDetailRow('Name', student.emergency_contact_name)}
            {renderDetailRow('Relationship', student.emergency_contact_relationship)}
            {renderDetailRow('Address', student.emergency_contact_address)}
            {renderDetailRow('Contact Number(s)', student.emergency_contact_phone)}
          </>
        ))}

        <View style={styles.bottomPadding} />
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  loadingContainer: {
    justifyContent: 'center',
    alignItems: 'center',
  },
  content: {
    flex: 1,
    padding: 16,
  },
  nameCard: {
    backgroundColor: colors.surface,
    borderRadius: 16,
    padding: 24,
    alignItems: 'center',
    marginBottom: 16,
    borderWidth: 1,
    borderColor: colors.border,
    shadowColor: colors.black,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.06,
    shadowRadius: 4,
    elevation: 2,
  },
  avatarContainer: {
    width: 80,
    height: 80,
    borderRadius: 40,
    backgroundColor: colors.primary,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 16,
  },
  avatarText: {
    color: colors.white,
    fontSize: 32,
    fontWeight: 'bold',
  },
  studentName: {
    fontSize: 22,
    fontWeight: '700',
    color: colors.textHeading,
    marginBottom: 4,
    textAlign: 'center',
  },
  studentRoom: {
    fontSize: 14,
    color: colors.textSecondary,
  },
  section: {
    backgroundColor: colors.surface,
    borderRadius: 12,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: colors.border,
    overflow: 'hidden',
  },
  sectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    padding: 16,
    backgroundColor: colors.surfaceMuted,
    borderBottomWidth: 1,
    borderBottomColor: colors.divider,
  },
  sectionTitle: {
    fontSize: 15,
    fontWeight: '600',
    color: colors.textHeading,
  },
  sectionContent: {
    padding: 16,
  },
  detailRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    paddingVertical: 8,
    borderBottomWidth: 1,
    borderBottomColor: colors.divider,
  },
  detailLabel: {
    fontSize: 14,
    color: colors.textSecondary,
    flex: 1,
  },
  detailValue: {
    fontSize: 14,
    fontWeight: '500',
    color: colors.text,
    flex: 1,
    textAlign: 'right',
  },
  subSectionTitle: {
    fontSize: 13,
    fontWeight: '600',
    color: colors.primary,
    marginTop: 8,
    marginBottom: 4,
  },
  subDivider: {
    height: 8,
  },
  bottomPadding: {
    height: 40,
  },
});

export default WardenStudentDetailScreen;
