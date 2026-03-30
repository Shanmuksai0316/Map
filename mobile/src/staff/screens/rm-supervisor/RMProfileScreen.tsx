import React, { useState } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  ScrollView,
  Alert,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { useAuthStore } from '../../../shared/store/auth.store';
import { theme } from '../../../shared/theme/theme';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';
import { StaffPrimaryButton } from '../../components/StaffPrimaryButton';

interface Props {
  navigation: any;
}

export const RMProfileScreen: React.FC<Props> = ({ navigation }) => {
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

  const handleHistory = () => {
    navigation.navigate('RMHistory');
  };

  return (
    <View style={styles.container}>
      <StaffScreenHeader
        title="Profile"
        variant="minimal"
        onBack={() => navigation.goBack()}
        onNotificationsPress={() => navigation.navigate('Notifications')}
      />

      <ScrollView style={styles.scrollView} showsVerticalScrollIndicator={false}>
        {/* Name Card */}
        <View style={styles.nameCard}>
          <View style={styles.avatarContainer}>
            <Icon name="account-circle" size={80} color={theme.colors.primary} />
          </View>
          <Text style={styles.userName}>{user?.name || 'Supervisor'}</Text>
          <Text style={styles.userRole}>Repair & Maintenance Supervisor</Text>
        </View>

        {/* Staff Details (name & role shown in header card above) */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Staff Details</Text>

          <View style={styles.detailRow}>
            <Text style={styles.detailLabel}>Employee ID</Text>
            <Text style={styles.detailValue}>{(user as any)?.employee_id || user?.id || 'N/A'}</Text>
          </View>
          <View style={styles.divider} />
          <View style={styles.detailRow}>
            <Text style={styles.detailLabel}>Phone Number</Text>
            <Text style={styles.detailValue}>{user?.phone || 'N/A'}</Text>
          </View>
          <View style={styles.divider} />
          <View style={styles.detailRow}>
            <Text style={styles.detailLabel}>Hostel Name</Text>
            <Text style={styles.detailValue}>{(user as any)?.hostel_name || 'N/A'}</Text>
          </View>
          <View style={styles.divider} />
          <View style={styles.detailRowColumn}>
            <Text style={styles.detailLabel}>Tenant Name</Text>
            <Text style={styles.detailValue} numberOfLines={2}>{selectedTenant?.name || 'N/A'}</Text>
          </View>
        </View>

        {/* History Button */}
        <GradientButton style={styles.historyButton} onPress={handleHistory}>
          <Icon name="history" size={24} color={theme.colors.primary} />
          <Text style={styles.historyButtonText}>History</Text>
          <Icon name="chevron-right" size={24} color={theme.colors.textSecondary} />
        </GradientButton>

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
  header: {
    display: 'none',
  },
  backButton: {
    display: 'none',
  },
  placeholder: {
    display: 'none',
  },
  headerTitle: {
    display: 'none',
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
  detailRowColumn: {
    flexDirection: 'column',
    paddingVertical: 12,
    gap: 4,
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
  historyButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: theme.colors.white,
    marginHorizontal: 16,
    marginTop: 16,
    borderRadius: 16,
    padding: 20,
    ...theme.shadows.medium,
  },
  historyButtonText: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.primary,
    marginLeft: 12,
    flex: 1,
  },
  logoutButton: {
    marginHorizontal: 16,
    marginTop: 16,
  },
  bottomPadding: {
    height: 40,
  },
});

export default RMProfileScreen;
