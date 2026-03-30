import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  Image,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { format } from 'date-fns';
import { theme } from '../../shared/theme/theme';
import { apiService } from '../../shared/services/api.service';
import { APP_CONFIG } from '../../shared/config/app.config';
import { EmptyState } from '../../shared/components';
import { GradientButton } from '../../shared/components/GradientButton';

interface Notice {
  id: string;
  title: string;
  description: string;
  category: string;
  priority: string;
  published_at: string;
  expires_at?: string;
  image_url?: string;
  location?: string;
  event_date?: string;
}

export const CommBoxScreen = ({ navigation }: any) => {
  const insets = useSafeAreaInsets();
  const [notices, setNotices] = useState<Notice[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const fetchNotices = async () => {
    try {
      const response = await apiService.get<{ data: Notice[] }>(
        APP_CONFIG.ENDPOINTS.NOTICES
      );
      setNotices(response.data || []);
    } catch (err: any) {
      // 403 or other errors: show empty list instead of failing (e.g. staff may not have notices access)
      if (err?.response?.status !== 403) {
        console.error('Error fetching notices:', err);
      }
      setNotices([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchNotices();
  }, []);

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    fetchNotices();
  }, []);

  const getCategoryIcon = (category: string) => {
    switch (category) {
      case 'event':
      case 'events':
        return 'calendar';
      case 'urgent':
        return 'alert-circle';
      case 'announcement':
        return 'megaphone';
      default:
        return 'document-text';
    }
  };

  const getCategoryColor = (priority: string) => {
    switch (priority) {
      case 'high':
      case 'urgent':
        return theme.colors.error;
      case 'medium':
        return theme.colors.warning;
      default:
        return theme.colors.primary;
    }
  };

  const HEADER_ROW_HEIGHT = 52;
  const HEADER_PADDING_TOP = Math.max(insets.top, 10);
  const HEADER_PADDING_BOTTOM = 6;

  return (
    <View style={styles.container}>
      {/* Header */}
      <View
        style={[
          styles.header,
          {
            paddingTop: HEADER_PADDING_TOP,
            paddingBottom: HEADER_PADDING_BOTTOM,
            minHeight: HEADER_PADDING_TOP + HEADER_ROW_HEIGHT + HEADER_PADDING_BOTTOM,
          },
        ]}>
        <View style={[styles.headerRow, { height: HEADER_ROW_HEIGHT }]}>
          <TouchableOpacity
            style={styles.backButton}
            onPress={() => (navigation?.canGoBack?.() ? navigation.goBack() : navigation.navigate('Home'))}
            accessibilityLabel="Go back">
            <Ionicons name="arrow-back" size={24} color={theme.colors.primary} />
          </TouchableOpacity>
          <Text style={styles.headerTitle}>Notice Board</Text>
          <View style={styles.headerSpacer} />
        </View>
      </View>

      <ScrollView
        style={styles.content}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>
        {loading ? (
          <View style={styles.loadingContainer}>
            <Text>Loading...</Text>
          </View>
        ) : notices.length === 0 ? (
          <EmptyState
            variant="no-data"
            title="No Announcements"
            subtitle="There are no announcements or events at this time"
          />
        ) : (
          <>
            {notices.map((notice) => (
            <TouchableOpacity
              key={notice.id}
              style={styles.noticeCard}
              onPress={() => navigation.navigate('NoticeDetail', { id: notice.id, notice })}>
              {notice.image_url && (
                <Image
                  source={{ uri: notice.image_url }}
                  style={styles.noticeImage}
                  resizeMode="cover"
                />
              )}
              <View style={styles.noticeContent}>
                <View style={styles.noticeHeader}>
                  <View
                    style={[
                      styles.categoryBadge,
                      { backgroundColor: `${getCategoryColor(notice.priority)}20` },
                    ]}>
                    <Ionicons
                      name={getCategoryIcon(notice.category)}
                      size={14}
                      color={getCategoryColor(notice.priority)}
                    />
                    <Text
                      style={[
                        styles.categoryText,
                        { color: getCategoryColor(notice.priority) },
                      ]}>
                      {notice.category || 'General'}
                    </Text>
                  </View>
                </View>

                <Text style={styles.noticeTitle}>{notice.title}</Text>

                {notice.event_date && (
                  <View style={styles.eventInfo}>
                    <Ionicons name="calendar-outline" size={14} color={theme.colors.textSecondary} />
                    <Text style={styles.eventInfoText}>
                      {format(new Date(notice.event_date), 'MMM dd, yyyy')}
                    </Text>
                  </View>
                )}

                {notice.location && (
                  <View style={styles.eventInfo}>
                    <Ionicons name="location-outline" size={14} color={theme.colors.textSecondary} />
                    <Text style={styles.eventInfoText}>{notice.location}</Text>
                  </View>
                )}

                <Text style={styles.noticeDescription} numberOfLines={2}>
                  {notice.description}
                </Text>

                <View style={styles.noticeFooter}>
                  <Text style={styles.publishedAt}>
                    {format(new Date(notice.published_at), 'MMM dd, yyyy')}
                  </Text>
                  <GradientButton style={styles.detailsButton}>
                    <Text style={styles.detailsButtonText}>Details</Text>
                    <Ionicons name="chevron-forward" size={14} color={theme.colors.primary} />
                  </GradientButton>
                </View>
              </View>
            </TouchableOpacity>
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
    backgroundColor: theme.colors.white,
  },
  header: {
    backgroundColor: theme.colors.white,
    paddingHorizontal: 16,
    ...theme.shadows.medium,
  },
  headerRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  backButton: {
    padding: 8,
  },
  headerTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: theme.colors.primary,
  },
  headerSpacer: {
    width: 40,
  },
  content: {
    flex: 1,
    padding: 16,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingTop: 40,
  },
  infoBanner: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    backgroundColor: `${theme.colors.primary}10`,
    padding: 12,
    borderRadius: theme.borderRadius.md,
    marginBottom: 16,
    borderLeftWidth: 4,
    borderLeftColor: theme.colors.primary,
    gap: 10,
  },
  infoBannerText: {
    flex: 1,
    fontSize: 13,
    color: theme.colors.text,
    lineHeight: 18,
  },
  infoBannerLink: {
    color: theme.colors.primary,
    fontWeight: '600',
  },
  noticeCard: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.borderRadius.lg,
    marginBottom: 16,
    overflow: 'hidden',
    ...theme.shadows.small,
  },
  noticeImage: {
    width: '100%',
    height: 160,
  },
  noticeContent: {
    padding: 16,
  },
  noticeHeader: {
    flexDirection: 'row',
    marginBottom: 8,
  },
  categoryBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 12,
    gap: 4,
  },
  categoryText: {
    fontSize: 12,
    fontWeight: '600',
    textTransform: 'capitalize',
  },
  noticeTitle: {
    fontSize: 17,
    fontWeight: '700',
    color: theme.colors.text,
    marginBottom: 8,
  },
  eventInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginBottom: 6,
  },
  eventInfoText: {
    fontSize: 13,
    color: theme.colors.textSecondary,
  },
  noticeDescription: {
    fontSize: 14,
    color: theme.colors.textSecondary,
    lineHeight: 20,
    marginTop: 4,
  },
  noticeFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: 12,
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: theme.colors.border,
  },
  publishedAt: {
    fontSize: 12,
    color: theme.colors.textMuted,
  },
  detailsButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 999,
  },
  detailsButtonText: {
    fontSize: 14,
    fontWeight: '600',
    color: theme.colors.primary,
  },
});
