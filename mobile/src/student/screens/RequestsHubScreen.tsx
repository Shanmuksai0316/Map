import React, { useCallback } from 'react';
import { View, Text, TouchableOpacity, StyleSheet, ScrollView } from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { SvgXml } from 'react-native-svg';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { theme } from '../../shared/theme/theme';
import { studentRequestsHubIcons } from '../../shared/assets/dashboard-icons/student-requests-hub-icons';

// Removed Sick Leave and Medical from request categories per user feedback
const REQUEST_CATEGORIES = [
  {
    id: 'housekeeping',
    title: 'House Keeping',
    iconSvgXml: studentRequestsHubIcons.housekeeping,
    description: 'Room cleaning, laundry pickup, etc.',
    screen: 'Tickets',
    params: { category: 'housekeeping' },
  },
  {
    id: 'repair',
    title: 'Repair & Maintenance',
    iconSvgXml: studentRequestsHubIcons.repairMaintenance,
    description: 'Electrical, plumbing, furniture repairs',
    screen: 'Tickets',
    params: { category: 'repair_maintenance' },
  },
];

export const RequestsHubScreen = ({ navigation }: any) => {
  const insets = useSafeAreaInsets();

  const handleBack = useCallback(() => {
    if (navigation?.canGoBack?.()) {
      navigation.goBack();
      return;
    }
    navigation?.navigate?.('Home');
  }, [navigation]);

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
            onPress={handleBack}
            accessibilityLabel="Go back">
            <Ionicons name="arrow-back" size={24} color={theme.colors.primary} />
          </TouchableOpacity>
          <Text style={styles.headerTitle}>Requests</Text>
          <View style={styles.headerSpacer} />
        </View>
      </View>

      <ScrollView style={styles.content}>
        {REQUEST_CATEGORIES.map((category) => (
          <TouchableOpacity
            key={category.id}
            style={styles.categoryCard}
            onPress={() => navigation.navigate(category.screen, category.params)}>
            <View style={styles.categoryIcon}>
              <SvgXml xml={category.iconSvgXml} width={34} height={34} />
            </View>
            <View style={styles.categoryContent}>
              <Text style={styles.categoryTitle}>{category.title}</Text>
              <Text style={styles.categoryDescription}>{category.description}</Text>
            </View>
            <Ionicons name="chevron-forward" size={20} color={theme.colors.textMuted} />
          </TouchableOpacity>
        ))}
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
    paddingHorizontal: 20,
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
  categoryCard: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.borderRadius.lg,
    padding: 16,
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 12,
    ...theme.shadows.small,
  },
  categoryIcon: {
    width: 52,
    height: 52,
    borderRadius: 26,
    backgroundColor: `${theme.colors.primary}15`,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 16,
  },
  categoryContent: {
    flex: 1,
  },
  categoryTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.text,
    marginBottom: 4,
  },
  categoryDescription: {
    fontSize: 13,
    color: theme.colors.textSecondary,
  },
});
