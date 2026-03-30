import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  ActivityIndicator,
  Alert,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import { apiService } from '../../../shared/services/api.service';
import { APP_CONFIG } from '../../../shared/config/app.config';
import { format } from 'date-fns';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { colors } from '../../../shared/theme/colors';
import { errorHandler } from '../../../shared/utils/errorHandler';
import { ErrorState } from '../../../shared/components/shared/ErrorState';
import { Notice } from '../../../shared/types';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

export const NoticeManagementScreen = ({ navigation }: any) => {
  const [notices, setNotices] = useState<Notice[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<any>(null);
  const [filter, setFilter] = useState<'all' | 'published' | 'draft' | 'scheduled'>('all');

  const fetchNotices = async () => {
    try {
      setError(null);
      const statusParam = filter !== 'all' ? `&status=${filter}` : '';
      const response = await apiService.get<{ data: Notice[] }>(
        `${APP_CONFIG.ENDPOINTS.ADMIN_NOTICES}?limit=100${statusParam}`
      );
      setNotices(response.data || []);
    } catch (err) {
      console.error('Failed to fetch notices:', err);
      const errorDetails = errorHandler.handleError(err);
      setError(errorDetails);
      setNotices([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchNotices();
  }, [filter]);

  const onRefresh = () => {
    setRefreshing(true);
    fetchNotices();
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'published':
        return colors.success;
      case 'draft':
        return colors.warning;
      case 'scheduled':
        return colors.info;
      default:
        return colors.textSecondary;
    }
  };

  if (loading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color={colors.primary} />
        <Text style={styles.loadingText}>Loading notices...</Text>
      </View>
    );
  }

  if (error) {
    return (
      <View style={styles.container}>
        <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Notice Management" />
        <ErrorState error={error} onRetry={fetchNotices} />
      </View>
    );
  }

  const filteredNotices = filter === 'all' 
    ? notices 
    : notices.filter(n => n.status === filter);

  return (
    <View style={styles.container}>
      <StaffScreenHeader
        onBack={() => navigation.goBack()}
        showBell={false}
        rightSlot={
          <GradientButton
            style={styles.addButton}
            onPress={() => {
              // Navigate to create notice screen (to be implemented)
              // For now, show alert
              Alert.alert('Info', 'Create notice feature is available in the web panel.');
            }}>
            <Ionicons name="add" size={22} color={colors.textOnPrimary} />
          </GradientButton>
        }  title="Notice Management" />
      {/* Filter Tabs */}
      <View style={styles.filterContainer}>
        <ScrollView horizontal showsHorizontalScrollIndicator={false}>
          {(['all', 'published', 'draft', 'scheduled'] as const).map((filterOption) => (
            <TouchableOpacity
              key={filterOption}
              style={[
                styles.filterChip,
                filter === filterOption && styles.filterChipActive,
              ]}
              onPress={() => setFilter(filterOption)}>
              <Text
                style={[
                  styles.filterChipText,
                  filter === filterOption && styles.filterChipTextActive,
                ]}>
                {filterOption.charAt(0).toUpperCase() + filterOption.slice(1)}
              </Text>
            </TouchableOpacity>
          ))}
        </ScrollView>
      </View>

      {/* Content */}
      <ScrollView
        style={styles.content}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>
        {filteredNotices.length === 0 ? (
          <View style={styles.emptyState}>
            <Ionicons name="megaphone-outline" size={48} color={colors.textMuted} />
            <Text style={styles.emptyTitle}>No Notices</Text>
            <Text style={styles.emptySubtitle}>
              {filter === 'all'
                ? 'No notices found. Create one to get started.'
                : `No ${filter} notices found.`}
            </Text>
          </View>
        ) : (
          <>
            <View style={styles.resultsHeader}>
              <Text style={styles.resultsCount}>
                {filteredNotices.length} notice{filteredNotices.length !== 1 ? 's' : ''}
              </Text>
            </View>
            {filteredNotices.map((notice) => (
              <View key={notice.id} style={styles.noticeCard}>
                <View style={styles.noticeHeader}>
                  <View style={styles.noticeInfo}>
                    <Text style={styles.noticeTitle}>{notice.title}</Text>
                    <Text style={styles.noticeDate}>
                      {format(new Date(notice.created_at), 'MMM dd, yyyy')}
                    </Text>
                  </View>
                  <View style={[styles.statusBadge, { backgroundColor: getStatusColor(notice.status || 'draft') + '20' }]}>
                    <Text style={[styles.statusText, { color: getStatusColor(notice.status || 'draft') }]}>
                      {notice.status || 'draft'}
                    </Text>
                  </View>
                </View>
                {notice.description && (
                  <Text style={styles.noticeDescription} numberOfLines={3}>
                    {notice.description}
                  </Text>
                )}
                <View style={styles.noticeFooter}>
                  <View style={styles.noticeMeta}>
                    {notice.target_roles && notice.target_roles.length > 0 && (
                      <View style={styles.metaItem}>
                        <Ionicons name="people-outline" size={14} color={colors.textMuted} />
                        <Text style={styles.metaText}>
                          {notice.target_roles.join(', ')}
                        </Text>
                      </View>
                    )}
                  </View>
                  <GradientButton
                    style={styles.viewButton}
                    onPress={() => {
                      // Navigate to notice detail (to be implemented)
                      Alert.alert('Notice Details', `Title: ${notice.title}\n\n${notice.description || 'No description'}`);
                    }}>
                    <Text style={styles.viewButtonText}>View</Text>
                  </GradientButton>
                </View>
              </View>
            ))}
          </>
        )}
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  subHeader: {
    paddingHorizontal: 16,
    paddingTop: 12,
    paddingBottom: 8,
  },
  subHeaderTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: colors.textHeading,
  },
  addButton: {
    padding: 6,
    backgroundColor: colors.primary,
    borderRadius: 8,
  },
  filterContainer: {
    backgroundColor: colors.white,
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  filterChip: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: colors.background,
    marginLeft: 8,
    borderWidth: 1,
    borderColor: colors.border,
  },
  filterChipActive: {
    backgroundColor: colors.primary,
    borderColor: colors.primary,
  },
  filterChipText: {
    fontSize: 14,
    fontWeight: '500',
    color: colors.text,
  },
  filterChipTextActive: {
    color: colors.white,
    fontWeight: '600',
  },
  content: {
    flex: 1,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
  },
  loadingText: {
    marginTop: 16,
    fontSize: 16,
    color: colors.textSecondary,
  },
  resultsHeader: {
    paddingHorizontal: 16,
    paddingTop: 16,
    paddingBottom: 8,
  },
  resultsCount: {
    fontSize: 14,
    color: colors.textSecondary,
    fontWeight: '500',
  },
  emptyState: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
    marginTop: 100,
  },
  emptyTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: colors.text,
    marginTop: 16,
    marginBottom: 8,
  },
  emptySubtitle: {
    fontSize: 14,
    color: colors.textSecondary,
    textAlign: 'center',
  },
  noticeCard: {
    backgroundColor: colors.white,
    marginHorizontal: 16,
    marginBottom: 16,
    borderRadius: 12,
    padding: 16,
    elevation: 2,
    shadowColor: colors.black,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  noticeHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 12,
  },
  noticeInfo: {
    flex: 1,
    marginRight: 12,
  },
  noticeTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: colors.text,
    marginBottom: 4,
  },
  noticeDate: {
    fontSize: 12,
    color: colors.textMuted,
  },
  statusBadge: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 16,
  },
  statusText: {
    fontSize: 12,
    fontWeight: '600',
    textTransform: 'capitalize',
  },
  noticeDescription: {
    fontSize: 14,
    color: colors.textSecondary,
    lineHeight: 20,
    marginBottom: 12,
  },
  noticeFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  noticeMeta: {
    flex: 1,
  },
  metaItem: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 4,
  },
  metaText: {
    fontSize: 12,
    color: colors.textMuted,
    marginLeft: 6,
  },
  viewButton: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 8,
    backgroundColor: colors.primary,
  },
  viewButtonText: {
    color: colors.white,
    fontSize: 14,
    fontWeight: '600',
  },
});
