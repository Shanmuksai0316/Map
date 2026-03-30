import React from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity, Image } from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { format } from 'date-fns';
import { theme } from '../../shared/theme/theme';
import { GradientButton } from '../../shared/components/GradientButton';

type NoticeParam = {
  id?: string;
  title?: string;
  description?: string;
  category?: string;
  priority?: string;
  published_at?: string;
  expires_at?: string;
  image_url?: string;
  location?: string;
  event_date?: string;
};

export const NoticeDetailScreen = ({ navigation, route }: any) => {
  const insets = useSafeAreaInsets();
  const notice: NoticeParam | undefined = route?.params?.notice;
  const id: string | undefined = route?.params?.id;

  const title = notice?.title || `Notice ${id ?? ''}`.trim();
  const description = notice?.description || 'No description available.';
  const category = notice?.category || 'general';
  const priority = notice?.priority || 'normal';

  const getPriorityColor = (p: string) => {
    switch (p) {
      case 'high':
      case 'urgent':
        return theme.colors.error;
      case 'medium':
        return theme.colors.warning;
      default:
        return theme.colors.primary;
    }
  };

  const formatDate = (value?: string) => {
    if (!value) return null;
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return null;
    return format(d, 'MMM dd, yyyy');
  };

  const HEADER_ROW_HEIGHT = 52;
  const HEADER_PADDING_TOP = Math.max(insets.top, 10);
  const HEADER_PADDING_BOTTOM = 6;

  return (
    <View style={styles.container}>
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
          <GradientButton style={styles.backButton} onPress={() => navigation.goBack()} accessibilityLabel="Go back">
            <Ionicons name="arrow-back" size={24} color={theme.colors.primary} />
          </GradientButton>
          <Text style={styles.headerTitle} numberOfLines={1}>{title}</Text>
          <View style={styles.headerSpacer} />
        </View>
      </View>

      <ScrollView style={styles.content} contentContainerStyle={styles.contentContainer}>
        {notice?.image_url ? (
          <Image source={{ uri: notice.image_url }} style={styles.image} resizeMode="cover" />
        ) : null}

        <View style={styles.metaRow}>
          <View style={[styles.badge, { backgroundColor: `${getPriorityColor(priority)}20` }]}>
            <Text style={[styles.badgeText, { color: getPriorityColor(priority) }]}>
              {String(category).toUpperCase()}
            </Text>
          </View>
          <View style={[styles.badge, { backgroundColor: `${getPriorityColor(priority)}20` }]}>
            <Text style={[styles.badgeText, { color: getPriorityColor(priority) }]}>
              {String(priority).toUpperCase()}
            </Text>
          </View>
        </View>

        <Text style={styles.title}>{title}</Text>

        {notice?.event_date ? (
          <View style={styles.infoRow}>
            <Ionicons name="calendar-outline" size={16} color={theme.colors.textSecondary} />
            <Text style={styles.infoText}>{formatDate(notice.event_date) ?? notice.event_date}</Text>
          </View>
        ) : null}

        {notice?.location ? (
          <View style={styles.infoRow}>
            <Ionicons name="location-outline" size={16} color={theme.colors.textSecondary} />
            <Text style={styles.infoText}>{notice.location}</Text>
          </View>
        ) : null}

        {notice?.published_at ? (
          <View style={styles.infoRow}>
            <Ionicons name="time-outline" size={16} color={theme.colors.textSecondary} />
            <Text style={styles.infoText}>Published: {formatDate(notice.published_at) ?? notice.published_at}</Text>
          </View>
        ) : null}

        {notice?.expires_at ? (
          <View style={styles.infoRow}>
            <Ionicons name="alert-circle-outline" size={16} color={theme.colors.textSecondary} />
            <Text style={styles.infoText}>Expires: {formatDate(notice.expires_at) ?? notice.expires_at}</Text>
          </View>
        ) : null}

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Details</Text>
          <Text style={styles.description}>{description}</Text>
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
    paddingHorizontal: 16,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: theme.colors.border,
    ...theme.shadows.medium,
  },
  headerRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  backButton: {
    padding: 8,
    borderRadius: 999,
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
  },
  contentContainer: {
    padding: 16,
    paddingBottom: 28,
  },
  image: {
    width: '100%',
    height: 220,
    borderRadius: theme.borderRadius.lg,
    marginBottom: 16,
  },
  metaRow: {
    flexDirection: 'row',
    gap: 8,
    marginBottom: 10,
    flexWrap: 'wrap',
  },
  badge: {
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 999,
  },
  badgeText: {
    fontSize: 12,
    fontWeight: '700',
  },
  title: {
    fontSize: 20,
    fontWeight: '800',
    color: theme.colors.text,
    marginBottom: 12,
  },
  infoRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 6,
  },
  infoText: {
    fontSize: 13,
    color: theme.colors.textSecondary,
  },
  section: {
    marginTop: 16,
    backgroundColor: theme.colors.card,
    borderRadius: theme.borderRadius.lg,
    padding: 14,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  sectionTitle: {
    fontSize: 14,
    fontWeight: '800',
    color: theme.colors.text,
    marginBottom: 8,
  },
  description: {
    fontSize: 14,
    color: theme.colors.textSecondary,
    lineHeight: 20,
  },
});
