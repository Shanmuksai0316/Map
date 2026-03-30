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
  Modal,
  TextInput,
  ActivityIndicator,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useAuthStore } from '../../store/auth.store';
import { apiService } from '../../services/api.service';
import { User } from '../../types';
import { APP_CONFIG } from '../../config/app.config';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { theme } from '../../theme/theme';

export const ProfileScreen = ({ navigation }: any) => {
  const insets = useSafeAreaInsets();
  const { user, logout, sendOTP } = useAuthStore();
  const [profile, setProfile] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [deletionModalVisible, setDeletionModalVisible] = useState(false);
  const [deletionOtp, setDeletionOtp] = useState('');
  const [deletionLoading, setDeletionLoading] = useState(false);
  const [deletionError, setDeletionError] = useState<string | null>(null);

  const fetchProfile = async () => {
    try {
      const response = await apiService.get<{ data: User }>(
        APP_CONFIG.ENDPOINTS.PROFILE
      );
      setProfile(response.data);
    } catch (error) {
      console.error('Error fetching profile:', error);
      // Fallback to user data from auth store
      setProfile(user);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchProfile();
  }, []);

  const onRefresh = () => {
    setRefreshing(true);
    fetchProfile();
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

  const handleEditProfile = () => {
    Alert.alert('Coming Soon', 'Profile editing will be available soon!');
  };

  const handleChangePassword = () => {
    Alert.alert('Coming Soon', 'Password change will be available soon!');
  };

  const handleSupport = () => {
    Alert.alert('Support', 'Contact: support@maphms.com\nPhone: +91-9876543210');
  };

  const getRoleDisplayName = (role: string) => {
    switch (role) {
      case 'student':
        return 'Student';
      case 'campus_manager':
        return 'Campus Manager';
      case 'rector':
        return 'Rector';
      case 'warden':
        return 'Warden';
      case 'guard':
        return 'Guard';
      case 'supervisor':
        return 'Supervisor';
      case 'manager':
        return 'Manager';
      default:
        return role;
    }
  };

  const profileData = profile || user;

  return (
    <View style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity
          style={styles.backButton}
          onPress={() => navigation.goBack()}>
          <Ionicons name="arrow-back" size={20} color={theme.colors.white} />
          <Text style={styles.backButtonText}>Back</Text>
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Profile</Text>
        <TouchableOpacity style={styles.editButton} onPress={handleEditProfile}>
          <Ionicons name="create-outline" size={18} color={theme.colors.white} />
          <Text style={styles.editButtonText}>Edit</Text>
        </TouchableOpacity>
      </View>

      <ScrollView
        style={styles.content}
        contentContainerStyle={[styles.scrollContent, { paddingBottom: Math.max(insets.bottom, 24) + 80 }]}
        showsVerticalScrollIndicator={true}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>
        {/* Profile Header */}
        <View style={styles.profileHeader}>
          <View style={styles.avatarContainer}>
            {profileData?.avatar ? (
              <Image source={{ uri: profileData.avatar }} style={styles.avatar} />
            ) : (
              <View style={styles.avatarPlaceholder}>
                <Text style={styles.avatarText}>
                  {profileData?.name?.charAt(0).toUpperCase() || 'U'}
                </Text>
              </View>
            )}
          </View>
          <Text style={styles.userName}>{profileData?.name}</Text>
          <Text style={styles.userRole}>
            {getRoleDisplayName(profileData?.role || '')}
          </Text>
        </View>

        {/* Personal Information */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Personal Information</Text>
          <View style={styles.infoCard}>
            <View style={styles.infoRow}>
              <Text style={styles.infoLabel}>Name</Text>
              <Text style={styles.infoValue}>{profileData?.name}</Text>
            </View>
            <View style={styles.infoRow}>
              <Text style={styles.infoLabel}>Phone</Text>
              <Text style={styles.infoValue}>{profileData?.phone}</Text>
            </View>
            {profileData?.email && (
              <View style={styles.infoRow}>
                <Text style={styles.infoLabel}>Email</Text>
                <Text style={styles.infoValue}>{profileData.email}</Text>
              </View>
            )}
            <View style={styles.infoRow}>
              <Text style={styles.infoLabel}>Role</Text>
              <Text style={styles.infoValue}>
                {getRoleDisplayName(profileData?.role || '')}
              </Text>
            </View>
          </View>
        </View>

        {/* Quick Actions */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Quick Actions</Text>
          <View style={styles.actionsCard}>
            <TouchableOpacity
              style={styles.actionItem}
              onPress={handleEditProfile}>
              <View style={styles.actionIcon}>
                <Ionicons name="create-outline" size={18} color={theme.colors.primary} />
              </View>
              <View style={styles.actionContent}>
                <Text style={styles.actionTitle}>Edit Profile</Text>
                <Text style={styles.actionSubtitle}>Update your information</Text>
              </View>
              <Ionicons name="chevron-forward" size={18} color={theme.colors.textSecondary} />
            </TouchableOpacity>

            <TouchableOpacity
              style={styles.actionItem}
              onPress={handleChangePassword}>
              <View style={styles.actionIcon}>
                <Ionicons name="lock-closed-outline" size={18} color={theme.colors.primary} />
              </View>
              <View style={styles.actionContent}>
                <Text style={styles.actionTitle}>Change Password</Text>
                <Text style={styles.actionSubtitle}>Update your password</Text>
              </View>
              <Ionicons name="chevron-forward" size={18} color={theme.colors.textSecondary} />
            </TouchableOpacity>

            <TouchableOpacity style={styles.actionItem} onPress={handleSupport}>
              <View style={styles.actionIcon}>
                <Ionicons name="help-circle-outline" size={18} color={theme.colors.primary} />
              </View>
              <View style={styles.actionContent}>
                <Text style={styles.actionTitle}>Support</Text>
                <Text style={styles.actionSubtitle}>Get help and support</Text>
              </View>
              <Ionicons name="chevron-forward" size={18} color={theme.colors.textSecondary} />
            </TouchableOpacity>

            <TouchableOpacity
              style={[styles.actionItem, styles.deletionAction]}
              onPress={handleRequestAccountDeletion}
              accessible
              accessibilityRole="button"
              accessibilityLabel="Request Account Deletion">
              <View style={styles.actionIcon}>
                <Ionicons name="trash-outline" size={18} color={theme.colors.error} />
              </View>
              <View style={styles.actionContent}>
                <Text style={styles.deletionActionTitle}>Request Account Deletion</Text>
                <Text style={styles.actionSubtitle}>Permanently delete your account</Text>
              </View>
              <Ionicons name="chevron-forward" size={18} color={theme.colors.textSecondary} />
            </TouchableOpacity>
          </View>
        </View>

        {/* App Information */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>App Information</Text>
          <View style={styles.infoCard}>
            <View style={styles.infoRow}>
              <Text style={styles.infoLabel}>App Version</Text>
              <Text style={styles.infoValue}>{APP_CONFIG.APP_VERSION}</Text>
            </View>
            <View style={styles.infoRow}>
              <Text style={styles.infoLabel}>Build Type</Text>
              <Text style={styles.infoValue}>
                {APP_CONFIG.BUILD_VARIANT === 'student' ? 'Student App' : 'Staff App'}
              </Text>
            </View>
          </View>
        </View>

        {/* Logout Button */}
        <TouchableOpacity
          style={styles.logoutButton}
          onPress={handleLogout}
          activeOpacity={0.7}
          accessible
          accessibilityRole="button"
          accessibilityLabel="Logout">
          <Ionicons name="log-out-outline" size={18} color={theme.colors.white} />
          <Text style={styles.logoutButtonText}>Logout</Text>
        </TouchableOpacity>

        {/* Footer */}
        <View style={styles.footer}>
          <Text style={styles.footerText}>
            © 2025 MAP HMS. All rights reserved.
          </Text>
        </View>
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
              <TouchableOpacity
                style={[styles.modalButton, styles.modalButtonCancel]}
                onPress={() => !deletionLoading && setDeletionModalVisible(false)}
                disabled={deletionLoading}>
                <Text style={styles.modalButtonCancelText}>Cancel</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.modalButton, styles.modalButtonSubmit]}
                onPress={handleSubmitDeletion}
                disabled={deletionLoading}>
                {deletionLoading ? (
                  <ActivityIndicator size="small" color={theme.colors.white} />
                ) : (
                  <Text style={styles.modalButtonSubmitText}>Submit</Text>
                )}
              </TouchableOpacity>
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
    backgroundColor: theme.colors.surface,
  },
  header: {
    backgroundColor: theme.colors.primary,
    padding: theme.spacing.lg,
    paddingTop: theme.spacing.xxl * 1.5,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  backButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.xs,
    padding: theme.spacing.sm,
  },
  backButtonText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
  },
  headerTitle: {
    color: theme.colors.white,
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
  },
  editButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.xs,
    backgroundColor: 'rgba(255,255,255,0.2)',
    paddingHorizontal: theme.spacing.md,
    paddingVertical: theme.spacing.sm,
    borderRadius: theme.borderRadius.sm,
  },
  editButtonText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
  },
  content: {
    flex: 1,
  },
  scrollContent: {
    paddingBottom: 120,
  },
  profileHeader: {
    backgroundColor: theme.colors.white,
    alignItems: 'center',
    padding: theme.spacing.xl,
    marginBottom: theme.spacing.md,
  },
  avatarContainer: {
    marginBottom: theme.spacing.md,
  },
  avatar: {
    width: 80,
    height: 80,
    borderRadius: 40,
  },
  avatarPlaceholder: {
    width: 80,
    height: 80,
    borderRadius: 40,
    backgroundColor: theme.colors.success,
    justifyContent: 'center',
    alignItems: 'center',
  },
  avatarText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.xxxl,
    fontWeight: theme.fontWeight.bold,
  },
  userName: {
    fontSize: theme.fontSize.xxl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
    marginBottom: theme.spacing.xs,
  },
  userRole: {
    fontSize: theme.fontSize.md,
    color: theme.colors.textSecondary,
  },
  section: {
    marginBottom: theme.spacing.md,
  },
  sectionTitle: {
    fontSize: theme.fontSize.lg,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
    paddingHorizontal: theme.spacing.lg,
    marginBottom: theme.spacing.sm,
  },
  infoCard: {
    backgroundColor: theme.colors.white,
    padding: theme.spacing.lg,
    marginHorizontal: theme.spacing.md,
    borderRadius: theme.borderRadius.sm,
    ...theme.shadows.small,
  },
  infoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: theme.spacing.sm,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.divider,
  },
  infoLabel: {
    fontSize: theme.fontSize.md,
    color: theme.colors.textSecondary,
    fontWeight: theme.fontWeight.medium,
  },
  infoValue: {
    fontSize: theme.fontSize.md,
    color: theme.colors.text,
    fontWeight: theme.fontWeight.semibold,
    textAlign: 'right',
    flex: 1,
    marginLeft: theme.spacing.md,
  },
  actionsCard: {
    backgroundColor: theme.colors.white,
    marginHorizontal: theme.spacing.md,
    borderRadius: theme.borderRadius.sm,
    ...theme.shadows.small,
  },
  actionItem: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: theme.spacing.lg,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.divider,
  },
  actionIcon: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: theme.colors.surface,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: theme.spacing.md,
  },
  actionContent: {
    flex: 1,
  },
  actionTitle: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
    marginBottom: 2,
  },
  actionSubtitle: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
  },
  actionArrow: {
    fontSize: theme.fontSize.lg,
    color: theme.colors.textMuted,
  },
  logoutButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: theme.spacing.sm,
    backgroundColor: '#D79F24', // Golden yellow to match bell icon
    margin: theme.spacing.md,
    padding: theme.spacing.md,
    borderRadius: theme.borderRadius.sm,
  },
  logoutButtonText: {
    color: theme.colors.primary,
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
  },
  footer: {
    alignItems: 'center',
    padding: theme.spacing.lg,
    marginTop: theme.spacing.lg,
  },
  footerText: {
    fontSize: theme.fontSize.xs,
    color: theme.colors.textMuted,
  },
  deletionAction: {
    borderLeftWidth: 3,
    borderLeftColor: theme.colors.error,
  },
  deletionActionTitle: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.error,
    marginBottom: 2,
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.5)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 24,
  },
  modalContent: {
    backgroundColor: theme.colors.white,
    borderRadius: theme.borderRadius.sm,
    padding: theme.spacing.xl,
    width: '100%',
    maxWidth: 340,
  },
  modalTitle: {
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
    marginBottom: theme.spacing.sm,
  },
  modalSubtitle: {
    fontSize: theme.fontSize.md,
    color: theme.colors.textSecondary,
    marginBottom: theme.spacing.md,
  },
  otpInput: {
    borderWidth: 1,
    borderColor: theme.colors.divider,
    borderRadius: theme.borderRadius.sm,
    padding: theme.spacing.md,
    fontSize: theme.fontSize.lg,
    color: theme.colors.text,
    marginBottom: theme.spacing.sm,
  },
  modalError: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.error,
    marginBottom: theme.spacing.sm,
  },
  modalButtons: {
    flexDirection: 'row',
    gap: theme.spacing.md,
    marginTop: theme.spacing.sm,
  },
  modalButton: {
    flex: 1,
    padding: theme.spacing.md,
    borderRadius: theme.borderRadius.sm,
    alignItems: 'center',
  },
  modalButtonCancel: {
    backgroundColor: theme.colors.surface,
  },
  modalButtonCancelText: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.textSecondary,
  },
  modalButtonSubmit: {
    backgroundColor: theme.colors.error,
  },
  modalButtonSubmitText: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.white,
  },
});

