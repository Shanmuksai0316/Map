import React from 'react';
import { View, TouchableOpacity, Text, StyleSheet } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import type { BottomTabBarProps } from '@react-navigation/bottom-tabs';
import { TabActions } from '@react-navigation/native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { theme } from '../../shared/theme/theme';

type TabKey = 'Home' | 'Attendance' | 'SocialMedia' | 'Profile';

const TABS: Array<{
  key: TabKey;
  label: string;
  icon: string;
}> = [
  { key: 'Home', label: 'Home', icon: 'home-outline' },
  { key: 'Attendance', label: 'Attendance', icon: 'calendar-outline' },
  { key: 'SocialMedia', label: 'Social Media', icon: 'people-outline' },
  { key: 'Profile', label: 'Profile', icon: 'person-outline' },
];

const FOOTER_ICON_COLOR = '#D79F24';

export const StudentPersistentFooter: React.FC<BottomTabBarProps> = ({
  state,
  descriptors,
  navigation,
}) => {
  const insets = useSafeAreaInsets();
  const activeRouteName = state.routes[state.index]?.name;

  return (
    <View
      style={[
        styles.container,
        {
          paddingBottom: Math.max(insets.bottom + theme.spacing.sm, theme.spacing.md),
        },
      ]}>
      {TABS.map((tab) => {
        const route = state.routes.find((item) => item.name === tab.key);
        if (!route) {
          return null;
        }

        const isActive = activeRouteName === tab.key;
        const options = descriptors[route.key]?.options;

        const handlePress = () => {
          const event = navigation.emit({
            type: 'tabPress',
            target: route.key,
            canPreventDefault: true,
          });

          if (!isActive && !event.defaultPrevented) {
            navigation.dispatch(TabActions.jumpTo(route.name));
          }
        };

        const handleLongPress = () => {
          navigation.emit({
            type: 'tabLongPress',
            target: route.key,
          });
        };

        const extraStyle =
          tab.key === 'Attendance'
            ? styles.tabGapAfterAttendance
            : tab.key === 'SocialMedia'
            ? styles.tabGapAfterSocial
            : null;

        return (
          <TouchableOpacity
            key={tab.key}
            style={[styles.tab, extraStyle]}
            onPress={handlePress}
            onLongPress={handleLongPress}
            hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}
            testID={options?.tabBarButtonTestID ?? `footer-tab-${tab.key.toLowerCase()}`}
            accessibilityLabel={options?.tabBarAccessibilityLabel ?? `${tab.label} tab`}>
            <Ionicons
              name={tab.icon}
              size={24}
              color={FOOTER_ICON_COLOR}
            />
            <Text style={[styles.label, isActive && styles.labelActive]}>{tab.label}</Text>
          </TouchableOpacity>
        );
      })}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'flex-start',
    backgroundColor: theme.colors.white,
    paddingHorizontal: theme.spacing.sm,
    paddingTop: theme.spacing.md,
    borderTopLeftRadius: theme.borderRadius.lg,
    borderTopRightRadius: theme.borderRadius.lg,
    ...theme.shadows.medium,
    shadowOffset: { width: 0, height: -2 },
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: theme.colors.border,
  },
  tab: {
    flex: 1,
    minHeight: 48,
    alignItems: 'center',
    justifyContent: 'center',
  },
  tabGapAfterAttendance: {
    marginRight: 16,
  },
  tabGapAfterSocial: {
    marginRight: 4,
  },
  label: {
    fontSize: theme.fontSize.sm,
    fontWeight: theme.fontWeight.medium,
    color: theme.colors.textMuted,
    marginTop: 6,
  },
  labelActive: {
    color: FOOTER_ICON_COLOR,
  },
});
