import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  Alert,
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

export const WardenProfileScreen: React.FC<Props> = ({ navigation }) => {
  const { user, logout } = useAuthStore();
  const [profile, setProfile] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const fetchProfile = useCallback(async () => {
    try {
      const response = await apiService.get<any>(APP_CONFIG.ENDPOINTS.PROFILE);
      setProfile(response?.data || response);
    } catch (error) {
      console.error('Error fetching profile:', error);
      setProfile(user); // Fallback to auth store user
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [user]);

  useEffect(() => {
    fetchProfile();
  }, [fetchProfile]);

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    fetchProfile();
  }, [fetchProfile]);

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
  const staffId = profileData?.staff_id || profileData?.id || 'N/A';

  return (
    <View style={styles.container}>
      <StaffScreenHeader
        onBack={() => navigation.goBack()}
        onNotificationsPress={() => navigation.navigate('Notifications')}  title="Profile" />

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
        {/* Name Card */}
        <View style={styles.nameCard}>
          <View style={styles.avatarContainer}>
            <Text style={styles.avatarText}>
              {profileData?.name?.charAt(0).toUpperCase() || 'W'}
            </Text>
          </View>
          <Text style={styles.userName}>{profileData?.name || 'Warden'}</Text>
          <View style={styles.roleBadge}>
            <Text style={styles.roleText}>Warden</Text>
          </View>
        </View>

        {/* Staff Details */}
        <View style={styles.detailsSection}>
          <Text style={styles.sectionTitle}>Staff Details</Text>
          <View style={styles.detailsCard}>
            <View style={styles.detailRow}>
              <Text style={styles.detailLabel}>Staff ID</Text>
              <Text style={styles.detailValue}>{staffId}</Text>
            </View>
            <View style={styles.divider} />
            <View style={styles.detailRow}>
              <Text style={styles.detailLabel}>Phone</Text>
              <Text style={styles.detailValue}>{profileData?.phone || 'N/A'}</Text>
            </View>
          </View>
        </View>

        {/* Logout Button */}
        <StaffPrimaryButton label="Logout" onPress={handleLogout} style={styles.logoutButton} />

        <View style={styles.bottomPadding} />
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
    display: 'none',
  },
  backButton: {
    display: 'none',
  },
  headerTitle: {
    display: 'none',
  },
  headerSpacer: {
    display: 'none',
  },
  content: {
    flex: 1,
    padding: 16,
  },
  nameCard: {
    backgroundColor: colors.surface,
    borderRadius: 16,
    padding: 24,
    alignItems: 'center',
    marginBottom: 16,
    borderWidth: 1,
    borderColor: colors.border,
    shadowColor: colors.black,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.06,
    shadowRadius: 4,
    elevation: 2,
  },
  avatarContainer: {
    width: 80,
    height: 80,
    borderRadius: 40,
    backgroundColor: colors.primary,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 16,
  },
  avatarText: {
    color: colors.white,
    fontSize: 32,
    fontWeight: 'bold',
  },
  userName: {
    fontSize: 22,
    fontWeight: '700',
    color: colors.textHeading,
    marginBottom: 8,
    textAlign: 'center',
  },
  roleBadge: {
    backgroundColor: colors.accent,
    paddingHorizontal: 16,
    paddingVertical: 6,
    borderRadius: 20,
  },
  roleText: {
    color: colors.textOnAccent,
    fontSize: 14,
    fontWeight: '600',
  },
  detailsSection: {
    marginBottom: 24,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.textSecondary,
    marginBottom: 12,
    marginLeft: 4,
  },
  detailsCard: {
    backgroundColor: colors.surface,
    borderRadius: 12,
    paddingHorizontal: 16,
    borderWidth: 1,
    borderColor: colors.border,
    shadowColor: colors.black,
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05,
    shadowRadius: 2,
    elevation: 1,
  },
  detailRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 16,
  },
  detailLabel: {
    fontSize: 15,
    color: colors.textSecondary,
    fontWeight: '500',
  },
  detailValue: {
    fontSize: 15,
    color: colors.text,
    fontWeight: '600',
  },
  divider: {
    height: 1,
    backgroundColor: colors.divider,
  },
  logoutButton: {
    marginTop: 16,
  },
  bottomPadding: {
    height: 40,
  },
});

export default WardenProfileScreen;
