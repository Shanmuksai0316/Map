import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  Alert,
  Modal,
} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useAuthStore } from '../../store/auth.store';
import { apiService } from '../../services/api.service';
import { Notice } from '../../types';
import { APP_CONFIG } from '../../config/app.config';
import { format } from 'date-fns';
import { theme } from '../../theme/theme';
import { errorHandler } from '../../utils/errorHandler';
import { ErrorState, LoadingState } from '../../components';

export const NoticesScreen = ({ navigation }: any) => {
  const { user } = useAuthStore();
  const [notices, setNotices] = useState<Notice[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [selectedNotice, setSelectedNotice] = useState<Notice | null>(null);
  const [showDetailModal, setShowDetailModal] = useState(false);
  const [filter, setFilter] = useState<'all' | 'general' | 'urgent' | 'event'>('all');
  const [error, setError] = useState<string | null>(null);

  const fetchNotices = async () => {
    try {
      setError(null);
      setLoading(true);
      const response = await apiService.get<{ data: Notice[] }>(
        APP_CONFIG.ENDPOINTS.NOTICES
      );
      setNotices(response.data || []);
    } catch (err) {
      const errorDetails = errorHandler.handleError(err);
      setError(errorDetails.message);
      setNotices([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchNotices();
  }, []);

  const onRefresh = () => {
    setRefreshing(true);
    fetchNotices();
  };

  const handleNoticePress = (notice: Notice) => {
    setSelectedNotice(notice);
    setShowDetailModal(true);
  };

  const getNoticeTypeColor = (type: string) => {
    switch (type) {
      case 'urgent':
        return theme.colors.danger;
      case 'event':
        return theme.colors.info;
      case 'general':
        return theme.colors.success;
      default:
        return theme.colors.textMuted;
    }
  };

  const getNoticeTypeIcon = (type: string) => {
    switch (type) {
      case 'urgent':
        return 'alert-circle';
      case 'event':
        return 'calendar';
      case 'general':
        return 'megaphone';
      default:
        return 'document-text-outline';
    }
  };

  const isNoticeExpired = (notice: Notice) => {
    if (!notice.expires_at) return false;
    return new Date(notice.expires_at) < new Date();
  };

  const filteredNotices = notices.filter((notice) => {
    if (filter === 'all') return true;
    return notice.type === filter;
  }).filter((notice) => !isNoticeExpired(notice));

  const renderNoticeCard = (notice: Notice) => (
    <TouchableOpacity
      key={notice.id}
      style={[
        styles.noticeCard,
        { borderLeftColor: getNoticeTypeColor(notice.type), borderLeftWidth: 4 },
      ]}
      onPress={() => handleNoticePress(notice)}>
      <View style={styles.noticeHeader}>
        <View style={styles.noticeTypeContainer}>
          <Ionicons
            name={getNoticeTypeIcon(notice.type)}
            size={24}
            color={theme.colors.white}
            style={styles.noticeIcon}
          />
          <View
            style={[
              styles.noticeTypeBadge,
              { backgroundColor: getNoticeTypeColor(notice.type) },
            ]}>
            <Text style={styles.noticeTypeText}>{notice.type.toUpperCase()}</Text>
          </View>
        </View>
        <Text style={styles.noticeDate}>
          {format(new Date(notice.created_at), 'MMM dd, yyyy')}
        </Text>
      </View>

      <Text style={styles.noticeTitle}>{notice.title}</Text>
      <Text style={styles.noticeDescription} numberOfLines={2}>
        {notice.description}
      </Text>

      <View style={styles.noticeFooter}>
        <Text style={styles.noticeAuthor}>By: {notice.created_by}</Text>
        {notice.expires_at && (
          <Text style={styles.noticeExpiry}>
            Expires: {format(new Date(notice.expires_at), 'MMM dd')}
          </Text>
        )}
      </View>
    </TouchableOpacity>
  );

  return (
    <View style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity
          style={styles.backButton}
          onPress={() => navigation.goBack()}>
          <Ionicons name="arrow-back" size={20} color={theme.colors.white} />
          <Text style={styles.backButtonText}>Back</Text>
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Notices</Text>
        <View style={styles.placeholder} />
      </View>

      {/* Filter Tabs */}
      <View style={styles.filterContainer}>
        <ScrollView horizontal showsHorizontalScrollIndicator={false}>
          <TouchableOpacity
            style={[styles.filterTab, filter === 'all' && styles.filterTabActive]}
            onPress={() => setFilter('all')}>
            <Ionicons
              name="apps-outline"
              size={16}
              color={filter === 'all' ? theme.colors.white : theme.colors.textSecondary}
              style={styles.filterIcon}
            />
            <Text
              style={[
                styles.filterTabText,
                filter === 'all' && styles.filterTabTextActive,
              ]}>
              All
            </Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={[styles.filterTab, filter === 'urgent' && styles.filterTabActive]}
            onPress={() => setFilter('urgent')}>
            <Ionicons
              name="alert-circle-outline"
              size={16}
              color={filter === 'urgent' ? theme.colors.white : theme.colors.textSecondary}
              style={styles.filterIcon}
            />
            <Text
              style={[
                styles.filterTabText,
                filter === 'urgent' && styles.filterTabTextActive,
              ]}>
              Urgent
            </Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={[styles.filterTab, filter === 'event' && styles.filterTabActive]}
            onPress={() => setFilter('event')}>
            <Ionicons
              name="calendar-outline"
              size={16}
              color={filter === 'event' ? theme.colors.white : theme.colors.textSecondary}
              style={styles.filterIcon}
            />
            <Text
              style={[
                styles.filterTabText,
                filter === 'event' && styles.filterTabTextActive,
              ]}>
              Events
            </Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={[styles.filterTab, filter === 'general' && styles.filterTabActive]}
            onPress={() => setFilter('general')}>
            <Ionicons
              name="megaphone-outline"
              size={16}
              color={filter === 'general' ? theme.colors.white : theme.colors.textSecondary}
              style={styles.filterIcon}
            />
            <Text
              style={[
                styles.filterTabText,
                filter === 'general' && styles.filterTabTextActive,
              ]}>
              General
            </Text>
          </TouchableOpacity>
        </ScrollView>
      </View>

      {/* Notices List */}
      <ScrollView
        style={styles.content}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>
        {loading ? (
          <LoadingState message="Loading notices..." />
        ) : error ? (
          <ErrorState error={error} onRetry={fetchNotices} />
        ) : filteredNotices.length === 0 ? (
          <View style={styles.emptyState}>
            <Ionicons
              name="mail-open-outline"
              size={64}
              color={theme.colors.textSecondary}
              style={styles.emptyIcon}
            />
            <Text style={styles.emptyTitle}>No Notices</Text>
            <Text style={styles.emptySubtitle}>
              {filter === 'all'
                ? 'No notices available at the moment'
                : `No ${filter} notices available`}
            </Text>
          </View>
        ) : (
          filteredNotices.map(renderNoticeCard)
        )}
      </ScrollView>

      {/* Notice Detail Modal */}
      <Modal
        visible={showDetailModal}
        animationType="slide"
        presentationStyle="pageSheet"
        onRequestClose={() => setShowDetailModal(false)}>
        {selectedNotice && (
          <View style={styles.modalContainer}>
            <View style={styles.modalHeader}>
              <View style={styles.modalTypeContainer}>
                <Ionicons
                  name={getNoticeTypeIcon(selectedNotice.type)}
                  size={32}
                  color={theme.colors.white}
                  style={styles.modalIcon}
                />
                <View
                  style={[
                    styles.modalTypeBadge,
                    { backgroundColor: getNoticeTypeColor(selectedNotice.type) },
                  ]}>
                  <Text style={styles.modalTypeText}>
                    {selectedNotice.type.toUpperCase()}
                  </Text>
                </View>
              </View>
              <TouchableOpacity onPress={() => setShowDetailModal(false)}>
                <Ionicons name="close" size={28} color={theme.colors.textSecondary} />
              </TouchableOpacity>
            </View>

            <ScrollView style={styles.modalContent}>
              <Text style={styles.modalTitle}>{selectedNotice.title}</Text>

              <View style={styles.modalMeta}>
                <View style={styles.modalMetaRow}>
                  <Text style={styles.modalMetaLabel}>Posted by:</Text>
                  <Text style={styles.modalMetaValue}>
                    {selectedNotice.created_by}
                  </Text>
                </View>
                <View style={styles.modalMetaRow}>
                  <Text style={styles.modalMetaLabel}>Date:</Text>
                  <Text style={styles.modalMetaValue}>
                    {format(new Date(selectedNotice.created_at), 'MMMM dd, yyyy HH:mm')}
                  </Text>
                </View>
                {selectedNotice.expires_at && (
                  <View style={styles.modalMetaRow}>
                    <Text style={styles.modalMetaLabel}>Expires:</Text>
                    <Text style={styles.modalMetaValue}>
                      {format(new Date(selectedNotice.expires_at), 'MMMM dd, yyyy')}
                    </Text>
                  </View>
                )}
                <View style={styles.modalMetaRow}>
                  <Text style={styles.modalMetaLabel}>Audience:</Text>
                  <Text style={styles.modalMetaValue}>
                    {selectedNotice.target_audience === 'all'
                      ? 'All Students & Staff'
                      : selectedNotice.target_audience === 'students'
                      ? 'Students Only'
                      : 'Staff Only'}
                  </Text>
                </View>
              </View>

              <View style={styles.modalDescriptionContainer}>
                <Text style={styles.modalDescriptionLabel}>Description:</Text>
                <Text style={styles.modalDescription}>
                  {selectedNotice.description}
                </Text>
              </View>
            </ScrollView>
          </View>
        )}
      </Modal>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  header: {
    backgroundColor: theme.colors.primary,
    padding: theme.spacing.lg,
    paddingTop: theme.spacing.xl * 2,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  backButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.xs,
    padding: theme.spacing.sm,
  },
  backButtonText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
  },
  headerTitle: {
    color: theme.colors.white,
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
  },
  placeholder: {
    width: theme.spacing.xxl * 1.5,
  },
  filterContainer: {
    backgroundColor: theme.colors.card,
    paddingVertical: theme.spacing.sm,
    paddingHorizontal: theme.spacing.md,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.divider,
  },
  filterTab: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.xs,
    paddingHorizontal: theme.spacing.md,
    paddingVertical: theme.spacing.sm,
    marginRight: theme.spacing.sm,
    borderRadius: theme.borderRadius.xl,
    backgroundColor: theme.colors.surface,
  },
  filterIcon: {
    marginTop: 1,
  },
  filterTabActive: {
    backgroundColor: theme.colors.primary,
  },
  filterTabText: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
    fontWeight: theme.fontWeight.medium,
  },
  filterTabTextActive: {
    color: theme.colors.white,
    fontWeight: theme.fontWeight.semibold,
  },
  content: {
    flex: 1,
    padding: theme.spacing.md,
  },
  emptyState: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: theme.spacing.xxl,
  },
  emptyIcon: {
    marginBottom: theme.spacing.md,
  },
  emptyTitle: {
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
    marginBottom: theme.spacing.sm,
  },
  emptySubtitle: {
    fontSize: theme.fontSize.md,
    color: theme.colors.textSecondary,
    textAlign: 'center',
  },
  noticeCard: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.borderRadius.lg,
    padding: theme.spacing.md,
    marginBottom: theme.spacing.md,
    borderLeftWidth: 4,
    borderLeftColor: theme.colors.primary,
    borderWidth: 1,
    borderColor: theme.colors.border,
    ...theme.shadows.small,
  },
  noticeHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: theme.spacing.sm,
  },
  noticeTypeContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.xs,
  },
  noticeIcon: {
    marginTop: 1,
  },
  noticeTypeBadge: {
    paddingHorizontal: theme.spacing.sm,
    paddingVertical: theme.spacing.xs,
    borderRadius: theme.borderRadius.xl,
  },
  noticeTypeText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.xs,
    fontWeight: theme.fontWeight.semibold,
  },
  noticeDate: {
    fontSize: theme.fontSize.xs,
    color: theme.colors.textMuted,
  },
  noticeTitle: {
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
    marginBottom: theme.spacing.sm,
  },
  noticeDescription: {
    fontSize: theme.fontSize.md,
    color: theme.colors.textSecondary,
    lineHeight: 20,
    marginBottom: theme.spacing.sm,
  },
  noticeFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingTop: theme.spacing.sm,
    borderTopWidth: 1,
    borderTopColor: theme.colors.divider,
  },
  noticeAuthor: {
    fontSize: theme.fontSize.xs,
    color: theme.colors.textSecondary,
    fontWeight: theme.fontWeight.medium,
  },
  noticeExpiry: {
    fontSize: theme.fontSize.xs,
    color: theme.colors.warning,
    fontWeight: theme.fontWeight.medium,
  },
  modalContainer: {
    flex: 1,
    backgroundColor: theme.colors.card,
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: theme.spacing.lg,
    paddingTop: theme.spacing.xl * 2,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.divider,
  },
  modalTypeContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.sm,
  },
  modalIcon: {
    fontSize: theme.fontSize.xxxl,
  },
  modalTypeBadge: {
    paddingHorizontal: theme.spacing.md,
    paddingVertical: theme.spacing.xs,
    borderRadius: theme.borderRadius.xl,
  },
  modalTypeText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.xs,
    fontWeight: theme.fontWeight.semibold,
  },
  modalClose: {
    fontSize: theme.fontSize.xxxl,
    color: theme.colors.textSecondary,
  },
  modalContent: {
    flex: 1,
    padding: theme.spacing.lg,
  },
  modalTitle: {
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
    marginBottom: theme.spacing.lg,
    lineHeight: 32,
  },
  modalMeta: {
    backgroundColor: theme.colors.surface,
    padding: theme.spacing.md,
    borderRadius: theme.borderRadius.lg,
    marginBottom: theme.spacing.lg,
  },
  modalMetaRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: theme.spacing.xs,
  },
  modalMetaLabel: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
    fontWeight: theme.fontWeight.medium,
  },
  modalMetaValue: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.text,
    fontWeight: theme.fontWeight.semibold,
    flex: 1,
    textAlign: 'right',
    marginLeft: theme.spacing.md,
  },
  modalDescriptionContainer: {
    marginBottom: theme.spacing.lg,
  },
  modalDescriptionLabel: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
    marginBottom: theme.spacing.sm,
  },
  modalDescription: {
    fontSize: theme.fontSize.md,
    color: theme.colors.textSecondary,
    lineHeight: 24,
  },
});

