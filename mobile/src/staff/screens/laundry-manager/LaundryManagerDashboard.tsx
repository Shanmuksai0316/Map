/**
 * Laundry Manager Dashboard
 * Requirements:
 * - Header: "Laundry Manager" on left, Bell icon (notifications) on right
 * - Greeting Card: Greeting + User name on left, College logo on right
 * - Actions: 2x2 grid with Raise Request, Active Requests, Profile, Notice Board
 * - NO stats matrix, NO bottom navigation
 */

import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  ScrollView,
  StyleSheet,
  RefreshControl,
} from 'react-native';
import { useAuthStore } from '../../../shared/store/auth.store';
import { useNotificationStore } from '../../../shared/store/notification.store';
import { apiService } from '../../../shared/services/api.service';
import { APP_CONFIG } from '../../../shared/config/app.config';
import { colors } from '../../../shared/theme/colors';
import { extractTenantLogoUrl } from '../../../shared/utils/tenant-logo-url.util';
import { laundryManagerIcons } from '../../../shared/assets/dashboard-icons/laundry-manager-icons';
import { StaffDashboardHeader } from '../../components/StaffDashboardHeader';
import { CollegeLogo, ActionTilesGrid } from '../../../shared/components';
import type { ActionTile } from '../../../shared/types';

interface Props {
  navigation: any;
}

export const LaundryManagerDashboard: React.FC<Props> = ({ navigation }) => {
  const { user, tenant } = useAuthStore();
  const {
    unreadCount,
    fetchUnreadCount,
  } = useNotificationStore();
  
  const [refreshing, setRefreshing] = useState(false);
  const [pendingCount, setPendingCount] = useState(0);
  const [tenantLogoUrl, setTenantLogoUrl] = useState<string | null>(null);

  const fetchDashboardData = useCallback(async () => {
    try {
      await Promise.all([fetchUnreadCount()]);
      const profileRes = await apiService.get<{ data: { tenant_logo_url?: string } }>(APP_CONFIG.ENDPOINTS.PROFILE);
      setTenantLogoUrl(extractTenantLogoUrl(profileRes));
    } catch (error) {
      console.error('Failed to fetch laundry manager dashboard:', error);
    }
  }, [fetchUnreadCount]);

  useEffect(() => {
    fetchDashboardData();
  }, [fetchDashboardData]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await fetchDashboardData();
    setRefreshing(false);
  }, [fetchDashboardData]);

  const getGreeting = () => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good Morning';
    if (hour < 17) return 'Good Afternoon';
    return 'Good Evening';
  };

  const actionTiles: ActionTile[] = [
    {
      id: 'raise-request',
      title: 'Raise Request',
      iconSvgXml: laundryManagerIcons.raiseRequest,
      color: colors.primary,
      onPress: () => navigation.navigate('RaiseRequest'),
    },
    {
      id: 'active-requests',
      title: 'Active Requests',
      iconSvgXml: laundryManagerIcons.activeRequest,
      color: colors.primary,
      badge: pendingCount > 0 ? pendingCount : undefined,
      onPress: () => navigation.navigate('LaundryRequestList'),
    },
    {
      id: 'comm-box',
      title: 'Notice Board',
      iconSvgXml: laundryManagerIcons.commBox,
      color: colors.primary,
      badge: 0,
      onPress: () => navigation.navigate('CommBox'),
    },
    {
      id: 'profile',
      title: 'Profile',
      iconSvgXml: laundryManagerIcons.profile,
      color: colors.primary,
      onPress: () => navigation.navigate('Profile'),
    },
  ];

  return (
    <View style={styles.container}>
      <StaffDashboardHeader
        onNotificationsPress={() => navigation.navigate('Notifications')}
        notificationCount={unreadCount}
      />

      <ScrollView
        style={styles.scrollView}
        showsVerticalScrollIndicator={false}
        contentContainerStyle={styles.scrollContent}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={onRefresh}
            tintColor={colors.primary}
          />
        }
      >
        {/* Greeting Card - same layout as student app */}
        <View style={styles.greetingCard}>
          <View style={styles.greetingTextContainer}>
            <Text style={styles.greeting} numberOfLines={1}>{getGreeting()},</Text>
            <Text style={styles.userName} numberOfLines={2} ellipsizeMode="tail">
              {user?.name || 'Manager'}
            </Text>
            <Text style={styles.roleName} numberOfLines={1}>Laundry Manager</Text>
          </View>
          <View style={styles.logoContainer}>
            <CollegeLogo
              logoUrl={tenantLogoUrl ?? undefined}
              collegeName={tenant?.name || 'College'}
              fillContainer
            />
          </View>
        </View>

        {/* Actions Section */}
        <View style={styles.actionsSection}>
          <Text style={styles.actionsTitle}>Actions</Text>
          <ActionTilesGrid tiles={actionTiles} columns={2} />
        </View>
        {/* Footer - same as student app */}
        <View style={styles.footer}>
          <Text style={styles.footerFlag}>Proudly made in India 🇮🇳</Text>
          <Text style={styles.footerVersion}>Version 1.0.0</Text>
          <Text style={styles.footerCompany}>OMAP Services Management Pvt Ltd</Text>
        </View>
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  scrollView: {
    flex: 1,
  },
  scrollContent: {
    paddingBottom: 40,
  },
  greetingCard: {
    backgroundColor: colors.surface,
    marginHorizontal: 14,
    marginTop: 24,
    marginBottom: 6,
    paddingLeft: 6,
    paddingTop: 8,
    paddingBottom: 8,
    paddingRight: 0,
    borderRadius: 16,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'stretch',
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: colors.border,
    minHeight: 168,
    maxHeight: 168,
  },
  greetingTextContainer: {
    flex: 0.4,
    flexShrink: 1,
    minWidth: 0,
    marginRight: 6,
    paddingLeft: 14,
    justifyContent: 'center',
    overflow: 'hidden',
  },
  logoContainer: {
    flex: 0.6,
    marginLeft: 8,
    marginRight: 12,
    alignSelf: 'stretch',
    padding: 12,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.white,
  },
  greeting: {
    fontSize: 14,
    color: colors.textSecondary,
  },
  userName: {
    fontSize: 18,
    fontWeight: '700',
    color: colors.textHeading,
    marginTop: 12,
  },
  roleName: {
    fontSize: 14,
    color: colors.textSecondary,
    marginTop: 4,
  },
  actionsSection: {
    paddingHorizontal: 16,
    paddingTop: 24,
  },
  actionsTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: colors.textHeading,
    marginBottom: 16,
    textAlign: 'center',
  },
  actionsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
    gap: 12,
  },
  actionTile: {
    width: '48%',
    backgroundColor: colors.surface,
    borderRadius: 16,
    padding: 20,
    alignItems: 'center',
    shadowColor: colors.black,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.06,
    shadowRadius: 6,
    elevation: 2,
    borderWidth: 1,
    borderColor: colors.border,
  },
  tileIconContainer: {
    width: 64,
    height: 64,
    borderRadius: 16,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 12,
    position: 'relative',
  },
  tileBadge: {
    position: 'absolute',
    top: -4,
    right: -4,
    backgroundColor: colors.error,
    borderRadius: 12,
    minWidth: 22,
    height: 22,
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 6,
  },
  tileBadgeText: {
    color: colors.white,
    fontSize: 11,
    fontWeight: '700',
  },
  tileTitle: {
    fontSize: 15,
    fontWeight: '600',
    color: colors.text,
    textAlign: 'center',
  },
  footer: {
    paddingVertical: 32,
    paddingHorizontal: 16,
    alignItems: 'center',
    marginTop: 24,
  },
  footerFlag: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.text,
    marginBottom: 4,
  },
  footerVersion: {
    fontSize: 14,
    color: colors.textSecondary,
    marginBottom: 4,
  },
  footerCompany: {
    fontSize: 12,
    color: colors.textMuted,
    textAlign: 'center',
  },
});

export default LaundryManagerDashboard;
