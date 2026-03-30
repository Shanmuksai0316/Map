import React, { useEffect, useState, useMemo, useCallback } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  Image,
} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useAuthStore } from '../../store/auth.store';
import { apiService } from '../../services/api.service';
import { DashboardStats, GatePass, Notice } from '../../types';
import { APP_CONFIG } from '../../config/app.config';
import { theme } from '../../theme/theme';
import { getGreeting } from '../../utils/greeting.util';
import { errorHandler } from '../../utils/errorHandler';

export const StudentDashboardScreen = ({ navigation }: any) => {
  const { user, isFeatureEnabled } = useAuthStore();
  const [_stats, setStats] = useState<DashboardStats | null>(null);
  const [recentGatePass, setRecentGatePass] = useState<GatePass | null>(null);
  const [notices, setNotices] = useState<Notice[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [tenantLogoUrl, setTenantLogoUrl] = useState<string | null>(null);
  const [errors, setErrors] = useState<{
    dashboard?: string;
    gatePass?: string;
    notices?: string;
    profile?: string;
  }>({});

  const fetchDashboardData = async () => {
    try {
      setErrors({});
      
      // Fetch all data in parallel, but handle errors individually
      const results = await Promise.allSettled([
        apiService.get<{ data: DashboardStats }>(APP_CONFIG.ENDPOINTS.DASHBOARD),
        apiService.get<{ data: GatePass[] }>(`${APP_CONFIG.ENDPOINTS.GATE_PASSES}?limit=1`),
        apiService.get<{ data: Notice[] }>(`${APP_CONFIG.ENDPOINTS.NOTICES}?limit=3`),
        apiService.get<{ data: any }>(APP_CONFIG.ENDPOINTS.PROFILE),
      ]);

      // #region agent log
      fetch('http://127.0.0.1:7242/ingest/f2a649ac-444d-418b-bdf8-72f54480be07', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Debug-Session-Id': '72ddcf',
        },
        body: JSON.stringify({
          sessionId: '72ddcf',
          runId: 'tenant-logo-run-1',
          hypothesisId: 'H1_H2',
          location: 'apps/student/DashboardScreen.tsx:fetchDashboardData',
          message: 'Dashboard data fetch results',
          data: {
            profileStatus: results[3]?.status,
          },
          timestamp: Date.now(),
        }),
      }).catch(() => {});
      // #endregion agent log

      // Handle dashboard stats
      if (results[0].status === 'fulfilled') {
        setStats(results[0].value.data);
        setErrors(prev => ({ ...prev, dashboard: undefined }));
      } else {
        const errorDetails = errorHandler.handleError(results[0].reason);
        setErrors(prev => ({ ...prev, dashboard: errorDetails.message }));
        setStats(null);
      }

      // Handle gate passes
      if (results[1].status === 'fulfilled') {
        setRecentGatePass(results[1].value.data[0] || null);
        setErrors(prev => ({ ...prev, gatePass: undefined }));
      } else {
        const errorDetails = errorHandler.handleError(results[1].reason);
        setErrors(prev => ({ ...prev, gatePass: errorDetails.message }));
        setRecentGatePass(null);
      }

      // Handle notices
      if (results[2].status === 'fulfilled') {
        setNotices(results[2].value.data || []);
        setErrors(prev => ({ ...prev, notices: undefined }));
      } else {
        const errorDetails = errorHandler.handleError(results[2].reason);
        setErrors(prev => ({ ...prev, notices: errorDetails.message }));
        setNotices([]);
      }

      // Handle profile
      if (results[3].status === 'fulfilled') {
        if (results[3].value.data?.tenant_logo_url) {
          const logoUrl = results[3].value.data.tenant_logo_url;
          setTenantLogoUrl(logoUrl);

          // #region agent log
          fetch('http://127.0.0.1:7242/ingest/f2a649ac-444d-418b-bdf8-72f54480be07', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-Debug-Session-Id': '72ddcf',
            },
            body: JSON.stringify({
              sessionId: '72ddcf',
              runId: 'tenant-logo-run-1',
              hypothesisId: 'H2',
              location: 'apps/student/DashboardScreen.tsx:fetchDashboardData',
              message: 'Profile fetch succeeded with tenant logo URL',
              data: {
                hasTenantLogoUrl: !!logoUrl,
                logoUrlLength: typeof logoUrl === 'string' ? logoUrl.length : 0,
              },
              timestamp: Date.now(),
            }),
          }).catch(() => {});
          // #endregion agent log
        }
        setErrors(prev => ({ ...prev, profile: undefined }));
      } else {
        const errorDetails = errorHandler.handleError(results[3].reason);
        setErrors(prev => ({ ...prev, profile: errorDetails.message }));

        // #region agent log
        fetch('http://127.0.0.1:7242/ingest/f2a649ac-444d-418b-bdf8-72f54480be07', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Debug-Session-Id': '72ddcf',
          },
          body: JSON.stringify({
            sessionId: '72ddcf',
            runId: 'tenant-logo-run-1',
            hypothesisId: 'H2',
            location: 'apps/student/DashboardScreen.tsx:fetchDashboardData',
            message: 'Profile fetch failed',
            data: {
              errorMessage: errorDetails.message,
            },
            timestamp: Date.now(),
          }),
        }).catch(() => {});
        // #endregion agent log
      }
    } catch (error) {
      const errorDetails = errorHandler.handleError(error);
      console.error('Dashboard fetch error:', errorDetails.message);
    } finally {
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchDashboardData();
  }, []);

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    fetchDashboardData();
  }, []);

  // Memoize greeting to avoid recalculation
  const greeting = useMemo(() => getGreeting(), []);

  // Memoize user name
  const userName = useMemo(() => user?.name || 'Student', [user?.name]);

  // Memoize status color function
  const getStatusColor = useCallback((status: string) => {
    switch (status) {
      case 'approved':
      case 'active':
        return theme.colors.success;
      case 'pending':
        return theme.colors.warning;
      case 'rejected':
        return theme.colors.error;
      default:
        return theme.colors.textMuted;
    }
  }, []);

  return (
    <ScrollView
      style={styles.container}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
      }>
      {/* Header */}
      <View style={styles.header}>
        {/* Logo Row - MAP Logo + Tenant Logo */}
        <View style={styles.logoRow}>
          <Image
            source={require('../../assets/map-logo.png')}
            style={styles.logoImage}
            resizeMode="contain"
          />
          {tenantLogoUrl && (
            <Image
              source={{ uri: tenantLogoUrl }}
              style={[styles.logoImage, styles.tenantLogo]}
              resizeMode="contain"
            />
          )}
        </View>
        
        {/* Greeting */}
        <View style={styles.greetingContainer}>
          <Text style={styles.greeting}>
            {greeting}, {userName}
          </Text>
        </View>
      </View>

      {/* Additional Services */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Additional Services</Text>
        <View style={styles.servicesGrid}>
          {/* 1. Gate Pass */}
          <TouchableOpacity
            style={styles.serviceCard}
            onPress={() => navigation.navigate('GatePass')}
            accessibilityRole="button"
            accessibilityLabel="Gate Pass - Create and manage gate pass requests"
            accessibilityHint="Double tap to open gate pass screen">
            <Ionicons
              name="log-out-outline"
              size={28}
              color={theme.colors.primary}
              style={styles.serviceIcon}
              accessibilityRole="image"
            />
            <Text style={styles.serviceLabel}>Gate Pass</Text>
          </TouchableOpacity>

          {/* 2. House Keeping */}
          <TouchableOpacity
            style={styles.serviceCard}
            onPress={() => navigation.navigate('Tickets', { category: 'housekeeping' })}
            accessibilityRole="button"
            accessibilityLabel="House Keeping - Create housekeeping service tickets"
            accessibilityHint="Double tap to open housekeeping tickets">
            <Ionicons
              name="home-outline"
              size={28}
              color={theme.colors.primary}
              style={styles.serviceIcon}
              accessibilityRole="image"
            />
            <Text style={styles.serviceLabel}>House Keeping</Text>
          </TouchableOpacity>

          {/* 3. Repair and Maintenance */}
          <TouchableOpacity
            style={styles.serviceCard}
            onPress={() => navigation.navigate('Tickets', { category: 'repair_maintenance' })}
            accessibilityRole="button"
            accessibilityLabel="Repair and Maintenance - Create repair and maintenance tickets"
            accessibilityHint="Double tap to open repair tickets">
            <Ionicons
              name="construct-outline"
              size={28}
              color={theme.colors.primary}
              style={styles.serviceIcon}
              accessibilityRole="image"
            />
            <Text style={styles.serviceLabel}>Repair and Maintenance</Text>
          </TouchableOpacity>

          {/* 4. Laundry */}
          {isFeatureEnabled('laundry_module_enabled') && (
          <TouchableOpacity
            style={styles.serviceCard}
            onPress={() => navigation.navigate('LaundryRequest')}
            accessibilityRole="button"
            accessibilityLabel="Laundry - Create laundry service requests"
            accessibilityHint="Double tap to open laundry requests">
            <Ionicons
              name="shirt-outline"
              size={28}
              color={theme.colors.primary}
              style={styles.serviceIcon}
              accessibilityRole="image"
            />
            <Text style={styles.serviceLabel}>Laundry</Text>
          </TouchableOpacity>
          )}

          {/* 5. Sports - hidden for now */}
          {false && isFeatureEnabled('sports_module_enabled') && (
          <TouchableOpacity
            style={styles.serviceCard}
            onPress={() => navigation.navigate('SportsBooking')}
            accessibilityRole="button"
            accessibilityLabel="Sports - Book sports facilities"
            accessibilityHint="Double tap to open sports booking">
            <Ionicons
              name="football-outline"
              size={28}
              color={theme.colors.primary}
              style={styles.serviceIcon}
              accessibilityRole="image"
            />
            <Text style={styles.serviceLabel}>Sports</Text>
          </TouchableOpacity>
          )}

          {/* 6. Sick Leave Token */}
          <TouchableOpacity
            style={styles.serviceCard}
            onPress={() => navigation.navigate('SickLeavePreview')}
            accessibilityRole="button"
            accessibilityLabel="Sick Leave Token - View and create sick leave requests"
            accessibilityHint="Double tap to open sick leave requests">
            <Ionicons
              name="medical-outline"
              size={28}
              color={theme.colors.primary}
              style={styles.serviceIcon}
              accessibilityRole="image"
            />
            <Text style={styles.serviceLabel}>Sick Leave Token</Text>
          </TouchableOpacity>

          {/* 7. Leaves */}
          <TouchableOpacity
            style={styles.serviceCard}
            onPress={() => navigation.navigate('LeavePreview')}
            accessibilityRole="button"
            accessibilityLabel="Leaves - View and create leave requests"
            accessibilityHint="Double tap to open leave requests">
            <Ionicons
              name="calendar-outline"
              size={28}
              color={theme.colors.primary}
              style={styles.serviceIcon}
              accessibilityRole="image"
            />
            <Text style={styles.serviceLabel}>Leaves</Text>
          </TouchableOpacity>

          {/* 8. Guest Entry */}
          <TouchableOpacity
            style={styles.serviceCard}
            onPress={() => navigation.navigate('GuestEntryPreview')}
            accessibilityRole="button"
            accessibilityLabel="Guest Entry - View and create guest entry requests"
            accessibilityHint="Double tap to open guest entry requests">
            <Ionicons
              name="people-outline"
              size={28}
              color={theme.colors.primary}
              style={styles.serviceIcon}
              accessibilityRole="image"
            />
            <Text style={styles.serviceLabel}>Guest Entry</Text>
          </TouchableOpacity>

          {/* 9. Room Change */}
          <TouchableOpacity
            style={styles.serviceCard}
            onPress={() => navigation.navigate('RoomChangePreview')}
            accessibilityRole="button"
            accessibilityLabel="Room Change - View and create room change requests"
            accessibilityHint="Double tap to open room change requests">
            <Ionicons
              name="swap-horizontal-outline"
              size={28}
              color={theme.colors.primary}
              style={styles.serviceIcon}
              accessibilityRole="image"
            />
            <Text style={styles.serviceLabel}>Room Change</Text>
          </TouchableOpacity>

          {/* 10. Feedback */}
          <TouchableOpacity
            style={styles.serviceCard}
            onPress={() => navigation.navigate('Feedback')}
            accessibilityRole="button"
            accessibilityLabel="Feedback - Submit feedback"
            accessibilityHint="Double tap to open feedback">
            <Ionicons
              name="chatbubble-outline"
              size={28}
              color={theme.colors.primary}
              style={styles.serviceIcon}
              accessibilityRole="image"
            />
            <Text style={styles.serviceLabel}>Feedback</Text>
          </TouchableOpacity>
        </View>
      </View>

      {/* Recent Gate Pass */}
      {recentGatePass && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Recent Gate Pass</Text>
          <View style={styles.card}>
            <View style={styles.cardHeader}>
              <Text style={styles.cardTitle}>{recentGatePass.purpose}</Text>
              <View
                style={[
                  styles.statusBadge,
                  { backgroundColor: getStatusColor(recentGatePass.status) },
                ]}>
                <Text style={styles.statusText}>
                  {recentGatePass.status.toUpperCase()}
                </Text>
              </View>
            </View>
            <Text style={styles.cardDetail}>
              Out: {recentGatePass.out_date} {recentGatePass.out_time}
            </Text>
            <Text style={styles.cardDetail}>
              Expected In: {recentGatePass.expected_in_date}{' '}
              {recentGatePass.expected_in_time}
            </Text>
          </View>
        </View>
      )}

      {/* Notices */}
      {notices.length > 0 && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Recent Notices</Text>
          {notices.map((notice) => (
            <View key={notice.id} style={styles.noticeCard}>
              <Text style={styles.noticeTitle}>{notice.title}</Text>
              <Text style={styles.noticeDescription} numberOfLines={2}>
                {notice.description}
              </Text>
              <Text style={styles.noticeDate}>
                {new Date(notice.created_at).toLocaleDateString()}
              </Text>
            </View>
          ))}
        </View>
      )}
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  header: {
    backgroundColor: theme.colors.card,
    paddingHorizontal: theme.spacing.lg,
    paddingTop: theme.spacing.lg,
    paddingBottom: theme.spacing.lg,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
    minHeight: 168,
  },
  logoRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: theme.spacing.md,
    marginBottom: theme.spacing.md,
  },
  logoImage: {
    width: 80,
    height: 80,
  },
  tenantLogo: {
    flex: 1,
    maxWidth: '50%',
  },
  greetingContainer: {
    flex: 1,
    alignItems: 'flex-start',
  },
  greeting: {
    color: theme.colors.text,
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
  },
  section: {
    padding: theme.spacing.lg,
  },
  sectionTitle: {
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
    marginBottom: theme.spacing.md,
  },
  servicesGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
  },
  serviceCard: {
    backgroundColor: theme.colors.card,
    width: '48%',
    padding: theme.spacing.lg,
    borderRadius: theme.borderRadius.lg,
    alignItems: 'center',
    marginBottom: theme.spacing.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    ...theme.shadows.small,
  },
  serviceIcon: {
    fontSize: 36,
    marginBottom: theme.spacing.sm,
  },
  serviceLabel: {
    fontSize: theme.fontSize.sm,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
    textAlign: 'center',
  },
  card: {
    backgroundColor: theme.colors.card,
    padding: theme.spacing.md,
    borderRadius: theme.borderRadius.lg,
    borderWidth: 1,
    borderColor: theme.colors.border,
    ...theme.shadows.small,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: theme.spacing.sm,
  },
  cardTitle: {
    fontSize: theme.fontSize.lg,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
    flex: 1,
  },
  statusBadge: {
    paddingHorizontal: theme.spacing.sm,
    paddingVertical: theme.spacing.xs,
    borderRadius: theme.borderRadius.xl,
  },
  statusText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.xs,
    fontWeight: theme.fontWeight.semibold,
  },
  cardDetail: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
    marginBottom: theme.spacing.xs,
  },
  noticeCard: {
    backgroundColor: theme.colors.card,
    padding: theme.spacing.md,
    borderRadius: theme.borderRadius.lg,
    marginBottom: theme.spacing.sm,
    borderWidth: 1,
    borderColor: theme.colors.border,
    ...theme.shadows.small,
  },
  noticeTitle: {
    fontSize: theme.fontSize.lg,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
    marginBottom: theme.spacing.xs,
  },
  noticeDescription: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
    marginBottom: theme.spacing.xs,
  },
  noticeDate: {
    fontSize: theme.fontSize.xs,
    color: theme.colors.textMuted,
  },
});

