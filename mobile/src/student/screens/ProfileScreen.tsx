import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  Alert,
  RefreshControl,
  Image,
  Share,
  Linking,
  Modal,
  TextInput,
  ActivityIndicator,
} from 'react-native';
import { GradientButton } from '../../shared/components/GradientButton';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useAuthStore } from '../../shared/store/auth.store';
import { apiService } from '../../shared/services/api.service';
import { Attendance } from '../../types';
import { APP_CONFIG } from '../../shared/config/app.config';
import { getStudentAppSharePayload } from '../../shared/constants/share-app.constants';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { theme } from '../../shared/theme/theme';
import { EmptyState } from '../../shared/components';
import { format, startOfMonth, endOfMonth } from 'date-fns';

export const ProfileScreen = ({ navigation }: any) => {
  const insets = useSafeAreaInsets();
  const { user, logout, sendOTP } = useAuthStore();
  const [profile, setProfile] = useState<any>(null);
  const [attendance, setAttendance] = useState<Attendance[]>([]);
  const [attendanceStats, setAttendanceStats] = useState({
    present: 0,
    absent: 0,
    total: 0,
  });
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [activeHistoryFilter, setActiveHistoryFilter] = useState<string>('all');
  const [deletionModalVisible, setDeletionModalVisible] = useState(false);
  const [deletionOtp, setDeletionOtp] = useState('');
  const [deletionLoading, setDeletionLoading] = useState(false);
  const [deletionError, setDeletionError] = useState<string | null>(null);

  const fetchProfileData = async () => {
    try {
      const [profileRes, attendanceRes] = await Promise.all([
        apiService.get<{ data: any }>(APP_CONFIG.ENDPOINTS.PROFILE),
        apiService.get<{ data: Attendance[] }>(APP_CONFIG.ENDPOINTS.ATTENDANCE),
      ]);

      setProfile(profileRes.data);

      const attendanceData = attendanceRes.data || [];
      setAttendance(attendanceData);

      // Calculate attendance stats for current month
      const currentMonth = new Date();
      const monthStart = startOfMonth(currentMonth);
      const monthEnd = endOfMonth(currentMonth);

      const currentMonthAttendance = attendanceData.filter(att => {
        const attDate = new Date(att.date);
        return attDate >= monthStart && attDate <= monthEnd;
      });

      const present = currentMonthAttendance.filter(att => att.status === 'present').length;
      const total = currentMonthAttendance.length;

      setAttendanceStats({
        present,
        absent: total - present,
        total,
      });

    } catch (error) {
      console.error('Error fetching profile data:', error);
      // Fallback to user data
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

  const handleLogout = () => {
    Alert.alert(
      'Logout',
      'Are you sure you want to logout?',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Logout',
          style: 'destructive',
          onPress: async () => {
            await logout();
          },
        },
      ]
    );
  };

  const handleRequestAccountDeletion = () => {
    Alert.alert(
      'Request Account Deletion',
      'This will permanently delete your account and data. You will be logged out immediately. This cannot be undone. Continue?',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Continue',
          style: 'destructive',
          onPress: async () => {
            const phone = user?.phone || profile?.phone;
            if (!phone) {
              Alert.alert('Error', 'Unable to verify your phone number. Please contact support.');
              return;
            }
            setDeletionError(null);
            setDeletionOtp('');
            setDeletionModalVisible(true);
            try {
              await sendOTP(phone);
            } catch (err: any) {
              setDeletionError(err?.message || 'Failed to send OTP. Please try again.');
            }
          },
        },
      ]
    );
  };

  const handleSubmitDeletion = async () => {
    if (!deletionOtp || deletionOtp.length !== 6) {
      setDeletionError('Please enter the 6-digit OTP sent to your phone.');
      return;
    }
    setDeletionLoading(true);
    setDeletionError(null);
    try {
      await apiService.post(APP_CONFIG.ENDPOINTS.ACCOUNT_DELETION_REQUEST, { otp: deletionOtp });
      setDeletionModalVisible(false);
      setDeletionOtp('');
      await logout();
    } catch (err: any) {
      const detail = err?.response?.data?.errors?.detail
        || err?.response?.data?.message
        || err?.message
        || 'Failed to submit deletion request. Please try again.';
      setDeletionError(detail);
    } finally {
      setDeletionLoading(false);
    }
  };

  const handleShareApp = async () => {
    try {
      const { title, message, url } = getStudentAppSharePayload();
      await Share.share({ title, message, url });
    } catch (error) {
      console.error('Error sharing app:', error);
    }
  };

  const openSocialMedia = (platform: string) => {
    const urls: { [key: string]: string } = {
      facebook: 'https://www.facebook.com/omapservices',
      instagram: 'https://www.instagram.com/omapservices',
      twitter: 'https://twitter.com/omapservices',
      linkedin: 'https://www.linkedin.com/company/omapservices',
    };
    
    const url = urls[platform];
    if (url) {
      Linking.openURL(url).catch(() => {
        Alert.alert('Error', 'Could not open social media link');
      });
    }
  };

  const profileData = profile || user;

  const HEADER_ROW_HEIGHT = 52;
  const HEADER_PADDING_TOP = Math.max(insets.top, 10);
  const HEADER_PADDING_BOTTOM = 6;

  return (
    <View style={styles.container}>
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
          <GradientButton
            style={styles.headerBackButton}
            onPress={() => {
              if (navigation?.canGoBack?.()) {
                navigation.goBack();
              } else {
                navigation.navigate('Home');
              }
            }}
            accessibilityLabel="Go back">
            <Ionicons name="arrow-back" size={24} color={theme.colors.primary} />
          </GradientButton>
          <Text style={styles.headerTitle}>Profile</Text>
          <View style={styles.headerSpacer} />
        </View>
      </View>

      <ScrollView
        style={styles.content}
        contentContainerStyle={[styles.scrollContent, { paddingBottom: Math.max(insets.bottom, 24) + 80 }]}
        showsVerticalScrollIndicator={true}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>

        {/* Intro Card - Student Name & ID */}
        <View style={styles.introCard}>
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
          <View style={styles.introText}>
            <Text style={styles.studentName}>{profileData?.name || 'Student'}</Text>
            <Text style={styles.studentId}>ID: {profileData?.student_uid || 'N/A'}</Text>
          </View>
        </View>

        {/* 1. My Profile - Navigate to Details */}
        <GradientButton
          style={styles.actionButton}
          onPress={() => navigation.navigate('ProfileDetails')}>
          <Ionicons name="person-outline" size={20} color={theme.colors.primary} />
          <Text style={styles.actionButtonText}>My Profile</Text>
          <Ionicons name="chevron-forward" size={20} color={theme.colors.textMuted} />
        </GradientButton>

        {/* 2. Room Change Request */}
        <GradientButton
          style={styles.actionButton}
          onPress={() => navigation.navigate('RoomChangePreview')}>
          <Ionicons name="swap-horizontal-outline" size={20} color={theme.colors.primary} />
          <Text style={styles.actionButtonText}>Room Change Request</Text>
          <Ionicons name="chevron-forward" size={20} color={theme.colors.textMuted} />
        </GradientButton>

        {/* (Attendance, Social Media, Share Application moved out of Profile) */}

        {/* Request Account Deletion (Apple 5.1.1(v)) */}
        <GradientButton
          style={[styles.actionButton, styles.deletionButton]}
          onPress={handleRequestAccountDeletion}
          accessible
          accessibilityRole="button"
          accessibilityLabel="Request Account Deletion">
          <Ionicons name="trash-outline" size={20} color={theme.colors.error} />
          <Text style={styles.deletionButtonText}>Request Account Deletion</Text>
          <Ionicons name="chevron-forward" size={20} color={theme.colors.textMuted} />
        </GradientButton>

        {/* 6. Logout Button */}
        <GradientButton
          style={styles.logoutButton}
          onPress={handleLogout}
          activeOpacity={0.7}
          accessible
          accessibilityRole="button"
          accessibilityLabel="Logout">
          <Ionicons name="log-out-outline" size={20} color={theme.colors.white} />
          <Text style={styles.logoutButtonText}>Logout</Text>
        </GradientButton>
      </ScrollView>

      {/* OTP Modal for Account Deletion */}
      <Modal
        visible={deletionModalVisible}
        transparent
        animationType="fade"
        onRequestClose={() => !deletionLoading && setDeletionModalVisible(false)}>
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <Text style={styles.modalTitle}>Verify with OTP</Text>
            <Text style={styles.modalSubtitle}>
              Enter the 6-digit code sent to your registered phone number.
            </Text>
            <TextInput
              style={styles.otpInput}
              placeholder="Enter OTP"
              placeholderTextColor={theme.colors.textMuted}
              value={deletionOtp}
              onChangeText={(t) => { setDeletionOtp(t.replace(/\D/g, '').slice(0, 6)); setDeletionError(null); }}
              keyboardType="number-pad"
              maxLength={6}
              editable={!deletionLoading}
              autoFocus
            />
            {deletionError ? (
              <Text style={styles.modalError}>{deletionError}</Text>
            ) : null}
            <View style={styles.modalButtons}>
              <GradientButton
                style={[styles.modalButton, styles.modalButtonCancel]}
                onPress={() => !deletionLoading && setDeletionModalVisible(false)}
                disabled={deletionLoading}>
                <Text style={styles.modalButtonCancelText}>Cancel</Text>
              </GradientButton>
              <GradientButton
                style={[styles.modalButton, styles.modalButtonSubmit]}
                onPress={handleSubmitDeletion}
                disabled={deletionLoading}>
                {deletionLoading ? (
                  <ActivityIndicator size="small" color={theme.colors.white} />
                ) : (
                  <Text style={styles.modalButtonSubmitText}>Submit</Text>
                )}
              </GradientButton>
            </View>
          </View>
        </View>
      </Modal>
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
    paddingHorizontal: 20,
    ...theme.shadows.medium,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  headerBackButton: {
    paddingVertical: theme.spacing.xs,
    paddingRight: theme.spacing.sm,
  },
  headerTitle: {
    flex: 1,
    textAlign: 'center',
    fontSize: theme.fontSize.lg,
    fontWeight: theme.fontWeight.bold,
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
    paddingBottom: 120,
  },
  introCard: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.borderRadius.lg,
    padding: 20,
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 16,
    ...theme.shadows.small,
  },
  avatarContainer: {
    marginRight: 16,
  },
  avatar: {
    width: 64,
    height: 64,
    borderRadius: 32,
  },
  avatarPlaceholder: {
    width: 64,
    height: 64,
    borderRadius: 32,
    backgroundColor: theme.colors.success,
    justifyContent: 'center',
    alignItems: 'center',
  },
  avatarText: {
    color: theme.colors.white,
    fontSize: 24,
    fontWeight: '700',
  },
  introText: {
    flex: 1,
  },
  studentName: {
    fontSize: 20,
    fontWeight: '700',
    color: theme.colors.text,
    marginBottom: 4,
  },
  studentId: {
    fontSize: 14,
    color: theme.colors.textMuted,
  },
  detailsCard: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.borderRadius.lg,
    padding: 16,
    marginBottom: 16,
    ...theme.shadows.small,
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
  },
  detailValue: {
    fontSize: 16,
    color: theme.colors.text,
    fontWeight: '500',
  },
  actionButton: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.borderRadius.lg,
    padding: 16,
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 12,
    ...theme.shadows.small,
  },
  actionButtonText: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.text,
    marginLeft: 12,
    flex: 1,
  },
  sectionCard: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.borderRadius.lg,
    padding: 16,
    marginBottom: 16,
    ...theme.shadows.small,
  },
  sectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 16,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: theme.colors.text,
    marginLeft: 12,
  },
  attendanceStats: {
    flexDirection: 'row',
    justifyContent: 'space-around',
    alignItems: 'center',
    marginBottom: 16,
  },
  statItem: {
    alignItems: 'center',
    flex: 1,
  },
  statNumber: {
    fontSize: 24,
    fontWeight: '700',
    color: theme.colors.primary,
    marginBottom: 4,
  },
  statLabel: {
    fontSize: 12,
    color: theme.colors.textSecondary,
    fontWeight: '500',
    textTransform: 'uppercase',
  },
  statDivider: {
    width: 1,
    height: 40,
    backgroundColor: theme.colors.divider,
  },
  sectionArrow: {
    alignSelf: 'flex-end',
  },
  historySection: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.borderRadius.lg,
    padding: 16,
    marginBottom: 16,
    ...theme.shadows.small,
  },
  filterScroll: {
    marginBottom: 16,
  },
  filterPill: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: theme.colors.surface,
    borderWidth: 1,
    borderColor: theme.colors.border,
    marginRight: 8,
  },
  filterPillActive: {
    backgroundColor: theme.colors.primary,
    borderColor: theme.colors.primary,
  },
  filterPillText: {
    fontSize: 14,
    fontWeight: '600',
    color: theme.colors.textSecondary,
  },
  filterPillTextActive: {
    color: theme.colors.white,
  },
  viewAllButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 12,
    paddingHorizontal: 20,
    backgroundColor: `${theme.colors.primary}15`,
    borderRadius: theme.borderRadius.md,
    alignSelf: 'flex-start',
  },
  viewAllButtonText: {
    fontSize: 14,
    fontWeight: '600',
    color: theme.colors.primary,
    marginRight: 8,
  },
  logoutButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    backgroundColor: '#D79F24', // Golden yellow to match bell icon
    padding: 16,
    borderRadius: theme.borderRadius.lg,
    marginTop: 16,
    ...theme.shadows.medium,
  },
  logoutButtonText: {
    color: theme.colors.primary,
    fontSize: 16,
    fontWeight: '600',
  },
  socialMediaContainer: {
    flexDirection: 'row',
    justifyContent: 'space-around',
    alignItems: 'center',
    paddingVertical: 12,
  },
  socialButton: {
    width: 48,
    height: 48,
    borderRadius: 24,
    backgroundColor: theme.colors.surface,
    justifyContent: 'center',
    alignItems: 'center',
    ...theme.shadows.small,
  },
  deletionButton: {
    borderLeftWidth: 3,
    borderLeftColor: theme.colors.error || '#DC3545',
  },
  deletionButtonText: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.error || '#DC3545',
    marginLeft: 12,
    flex: 1,
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.5)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 24,
  },
  modalContent: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.borderRadius.lg,
    padding: 24,
    width: '100%',
    maxWidth: 340,
  },
  modalTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: theme.colors.text,
    marginBottom: 8,
  },
  modalSubtitle: {
    fontSize: 14,
    color: theme.colors.textSecondary,
    marginBottom: 16,
  },
  otpInput: {
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.borderRadius.md,
    padding: 14,
    fontSize: 18,
    color: theme.colors.text,
    marginBottom: 12,
  },
  modalError: {
    fontSize: 13,
    color: theme.colors.error || '#DC3545',
    marginBottom: 12,
  },
  modalButtons: {
    flexDirection: 'row',
    gap: 12,
    marginTop: 8,
  },
  modalButton: {
    flex: 1,
    padding: 14,
    borderRadius: theme.borderRadius.md,
    alignItems: 'center',
  },
  modalButtonCancel: {
    backgroundColor: theme.colors.surface,
  },
  modalButtonCancelText: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.textSecondary,
  },
  modalButtonSubmit: {
    backgroundColor: theme.colors.error || '#DC3545',
  },
  modalButtonSubmitText: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.white,
  },
});
