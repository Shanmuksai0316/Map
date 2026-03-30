import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  ActivityIndicator,
  TextInput,
  Alert,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import { useAuthStore } from '../../../shared/store/auth.store';
import { apiService } from '../../../shared/services/api.service';
import { APP_CONFIG } from '../../../shared/config/app.config';
import { format } from 'date-fns';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { colors } from '../../../shared/theme/colors';
import { errorHandler } from '../../../shared/utils/errorHandler';
import { ErrorState } from '../../../shared/components/shared/ErrorState';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface Student {
  id: number;
  student_uid: string;
  name: string;
  email?: string;
  phone?: string;
  hostel_name?: string;
  room_number?: string;
  status: 'active' | 'inactive' | 'suspended';
  created_at: string;
}

export const StudentManagementScreen = ({ navigation }: any) => {
  const { user } = useAuthStore();
  const [students, setStudents] = useState<Student[]>([]);
  const [filteredStudents, setFilteredStudents] = useState<Student[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<any>(null);
  const [searchQuery, setSearchQuery] = useState('');

  const fetchStudents = async () => {
    try {
      setError(null);
      // Note: Students endpoint may need to be added to backend
      // For now, try the endpoint and handle gracefully if it doesn't exist
      const response = await apiService.get<{ data: Student[] }>(
        APP_CONFIG.ENDPOINTS.STUDENTS
      ).catch(() => {
        // If endpoint doesn't exist, return empty array
        return { data: [] };
      });
      const studentList = response.data || [];
      setStudents(studentList);
      setFilteredStudents(studentList);
    } catch (err) {
      console.error('Failed to fetch students:', err);
      // Don't show error if endpoint doesn't exist - just show empty state
      setStudents([]);
      setFilteredStudents([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchStudents();
  }, []);

  useEffect(() => {
    if (searchQuery.trim()) {
      const filtered = students.filter(
        (student) =>
          student.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
          student.student_uid.toLowerCase().includes(searchQuery.toLowerCase()) ||
          student.email?.toLowerCase().includes(searchQuery.toLowerCase())
      );
      setFilteredStudents(filtered);
    } else {
      setFilteredStudents(students);
    }
  }, [searchQuery, students]);

  const onRefresh = () => {
    setRefreshing(true);
    fetchStudents();
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'active':
        return colors.success;
      case 'inactive':
        return colors.textMuted;
      case 'suspended':
        return colors.error;
      default:
        return colors.textSecondary;
    }
  };

  if (loading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color={colors.primary} />
        <Text style={styles.loadingText}>Loading students...</Text>
      </View>
    );
  }

  if (error) {
    return (
      <View style={styles.container}>
        <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Student Management" />
        <ErrorState error={error} onRetry={fetchStudents} />
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <StaffScreenHeader
        onBack={() => navigation.goBack()}
        showBell={false}
        rightSlot={
          <GradientButton
            style={styles.addButton}
            onPress={() => {
              Alert.alert('Info', 'Student import feature will be available in the web panel.');
            }}>
            <Ionicons name="add" size={24} color={colors.primary} />
          </GradientButton>
        }  title="Student Management" />

      {/* Search Bar */}
      <View style={styles.searchContainer}>
        <Ionicons name="search-outline" size={20} color={colors.textMuted} style={styles.searchIcon} />
        <TextInput
          style={styles.searchInput}
          placeholder="Search by name, UID, or email..."
          value={searchQuery}
          onChangeText={setSearchQuery}
          placeholderTextColor={colors.textMuted}
        />
        {searchQuery.length > 0 && (
          <TouchableOpacity onPress={() => setSearchQuery('')}>
            <Ionicons name="close-circle" size={20} color={colors.textMuted} />
          </TouchableOpacity>
        )}
      </View>

      {/* Content */}
      <ScrollView
        style={styles.content}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>
        {filteredStudents.length === 0 ? (
          <View style={styles.emptyState}>
            <Ionicons name="people-outline" size={48} color={colors.textMuted} />
            <Text style={styles.emptyTitle}>
              {searchQuery ? 'No students found' : 'No students'}
            </Text>
            <Text style={styles.emptySubtitle}>
              {searchQuery
                ? 'Try adjusting your search query'
                : 'Students will appear here once imported'}
            </Text>
          </View>
        ) : (
          <>
            <View style={styles.resultsHeader}>
              <Text style={styles.resultsCount}>
                {filteredStudents.length} student{filteredStudents.length !== 1 ? 's' : ''}
              </Text>
            </View>
            {filteredStudents.map((student) => (
              <View key={student.id} style={styles.studentCard}>
                <View style={styles.studentHeader}>
                  <View style={styles.studentInfo}>
                    <Text style={styles.studentName}>{student.name}</Text>
                    <Text style={styles.studentUid}>{student.student_uid}</Text>
                  </View>
                  <View style={[styles.statusBadge, { backgroundColor: getStatusColor(student.status) + '20' }]}>
                    <Text style={[styles.statusText, { color: getStatusColor(student.status) }]}>
                      {student.status}
                    </Text>
                  </View>
                </View>
                {(student.hostel_name || student.room_number) && (
                  <View style={styles.studentDetails}>
                    <Ionicons name="business-outline" size={14} color={colors.textMuted} />
                    <Text style={styles.studentDetailText}>
                      {student.hostel_name}
                      {student.room_number && ` • Room ${student.room_number}`}
                    </Text>
                  </View>
                )}
                {student.email && (
                  <View style={styles.studentDetails}>
                    <Ionicons name="mail-outline" size={14} color={colors.textMuted} />
                    <Text style={styles.studentDetailText}>{student.email}</Text>
                  </View>
                )}
                {student.phone && (
                  <View style={styles.studentDetails}>
                    <Ionicons name="call-outline" size={14} color={colors.textMuted} />
                    <Text style={styles.studentDetailText}>{student.phone}</Text>
                  </View>
                )}
              </View>
            ))}
          </>
        )}
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  addButton: {
    padding: 8,
  },
  searchContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.white,
    marginHorizontal: 16,
    marginTop: 16,
    marginBottom: 8,
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: colors.border,
  },
  searchIcon: {
    marginRight: 12,
  },
  searchInput: {
    flex: 1,
    fontSize: 16,
    color: colors.text,
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
  resultsHeader: {
    paddingHorizontal: 16,
    paddingTop: 16,
    paddingBottom: 8,
  },
  resultsCount: {
    fontSize: 14,
    color: colors.textSecondary,
    fontWeight: '500',
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
  studentCard: {
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
  studentHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 12,
  },
  studentInfo: {
    flex: 1,
    marginRight: 12,
  },
  studentName: {
    fontSize: 18,
    fontWeight: 'bold',
    color: colors.text,
    marginBottom: 4,
  },
  studentUid: {
    fontSize: 14,
    color: colors.textSecondary,
  },
  statusBadge: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 16,
  },
  statusText: {
    fontSize: 12,
    fontWeight: '600',
    textTransform: 'capitalize',
  },
  studentDetails: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: 8,
  },
  studentDetailText: {
    fontSize: 14,
    color: colors.textSecondary,
    marginLeft: 8,
  },
});
