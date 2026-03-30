import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity, Linking, Alert, ScrollView, Share } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { theme } from '../../shared/theme/theme';
import { getStudentAppSharePayload } from '../../shared/constants/share-app.constants';

export const SocialMediaScreen = ({ navigation }: any) => {
  const insets = useSafeAreaInsets();
  const HEADER_ROW_HEIGHT = 52;
  const HEADER_PADDING_TOP = Math.max(insets.top, 10);
  const HEADER_PADDING_BOTTOM = 6;
  const links: { id: string; label: string; icon: string; color: string; url?: string }[] = [
    {
      id: 'facebook',
      label: 'Facebook',
      icon: 'logo-facebook',
      color: '#1877F2',
      url: 'https://www.facebook.com/omapservices',
    },
    {
      id: 'instagram',
      label: 'Instagram',
      icon: 'logo-instagram',
      color: '#E4405F',
      url: 'https://www.instagram.com/omapservices',
    },
    {
      id: 'twitter',
      label: 'Twitter / X',
      icon: 'logo-twitter',
      color: '#1DA1F2',
      url: 'https://twitter.com/omapservices',
    },
    {
      id: 'linkedin',
      label: 'LinkedIn',
      icon: 'logo-linkedin',
      color: '#0A66C2',
      url: 'https://www.linkedin.com/company/omapservices',
    },
    {
      id: 'share',
      label: 'Share this application',
      icon: 'share-outline',
      color: theme.colors.primary,
    },
  ];

  const handlePress = async (item: (typeof links)[number]) => {
    if (item.id === 'share') {
      try {
        const { title, message, url } = getStudentAppSharePayload();
        await Share.share({ title, message, url });
      } catch (error) {
        console.error('Error sharing app:', error);
      }
      return;
    }

    if (!item.url) return;

    try {
      await Linking.openURL(item.url);
    } catch {
      Alert.alert('Error', 'Could not open this link right now.');
    }
  };

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
          <TouchableOpacity
            style={styles.backButton}
            onPress={() => (navigation?.canGoBack?.() ? navigation.goBack() : navigation.navigate('Home'))}
            accessibilityLabel="Go back">
            <Ionicons name="arrow-back" size={24} color={theme.colors.primary} />
          </TouchableOpacity>
          <Text style={styles.headerTitle}>Social Media</Text>
          <View style={styles.headerSpacer} />
        </View>
      </View>

      <ScrollView contentContainerStyle={styles.content}>
        <Text style={styles.title}>Stay connected</Text>
        <Text style={styles.subtitle}>
          Follow us on social media and share the app with your friends.
        </Text>

        <View style={styles.list}>
          {links.map((item) => (
            <TouchableOpacity
              key={item.id}
              style={styles.row}
              onPress={() => handlePress(item)}>
              <View style={[styles.iconCircle, { backgroundColor: `${item.color}20` }]}>
                <Ionicons name={item.icon} size={22} color={item.color} />
              </View>
              <Text style={styles.label}>{item.label}</Text>
              <Ionicons
                name="chevron-forward"
                size={20}
                color={theme.colors.textMuted}
              />
            </TouchableOpacity>
          ))}
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
    paddingHorizontal: 20,
    ...theme.shadows.medium,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  backButton: {
    padding: 8,
  },
  headerTitle: {
    flex: 1,
    textAlign: 'center',
    fontSize: theme.fontSize.lg,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.primary,
  },
  headerSpacer: {
    width: 40,
  },
  content: {
    padding: theme.spacing.lg,
    paddingBottom: theme.spacing.xl,
  },
  title: {
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.primary,
    marginBottom: theme.spacing.xs,
  },
  subtitle: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
    marginBottom: theme.spacing.lg,
  },
  list: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.borderRadius.lg,
    ...theme.shadows.medium,
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: theme.spacing.lg,
    paddingVertical: theme.spacing.md,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.divider,
  },
  iconCircle: {
    width: 40,
    height: 40,
    borderRadius: 20,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: theme.spacing.md,
  },
  label: {
    flex: 1,
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
  },
});
