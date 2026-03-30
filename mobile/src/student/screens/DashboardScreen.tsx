/**
 * StudentDashboardScreen
 *
 * Main dashboard for the Student (Vidyarthi) app. Displays a personalised
 * greeting, the student's room/hostel info, their college logo, and a grid
 * of action tiles (Profile, Requests, Emergency, Out-pass, Leave, Sports,
 * Laundry, Notice Board, Feedback).
 *
 * Data flow:
 *   - GET /mobile/profile           -> student name, room allocation, tenant logo
 *   - GET /mobile/notifications/unread-count -> notification badge count
 *
 * Feature flags (from auth store) control tile visibility. For example,
 * `sports_module_enabled` gates the Sports tile.
 *
 * Asset / font dependencies:
 *   - Custom font: EthnocentricRg (used for the "VIDYARTHI" header text).
 *     Must be linked in both Android (assets/fonts/) and iOS (Info.plist).
 *   - karta-logo.png (shared/assets/) -- header logo
 *
 * Notes for store release:
 *   - Ensure the API base URL in app.config.ts points to the production server.
 *   - Verify that EthnocentricRg.otf is bundled (Android: android/app/src/main/assets/fonts,
 *     iOS: added to the Xcode project and listed in Info.plist UIAppFonts).
 */
import React, { useEffect, useState, useCallback } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  Dimensions,
} from 'react-native';
import { GradientButton } from '../../shared/components/GradientButton';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { SvgXml } from 'react-native-svg';
import { useAuthStore } from '../../shared/store/auth.store';
import { apiService } from '../../shared/services/api.service';
import { APP_CONFIG } from '../../shared/config/app.config';
import { theme } from '../../shared/theme/theme';
import { getGreeting } from '../../shared/utils/greeting.util';
import { ActionTilesGrid, CollegeLogo } from '../../shared/components';
import type { ActionTile } from '../../shared/types';
import { extractTenantLogoUrl } from '../../shared/utils/tenant-logo-url.util';
import { studentProfileIconSvgXml } from '../../shared/assets/dashboard-icons/student-profile-icon';
import { studentRequestsIconSvgXml } from '../../shared/assets/dashboard-icons/student-requests-icon';
import { studentEmergencyIconSvgXml } from '../../shared/assets/dashboard-icons/student-emergency-icon';
import { studentOutpassIconSvgXml } from '../../shared/assets/dashboard-icons/student-outpass-icon';
import { studentLeaveIconSvgXml } from '../../shared/assets/dashboard-icons/student-leave-icon';
import { studentSportsIconSvgXml } from '../../shared/assets/dashboard-icons/student-sports-icon';
import { studentLaundryIconSvgXml } from '../../shared/assets/dashboard-icons/student-laundry-icon';
import { studentCommBoxIconSvgXml } from '../../shared/assets/dashboard-icons/student-commbox-icon';
import { studentFeedbackIconSvgXml } from '../../shared/assets/dashboard-icons/student-feedback-icon';
import { studentParcelIconSvgXml } from '../../shared/assets/dashboard-icons/student-parcel-icon';
import { studentRequestsHubIcons } from '../../shared/assets/dashboard-icons/student-requests-hub-icons';
import { mapLogoColorSvgXml } from '../../shared/assets/map-logo-color';

const ACTION_CONFIG = [
  { id: 'profile', title: 'My Profile', screen: 'Profile', iconSvgXml: studentProfileIconSvgXml },
  { id: 'requests', title: 'Requests', screen: 'RequestsHub', iconSvgXml: studentRequestsIconSvgXml },
  { id: 'emergency', title: 'Emergency', screen: 'Emergency', iconSvgXml: studentEmergencyIconSvgXml },
  { id: 'outpass', title: 'Out-pass', screen: 'GatePass', iconSvgXml: studentOutpassIconSvgXml },
  {
    id: 'guestEntry',
    title: 'Guest Entry',
    screen: 'GuestEntryPreview',
    iconSvgXml: studentRequestsHubIcons.guestEntry,
  },
  { id: 'leave', title: 'Leave', screen: 'LeavePreview', iconSvgXml: studentLeaveIconSvgXml },
  {
    id: 'sports',
    title: 'Sports',
    screen: 'SportsBooking',
    featureFlag: 'sports_module_enabled',
    iconSvgXml: studentSportsIconSvgXml,
  },
  { id: 'laundry', title: 'Laundry', screen: 'LaundryRequest', iconSvgXml: studentLaundryIconSvgXml },
  { id: 'parcels', title: 'Parcels', screen: 'ParcelList', iconSvgXml: studentParcelIconSvgXml },
  { id: 'commbox', title: 'Notice Board', screen: 'CommBox', iconSvgXml: studentCommBoxIconSvgXml },
  { id: 'feedback', title: 'Feedback', screen: 'Feedback', iconSvgXml: studentFeedbackIconSvgXml },
] as const;

export const StudentDashboardScreen = ({ navigation }: any) => {
  const insets = useSafeAreaInsets();
  const { user, isFeatureEnabled } = useAuthStore();
  const [refreshing, setRefreshing] = useState(false);
  const [notificationCount, setNotificationCount] = useState(0);
  const [tenantLogoUrl, setTenantLogoUrl] = useState<string | null>(null);
  const [tenantName, setTenantName] = useState('College');
  const [studentInfo, setStudentInfo] = useState<{
    name: string;
    roomNumber: string;
    hostelName: string;
  } | null>(null);

  const fetchDashboardData = useCallback(async () => {
    const profilePath = APP_CONFIG.ENDPOINTS.PROFILE;
    const notifPath = APP_CONFIG.ENDPOINTS.NOTIFICATIONS;
    try {
      const profileRes = await apiService.get<{ data?: any }>(profilePath);
      const profile = profileRes?.data ?? profileRes;

      const roomAllocation = profile?.student?.room_allocation;
      const roomNumber = roomAllocation
        ? `${roomAllocation.block_code || ''}${roomAllocation.floor_code || ''}${roomAllocation.room_number || ''}`.trim() ||
          'N/A'
        : profile?.student_uid || profile?.student?.student_uid || 'N/A';

      const hostelName =
        roomAllocation?.hostel_name ||
        profile?.student?.hostel_name ||
        profile?.hostel_name ||
        profile?.hostel?.name ||
        'N/A';

      setStudentInfo({
        name: profile.name || user?.name || 'Student',
        roomNumber,
        hostelName,
      });

      setTenantLogoUrl(extractTenantLogoUrl(profileRes));
      setTenantName(
        profile?.tenant?.name ||
        profile?.tenant_name ||
        profile?.college_name ||
        (user as any)?.tenant_name ||
        'College'
      );

      // Fetch notification count (backend: GET /mobile/notifications/unread-count)
      const notifRes = await apiService.get<{ data: { unread_count: number } }>(
        notifPath + '/unread-count'
      );
      setNotificationCount(notifRes.data?.unread_count ?? 0);
    } catch (error) {
      console.error('Dashboard fetch error:', error);
    } finally {
      setRefreshing(false);
    }
  }, [user]);

  useEffect(() => {
    fetchDashboardData();
  }, [fetchDashboardData]);

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    fetchDashboardData();
  }, [fetchDashboardData]);

  const greeting = getGreeting();
  const windowWidth = Dimensions.get('window').width;
  const isCompactLayout = windowWidth < 380;

  const visibleTiles: ActionTile[] = ACTION_CONFIG.filter((card) => {
    if (card.id === 'sports') return false;
    if ('featureFlag' in card && card.featureFlag) {
      return isFeatureEnabled(card.featureFlag);
    }
    return true;
  }).map((card) => ({
    id: card.id,
    title: card.title,
    iconSvgXml: card.iconSvgXml,
    color: card.id === 'emergency' ? theme.colors.error : theme.colors.primary,
    onPress: () => navigation.navigate(card.screen),
  }));

  return (
    <View style={styles.container}>
      {/* Header: logo left, VIDYARTHI text (yellow-gold) center, bell right */}
      <View
        style={[
          styles.header,
          { paddingTop: Math.max(insets.top + 8, 24) },
        ]}>
        <View style={styles.headerLeft}>
          <View style={styles.headerLogoImg}>
            <SvgXml xml={mapLogoColorSvgXml} width={88} height={56} />
          </View>
        </View>
        <View style={styles.headerCenter}>
          <Text style={styles.vidyarthiText}>VIDYARTHI</Text>
        </View>
        <View style={styles.headerRight}>
          <GradientButton
            style={styles.notificationButton}
            onPress={() => navigation.navigate('Notifications')}
            accessibilityLabel={`Notifications, ${notificationCount} unread`}>
            <Ionicons name="notifications-outline" size={24} color="#D79F24" />
            {notificationCount > 0 && (
              <View style={styles.notificationBadge}>
                <Text style={styles.notificationBadgeText}>
                  {notificationCount > 99 ? '99+' : notificationCount}
                </Text>
              </View>
            )}
          </GradientButton>
        </View>
      </View>
        
      <ScrollView
        style={styles.content}
        contentContainerStyle={[styles.scrollContent, { minHeight: Dimensions.get('window').height - 190 }]}
        showsVerticalScrollIndicator={true}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>

        {/* Intro Card - student greeting card with text on the left and logo card on the right */}
        <View style={[styles.introCard, isCompactLayout && styles.introCardCompact]} collapsable={false}>
          <View style={[styles.introContent, isCompactLayout && styles.introContentCompact]}>
            <View style={[styles.introTextWrapper, isCompactLayout && styles.introTextWrapperCompact]}>
              <Text style={styles.greeting} numberOfLines={1}>{greeting},</Text>
              <Text style={styles.studentName} numberOfLines={1} ellipsizeMode="tail">
                {studentInfo?.name || user?.name || 'Student'}
              </Text>
              <Text style={styles.studentDetails} numberOfLines={1} ellipsizeMode="tail">
                Room {studentInfo?.roomNumber}
              </Text>
            </View>
            <View style={[styles.introLogoContainer, isCompactLayout && styles.introLogoContainerCompact]}>
              <CollegeLogo
                logoUrl={tenantLogoUrl ?? undefined}
                collegeName={tenantName}
                fillContainer
              />
            </View>
          </View>
        </View>

        {/* Actions Section - 2x4 Grid */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Actions</Text>
          <ActionTilesGrid tiles={visibleTiles} columns={2} />
        </View>

        {/* Footer */}
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
    backgroundColor: theme.colors.white,
  },
  header: {
    backgroundColor: theme.colors.white,
    paddingBottom: 16,
    paddingHorizontal: 20,
    minHeight: 84,
    flexDirection: 'row',
    alignItems: 'center',
    ...theme.shadows.medium,
  },
  headerLeft: {
    width: 60,
    justifyContent: 'flex-end',
    alignItems: 'flex-start',
  },
  headerLogoImg: {
    // Keep logo visually balanced so spacing between
    // VIDYARTHI text and logo/bell is symmetrical.
    marginLeft: -8,
    marginTop: 6,
  },
  headerLogo: {
    width: 60,
    height: 72,
  },
  headerCenter: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingLeft: 8,
  },
  headerRight: {
    width: 60,
    justifyContent: 'flex-end',
    alignItems: 'flex-end',
  },
  vidyarthiText: {
    fontFamily: 'EthnocentricRg',
    fontSize: 18,
    color: theme.colors.primary,
  },
  notificationButton: {
    position: 'relative',
    padding: 8,
    marginTop: 4,
  },
  notificationBadge: {
    position: 'absolute',
    top: 4,
    right: 4,
    backgroundColor: theme.colors.error,
    borderRadius: 10,
    minWidth: 18,
    height: 18,
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 4,
  },
  notificationBadgeText: {
    color: theme.colors.white,
    fontSize: 10,
    fontWeight: '700',
  },
  content: {
    flex: 1,
  },
  scrollContent: {
    flexGrow: 1,
    paddingBottom: 32,
  },
  // Greeting card
  introCard: {
    backgroundColor: theme.colors.surface,
    marginHorizontal: 14,
    marginTop: 24,
    marginBottom: 6,
    paddingVertical: 20,
    paddingLeft: 20,
    paddingRight: 16,
    borderRadius: 16,
    flexDirection: 'row',
    alignItems: 'stretch',
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: theme.colors.border,
    minHeight: 150,
    shadowColor: '#000',
    shadowOpacity: 0.1,
    shadowRadius: 10,
    shadowOffset: { width: 0, height: 4 },
    elevation: 4,
  },
  introCardCompact: {},
  introContent: {
    flexDirection: 'row',
    alignItems: 'stretch',
    flex: 1,
  },
  introContentCompact: {},
  introTextWrapper: {
    flex: 1,
    flexShrink: 1,
    minWidth: 0,
    justifyContent: 'center',
  },
  introTextWrapperCompact: {},
  greetingRow: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  greetingLogoWrapper: {
    marginLeft: 8,
  },
  introLogoContainer: {
    flex: 1,
    marginLeft: 16,
    alignSelf: 'stretch',
    padding: 10,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.white,
  },
  introLogoContainerCompact: {},
  greeting: {
    fontSize: 14,
    color: theme.colors.textSecondary,
  },
  studentName: {
    fontSize: 18,
    fontWeight: '700',
    color: theme.colors.text,
    marginTop: 4,
  },
  studentDetails: {
    fontSize: 12,
    color: theme.colors.textMuted,
    marginTop: 4,
  },
  tenantLogo: {
    width: '100%',
    height: '100%',
    borderRadius: 0,
  },
  logoPlaceholder: {
    width: '100%',
    height: '100%',
    borderRadius: 0,
    backgroundColor: theme.colors.surface,
    justifyContent: 'center',
    alignItems: 'center',
  },
  section: {
    paddingHorizontal: 16,
    marginTop: 4,
    marginBottom: 24,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: theme.colors.text,
    marginBottom: 10,
  },
  actionsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
  },
  actionCard: {
    backgroundColor: theme.colors.card,
    width: '48%',
    padding: 20,
    borderRadius: theme.borderRadius.lg,
    alignItems: 'center',
    marginBottom: 12,
    ...theme.shadows.small,
  },
  actionIconContainer: {
    width: 56,
    height: 56,
    borderRadius: 28,
    backgroundColor: `${theme.colors.primary}15`,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 12,
  },
  actionLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: theme.colors.text,
    textAlign: 'center',
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
