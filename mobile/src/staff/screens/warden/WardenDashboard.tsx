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
import { useEmergencyStore } from '../../../shared/store/emergency.store';
import { apiService } from '../../../shared/services/api.service';
import { APP_CONFIG } from '../../../shared/config/app.config';
import { colors } from '../../../shared/theme/colors';
import { getGreeting } from '../../../shared/utils/greeting.util';
import { extractTenantLogoUrl } from '../../../shared/utils/tenant-logo-url.util';
import { wardenIcons } from '../../../shared/assets/dashboard-icons/warden-icons';
import { campusManagerIcons } from '../../../shared/assets/dashboard-icons/campus-manager-icons';
import { StaffDashboardHeader } from '../../components/StaffDashboardHeader';
import { ActionTilesGrid, CollegeLogo, EmergencyBlinkingCard } from '../../../shared/components';
import type { ActionTile } from '../../../shared/types';

interface Props {
  navigation: any;
}

export const WardenDashboard: React.FC<Props> = ({ navigation }) => {
  const { user, tenant } = useAuthStore();
  const {
    unreadCount,
    fetchUnreadCount,
  } = useNotificationStore();
  const { unacknowledgedCount, fetchUnacknowledgedCount } = useEmergencyStore();
  
  const [refreshing, setRefreshing] = useState(false);
  const [tenantLogoUrl, setTenantLogoUrl] = useState<string | null>(null);

  const fetchDashboardData = useCallback(async () => {
    try {
      // Fetch profile to get tenant logo
      const profileRes = await apiService.get<any>(APP_CONFIG.ENDPOINTS.PROFILE);
      setTenantLogoUrl(extractTenantLogoUrl(profileRes));
    } catch (error) {
      console.error('Failed to fetch warden dashboard:', error);
    }
  }, []);

  useEffect(() => {
    fetchDashboardData();
    fetchUnreadCount();
  }, [fetchDashboardData, fetchUnreadCount]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await Promise.all([
      fetchDashboardData(),
      fetchUnreadCount(),
      fetchUnacknowledgedCount(),
    ]);
    setRefreshing(false);
  }, [fetchDashboardData, fetchUnreadCount, fetchUnacknowledgedCount]);

  const greeting = getGreeting();

  const actionTiles: ActionTile[] = [
    {
      id: 'attendance',
      title: 'Attendance',
      iconSvgXml: wardenIcons.attendance,
      color: colors.primary,
      onPress: () => navigation.navigate('WardenAttendance'),
    },
    {
      id: 'checklist',
      title: 'Checklist',
      iconSvgXml: wardenIcons.checklist,
      color: colors.primary,
      onPress: () => navigation.navigate('WardenChecklist'),
    },
    {
      id: 'emergency',
      title: 'Emergency',
      iconSvgXml: campusManagerIcons.emergency,
      color: colors.error,
      badge: unacknowledgedCount,
      onPress: () => {
        try {
          navigation.navigate('WardenEmergency');
        } catch (e) {
          console.error('Navigation error to Emergency:', e);
        }
      },
    },
    {
      id: 'requests',
      title: 'Requests',
      iconSvgXml: wardenIcons.requests,
      color: colors.primary,
      onPress: () => navigation.navigate('WardenRequests'),
    },
    {
      id: 'commbox',
      title: 'Notice Board',
      iconSvgXml: wardenIcons.commBox,
      color: colors.primary,
      badge: 0,
      onPress: () => navigation.navigate('CommBox'),
    },
    {
      id: 'students',
      title: 'Students',
      iconSvgXml: wardenIcons.students,
      color: colors.primary,
      onPress: () => navigation.navigate('WardenStudentList'),
    },
    {
      id: 'parcels',
      title: 'Parcels',
      iconSvgXml: wardenIcons.parcel,
      color: colors.primary,
      onPress: () => navigation.navigate('WardenParcel'),
    },
    {
      id: 'profile',
      title: 'Profile',
      iconSvgXml: wardenIcons.profile,
      color: colors.primary,
      onPress: () => navigation.navigate('WardenProfile'),
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
          <RefreshControl 
            refreshing={refreshing} 
            onRefresh={onRefresh}
            tintColor={colors.primary}
          />
        }
      >
        {/* Greeting Card - same layout as student app */}
        <View
          style={styles.greetingCard}
        >
          <View style={styles.greetingContent}>
            <View style={styles.greetingText}>
              <Text style={styles.greeting} numberOfLines={1}>{greeting},</Text>
              <Text style={styles.userName} numberOfLines={2} ellipsizeMode="tail">
                {user?.name || 'Warden'}
              </Text>
              <Text style={styles.roleName} numberOfLines={1}>Warden</Text>
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

        {/* Emergency Alert (if any unacknowledged) */}
        {unacknowledgedCount > 0 && (
          <EmergencyBlinkingCard
            title="Emergency Alerts"
            count={unacknowledgedCount}
            onPress={() => {
              try {
                navigation.navigate('WardenEmergency');
              } catch (e) {
                console.error('Navigation error to Emergency:', e);
              }
            }}
          />
        )}

        {/* Actions Section */}
        <View style={styles.actionsSection}>
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
    backgroundColor: colors.background,
  },
  scrollView: {
    flex: 1,
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
  greetingContent: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'stretch',
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
  actionsSection: {
    marginTop: 24,
    paddingHorizontal: 16,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: colors.textHeading,
    textAlign: 'center',
    marginBottom: 16,
  },
  tilesContainer: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
  },
  bottomPadding: {
    height: 24,
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

export default WardenDashboard;
