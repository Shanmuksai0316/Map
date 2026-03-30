/**
 * Campus Manager profile – separate page with name, role, employee id,
 * phone, hostel name, tenant name, and logout.
 */

import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  Alert,
  ActivityIndicator,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { useAuthStore } from '../../../shared/store/auth.store';
import { apiService } from '../../../shared/services/api.service';
import { APP_CONFIG } from '../../../shared/config/app.config';
import { theme } from '../../../shared/theme/theme';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';
import { StaffPrimaryButton } from '../../components/StaffPrimaryButton';

interface ProfileData {
  name?: string;
  role?: string;
  employee_id?: string;
  phone?: string;
  hostel_name?: string;
  tenant_name?: string;
}

export const CampusManagerProfileScreen = ({ navigation }: any) => {
  const { user, selectedTenant, logout } = useAuthStore();
  const [profile, setProfile] = useState<ProfileData | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const res = await apiService.get<{ data?: ProfileData }>(APP_CONFIG.ENDPOINTS.PROFILE);
        const data = res?.data ?? res;
        if (!cancelled && data) {
          setProfile({
            name: data.name ?? user?.name,
            role: data.role ?? user?.role ?? 'Campus Manager',
            employee_id: data.employee_id ?? (user as any)?.employee_id ?? `EMP${user?.id ?? '000'}`,
            phone: data.phone ?? (user as any)?.phone ?? 'Not provided',
            hostel_name: data.hostel_name ?? (data as any)?.hostel?.name ?? '—',
            tenant_name: selectedTenant?.name ?? data.tenant_name ?? '—',
          });
        }
      } catch {
        if (!cancelled) {
          setProfile({
            name: user?.name ?? 'Campus Manager',
            role: user?.role ?? 'Campus Manager',
            employee_id: (user as any)?.employee_id ?? `EMP${user?.id ?? '000'}`,
            phone: (user as any)?.phone ?? 'Not provided',
            hostel_name: '—',
            tenant_name: selectedTenant?.name ?? '—',
          });
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => { cancelled = true; };
  }, [user, selectedTenant]);

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
            } catch {
              Alert.alert('Error', 'Failed to logout');
            }
          },
        },
      ]
    );
  };

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color={theme.colors.primary} />
      </View>
    );
  }

  const rows: { label: string; value: string; icon: string }[] = [
    { label: 'Name', value: profile?.name ?? '—', icon: 'account' },
    { label: 'Role', value: profile?.role ?? '—', icon: 'badge-account' },
    { label: 'Employee ID', value: profile?.employee_id ?? '—', icon: 'identifier' },
    { label: 'Phone Number', value: profile?.phone ?? '—', icon: 'phone' },
    { label: 'Hostel Name', value: profile?.hostel_name ?? '—', icon: 'office-building' },
    { label: 'Tenant Name', value: profile?.tenant_name ?? '—', icon: 'domain' },
  ];

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Profile" />

      <ScrollView style={styles.scroll} contentContainerStyle={styles.scrollContent}>
        <View style={styles.avatarSection}>
          <View style={styles.avatar}>
            <Icon name="account" size={40} color={theme.colors.white} />
          </View>
          <Text style={styles.userName}>{profile?.name ?? 'Campus Manager'}</Text>
          <Text style={styles.userRole}>{profile?.role ?? 'Campus Manager'}</Text>
        </View>

        <View style={styles.detailsCard}>
          {rows.map((row) => (
            <View key={row.label} style={styles.detailRow}>
              <View style={styles.detailIconContainer}>
                <Icon name={row.icon as any} size={20} color={theme.colors.primary} />
              </View>
              <View style={styles.detailContent}>
                <Text style={styles.detailLabel}>{row.label}</Text>
                <Text style={styles.detailValue}>{row.value}</Text>
              </View>
            </View>
          ))}
        </View>

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
  center: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  scroll: {
    flex: 1,
  },
  scrollContent: {
    padding: 20,
  },
  avatarSection: {
    alignItems: 'center',
    marginBottom: 24,
  },
  avatar: {
    width: 80,
    height: 80,
    borderRadius: 40,
    backgroundColor: theme.colors.primary,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 12,
  },
  userName: {
    fontSize: 20,
    fontWeight: '700',
    color: theme.colors.text,
  },
  userRole: {
    fontSize: 14,
    color: theme.colors.textSecondary,
    marginTop: 4,
  },
  detailsCard: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.borderRadius.lg,
    padding: 16,
    marginBottom: 24,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  detailRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 16,
  },
  detailIconContainer: {
    width: 40,
    height: 40,
    borderRadius: 10,
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
    marginTop: 16,
  },
  bottomPadding: {
    height: 40,
  },
});

export default CampusManagerProfileScreen;
