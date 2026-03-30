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
import { theme } from '../../../shared/theme/theme';
import { extractTenantLogoUrl } from '../../../shared/utils/tenant-logo-url.util';
import { sportsManagerIcons } from '../../../shared/assets/dashboard-icons/sports-manager-icons';
import { StaffDashboardHeader } from '../../components/StaffDashboardHeader';
import { CollegeLogo, ActionTilesGrid } from '../../../shared/components';
import type { ActionTile, DashboardStats } from '../../../shared/types';

interface Props {
  navigation: any;
}

export const SportsManagerDashboard: React.FC<Props> = ({ navigation }) => {
  const { user, tenant } = useAuthStore();
  const {
    unreadCount,
    fetchUnreadCount,
  } = useNotificationStore();
  
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [tenantLogoUrl, setTenantLogoUrl] = useState<string | null>(null);
  const [_isLoading, setIsLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const fetchDashboardData = useCallback(async () => {
    try {
      const [dashboardRes, profileRes] = await Promise.all([
        apiService.get<{ data: DashboardStats }>(APP_CONFIG.ENDPOINTS.DASHBOARD),
        apiService.get<{ data: { tenant_logo_url?: string } }>(APP_CONFIG.ENDPOINTS.PROFILE),
      ]);
      setStats(dashboardRes?.data ?? null);
      setTenantLogoUrl(extractTenantLogoUrl(profileRes));
    } catch (error) {
      console.error('Failed to fetch sports manager dashboard:', error);
      // Show error state - no mock data in production
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchDashboardData();
    fetchUnreadCount();
  }, [fetchDashboardData, fetchUnreadCount]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await Promise.all([fetchDashboardData(), fetchUnreadCount()]);
    setRefreshing(false);
  }, [fetchDashboardData, fetchUnreadCount]);

  const getGreeting = () => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good Morning';
    if (hour < 17) return 'Good Afternoon';
    return 'Good Evening';
  };

  const actionTiles: ActionTile[] = [
    {
      id: 'profile',
      title: 'Profile',
      iconSvgXml: sportsManagerIcons.profile,
      color: theme.colors.primary,
      onPress: () => navigation.navigate('SportsProfile'),
    },
    {
      id: 'raise-request',
      title: 'Raise Request',
      iconSvgXml: sportsManagerIcons.activeRequest,
      color: theme.colors.primary,
      onPress: () => navigation.navigate('SportsRaiseRequest'),
    },
    {
      id: 'active-requests',
      title: 'Active Requests',
      iconSvgXml: sportsManagerIcons.activeRequest,
      color: theme.colors.primary,
      badge: stats?.active_bookings,
      onPress: () => navigation.navigate('SportsActiveRequests'),
    },
    {
      id: 'checklist',
      title: 'Checklist',
      iconSvgXml: sportsManagerIcons.activeRequest,
      color: theme.colors.primary,
      onPress: () => navigation.navigate('Checklist'),
    },
    {
      id: 'courts',
      title: 'List of Courts',
      iconSvgXml: sportsManagerIcons.courtsList,
      color: theme.colors.primary,
      onPress: () => navigation.navigate('CourtSetup'),
    },
    {
      id: 'comm-box',
      title: 'Notice Board',
      iconSvgXml: sportsManagerIcons.commBox,
      color: theme.colors.primary,
      badge: 0,
      onPress: () => navigation.navigate('CommBox'),
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
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
      >
        {/* Greeting Card - same layout as student app */}
        <View style={styles.greetingCard}>
          <View style={styles.greetingContent}>
            <View style={styles.greetingText}>
              <Text style={styles.greeting} numberOfLines={1}>{getGreeting()},</Text>
              <Text style={styles.userName} numberOfLines={2} ellipsizeMode="tail">
                {user?.name || 'Manager'}
              </Text>
              <Text style={styles.roleName} numberOfLines={1}>Sports Manager</Text>
            </View>
            <View style={styles.logoContainer}>
              <CollegeLogo
                logoUrl={tenantLogoUrl ?? undefined}
                collegeName={tenant?.name || 'College'}
                fillContainer
              />
            </View>
          </View>
        </View>

        {/* Actions */}
        <View style={styles.tilesSection}>
          <Text style={styles.sectionTitle}>Actions</Text>
          <ActionTilesGrid tiles={actionTiles} columns={2} />
        </View>

        {/* Footer - same as student app */}
        <View style={styles.footer}>
          <Text style={styles.footerFlag}>Proudly made in India 🇮🇳</Text>
          <Text style={styles.footerVersion}>Version 1.0.0</Text>
          <Text style={styles.footerCompany}>OMAP Services Management Pvt Ltd</Text>
        </View>
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
  greetingCard: {
    backgroundColor: theme.colors.card,
    marginHorizontal: 14,
    marginTop: 24,
    marginBottom: 6,
    paddingLeft: 6,
    paddingTop: 8,
    paddingBottom: 8,
    paddingRight: 0,
    borderRadius: theme.borderRadius.lg,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'stretch',
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: theme.colors.border,
    minHeight: 168,
    maxHeight: 168,
  },
  greetingContent: {
    flexDirection: 'row',
    alignItems: 'stretch',
    justifyContent: 'space-between',
    flex: 1,
  },
  greetingText: {
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
    borderRadius: theme.borderRadius.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.white,
  },
  greeting: {
    fontSize: 14,
    color: theme.colors.textSecondary,
  },
  userName: {
    fontSize: 18,
    fontWeight: '700',
    color: theme.colors.textHeading,
    marginTop: 12,
  },
  roleName: {
    fontSize: 14,
    color: theme.colors.textSecondary,
    marginTop: 4,
  },
  tilesSection: {
    paddingHorizontal: 16,
    paddingTop: 16,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: theme.colors.textHeading,
    marginBottom: 16,
    paddingHorizontal: 8,
    textAlign: 'center',
  },
  bottomPadding: {
    height: 24,
  },
  footer: {
    paddingVertical: theme.spacing.xl,
    paddingHorizontal: theme.spacing.md,
    alignItems: 'center',
    marginTop: theme.spacing.lg,
  },
  footerFlag: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
    marginBottom: theme.spacing.xs,
  },
  footerVersion: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
    marginBottom: theme.spacing.xs,
  },
  footerCompany: {
    fontSize: theme.fontSize.xs,
    color: theme.colors.textMuted,
    textAlign: 'center',
  },
});

export default SportsManagerDashboard;
