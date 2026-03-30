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
} from 'react-native';
import { useAuthStore } from '../../store/auth.store';
import { apiService } from '../../services/api.service';
import { User } from '../../types';
import { APP_CONFIG } from '../../config/app.config';

export const StaffProfileScreen = ({ navigation }: any) => {
  const { user, logout } = useAuthStore();
  const [profile, setProfile] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

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

  const handleEditProfile = () => {
    Alert.alert('Coming Soon', 'Profile editing will be available soon!');
  };

  const handleChangePassword = () => {
    Alert.alert('Coming Soon', 'Password change will be available soon!');
  };

  const handleSupport = () => {
    Alert.alert('Support', 'Contact: support@maphms.com\nPhone: +91-9876543210');
  };

  const handleStaffDirectory = () => {
    Alert.alert('Staff Directory', 'Staff directory will be available soon!');
  };

  const handleSystemSettings = () => {
    Alert.alert('System Settings', 'System settings will be available soon!');
  };

  const getRoleDisplayName = (role: string) => {
    switch (role) {
      case 'campus_manager':
        return 'Campus Manager';
      case 'rector':
        return 'Rector';
      case 'warden':
        return 'Warden';
      case 'guard':
        return 'Security Guard';
      case 'hk_supervisor':
        return 'HK Supervisor';
      case 'rm_supervisor':
        return 'RM Supervisor';
      case 'laundry_manager':
        return 'Laundry Manager';
      case 'sports_manager':
        return 'Sports Manager';
      default:
        return role;
    }
  };

  const getRoleColor = (role: string) => {
    switch (role) {
      case 'campus_manager':
        return '#4CAF50';
      case 'rector':
        return '#2196F3';
      case 'warden':
        return '#FF9800';
      case 'guard':
        return '#9C27B0';
      case 'hk_supervisor':
        return '#795548';
      case 'rm_supervisor':
        return '#607D8B';
      case 'laundry_manager':
        return '#FF5722';
      case 'sports_manager':
        return '#E91E63';
      default:
        return '#666';
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
          <Text style={styles.backButtonText}>← Back</Text>
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Profile</Text>
        <TouchableOpacity style={styles.editButton} onPress={handleEditProfile}>
          <Text style={styles.editButtonText}>Edit</Text>
        </TouchableOpacity>
      </View>

      <ScrollView
        style={styles.content}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>
        {/* Profile Header */}
        <View style={styles.profileHeader}>
          <View style={styles.avatarContainer}>
            {profileData?.avatar ? (
              <Image source={{ uri: profileData.avatar }} style={styles.avatar} />
            ) : (
              <View style={[
                styles.avatarPlaceholder,
                { backgroundColor: getRoleColor(profileData?.role || '') }
              ]}>
                <Text style={styles.avatarText}>
                  {profileData?.name?.charAt(0).toUpperCase() || 'U'}
                </Text>
              </View>
            )}
          </View>
          <Text style={styles.userName}>{profileData?.name}</Text>
          <View style={[
            styles.roleBadge,
            { backgroundColor: getRoleColor(profileData?.role || '') }
          ]}>
            <Text style={styles.roleText}>
              {getRoleDisplayName(profileData?.role || '')}
            </Text>
          </View>
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

        {/* Staff Actions */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Staff Actions</Text>
          <View style={styles.actionsCard}>
            <TouchableOpacity
              style={styles.actionItem}
              onPress={handleEditProfile}>
              <View style={styles.actionIcon}>
                <Text style={styles.actionIconText}>✏️</Text>
              </View>
              <View style={styles.actionContent}>
                <Text style={styles.actionTitle}>Edit Profile</Text>
                <Text style={styles.actionSubtitle}>Update your information</Text>
              </View>
              <Text style={styles.actionArrow}>›</Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={styles.actionItem}
              onPress={handleChangePassword}>
              <View style={styles.actionIcon}>
                <Text style={styles.actionIconText}>🔒</Text>
              </View>
              <View style={styles.actionContent}>
                <Text style={styles.actionTitle}>Change Password</Text>
                <Text style={styles.actionSubtitle}>Update your password</Text>
              </View>
              <Text style={styles.actionArrow}>›</Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={styles.actionItem}
              onPress={handleStaffDirectory}>
              <View style={styles.actionIcon}>
                <Text style={styles.actionIconText}>👥</Text>
              </View>
              <View style={styles.actionContent}>
                <Text style={styles.actionTitle}>Staff Directory</Text>
                <Text style={styles.actionSubtitle}>View staff members</Text>
              </View>
              <Text style={styles.actionArrow}>›</Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={styles.actionItem}
              onPress={handleSystemSettings}>
              <View style={styles.actionIcon}>
                <Text style={styles.actionIconText}>⚙️</Text>
              </View>
              <View style={styles.actionContent}>
                <Text style={styles.actionTitle}>System Settings</Text>
                <Text style={styles.actionSubtitle}>Configure preferences</Text>
              </View>
              <Text style={styles.actionArrow}>›</Text>
            </TouchableOpacity>
          </View>
        </View>

        {/* Support */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Support & Help</Text>
          <View style={styles.actionsCard}>
            <TouchableOpacity style={styles.actionItem} onPress={handleSupport}>
              <View style={styles.actionIcon}>
                <Text style={styles.actionIconText}>🆘</Text>
              </View>
              <View style={styles.actionContent}>
                <Text style={styles.actionTitle}>Support</Text>
                <Text style={styles.actionSubtitle}>Get help and support</Text>
              </View>
              <Text style={styles.actionArrow}>›</Text>
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
              <Text style={styles.infoValue}>Staff App</Text>
            </View>
            <View style={styles.infoRow}>
              <Text style={styles.infoLabel}>User Role</Text>
              <Text style={styles.infoValue}>
                {getRoleDisplayName(profileData?.role || '')}
              </Text>
            </View>
          </View>
        </View>

        {/* Logout Button */}
        <TouchableOpacity style={styles.logoutButton} onPress={handleLogout}>
          <Text style={styles.logoutButtonText}>Logout</Text>
        </TouchableOpacity>

        {/* Footer */}
        <View style={styles.footer}>
          <Text style={styles.footerText}>
            © 2025 MAP HMS. All rights reserved.
          </Text>
        </View>
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
  },
  header: {
    backgroundColor: '#4CAF50',
    padding: 20,
    paddingTop: 60,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  backButton: {
    padding: 8,
  },
  backButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
  headerTitle: {
    color: '#fff',
    fontSize: 20,
    fontWeight: 'bold',
  },
  editButton: {
    backgroundColor: 'rgba(255,255,255,0.2)',
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 8,
  },
  editButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
  content: {
    flex: 1,
  },
  profileHeader: {
    backgroundColor: '#fff',
    alignItems: 'center',
    padding: 32,
    marginBottom: 16,
  },
  avatarContainer: {
    marginBottom: 16,
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
    justifyContent: 'center',
    alignItems: 'center',
  },
  avatarText: {
    color: '#fff',
    fontSize: 32,
    fontWeight: 'bold',
  },
  userName: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#333',
    marginBottom: 12,
  },
  roleBadge: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 20,
  },
  roleText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '600',
  },
  section: {
    marginBottom: 16,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#333',
    paddingHorizontal: 20,
    marginBottom: 12,
  },
  infoCard: {
    backgroundColor: '#fff',
    padding: 20,
    marginHorizontal: 16,
    borderRadius: 12,
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  infoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#f0f0f0',
  },
  infoLabel: {
    fontSize: 16,
    color: '#666',
    fontWeight: '500',
  },
  infoValue: {
    fontSize: 16,
    color: '#333',
    fontWeight: '600',
    textAlign: 'right',
    flex: 1,
    marginLeft: 16,
  },
  actionsCard: {
    backgroundColor: '#fff',
    marginHorizontal: 16,
    borderRadius: 12,
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  actionItem: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 20,
    borderBottomWidth: 1,
    borderBottomColor: '#f0f0f0',
  },
  actionIcon: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: '#f5f5f5',
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 16,
  },
  actionIconText: {
    fontSize: 20,
  },
  actionContent: {
    flex: 1,
  },
  actionTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: '#333',
    marginBottom: 2,
  },
  actionSubtitle: {
    fontSize: 14,
    color: '#666',
  },
  actionArrow: {
    fontSize: 20,
    color: '#ccc',
  },
  logoutButton: {
    backgroundColor: '#f44336',
    margin: 16,
    padding: 16,
    borderRadius: 8,
    alignItems: 'center',
  },
  logoutButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
  footer: {
    alignItems: 'center',
    padding: 20,
    marginTop: 20,
  },
  footerText: {
    fontSize: 12,
    color: '#999',
  },
});
