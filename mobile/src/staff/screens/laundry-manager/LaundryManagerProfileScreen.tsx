/**
 * Laundry Manager Profile Screen
 * Requirements:
 * - Header: "Profile" center aligned, back arrow left aligned
 * - Name Card: User name center aligned, job role below
 * - Staff Details: Name and Phone rows
 * - Logout button at bottom with icon
 */

import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  Alert,
  RefreshControl,
} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useAuthStore } from '../../../shared/store/auth.store';
import { apiService } from '../../../shared/services/api.service';
import { APP_CONFIG } from '../../../shared/config/app.config';
import { colors } from '../../../shared/theme/colors';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';
import { StaffPrimaryButton } from '../../components/StaffPrimaryButton';

interface Props {
  navigation: any;
}

export const LaundryManagerProfileScreen: React.FC<Props> = ({ navigation }) => {
  const { user, logout } = useAuthStore();
  const [profile, setProfile] = useState<any>(null);
  const [refreshing, setRefreshing] = useState(false);

  const fetchProfile = async () => {
    try {
      const response = await apiService.get<{ data: any }>(APP_CONFIG.ENDPOINTS.PROFILE);
      setProfile(response.data);
    } catch (error) {
      console.error('Error fetching profile:', error);
      setProfile(user);
    } finally {
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

  const profileData = profile || user;

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Profile" />

      <ScrollView
        style={styles.content}
        contentContainerStyle={styles.contentContainer}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={colors.primary} />
        }
      >
        {/* Name Card */}
        <View style={styles.nameCard}>
          <View style={styles.avatarContainer}>
            <View style={styles.avatar}>
              <Text style={styles.avatarText}>
                {profileData?.name?.charAt(0).toUpperCase() || 'L'}
              </Text>
            </View>
          </View>
          <Text style={styles.userName}>{profileData?.name || 'Laundry Manager'}</Text>
          <Text style={styles.userRole}>Laundry Manager</Text>
        </View>

        {/* Staff Details */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Staff Details</Text>
          <View style={styles.detailsCard}>
            <View style={styles.detailRow}>
              <Text style={styles.detailLabel}>Name</Text>
              <Text style={styles.detailValue}>{profileData?.name || 'N/A'}</Text>
            </View>
            <View style={styles.divider} />
            <View style={styles.detailRow}>
              <Text style={styles.detailLabel}>Phone</Text>
              <Text style={styles.detailValue}>{profileData?.phone || 'N/A'}</Text>
            </View>
          </View>
        </View>
      </ScrollView>

      {/* Logout Button */}
      <View style={[styles.footer, { paddingBottom: Math.max(insets.bottom, 20) }]}>
        <StaffPrimaryButton label="Logout" onPress={handleLogout} />
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  content: {
    flex: 1,
  },
  contentContainer: {
    padding: 16,
  },
  nameCard: {
    backgroundColor: colors.surface,
    borderRadius: 16,
    padding: 24,
    alignItems: 'center',
    marginBottom: 24,
    borderWidth: 1,
    borderColor: colors.border,
  },
  avatarContainer: {
    marginBottom: 16,
  },
  avatar: {
    width: 80,
    height: 80,
    borderRadius: 40,
    backgroundColor: colors.primary,
    justifyContent: 'center',
    alignItems: 'center',
  },
  avatarText: {
    fontSize: 32,
    fontWeight: '700',
    color: colors.white,
  },
  userName: {
    fontSize: 24,
    fontWeight: '700',
    color: colors.textHeading,
    marginBottom: 4,
    textAlign: 'center',
  },
  userRole: {
    fontSize: 16,
    color: colors.textSecondary,
    textAlign: 'center',
  },
  section: {
    marginBottom: 24,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.textHeading,
    marginBottom: 12,
  },
  detailsCard: {
    backgroundColor: colors.surface,
    borderRadius: 12,
    padding: 16,
    borderWidth: 1,
    borderColor: colors.border,
  },
  detailRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 12,
  },
  divider: {
    height: 1,
    backgroundColor: colors.border,
  },
  detailLabel: {
    fontSize: 15,
    color: colors.textSecondary,
  },
  detailValue: {
    fontSize: 15,
    fontWeight: '600',
    color: colors.text,
  },
  footer: {
    padding: 16,
    backgroundColor: colors.surface,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
});

export default LaundryManagerProfileScreen;
