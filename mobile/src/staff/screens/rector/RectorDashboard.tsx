import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  ScrollView,
  StyleSheet,
  RefreshControl,
  TouchableOpacity,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { useAuthStore } from '../../../shared/store/auth.store';
import { useNotificationStore } from '../../../shared/store/notification.store';
import { apiService } from '../../../shared/services/api.service';
import { APP_CONFIG } from '../../../shared/config/app.config';
import { theme } from '../../../shared/theme/theme';
import { extractTenantLogoUrl } from '../../../shared/utils/tenant-logo-url.util';
import { rectorIcons } from '../../../shared/assets/dashboard-icons/rector-icons';
import { StaffDashboardHeader } from '../../components/StaffDashboardHeader';
import { CollegeLogo, ActionTilesGrid } from '../../../shared/components';
import type { RectorStats, ActionTile } from '../../../shared/types';

interface Props {
  navigation: any;
}

export const RectorDashboard: React.FC<Props> = ({ navigation }) => {
  const { user, tenant } = useAuthStore();
  const {
    unreadCount,
    fetchUnreadCount,
  } = useNotificationStore();
  
  const [stats, setStats] = useState<RectorStats | null>(null);
  const [tenantLogoUrl, setTenantLogoUrl] = useState<string | null>(null);
  const [_isLoading, setIsLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [_error, setError] = useState<string | null>(null);

  const fetchDashboardData = useCallback(async () => {
    try {
      setError(null);
      const [dashboardRes, profileRes] = await Promise.all([
        apiService.get<{ data: RectorStats }>('/mobile/rector/dashboard'),
        apiService.get<{ data?: { tenant_logo_url?: string }; tenant_logo_url?: string }>(APP_CONFIG.ENDPOINTS.PROFILE),
      ]);
      setStats(dashboardRes?.data ?? dashboardRes);
      setTenantLogoUrl(extractTenantLogoUrl(profileRes));
    } catch (e) {
      console.error('Failed to fetch rector dashboard:', e);
      setError('Failed to load dashboard. Pull down to refresh.');
      setStats(null);
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
      id: 'outpass',
      title: 'Outpass',
      icon: 'exit-run',
      iconSvgXml: rectorIcons.outPass,
      color: theme.colors.primary,
      badge: stats?.tickets_pending,
      onPress: () => navigation.navigate('RectorOutpassList'),
    },
    {
      id: 'leave',
      title: 'Leave',
      icon: 'calendar-clock',
      iconSvgXml: rectorIcons.leave,
      color: theme.colors.primary,
      onPress: () => navigation.navigate('RectorLeaveList'),
    },
    {
      id: 'guest-entry',
      title: 'Guest Entry',
      icon: 'account-multiple',
      iconSvgXml: rectorIcons.guestEntry,
      color: theme.colors.primary,
      onPress: () => navigation.navigate('RectorGuestEntryList'),
    },
    {
      id: 'profile',
      title: 'Profile',
      icon: 'account',
      iconSvgXml: rectorIcons.profile,
      color: theme.colors.primary,
      onPress: () => navigation.navigate('RectorProfile'),
    },
    {
      id: 'comm-box',
      title: 'Notice Board',
      icon: 'email-outline',
      iconSvgXml: rectorIcons.commBox,
      color: theme.colors.primary,
      badge: 0,
      onPress: () => navigation.navigate('CommBox'),
    },
  ];

  return (
    <View style={styles.container}>
      {/* Header */}
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
        <View
          style={styles.greetingCard}
        >
          <View style={styles.greetingContent}>
            <View style={styles.greetingText}>
              <Text style={styles.greeting} numberOfLines={1}>{getGreeting()},</Text>
              <Text style={styles.userName} numberOfLines={2} ellipsizeMode="tail">
                {user?.name || 'Rector'}
              </Text>
              <Text style={styles.roleName} numberOfLines={1}>Rector</Text>
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

        {/* Action Tiles - section format matches other dashboards */}
        <View style={styles.actionsSection}>
          <Text style={styles.sectionTitle}>Quick Actions</Text>
          <ActionTilesGrid tiles={actionTiles} columns={2} />
        </View>

        {/* Pending Approvals Alert */}
        {stats && stats.tickets_pending > 0 && (
          <TouchableOpacity
            style={styles.pendingAlert}
            onPress={() => navigation.navigate('OutpassList')}
          >
            <View style={styles.pendingAlertIcon}>
              <Icon name="clock-alert" size={24} color="#F59E0B" />
            </View>
            <View style={styles.pendingAlertContent}>
              <Text style={styles.pendingAlertTitle}>Pending Approvals</Text>
              <Text style={styles.pendingAlertSubtitle}>
                {stats.tickets_pending} request{stats.tickets_pending > 1 ? 's' : ''} waiting for approval
              </Text>
            </View>
            <Icon name="chevron-right" size={24} color="#F59E0B" />
          </TouchableOpacity>
        )}

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
    flex: 0.45,
    flexShrink: 1,
    minWidth: 0,
    justifyContent: 'center',
    marginRight: 6,
    paddingLeft: 14,
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
    color: theme.colors.text,
    marginTop: 12,
  },
  roleName: {
    fontSize: 14,
    color: theme.colors.textSecondary,
    marginTop: 4,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: theme.colors.text,
    marginBottom: 12,
    textAlign: 'center',
  },
  actionsSection: {
    marginTop: 24,
    paddingHorizontal: 16,
  },
  pendingAlert: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: theme.colors.warningLight,
    marginHorizontal: 16,
    marginTop: 16,
    padding: 16,
    borderRadius: theme.borderRadius.md,
    borderWidth: 1,
    borderColor: theme.colors.warning,
  },
  pendingAlertIcon: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: `${theme.colors.warning}30`,
    justifyContent: 'center',
    alignItems: 'center',
  },
  pendingAlertContent: {
    flex: 1,
    marginLeft: 12,
  },
  pendingAlertTitle: {
    fontSize: 15,
    fontWeight: '600',
    color: theme.colors.warning,
  },
  pendingAlertSubtitle: {
    fontSize: 13,
    color: theme.colors.warning,
    marginTop: 2,
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

export default RectorDashboard;
