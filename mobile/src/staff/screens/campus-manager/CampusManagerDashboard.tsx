import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  ScrollView,
  StyleSheet,
  RefreshControl,
  TouchableOpacity,
} from 'react-native';
import { useAuthStore } from '../../../shared/store/auth.store';
import { useNotificationStore } from '../../../shared/store/notification.store';
import { useEmergencyStore } from '../../../shared/store/emergency.store';
import { apiService } from '../../../shared/services/api.service';
import { APP_CONFIG } from '../../../shared/config/app.config';
import { theme } from '../../../shared/theme/theme';
import { extractTenantLogoUrl } from '../../../shared/utils/tenant-logo-url.util';
import { campusManagerIcons } from '../../../shared/assets/dashboard-icons/campus-manager-icons';
import { StaffDashboardHeader } from '../../components/StaffDashboardHeader';
import {
  CollegeLogo,
  ActionTilesGrid,
  EmergencyBlinkingCard,
} from '../../../shared/components';
import type { ActionTile } from '../../../shared/types';

interface Props {
  navigation: any;
}

export const CampusManagerDashboard: React.FC<Props> = ({ navigation }) => {
  const { user, selectedTenant } = useAuthStore();
  const {
    unreadCount,
    fetchUnreadCount,
  } = useNotificationStore();
  const { unacknowledgedCount, fetchUnacknowledgedCount } = useEmergencyStore();
  
  const [tenantLogoUrl, setTenantLogoUrl] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const fetchDashboardData = useCallback(async () => {
    try {
      const profileRes = await apiService.get<{ data?: { tenant_logo_url?: string } }>(APP_CONFIG.ENDPOINTS.PROFILE);
      setTenantLogoUrl(extractTenantLogoUrl(profileRes));
    } catch (error) {
      console.error('Failed to fetch dashboard profile:', error);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchDashboardData();
    fetchUnreadCount();
    fetchUnacknowledgedCount();
  }, [fetchDashboardData, fetchUnreadCount, fetchUnacknowledgedCount]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await Promise.all([
      fetchDashboardData(),
      fetchUnreadCount(),
      fetchUnacknowledgedCount(),
    ]);
    setRefreshing(false);
  }, [fetchDashboardData, fetchUnreadCount, fetchUnacknowledgedCount]);

  const getGreeting = () => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good Morning';
    if (hour < 17) return 'Good Afternoon';
    return 'Good Evening';
  };

  const getFormattedName = () => {
    const name = user?.name || 'Manager';
    // Add Mr./Ms. prefix if not already present
    if (!name.toLowerCase().startsWith('mr.') && !name.toLowerCase().startsWith('ms.') && !name.toLowerCase().startsWith('mrs.')) {
      return `Mr. ${name}`;
    }
    return name;
  };

  const navigateTo = (routeName: string, params?: Record<string, unknown>) => {
    try {
      navigation.navigate(routeName, params);
    } catch (error) {
      console.error(`Navigation error to ${routeName}:`, error);
    }
  };

  const actionTiles: ActionTile[] = [
    {
      id: 'checklists',
      title: 'Checklists',
      iconSvgXml: campusManagerIcons.checklist,
      color: theme.colors.primary,
      onPress: () => {
        navigateTo('MyChecklist');
      },
    },
    {
      id: 'requests',
      title: 'Requests',
      iconSvgXml: campusManagerIcons.requests,
      color: theme.colors.primary,
      onPress: () => {
        navigateTo('Requests');
      },
    },
    {
      id: 'comm-box',
      title: 'Notice Board',
      iconSvgXml: campusManagerIcons.commBox,
      color: theme.colors.primary,
      onPress: () => {
        navigateTo('Notice Board');
      },
    },
    {
      id: 'emergency',
      title: 'Emergency',
      iconSvgXml: campusManagerIcons.emergency,
      color: theme.colors.error,
      badge: unacknowledgedCount,
      onPress: () => {
        navigateTo('Emergency');
      },
    },
    {
      id: 'my-staff',
      title: 'My Staff',
      iconSvgXml: campusManagerIcons.myStaff,
      color: theme.colors.primary,
      onPress: () => {
        navigateTo('My Staff');
      },
    },
    {
      id: 'profile',
      title: 'Profile',
      iconSvgXml: campusManagerIcons.profile,
      color: theme.colors.primary,
      onPress: () => {
        navigateTo('Profile');
      },
    },
  ];

  return (
    <View style={styles.container}>
      <StaffDashboardHeader
        onNotificationsPress={() => navigateTo('Notifications')}
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
                {getFormattedName()}
              </Text>
            </View>
            <View style={styles.logoContainer}>
              <CollegeLogo
                logoUrl={tenantLogoUrl ?? undefined}
                collegeName={selectedTenant?.name || 'College'}
                fillContainer
              />
            </View>
          </View>
        </View>

        {/* Emergency Alert (if any unacknowledged) */}
        {unacknowledgedCount > 0 && (
          <EmergencyBlinkingCard
            title="Emergency Alerts"
            description={`${unacknowledgedCount} unacknowledged incident${unacknowledgedCount > 1 ? 's' : ''} require your attention`}
            count={unacknowledgedCount}
            onPress={() => {
              try {
                navigation.navigate('Emergency');
              } catch (error) {
                console.error('Navigation error to Emergency:', error);
              }
            }}
          />
        )}

        {/* Action Tiles */}
        <View style={styles.tilesSection}>
          <Text style={styles.sectionTitle}>Quick Actions</Text>
          <ActionTilesGrid tiles={actionTiles} columns={2} />
        </View>

        {/* Footer - same as student app */}
        <View style={styles.footer}>
          <Text style={styles.footerFlag}>Proudly made in India 🇮🇳</Text>
          <Text style={styles.footerVersion}>Version 1.0.0</Text>
          <Text style={styles.footerCompany}>OMAP Services Management Pvt Ltd</Text>
        </View>

        {/* Bottom padding */}
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
    paddingTop: 8,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: theme.colors.text,
    marginBottom: 16,
    paddingHorizontal: 8,
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

export default CampusManagerDashboard;
