import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  TextInput,
  Alert,
} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useAuthStore } from '../../store/auth.store';
import { apiService } from '../../services/api.service';
import { Student } from '../../types';
import { APP_CONFIG } from '../../config/app.config';
import { colors } from '../../theme/colors';

export const WardenStudentsScreen = ({ navigation }: any) => {
  const { user } = useAuthStore();
  const [students, setStudents] = useState<Student[]>([]);
  const [filteredStudents, setFilteredStudents] = useState<Student[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');

  const fetchStudents = async () => {
    try {
      const response = await apiService.get<{ data: Student[] }>(APP_CONFIG.ENDPOINTS.WARDEN_STUDENTS);
      setStudents(response.data);
      setFilteredStudents(response.data);
    } catch (error) {
      console.error('Students fetch error:', error);
      // Mock data for demo
      setStudents([
        {
          id: 1,
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
        },
        {
          id: 2,
          name: 'Jane Smith',
          email: 'jane.smith@student.com',
          phone: '+1234567891',
          room_id: 1,
          room_number: '101',
          hostel_id: 1,
          hostel_name: 'Hostel A',
          tenant_id: 'tenant_1',
          created_at: '2025-01-01T00:00:00Z',
          updated_at: '2025-01-01T00:00:00Z',
        },
        {
          id: 3,
          name: 'Mike Johnson',
          email: 'mike.johnson@student.com',
          phone: '+1234567892',
          room_id: 2,
          room_number: '102',
          hostel_id: 1,
          hostel_name: 'Hostel A',
          tenant_id: 'tenant_1',
          created_at: '2025-01-01T00:00:00Z',
          updated_at: '2025-01-01T00:00:00Z',
        },
        {
          id: 4,
          name: 'Sarah Wilson',
          email: 'sarah.wilson@student.com',
          phone: '+1234567893',
          room_id: 3,
          room_number: '201',
          hostel_id: 1,
          hostel_name: 'Hostel A',
          tenant_id: 'tenant_1',
          created_at: '2025-01-01T00:00:00Z',
          updated_at: '2025-01-01T00:00:00Z',
        },
        {
          id: 5,
          name: 'David Brown',
          email: 'david.brown@student.com',
          phone: '+1234567894',
          room_id: 4,
          room_number: '202',
          hostel_id: 1,
          hostel_name: 'Hostel A',
          tenant_id: 'tenant_1',
          created_at: '2025-01-01T00:00:00Z',
          updated_at: '2025-01-01T00:00:00Z',
        },
        {
          id: 6,
          name: 'Emily Davis',
          email: 'emily.davis@student.com',
          phone: '+1234567895',
          room_id: 4,
          room_number: '202',
          hostel_id: 1,
          hostel_name: 'Hostel A',
          tenant_id: 'tenant_1',
          created_at: '2025-01-01T00:00:00Z',
          updated_at: '2025-01-01T00:00:00Z',
        },
      ]);
      setFilteredStudents([
        {
          id: 1,
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
        },
        {
          id: 2,
          name: 'Jane Smith',
          email: 'jane.smith@student.com',
          phone: '+1234567891',
          room_id: 1,
          room_number: '101',
          hostel_id: 1,
          hostel_name: 'Hostel A',
          tenant_id: 'tenant_1',
          created_at: '2025-01-01T00:00:00Z',
          updated_at: '2025-01-01T00:00:00Z',
        },
        {
          id: 3,
          name: 'Mike Johnson',
          email: 'mike.johnson@student.com',
          phone: '+1234567892',
          room_id: 2,
          room_number: '102',
          hostel_id: 1,
          hostel_name: 'Hostel A',
          tenant_id: 'tenant_1',
          created_at: '2025-01-01T00:00:00Z',
          updated_at: '2025-01-01T00:00:00Z',
        },
        {
          id: 4,
          name: 'Sarah Wilson',
          email: 'sarah.wilson@student.com',
          phone: '+1234567893',
          room_id: 3,
          room_number: '201',
          hostel_id: 1,
          hostel_name: 'Hostel A',
          tenant_id: 'tenant_1',
          created_at: '2025-01-01T00:00:00Z',
          updated_at: '2025-01-01T00:00:00Z',
        },
        {
          id: 5,
          name: 'David Brown',
          email: 'david.brown@student.com',
          phone: '+1234567894',
          room_id: 4,
          room_number: '202',
          hostel_id: 1,
          hostel_name: 'Hostel A',
          tenant_id: 'tenant_1',
          created_at: '2025-01-01T00:00:00Z',
          updated_at: '2025-01-01T00:00:00Z',
        },
        {
          id: 6,
          name: 'Emily Davis',
          email: 'emily.davis@student.com',
          phone: '+1234567895',
          room_id: 4,
          room_number: '202',
          hostel_id: 1,
          hostel_name: 'Hostel A',
          tenant_id: 'tenant_1',
          created_at: '2025-01-01T00:00:00Z',
          updated_at: '2025-01-01T00:00:00Z',
        },
      ]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchStudents();
  }, []);

  useEffect(() => {
    if (searchQuery.trim() === '') {
      setFilteredStudents(students);
    } else {
      const filtered = students.filter(student =>
        student.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        (student.email?.toLowerCase() || '').includes(searchQuery.toLowerCase()) ||
        (student.room_number || '').includes(searchQuery) ||
        student.phone.includes(searchQuery)
      );
      setFilteredStudents(filtered);
    }
  }, [searchQuery, students]);

  const onRefresh = () => {
    setRefreshing(true);
    fetchStudents();
  };

  const handleStudentPress = (student: Student) => {
    navigation.navigate('WardenStudentDetail', { studentId: student.id });
  };

  const StudentCard = ({ student }: { student: Student }) => (
    <TouchableOpacity
      style={styles.studentCard}
      onPress={() => handleStudentPress(student)}>
      <View style={styles.studentHeader}>
        <View style={styles.studentInfo}>
          <Text style={styles.studentName}>{student.name}</Text>
          <Text style={styles.studentEmail}>{student.email}</Text>
          <Text style={styles.studentPhone}>{student.phone}</Text>
        </View>
        <View style={styles.roomBadge}>
          <Text style={styles.roomNumber}>{student.room_number}</Text>
        </View>
      </View>
      <View style={styles.viewButtonContainer}>
        <Text style={styles.viewButtonText}>View</Text>
        <Ionicons name="chevron-forward" size={16} color={colors.primary} />
      </View>
    </TouchableOpacity>
  );

  // Group students by hostel for summary
  const hostelGroups = filteredStudents.reduce((groups, student) => {
    const hostel = student.hostel_name;
    if (!groups[hostel]) {
      groups[hostel] = [];
    }
    groups[hostel].push(student);
    return groups;
  }, {} as Record<string, Student[]>);

  const totalStudents = filteredStudents.length;
  const totalHostels = Object.keys(hostelGroups).length;

  return (
    <View style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <View>
          <Text style={styles.greeting}>Students</Text>
          <Text style={styles.subGreeting}>
            {totalStudents} students across {totalHostels} hostels
          </Text>
        </View>
      </View>

      {/* Search */}
      <View style={styles.searchContainer}>
        <TextInput
          style={styles.searchInput}
          placeholder="Search by name, email, room, or phone..."
          value={searchQuery}
          onChangeText={setSearchQuery}
          placeholderTextColor={colors.textMuted}
        />
      </View>

      {/* Students List */}
      <ScrollView
        style={styles.studentsList}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>
        {Object.entries(hostelGroups).map(([hostelName, hostelStudents]) => (
          <View key={hostelName} style={styles.hostelSection}>
            <Text style={styles.hostelTitle}>
              {hostelName} ({hostelStudents.length} students)
            </Text>
            {hostelStudents.map((student) => (
              <StudentCard key={student.id} student={student} />
            ))}
          </View>
        ))}
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
  },
  greeting: {
    color: colors.surface,
    fontSize: 24,
    fontWeight: 'bold',
  },
  subGreeting: {
    color: colors.surface,
    fontSize: 14,
    opacity: 0.8,
    marginTop: 4,
  },
  searchContainer: {
    backgroundColor: colors.surface,
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  searchInput: {
    backgroundColor: colors.surfaceMuted,
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderRadius: 12,
    fontSize: 14,
    color: colors.textPrimary,
  },
  studentsList: {
    flex: 1,
  },
  hostelSection: {
    padding: 20,
  },
  hostelTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: colors.textPrimary,
    marginBottom: 12,
  },
  studentCard: {
    backgroundColor: colors.surface,
    padding: 16,
    borderRadius: 12,
    marginBottom: 8,
    elevation: 1,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.1,
    shadowRadius: 2,
  },
  studentHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 8,
  },
  studentInfo: {
    flex: 1,
  },
  studentName: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.textPrimary,
  },
  studentEmail: {
    fontSize: 14,
    color: colors.textMuted,
    marginTop: 2,
  },
  studentPhone: {
    fontSize: 14,
    color: colors.textMuted,
    marginTop: 1,
  },
  roomBadge: {
    backgroundColor: colors.primary,
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
  },
  roomNumber: {
    color: colors.surface,
    fontSize: 12,
    fontWeight: '600',
  },
  hostelName: {
    fontSize: 12,
    color: colors.textMuted,
    fontStyle: 'italic',
  },
  viewButtonContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'flex-end',
    marginTop: 8,
    paddingTop: 8,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  viewButtonText: {
    fontSize: 14,
    color: colors.primary,
    fontWeight: '600',
    marginRight: 4,
  },
});
