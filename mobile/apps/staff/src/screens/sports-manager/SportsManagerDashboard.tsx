import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  Alert,
  Image,
} from 'react-native';
import { useAuthStore } from '../../store/auth.store';
import { apiService } from '../../services/api.service';
import { DashboardStats } from '../../types';
import { APP_CONFIG } from '../../config/app.config';
import { format } from 'date-fns';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { theme } from '../../theme/theme';
import { errorHandler } from '../../utils/errorHandler';
import { ErrorState } from '../../components/shared/ErrorState';
import { colors } from '../../theme/colors';
import { KebabMenu } from '../../components/shared/KebabMenu';
import { getGreeting } from '../../utils/greeting.util';

export const SportsManagerDashboard = ({ navigation }: any) => {
  const { user, logout } = useAuthStore();
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<any>(null);

  const fetchDashboardData = async () => {
    try {
      setError(null);
      const response = await apiService.get<{ data: DashboardStats }>(
        APP_CONFIG.ENDPOINTS.DASHBOARD
      );
      setStats(response.data || {
        total_facilities: 0,
        active_bookings: 0,
        available_facilities: 0,
        pending_checklists: 0,
      });
    } catch (err) {
      console.error('Dashboard fetch error:', err);
      const errorDetails = errorHandler.handleError(err);
      setError(errorDetails);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchDashboardData();
  }, []);

  const onRefresh = () => {
    setRefreshing(true);
    fetchDashboardData();
  };

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
            await logout();
          },
        },
      ]
    );
  };

  const StatCard = ({ title, value, icon, color = '#4CAF50' }: any) => (
    <View style={[styles.statCard, { borderLeftColor: color }]}>
      <View style={styles.statHeader}>
        <Ionicons name={icon} size={20} color={color} style={styles.statIcon} />
        <Text style={styles.statTitle}>{title}</Text>
      </View>
      <Text style={styles.statValue}>{value}</Text>
    </View>
  );

  return (
    <ScrollView
      style={styles.container}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
      }>
      {/* Error State */}
      {error && !loading && (
        <ErrorState error={error} onRetry={fetchDashboardData} />
      )}

      {/* Loading State */}
      {loading && !error && (
        <View style={styles.loadingContainer}>
          <Text style={styles.loadingText}>Loading dashboard...</Text>
        </View>
      )}

      {/* Content */}
      {!loading && !error && (
        <>
      {/* Header */}
      <View style={styles.header}>
        <View style={styles.headerTop}>
          <View style={styles.logoContainer}>
            <Image
              source={require('../../assets/map-logo.png')}
              style={styles.logoImage}
              resizeMode="contain"
            />
            <Text style={styles.appTitle}>Sports App</Text>
          </View>
          <KebabMenu
            options={[
              {
                label: 'Profile',
                icon: 'person-outline',
                onPress: () => navigation.navigate('Profile'),
              },
              {
                label: 'Notifications',
                icon: 'notifications-outline',
                onPress: () => navigation.navigate('Notifications'),
              },
              {
                label: 'Announcements',
                icon: 'megaphone-outline',
                onPress: () => navigation.navigate('Announcements'),
              },
              {
                label: 'Logout',
                icon: 'log-out-outline',
                onPress: handleLogout,
                destructive: true,
              },
            ]}
          />
        </View>
        <View style={styles.greetingContainer}>
          <Text style={styles.greeting}>{getGreeting()},</Text>
          <Text style={styles.userName}>{user?.name}</Text>
        </View>
      </View>

      {/* Quick Actions */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Daily Operations</Text>
        <View style={styles.actionsGrid}>
          <TouchableOpacity
            style={styles.actionCard}
            onPress={() => navigation.navigate('Checklist')}>
            <View style={[styles.actionIconContainer, { backgroundColor: 'rgba(76, 175, 80, 0.1)' }]}>
              <Ionicons name="checkmark-done-circle-outline" size={24} color={theme.colors.success} />
            </View>
            <Text style={styles.actionText}>Daily</Text>
            <Text style={styles.actionSubtext}>Checklist</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.actionCard}
            onPress={() => navigation.navigate('SportsBlockouts')}>
            <View style={[styles.actionIconContainer, { backgroundColor: 'rgba(255, 152, 0, 0.1)' }]}>
              <Ionicons name="ban-outline" size={24} color="#FF9800" />
            </View>
            <Text style={styles.actionText}>Blockouts</Text>
            <Text style={styles.actionSubtext}>Manage</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.actionCard}
            onPress={() => navigation.navigate('SportsFacilityMonitoring')}>
            <View style={[styles.actionIconContainer, { backgroundColor: 'rgba(33, 150, 243, 0.1)' }]}>
              <Ionicons name="analytics-outline" size={24} color="#2196F3" />
            </View>
            <Text style={styles.actionText}>Monitoring</Text>
            <Text style={styles.actionSubtext}>Occupancy</Text>
          </TouchableOpacity>
        </View>
      </View>

      {/* Statistics */}
      {stats && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Sports Facility Overview</Text>
          <View style={styles.statsGrid}>
            <StatCard
              title="Total Facilities"
              value={stats.total_facilities?.toString() || "0"}
              icon="business-outline"
              color="#2196F3"
            />
            <StatCard
              title="Available Now"
              value={stats.available_facilities?.toString() || "0"}
              icon="checkmark-circle-outline"
              color="#4CAF50"
            />
            <StatCard
              title="In Use"
              value={stats.active_bookings?.toString() || "0"}
              icon="fitness-outline"
              color="#FF9800"
            />
            <StatCard
              title="Maintenance"
              value="0"
              icon="construct-outline"
              color="#9C27B0"
            />
          </View>
        </View>
      )}



      {/* Quick Stats */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Monthly Summary</Text>
        <View style={styles.quickStats}>
          <View style={styles.quickStatItem}>
            <Text style={styles.quickStatNumber}>
              {stats ? (stats.total_facilities || 0) : 0}
            </Text>
            <Text style={styles.quickStatLabel}>Total Facilities</Text>
          </View>
          <View style={styles.quickStatItem}>
            <Text style={styles.quickStatNumber}>
              {stats ? (stats.available_facilities || 0) : 0}
            </Text>
            <Text style={styles.quickStatLabel}>Available Now</Text>
          </View>
          <View style={styles.quickStatItem}>
            <Text style={styles.quickStatNumber}>
              {stats ? (stats.active_bookings || 0) : 0}
            </Text>
            <Text style={styles.quickStatLabel}>Currently In Use</Text>
          </View>
        </View>
      </View>
        </>
      )}
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
  },
  header: {
    backgroundColor: theme.colors.primary,
    padding: 20,
    paddingTop: 60,
  },
  headerTop: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 16,
  },
  logoContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  logoImage: {
    width: 40,
    height: 40,
    marginRight: 12,
  },
  appTitle: {
    color: colors.surface,
    fontSize: 20,
    fontWeight: 'bold',
  },
  greetingContainer: {
    marginTop: 8,
  },
  greeting: {
    color: colors.surface,
    fontSize: 18,
    fontWeight: '600',
    marginBottom: 4,
  },
  userName: {
    color: colors.surface,
    fontSize: 24,
    fontWeight: 'bold',
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
  },
  loadingText: {
    fontSize: 16,
    color: colors.textSecondary,
    marginTop: 16,
  },
  section: {
    padding: 20,
  },
  sectionTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#333',
    marginBottom: 16,
  },
  actionsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
  },
  actionCard: {
    backgroundColor: '#fff',
    width: '48%',
    padding: 20,
    borderRadius: 12,
    alignItems: 'center',
    marginBottom: 16,
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  actionIconContainer: {
    width: 48,
    height: 48,
    borderRadius: 24,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 8,
  },
  actionText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#333',
    marginBottom: 2,
  },
  actionSubtext: {
    fontSize: 12,
    color: '#666',
  },
  statsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
  },
  statCard: {
    backgroundColor: '#fff',
    width: '48%',
    padding: 16,
    borderRadius: 12,
    marginBottom: 12,
    borderLeftWidth: 4,
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  statHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 8,
  },
  statIcon: {
    fontSize: 20,
    marginRight: 8,
  },
  statTitle: {
    fontSize: 14,
    color: '#666',
    fontWeight: '500',
  },
  statValue: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#333',
  },
  bookingCard: {
    backgroundColor: '#fff',
    padding: 16,
    borderRadius: 12,
    marginBottom: 12,
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  bookingHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  bookingFacility: {
    fontSize: 16,
    fontWeight: '600',
    color: '#333',
  },
  bookingStatusBadge: {
    backgroundColor: '#4CAF50',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
  },
  bookingStatusText: {
    color: '#fff',
    fontSize: 10,
    fontWeight: '600',
  },
  bookingStudent: {
    fontSize: 14,
    color: '#333',
    marginBottom: 4,
  },
  bookingDetails: {
    fontSize: 12,
    color: '#666',
    marginBottom: 4,
  },
  bookingPurpose: {
    fontSize: 12,
    color: '#999',
  },
  facilityCard: {
    backgroundColor: '#fff',
    padding: 16,
    borderRadius: 12,
    marginBottom: 12,
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  facilityHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  facilityName: {
    fontSize: 16,
    fontWeight: '600',
    color: '#333',
  },
  facilityStatusBadge: {
    backgroundColor: '#2196F3',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
  },
  facilityStatusText: {
    color: '#fff',
    fontSize: 10,
    fontWeight: '600',
  },
  facilityDetails: {
    fontSize: 12,
    color: '#666',
    marginBottom: 8,
  },
  facilityProgress: {
    height: 6,
    backgroundColor: '#f0f0f0',
    borderRadius: 3,
  },
  facilityProgressFill: {
    height: '100%',
    backgroundColor: '#4CAF50',
    borderRadius: 3,
  },
  eventCard: {
    backgroundColor: '#fff',
    padding: 16,
    borderRadius: 12,
    marginBottom: 12,
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  eventHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  eventName: {
    fontSize: 16,
    fontWeight: '600',
    color: '#333',
    flex: 1,
  },
  eventDate: {
    fontSize: 12,
    color: '#666',
    fontWeight: '600',
  },
  eventDetails: {
    fontSize: 12,
    color: '#666',
    marginBottom: 4,
  },
  eventParticipants: {
    fontSize: 12,
    color: '#999',
  },
  quickStats: {
    backgroundColor: '#fff',
    padding: 20,
    borderRadius: 12,
    flexDirection: 'row',
    justifyContent: 'space-around',
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  quickStatItem: {
    alignItems: 'center',
  },
  quickStatNumber: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#4CAF50',
    marginBottom: 4,
  },
  quickStatLabel: {
    fontSize: 12,
    color: '#666',
    textAlign: 'center',
  },
});
