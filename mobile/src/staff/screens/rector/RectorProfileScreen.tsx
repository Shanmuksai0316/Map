import React from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  ScrollView,
  Alert,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { useAuthStore } from '../../../shared/store/auth.store';
import { theme } from '../../../shared/theme/theme';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';
import { StaffPrimaryButton } from '../../components/StaffPrimaryButton';

interface Props {
  navigation: any;
}

export const RectorProfileScreen: React.FC<Props> = ({ navigation }) => {
  const { user, selectedTenant, logout } = useAuthStore();

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
            try {
              await logout();
            } catch (error) {
              Alert.alert('Error', 'Failed to logout');
            }
          },
        },
      ]
    );
  };

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Profile" />

      <ScrollView 
        style={styles.content}
        showsVerticalScrollIndicator={false}
      >
        {/* Intro Card */}
        <View style={styles.introCard}>
          <View style={styles.avatar}>
            <Icon name="account" size={40} color={theme.colors.white} />
          </View>
          <Text style={styles.userName}>{user?.name || 'Rector'}</Text>
          <Text style={styles.userRole}>{user?.role || 'Rector'}</Text>
        </View>

        {/* Details Section */}
        <View style={styles.detailsSection}>
          <Text style={styles.sectionTitle}>Details</Text>

          {/* Unique ID */}
          <View style={styles.detailRow}>
            <View style={styles.detailIconContainer}>
              <Icon name="badge-account" size={20} color={theme.colors.primary} />
            </View>
            <View style={styles.detailContent}>
              <Text style={styles.detailLabel}>Unique ID</Text>
              <Text style={styles.detailValue}>
                {user?.employee_id || `EMP${user?.id || '000'}`}
              </Text>
            </View>
          </View>

          {/* Phone Number */}
          <View style={styles.detailRow}>
            <View style={styles.detailIconContainer}>
              <Icon name="phone" size={20} color={theme.colors.primary} />
            </View>
            <View style={styles.detailContent}>
              <Text style={styles.detailLabel}>Phone Number</Text>
              <Text style={styles.detailValue}>
                {user?.phone || 'Not provided'}
              </Text>
            </View>
          </View>

          {/* Hostel Name / College */}
          <View style={styles.detailRow}>
            <View style={styles.detailIconContainer}>
              <Icon name="office-building" size={20} color={theme.colors.primary} />
            </View>
            <View style={styles.detailContent}>
              <Text style={styles.detailLabel}>College</Text>
              <Text style={styles.detailValue}>
                {selectedTenant?.name || 'Not assigned'}
              </Text>
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
    backgroundColor: theme.colors.background,
  },
  content: {
    flex: 1,
  },
  introCard: {
    alignItems: 'center',
    backgroundColor: theme.colors.card,
    marginHorizontal: 16,
    marginTop: 16,
    borderRadius: theme.borderRadius.lg,
    padding: 24,
    ...theme.shadows.medium,
  },
  avatar: {
    width: 80,
    height: 80,
    borderRadius: 40,
    backgroundColor: theme.colors.primary,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 16,
  },
  userName: {
    fontSize: 22,
    fontWeight: '700',
    color: theme.colors.text,
    textAlign: 'center',
    marginBottom: 4,
  },
  userRole: {
    fontSize: 16,
    color: theme.colors.textSecondary,
    textAlign: 'center',
  },
  detailsSection: {
    backgroundColor: theme.colors.card,
    marginHorizontal: 16,
    marginTop: 16,
    borderRadius: theme.borderRadius.lg,
    padding: 20,
    ...theme.shadows.small,
  },
  sectionTitle: {
    fontSize: 14,
    fontWeight: '600',
    color: theme.colors.textSecondary,
    textTransform: 'uppercase',
    letterSpacing: 1,
    marginBottom: 16,
  },
  detailRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 16,
  },
  detailIconContainer: {
    width: 40,
    height: 40,
    borderRadius: theme.borderRadius.sm,
    backgroundColor: `${theme.colors.primary}15`,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 14,
  },
  detailContent: {
    flex: 1,
  },
  detailLabel: {
    fontSize: 12,
    color: theme.colors.textSecondary,
    marginBottom: 2,
  },
  detailValue: {
    fontSize: 15,
    fontWeight: '500',
    color: theme.colors.text,
  },
  logoutButton: {
    marginHorizontal: 16,
    marginTop: 16,
  },
  bottomPadding: {
    height: 40,
  },
});

export default RectorProfileScreen;
