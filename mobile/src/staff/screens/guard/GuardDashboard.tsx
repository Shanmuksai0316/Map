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
import { useChecklistStore } from '../../../shared/store/checklist.store';
import { api } from '../../../services/api';
import { apiService } from '../../../shared/services/api.service';
import { APP_CONFIG } from '../../../shared/config/app.config';
import { guardIcons } from '../../../shared/assets/dashboard-icons/guard-icons';
import { StaffDashboardHeader } from '../../components/StaffDashboardHeader';
import { extractTenantLogoUrl } from '../../../shared/utils/tenant-logo-url.util';
import {
  CollegeLogo,
  ActionTilesGrid,
} from '../../../shared/components';
import type { ActionTile, GuardDashboardStats } from '../../../shared/types';
import { theme } from '../../../shared/theme/theme';

interface Props {
  navigation: any;
}

export const GuardDashboard: React.FC<Props> = ({ navigation }) => {
  const { user, tenant } = useAuthStore();
  const {
    unreadCount,
    fetchUnreadCount,
  } = useNotificationStore();
  const { pendingTasks, fetchTodayChecklist } = useChecklistStore();
  
  const [_stats, setStats] = useState<GuardDashboardStats | null>(null);
  const [tenantLogoUrl, setTenantLogoUrl] = useState<string | null>(null);
  const [_isLoading, setIsLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const fetchDashboardData = useCallback(async () => {
    try {
      setError(null);
      const [statsRes, profileRes] = await Promise.allSettled([
        api.get('/guard/dashboard/stats'),
        apiService.get<{ data: { tenant_logo_url?: string } }>(APP_CONFIG.ENDPOINTS.PROFILE),
      ]);
      const statsData = statsRes.status === 'fulfilled' ? (statsRes.value?.data?.data ?? statsRes.value?.data ?? null) : null;
      setStats(statsData);
      const profilePayload = profileRes.status === 'fulfilled' ? profileRes.value : null;
      setTenantLogoUrl(extractTenantLogoUrl(profilePayload));
      // Only show error if both failed and at least one was not 403 (permission)
      const statsRejected = statsRes.status === 'rejected' && statsRes.reason?.response?.status !== 403;
      const profileRejected = profileRes.status === 'rejected' && profileRes.reason?.response?.status !== 403;
      if (statsRejected && profileRejected) {
        setError('Failed to load dashboard. Pull down to refresh.');
        setStats(null);
      }
    } catch (e) {
      console.error('Failed to fetch guard dashboard:', e);
      setError('Failed to load dashboard. Pull down to refresh.');
      setStats(null);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchDashboardData();
    fetchUnreadCount();
    fetchTodayChecklist();
  }, [fetchDashboardData, fetchUnreadCount, fetchTodayChecklist]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await Promise.all([
      fetchDashboardData(),
      fetchUnreadCount(),
      fetchTodayChecklist(),
    ]);
    setRefreshing(false);
  }, [fetchDashboardData, fetchUnreadCount, fetchTodayChecklist]);

  const getGreeting = () => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good Morning';
    if (hour < 17) return 'Good Afternoon';
    return 'Good Evening';
  };

  // Order: QR code, checklist, leave, out pass, guest entry, comm box, profile
  const actionTiles: ActionTile[] = [
    {
      id: 'qr-code',
      title: 'QR Code',
      iconSvgXml: guardIcons.qrScan,
      color: theme.colors.primary,
      onPress: () => navigation.navigate('GuardQRScanner'),
    },
    {
      id: 'checklist',
      title: 'Checklist',
      iconSvgXml: guardIcons.checklist,
      color: theme.colors.primary,
      badge: pendingTasks,
      onPress: () => navigation.navigate('GuardChecklist'),
    },
    {
      id: 'leave',
      title: 'Leave',
      iconSvgXml: guardIcons.leave,
      color: theme.colors.primary,
      onPress: () => navigation.navigate('GuardLeaveList'),
    },
    {
      id: 'outpass',
      title: 'Outpass',
      iconSvgXml: guardIcons.outPass,
      color: theme.colors.primary,
      onPress: () => navigation.navigate('GuardOutpassList'),
    },
    {
      id: 'guest-entry',
      title: 'Guest Entry',
      iconSvgXml: guardIcons.guestEntry,
      color: theme.colors.primary,
      onPress: () => navigation.navigate('GuardGuestEntryList'),
    },
    {
      id: 'comm-box',
      title: 'Notice Board',
      iconSvgXml: guardIcons.commBox,
      color: theme.colors.primary,
      badge: 0,
      onPress: () => navigation.navigate('CommBox', { hideFilter: true }),
    },
    {
      id: 'profile',
      title: 'Profile',
      iconSvgXml: guardIcons.profile,
      color: theme.colors.primary,
      onPress: () => navigation.navigate('GuardProfile'),
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
        <View
          style={styles.greetingCard}
        >
          <View style={styles.greetingContent}>
            <View style={styles.greetingText}>
              <Text style={styles.greeting} numberOfLines={1}>{getGreeting()},</Text>
              <Text style={styles.userName} numberOfLines={2} ellipsizeMode="tail">
                {user?.name || 'Guard'}
              </Text>
              <Text style={styles.roleName} numberOfLines={1}>Guard</Text>
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
    justifyContent: 'space-between',
    flex: 1,
  },
  greetingText: {
    flex: 0.4,
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

export default GuardDashboard;
