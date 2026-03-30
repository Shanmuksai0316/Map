/**
 * StaffDashboardHeader
 *
 * Shared header component used across all staff role dashboards (Security Guard,
 * Warden, HK Supervisor, Rector, etc.). Layout:
 *   Left   -- Karta logo (karta-logo.png)
 *   Center -- "Karta" text in brand accent using the EthnocentricRg custom font
 *   Right  -- Notification bell with unread-count badge (NotificationBellWithBadge)
 *             and an optional kebab overflow menu
 *
 * Font dependency:
 *   - Custom font EthnocentricRg must be linked on both platforms.
 *
 * Props:
 *   - title / subtitle are accepted but currently unused (reserved for future
 *     role-specific text if needed).
 *   - onNotificationsPress -- callback for bell tap
 *   - notificationCount   -- badge number
 *   - menuOptions         -- items for the kebab menu (logout, settings, etc.)
 */
import React from 'react';
import { View } from 'react-native';
import { KebabMenu } from '../../shared/components/shared/KebabMenu';
import { StaffScreenHeader } from './StaffScreenHeader';

type HeaderMenuOption = {
  label: string;
  icon: string;
  onPress: () => void;
  destructive?: boolean;
};

interface StaffDashboardHeaderProps {
  /** Role/screen title (e.g. "Security Guard", "Karta" in Figma) */
  title: string;
  subtitle?: string;
  onNotificationsPress?: () => void;
  notificationCount?: number;
  menuOptions?: HeaderMenuOption[];
}

/**
 * Staff app header: same for all roles.
 * Left: Karta logo, Center: brand text, Right: bell.
 */
export const StaffDashboardHeader: React.FC<StaffDashboardHeaderProps> = ({
  title,
  onNotificationsPress,
  notificationCount = 0,
  menuOptions,
}) => {
  return (
    <StaffScreenHeader
      title="Kartā"
      variant="brand"
      showBack={false}
      notificationCount={notificationCount}
      onNotificationsPress={onNotificationsPress}
      showBell={Boolean(onNotificationsPress)}
      rightSlot={menuOptions?.length ? <KebabMenu options={menuOptions} /> : undefined}
    />
  );
};
