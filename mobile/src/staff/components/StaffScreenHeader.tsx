import React from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import { SvgXml } from 'react-native-svg';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { useRoute } from '@react-navigation/native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { NotificationBellWithBadge } from '../../shared/components/NotificationBellWithBadge';
import { RADIAL_GRADIENT_ICON_COLOR } from '../../shared/components/RadialGradientButton';
import { mapLogoColorSvgXml } from '../../shared/assets/map-logo-color';
import { theme } from '../../shared/theme/theme';

interface StaffScreenHeaderProps {
  title?: string;
  onBack?: () => void;
  showBack?: boolean;
  notificationCount?: number;
  onNotificationsPress?: () => void;
  showBell?: boolean;
  rightSlot?: React.ReactNode;
  /**
   * variant:
   * - 'brand'   -> MAP logo + KARTA + accent underline (used on dashboards)
   * - 'minimal' -> text-only header (detail / list screens like Requests)
   * - 'profile' -> text-only "Profile", no logo, no KARTA (profile screens)
   */
  variant?: 'brand' | 'minimal' | 'profile';
  /** @deprecated Branding is now controlled only by `variant` */
  showLogo?: boolean;
}

const HEADER_CONTENT_HEIGHT = 64;
const BRAND_HEADER_HEIGHT = 84;
const STAFF_BELL_COLOR = RADIAL_GRADIENT_ICON_COLOR;

export const StaffScreenHeader: React.FC<StaffScreenHeaderProps> = ({
  title,
  onBack,
  showBack = true,
  notificationCount = 0,
  onNotificationsPress,
  showBell = true,
  rightSlot,
  variant = 'minimal',
  showLogo,
}) => {
  const route = useRoute();
  const insets = useSafeAreaInsets();
  const paddingTop = Math.max(insets.top, 6);
  const brandPaddingTop = Math.max(insets.top + 8, 24);
  // Branded MAP + KARTA header is reserved for dashboard/root screens only.
  // Any screen with a back button must render minimal style.
  const isBrandVariant = variant === 'brand' && !showBack;

  const derivedRouteTitle = String(route?.name ?? '')
    .replace(/([a-z])([A-Z])/g, '$1 $2')
    .replace(/_/g, ' ')
    .replace(/\b(screen|stack|navigator)\b/gi, '')
    .replace(/\s+/g, ' ')
    .trim();

  const resolvedTitle = (() => {
    const trimmed = title?.trim();
    if (trimmed) return trimmed;
    if (isBrandVariant) return 'Kartā';
    return derivedRouteTitle || '';
  })();

  // Non-home screens must never show branded MAP/KARTA chrome.
  const shouldShowLogo = isBrandVariant && showLogo !== false;

  const containerStyle = isBrandVariant
    ? [styles.brandContainer, { paddingTop: brandPaddingTop }]
    : [styles.container, { paddingTop, minHeight: paddingTop + HEADER_CONTENT_HEIGHT }];
  const leftStyle = isBrandVariant ? styles.brandLeft : styles.left;
  const centerStyle = isBrandVariant ? styles.brandCenter : styles.center;
  const rightStyle = isBrandVariant ? styles.brandRight : styles.right;
  const logoWrapStyle = isBrandVariant ? styles.brandLogoWrap : styles.logoWrap;
  const titleStyle = isBrandVariant ? styles.brandTitle : styles.title;
  const bellSize = isBrandVariant ? 24 : 22;

  return (
    <View style={containerStyle}>
      <View style={leftStyle}>
        {showBack && onBack ? (
          <TouchableOpacity
            onPress={onBack}
            style={styles.backButton}
            accessibilityLabel="Go back">
            <Icon name="arrow-left" size={22} color={theme.colors.primary} />
          </TouchableOpacity>
        ) : null}
        {shouldShowLogo && (
          <View style={logoWrapStyle}>
            <SvgXml xml={mapLogoColorSvgXml} width={88} height={56} />
          </View>
        )}
      </View>

      <View style={centerStyle}>
        {shouldShowLogo ? (
          <View style={styles.titleWrapper}>
            <Text style={titleStyle} numberOfLines={1} ellipsizeMode="tail">
              {resolvedTitle}
            </Text>
            {!isBrandVariant && <View style={styles.titleAccent} />}
          </View>
        ) : (
          <Text style={styles.minimalTitle} numberOfLines={1} ellipsizeMode="tail">
            {resolvedTitle}
          </Text>
        )}
      </View>

      <View style={rightStyle}>
        {showBell && onNotificationsPress ? (
          <NotificationBellWithBadge
            count={notificationCount}
            onPress={onNotificationsPress}
            color={STAFF_BELL_COLOR}
            size={bellSize}
          />
        ) : null}
        {rightSlot}
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    backgroundColor: theme.colors.white,
    paddingHorizontal: 20,
    paddingBottom: 6,
    flexDirection: 'row',
    alignItems: 'center',
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
    ...theme.shadows.small,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.08,
    shadowRadius: 6,
    elevation: 2,
  },
  brandContainer: {
    backgroundColor: theme.colors.white,
    paddingHorizontal: 20,
    paddingBottom: 16,
    minHeight: BRAND_HEADER_HEIGHT,
    flexDirection: 'row',
    alignItems: 'center',
    ...theme.shadows.medium,
  },
  left: {
    flexDirection: 'row',
    alignItems: 'center',
    minWidth: 48,
  },
  brandLeft: {
    width: 60,
    justifyContent: 'flex-end',
    alignItems: 'flex-start',
  },
  backButton: {
    width: 40,
    height: 40,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 4,
  },
  logoWrap: {
    width: 48,
    height: 48,
    alignItems: 'center',
    justifyContent: 'center',
  },
  brandLogoWrap: {
    marginLeft: -8,
    marginTop: 6,
  },
  center: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 8,
  },
  brandCenter: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingLeft: 8,
  },
  titleWrapper: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  title: {
    fontFamily: 'EthnocentricRg',
    fontSize: theme.fontSize.lg,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.primary,
  },
  brandTitle: {
    fontFamily: 'EthnocentricRg',
    fontSize: 18,
    color: theme.colors.primary,
  },
  minimalTitle: {
    fontSize: theme.fontSize.lg,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.primary,
  },
  titleAccent: {
    marginTop: 2,
    width: 32,
    height: 2,
    borderRadius: 1,
    backgroundColor: STAFF_BELL_COLOR,
  },
  right: {
    minWidth: 48,
    alignItems: 'flex-end',
    justifyContent: 'center',
    flexDirection: 'row',
  },
  brandRight: {
    width: 60,
    justifyContent: 'flex-end',
    alignItems: 'flex-end',
    flexDirection: 'row',
  },
});
