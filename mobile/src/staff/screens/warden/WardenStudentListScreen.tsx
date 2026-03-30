import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  TextInput,
  ActivityIndicator,
} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { apiService } from '../../../shared/services/api.service';
import { APP_CONFIG } from '../../../shared/config/app.config';
import { colors } from '../../../shared/theme/colors';
import { matchesPhoneQuery } from '../../../shared/utils/phone-search.util';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface Student {
  id: string;
  map_student_id?: string;
  name: string;
  phone?: string;
  email?: string;
  roll_no?: string;
  program?: string;
  year_of_study?: number;
  hostel_name?: string;
  room_no?: string;
  bed_no?: string;
}

interface Props {
  navigation: any;
}

export const WardenStudentListScreen: React.FC<Props> = ({ navigation }) => {
  const [students, setStudents] = useState<Student[]>([]);
  const [filteredStudents, setFilteredStudents] = useState<Student[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');

  const fetchStudents = useCallback(async () => {
    try {
      const response = await apiService.get<any>(APP_CONFIG.ENDPOINTS.WARDEN_STUDENTS);
      const studentsData = response?.data || response || [];
      setStudents(studentsData);
      setFilteredStudents(studentsData);
    } catch (error) {
      console.error('Failed to fetch students:', error);
      setStudents([]);
      setFilteredStudents([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    fetchStudents();
  }, [fetchStudents]);

  useEffect(() => {
    if (searchQuery.trim() === '') {
      setFilteredStudents(students);
    } else {
      const query = searchQuery.toLowerCase();
      const filtered = students.filter(
        (s) =>
          s.name?.toLowerCase().includes(query) ||
          s.room_no?.toLowerCase().includes(query) ||
          s.roll_no?.toLowerCase().includes(query) ||
          s.map_student_id?.toLowerCase().includes(query) ||
          Boolean(s.phone && matchesPhoneQuery(s.phone, searchQuery))
      );
      setFilteredStudents(filtered);
    }
  }, [searchQuery, students]);

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    fetchStudents();
  }, [fetchStudents]);

  const handleStudentPress = (student: Student) => {
    navigation.navigate('WardenStudentDetail', { student });
  };

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Students" />

      {/* Search Bar */}
      <View style={styles.searchContainer}>
        <Ionicons name="search-outline" size={20} color={colors.textMuted} />
        <TextInput
          style={styles.searchInput}
          placeholder="Search by name, room, roll no, or phone"
          placeholderTextColor={colors.textMuted}
          value={searchQuery}
          onChangeText={setSearchQuery}
        />
        {searchQuery.length > 0 && (
          <TouchableOpacity onPress={() => setSearchQuery('')}>
            <Ionicons name="close-circle" size={20} color={colors.textMuted} />
          </TouchableOpacity>
        )}
      </View>

      {/* Content */}
      {loading ? (
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color={colors.primary} />
        </View>
      ) : (
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
          {filteredStudents.length === 0 ? (
            <View style={styles.emptyState}>
              <Ionicons name="people-outline" size={64} color={colors.textMuted} />
              <Text style={styles.emptyTitle}>No Students Found</Text>
              <Text style={styles.emptySubtitle}>
                {searchQuery ? 'Try a different search term' : 'No students allocated to your hostel'}
              </Text>
            </View>
          ) : (
            <>
              {filteredStudents.map((student) => (
                <TouchableOpacity
                  key={student.id}
                  style={styles.studentCard}
                  onPress={() => handleStudentPress(student)}
                  activeOpacity={0.7}
                >
                  <View style={styles.avatarContainer}>
                    <Text style={styles.avatarText}>
                      {student.name?.charAt(0).toUpperCase() || 'S'}
                    </Text>
                  </View>
                  <View style={styles.studentInfo}>
                    <Text style={styles.studentName}>{student.name}</Text>
                    <View style={styles.studentDetails}>
                      <View style={styles.detailItem}>
                        <Ionicons name="bed-outline" size={14} color={colors.textMuted} />
                        <Text style={styles.detailText}>
                          Room {student.room_no || 'N/A'}
                        </Text>
                      </View>
                      <View style={styles.detailItem}>
                        <Ionicons name="business-outline" size={14} color={colors.textMuted} />
                        <Text style={styles.detailText}>
                          {student.hostel_name || 'N/A'}
                        </Text>
                      </View>
                    </View>
                  </View>
                  <Ionicons name="chevron-forward" size={20} color={colors.textMuted} />
                </TouchableOpacity>
              ))}
              <View style={styles.bottomPadding} />
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
    backgroundColor: colors.surface,
    marginHorizontal: 16,
    marginTop: 16,
    paddingHorizontal: 16,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: colors.border,
  },
  searchInput: {
    flex: 1,
    paddingVertical: 14,
    marginLeft: 12,
    fontSize: 15,
    color: colors.text,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  content: {
    flex: 1,
    padding: 16,
  },
  emptyState: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: 80,
  },
  emptyTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: colors.textSecondary,
    marginTop: 16,
  },
  emptySubtitle: {
    fontSize: 14,
    color: colors.textMuted,
    marginTop: 4,
  },
  studentCard: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.surface,
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: colors.border,
    shadowColor: colors.black,
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05,
    shadowRadius: 2,
    elevation: 1,
  },
  avatarContainer: {
    width: 48,
    height: 48,
    borderRadius: 24,
    backgroundColor: colors.primary,
    justifyContent: 'center',
    alignItems: 'center',
  },
  avatarText: {
    color: colors.white,
    fontSize: 20,
    fontWeight: '700',
  },
  studentInfo: {
    flex: 1,
    marginLeft: 12,
  },
  studentName: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.textHeading,
    marginBottom: 4,
  },
  studentDetails: {
    flexDirection: 'row',
    gap: 16,
  },
  detailItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
  },
  detailText: {
    fontSize: 13,
    color: colors.textMuted,
  },
  bottomPadding: {
    height: 40,
  },
});

export default WardenStudentListScreen;
