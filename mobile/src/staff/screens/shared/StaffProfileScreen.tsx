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
import { useAuthStore } from '../../../shared/store/auth.store';
import { apiService } from '../../../shared/services/api.service';
import { User } from '../../../shared/types';
import { APP_CONFIG } from '../../../shared/config/app.config';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';
import { StaffPrimaryButton } from '../../components/StaffPrimaryButton';

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
      // Fallback to user data from 'auth store'
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
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Profile" />

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

        {/* Logout Button */}
        <StaffPrimaryButton label="Logout" onPress={handleLogout} style={styles.logoutButton} />

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
  logoutButton: {
    margin: 16,
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
