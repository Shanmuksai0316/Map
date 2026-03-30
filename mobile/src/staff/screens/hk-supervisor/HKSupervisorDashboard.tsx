import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  ScrollView,
  StyleSheet,
  RefreshControl,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { useAuthStore } from '../../../shared/store/auth.store';
import { useNotificationStore } from '../../../shared/store/notification.store';
import { apiService } from '../../../shared/services/api.service';
import { APP_CONFIG } from '../../../shared/config/app.config';
import { theme } from '../../../shared/theme/theme';
import { extractTenantLogoUrl } from '../../../shared/utils/tenant-logo-url.util';
import { housekeepingIcons } from '../../../shared/assets/housekeeping-icons';
import { StaffDashboardHeader } from '../../components/StaffDashboardHeader';
import { CollegeLogo, ActionTilesGrid } from '../../../shared/components';
import type { SupervisorStats, ActionTile } from '../../../shared/types';

interface Props {
  navigation: any;
}

export const HKSupervisorDashboard: React.FC<Props> = ({ navigation }) => {
  const { user, tenant } = useAuthStore();
  const {
    unreadCount,
    fetchUnreadCount,
  } = useNotificationStore();
  
  const [stats, setStats] = useState<SupervisorStats | null>(null);
  const [tenantLogoUrl, setTenantLogoUrl] = useState<string | null>(null);
  const [_isLoading, setIsLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const fetchDashboardData = useCallback(async () => {
    try {
      setError(null);
      const [dashboardRes, profileRes] = await Promise.all([
        apiService.get('/mobile/supervisor/dashboard?type=housekeeping'),
        apiService.get<{ data?: { tenant_logo_url?: string } }>(APP_CONFIG.ENDPOINTS.PROFILE),
      ]);
      setStats(dashboardRes?.data?.data ?? dashboardRes?.data);
      setTenantLogoUrl(extractTenantLogoUrl(profileRes));
    } catch (e) {
      console.error('Failed to fetch HK supervisor dashboard:', e);
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
      id: 'checklist',
      title: 'Checklist',
      iconSvgXml: housekeepingIcons.checklist,
      color: theme.colors.primary,
      onPress: () => navigation.navigate('HKChecklist'),
    },
    {
      id: 'requests',
      title: 'Requests',
      iconSvgXml: housekeepingIcons.requests,
      color: theme.colors.primary,
      badge: stats?.pending_requests,
      onPress: () => navigation.navigate('HKRequests'),
    },
    {
      id: 'comm-box',
      title: 'Notice Board',
      iconSvgXml: housekeepingIcons.commBox,
      color: theme.colors.primary,
      badge: 0,
      onPress: () => navigation.navigate('CommBox'),
    },
    {
      id: 'profile',
      title: 'Profile',
      iconSvgXml: housekeepingIcons.profile,
      color: theme.colors.primary,
      onPress: () => navigation.navigate('HKProfile'),
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
        {/* Error Banner */}
        {error && (
          <View style={styles.errorBanner}>
            <Icon name="alert-circle-outline" size={20} color="#DC2626" />
            <Text style={styles.errorText}>{error}</Text>
          </View>
        )}

        {/* Greeting Card - same layout as student app */}
        <View style={styles.greetingCard}>
          <View style={styles.greetingContent}>
            <View style={styles.greetingText}>
              <Text style={styles.greeting} numberOfLines={1}>{getGreeting()},</Text>
              <Text style={styles.userName} numberOfLines={2} ellipsizeMode="tail">
                {user?.name || 'Supervisor'}
              </Text>
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

        {/* Action Tiles */}
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
  errorBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: theme.colors.errorLight,
    marginHorizontal: 16,
    marginTop: 12,
    padding: 12,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: theme.colors.error,
  },
  errorText: {
    marginLeft: 8,
    color: theme.colors.error,
    fontSize: 14,
    flex: 1,
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
    flex: 1,
    justifyContent: 'space-between',
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

export default HKSupervisorDashboard;
