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

export const SportsManagerProfileScreen: React.FC<Props> = ({ navigation }) => {
  const { user, logout } = useAuthStore();

  const handleLogout = () => {
    Alert.alert(
      'Logout',
      'Are you sure you want to logout?',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Logout',
          style: 'destructive',
          onPress: () => {
            logout();
            navigation.reset({
              index: 0,
              routes: [{ name: 'Login' }],
            });
          },
        },
      ]
    );
  };

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Profile" />

      <ScrollView style={styles.scrollView} showsVerticalScrollIndicator={false}>
        {/* Name Card */}
        <View style={styles.nameCard}>
          <View style={styles.avatarContainer}>
            <Icon name="account-circle" size={80} color={theme.colors.primary} />
          </View>
          <Text style={styles.userName}>{user?.name || 'Sports Manager'}</Text>
          <Text style={styles.userRole}>Sports Manager</Text>
        </View>

        {/* Staff Details */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Staff Details</Text>
          
          <View style={styles.detailRow}>
            <Text style={styles.detailLabel}>Staff ID</Text>
            <Text style={styles.detailValue}>{user?.id || 'N/A'}</Text>
          </View>

          <View style={styles.divider} />

          <View style={styles.detailRow}>
            <Text style={styles.detailLabel}>Phone</Text>
            <Text style={styles.detailValue}>{user?.phone || 'N/A'}</Text>
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
  scrollView: {
    flex: 1,
  },
  nameCard: {
    backgroundColor: theme.colors.white,
    marginHorizontal: 16,
    marginTop: 16,
    borderRadius: 16,
    padding: 24,
    alignItems: 'center',
    ...theme.shadows.medium,
  },
  avatarContainer: {
    marginBottom: 16,
  },
  userName: {
    fontSize: 24,
    fontWeight: '700',
    color: theme.colors.textHeading,
    marginBottom: 4,
  },
  userRole: {
    fontSize: 16,
    color: theme.colors.textSecondary,
  },
  section: {
    backgroundColor: theme.colors.white,
    marginHorizontal: 16,
    marginTop: 16,
    borderRadius: 16,
    padding: 20,
    ...theme.shadows.medium,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: theme.colors.textHeading,
    marginBottom: 16,
  },
  detailRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 12,
  },
  detailLabel: {
    fontSize: 16,
    color: theme.colors.textSecondary,
  },
  detailValue: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.text,
  },
  divider: {
    height: 1,
    backgroundColor: theme.colors.border,
  },
  logoutButton: {
    marginHorizontal: 16,
    marginTop: 16,
  },
  bottomPadding: {
    height: 40,
  },
});

export default SportsManagerProfileScreen;
