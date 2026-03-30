import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  Image,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useAuthStore } from '../../shared/store/auth.store';
import { apiService } from '../../shared/services/api.service';
import { APP_CONFIG } from '../../shared/config/app.config';
import { theme } from '../../shared/theme/theme';

export const ProfileDetailsScreen = ({ navigation }: any) => {
  const insets = useSafeAreaInsets();
  const { user } = useAuthStore();
  const [profile, setProfile] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const fetchProfileData = async () => {
    try {
      const profileRes = await apiService.get<{ data: any }>(APP_CONFIG.ENDPOINTS.PROFILE);
      setProfile(profileRes.data);
    } catch (error) {
      console.error('Error fetching profile data:', error);
      setProfile(user);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchProfileData();
  }, []);

  const onRefresh = () => {
    setRefreshing(true);
    fetchProfileData();
  };

  const profileData = profile || user;

  const HEADER_ROW_HEIGHT = 52;
  const HEADER_PADDING_TOP = Math.max(insets.top, 10);
  const HEADER_PADDING_BOTTOM = 6;

  return (
    <View style={styles.container}>
      {/* Header */}
      <View
        style={[
          styles.header,
          {
            paddingTop: HEADER_PADDING_TOP,
            paddingBottom: HEADER_PADDING_BOTTOM,
            minHeight: HEADER_PADDING_TOP + HEADER_ROW_HEIGHT + HEADER_PADDING_BOTTOM,
          },
        ]}>
        <View style={[styles.headerRow, { height: HEADER_ROW_HEIGHT }]}>
          <TouchableOpacity
            style={styles.backButton}
            onPress={() => (navigation?.canGoBack?.() ? navigation.goBack() : navigation.navigate('Profile'))}
            accessibilityLabel="Go back">
            <Ionicons name="arrow-back" size={24} color={theme.colors.primary} />
          </TouchableOpacity>
          <Text style={styles.headerTitle}>My Profile</Text>
          <View style={styles.headerSpacer} />
        </View>
      </View>

      <ScrollView
        style={styles.content}
        contentContainerStyle={[
          styles.scrollContent,
          { paddingBottom: Math.max(insets.bottom, 24) + 80 },
        ]}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>

        {/* Profile Card - Avatar and Basic Info */}
        <View style={styles.profileCard}>
          <View style={styles.avatarContainer}>
            {profileData?.avatar ? (
              <Image source={{ uri: profileData.avatar }} style={styles.avatar} />
            ) : (
              <View style={styles.avatarPlaceholder}>
                <Text style={styles.avatarText}>
                  {profileData?.name?.charAt(0).toUpperCase() || 'S'}
                </Text>
              </View>
            )}
          </View>
          <Text style={styles.studentName}>{profileData?.name || 'Student'}</Text>
          <Text style={styles.studentId}>ID: {profileData?.student_uid || 'N/A'}</Text>
        </View>

        {/* Basic Details Card */}
        <View style={styles.detailsCard}>
          <Text style={styles.sectionTitle}>Basic Details</Text>
          
          <View style={styles.detailRow}>
            <Ionicons name="call-outline" size={20} color={theme.colors.primary} />
            <Text style={styles.detailLabel}>Phone</Text>
            <Text style={styles.detailValue}>{profileData?.phone || 'N/A'}</Text>
          </View>
          
          <View style={styles.detailRow}>
            <Ionicons name="mail-outline" size={20} color={theme.colors.primary} />
            <Text style={styles.detailLabel}>Email</Text>
            <Text style={styles.detailValue}>{profileData?.email || 'N/A'}</Text>
          </View>
          
          <View style={styles.detailRow}>
            <Ionicons name="calendar-outline" size={20} color={theme.colors.primary} />
            <Text style={styles.detailLabel}>Date of Birth</Text>
            <Text style={styles.detailValue}>{profileData?.date_of_birth || 'N/A'}</Text>
          </View>
          
          <View style={styles.detailRow}>
            <Ionicons name="people-outline" size={20} color={theme.colors.primary} />
            <Text style={styles.detailLabel}>Gender</Text>
            <Text style={styles.detailValue}>{profileData?.gender || 'N/A'}</Text>
          </View>
        </View>

        {/* Hostel Details Card */}
        <View style={styles.detailsCard}>
          <Text style={styles.sectionTitle}>Hostel Details</Text>
          
          <View style={styles.detailRow}>
            <Ionicons name="business-outline" size={20} color={theme.colors.primary} />
            <Text style={styles.detailLabel}>Hostel</Text>
            <Text style={styles.detailValue}>
              {profileData?.student?.room_allocation?.hostel_name || 
               profileData?.student?.hostel_name || 'N/A'}
            </Text>
          </View>
          
          <View style={styles.detailRow}>
            <Ionicons name="home-outline" size={20} color={theme.colors.primary} />
            <Text style={styles.detailLabel}>Room</Text>
            <Text style={styles.detailValue}>
              {profileData?.student?.room_allocation
                ? `${profileData.student.room_allocation.block_code || ''}${
                    profileData.student.room_allocation.floor_code || ''
                  }${profileData.student.room_allocation.room_number || ''}`.trim()
                : 'N/A'}
            </Text>
          </View>
          
          <View style={styles.detailRow}>
            <Ionicons name="bed-outline" size={20} color={theme.colors.primary} />
            <Text style={styles.detailLabel}>Bed</Text>
            <Text style={styles.detailValue}>
              {profileData?.student?.room_allocation?.bed_number || 'N/A'}
            </Text>
          </View>
        </View>

        {/* Academic Details Card */}
        {(profileData?.student?.course || profileData?.student?.year) && (
          <View style={styles.detailsCard}>
            <Text style={styles.sectionTitle}>Academic Details</Text>
            
            {profileData?.student?.course && (
              <View style={styles.detailRow}>
                <Ionicons name="school-outline" size={20} color={theme.colors.primary} />
                <Text style={styles.detailLabel}>Course</Text>
                <Text style={styles.detailValue}>{profileData.student.course}</Text>
              </View>
            )}
            
            {profileData?.student?.year && (
              <View style={styles.detailRow}>
                <Ionicons name="ribbon-outline" size={20} color={theme.colors.primary} />
                <Text style={styles.detailLabel}>Year</Text>
                <Text style={styles.detailValue}>{profileData.student.year}</Text>
              </View>
            )}
          </View>
        )}
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.white,
  },
  header: {
    backgroundColor: theme.colors.white,
    paddingHorizontal: 16,
    ...theme.shadows.medium,
  },
  headerRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  backButton: {
    padding: 8,
  },
  headerTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: theme.colors.primary,
  },
  headerSpacer: {
    width: 40,
  },
  content: {
    flex: 1,
  },
  scrollContent: {
    padding: 16,
  },
  profileCard: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.borderRadius.lg,
    padding: 24,
    alignItems: 'center',
    marginBottom: 16,
    ...theme.shadows.small,
  },
  avatarContainer: {
    marginBottom: 16,
  },
  avatar: {
    width: 96,
    height: 96,
    borderRadius: 48,
    borderWidth: 3,
    borderColor: theme.colors.primary,
  },
  avatarPlaceholder: {
    width: 96,
    height: 96,
    borderRadius: 48,
    backgroundColor: theme.colors.primary,
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 3,
    borderColor: theme.colors.primary,
  },
  avatarText: {
    color: theme.colors.white,
    fontSize: 36,
    fontWeight: '700',
  },
  studentName: {
    fontSize: 24,
    fontWeight: '700',
    color: theme.colors.text,
    marginBottom: 4,
    textAlign: 'center',
  },
  studentId: {
    fontSize: 16,
    color: theme.colors.textSecondary,
    textAlign: 'center',
  },
  detailsCard: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.borderRadius.lg,
    padding: 16,
    marginBottom: 16,
    ...theme.shadows.small,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: theme.colors.text,
    marginBottom: 16,
  },
  detailRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.divider,
  },
  detailLabel: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.text,
    marginLeft: 12,
    flex: 1,
    marginRight: 12,
  },
  detailValue: {
    fontSize: 16,
    color: theme.colors.textSecondary,
    fontWeight: '500',
    textAlign: 'right',
    flexShrink: 1,
    flexGrow: 1,
    minWidth: 0,
    flexWrap: 'wrap',
  },
});
