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
} from 'react-native';
import { useAuthStore } from '../../../shared/store/auth.store';
import { apiService } from '../../../shared/services/api.service';
import { APP_CONFIG } from '../../../shared/config/app.config';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { colors } from '../../../shared/theme/colors';
import { RevealablePII } from '../../../shared/components/shared/RevealablePII';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';
import { GradientButton } from '../../../shared/components/GradientButton';

interface Student {
  id: number;
  student_uid: string;
  name: string;
  roll_no?: string;
  hostel_name?: string;
  room_no?: string;
  phone?: string;
  guardian?: any;
  medical_notes?: any;
}

export const RectorInsightsScreen = ({ navigation, route }: any) => {
  const { user } = useAuthStore();
  const [students, setStudents] = useState<Student[]>([]);
  const [filteredStudents, setFilteredStudents] = useState<Student[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');

  const fetchStudents = async () => {
    try {
      // Use students endpoint - Rector should have access to view students
      const response = await apiService.get<{ data: Student[] }>(
        `${APP_CONFIG.ENDPOINTS.STUDENTS || '/students'}?limit=100`
      );
      
      const studentList = response.data || [];
      setStudents(studentList);
      setFilteredStudents(studentList);
    } catch (error) {
      console.error('Failed to fetch students:', error);
      // Show empty state - no mock data in production
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
    // Filter students by search query
    if (!searchQuery.trim()) {
      setFilteredStudents(students);
      return;
    }

    const query = searchQuery.toLowerCase();
    const filtered = students.filter((student) => {
      return (
        student.name?.toLowerCase().includes(query) ||
        student.student_uid?.toLowerCase().includes(query) ||
        student.roll_no?.toLowerCase().includes(query) ||
        student.hostel_name?.toLowerCase().includes(query)
      );
    });
    setFilteredStudents(filtered);
  }, [searchQuery, students]);

  const onRefresh = () => {
    setRefreshing(true);
    fetchStudents();
  };

  const StudentCard = ({ student }: { student: Student }) => (
    <View style={styles.studentCard}>
      <View style={styles.studentHeader}>
        <View style={styles.studentInfo}>
          <Text style={styles.studentName}>{student.name}</Text>
          <Text style={styles.studentUid}>{student.student_uid}</Text>
        </View>
        {student.roll_no && (
          <View style={styles.rollBadge}>
            <Text style={styles.rollText}>{student.roll_no}</Text>
          </View>
        )}
      </View>

      <View style={styles.studentDetails}>
        {student.hostel_name && (
          <View style={styles.detailRow}>
            <Ionicons name="home-outline" size={16} color={colors.textSecondary} />
            <Text style={styles.detailText}>{student.hostel_name}</Text>
            {student.room_no && <Text style={styles.detailText}> • Room {student.room_no}</Text>}
          </View>
        )}
      </View>

      <View style={styles.piiSection}>
        <Text style={styles.piiSectionTitle}>Contact Information</Text>
        
        <RevealablePII
          label="Phone Number"
          value={student.phone || null}
          studentId={student.id}
          piiType="phone"
          onReveal={() => {}}
        />

        <RevealablePII
          label="Guardian Information"
          value={student.guardian ? JSON.stringify(student.guardian) : null}
          studentId={student.id}
          piiType="guardian"
          onReveal={() => {}}
        />

        {student.medical_notes && (
          <RevealablePII
            label="Medical Notes"
            value={typeof student.medical_notes === 'string' ? student.medical_notes : JSON.stringify(student.medical_notes)}
            studentId={student.id}
            piiType="medical"
            onReveal={() => {}}
          />
        )}
      </View>
    </View>
  );

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Insights" />

      {/* Search Bar */}
      <View style={styles.searchContainer}>
        <Ionicons name="search-outline" size={20} color={colors.textSecondary} style={styles.searchIcon} />
        <TextInput
          style={styles.searchInput}
          placeholder="Search by name, UID, roll number..."
          value={searchQuery}
          onChangeText={setSearchQuery}
          placeholderTextColor={colors.textMuted}
        />
        {searchQuery.length > 0 && (
          <GradientButton onPress={() => setSearchQuery('')} style={styles.clearButton}>
            <Ionicons name="close-circle" size={20} color={colors.textSecondary} />
          </GradientButton>
        )}
      </View>

      {/* Content */}
      {loading ? (
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color={colors.primary} />
          <Text style={styles.loadingText}>Loading students...</Text>
        </View>
      ) : (
        <ScrollView
          style={styles.content}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
          }>
          {filteredStudents.length === 0 ? (
            <View style={styles.emptyState}>
              <Ionicons name="people-outline" size={48} color={colors.textMuted} />
              <Text style={styles.emptyTitle}>No Students Found</Text>
              <Text style={styles.emptySubtitle}>
                {searchQuery ? 'Try a different search term' : 'No students available'}
              </Text>
            </View>
          ) : (
            <>
              <View style={styles.resultsHeader}>
                <Text style={styles.resultsCount}>
                  {filteredStudents.length} student{filteredStudents.length !== 1 ? 's' : ''} found
                </Text>
              </View>
              {filteredStudents.map((student) => (
                <StudentCard key={student.id} student={student} />
              ))}
            </>
          )}
        </ScrollView>
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  searchContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.white,
    marginHorizontal: 16,
    marginTop: 16,
    marginBottom: 8,
    paddingHorizontal: 16,
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
    paddingVertical: 12,
  },
  clearButton: {
    padding: 6,
    borderRadius: 999,
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
    alignItems: 'center',
    marginBottom: 12,
  },
  studentInfo: {
    flex: 1,
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
  rollBadge: {
    backgroundColor: colors.primary + '20',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 8,
  },
  rollText: {
    fontSize: 12,
    fontWeight: '600',
    color: colors.primary,
  },
  studentDetails: {
    marginBottom: 16,
  },
  detailRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 8,
  },
  detailText: {
    fontSize: 14,
    color: colors.textSecondary,
    marginLeft: 8,
  },
  piiSection: {
    marginTop: 16,
    paddingTop: 16,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  piiSectionTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.text,
    marginBottom: 12,
  },
});
