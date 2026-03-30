import React from 'react';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { theme } from '../theme/theme';
import { AnimatedTabIcon } from '../components/AnimatedTabIcon';
import { GuardDashboard } from '../screens/staff/GuardDashboard';
import { GuardQRScannerScreen } from '../screens/staff/GuardQRScannerScreen';
import { GuardGatePassScreen } from '../screens/staff/GuardGatePassScreen';
import { GuardChecklistScreen } from '../screens/staff/GuardChecklistScreen';

const Tab = createBottomTabNavigator();

export const GuardTabsNavigator = () => {
  const insets = useSafeAreaInsets();
  
  return (
    <Tab.Navigator
      screenOptions={({ route }) => ({
        headerShown: false,
        tabBarActiveTintColor: theme.colors.primary,
        tabBarInactiveTintColor: theme.colors.textSecondary,
        tabBarActiveBackgroundColor: 'rgba(255, 107, 53, 0.08)',
        tabBarStyle: {
          backgroundColor: theme.colors.white,
          borderTopWidth: 0,
          paddingBottom: Math.max(insets.bottom, theme.spacing.sm),
          paddingTop: theme.spacing.sm,
          height: 70 + Math.max(insets.bottom - theme.spacing.sm, 0),
          elevation: 12,
          shadowColor: theme.colors.black,
          shadowOffset: { width: 0, height: -4 },
          shadowOpacity: 0.12,
          shadowRadius: 8,
          borderTopLeftRadius: theme.borderRadius.lg,
          borderTopRightRadius: theme.borderRadius.lg,
        },
        tabBarLabelStyle: {
          ...theme.tabBar.label,
        },
        tabBarIcon: ({ color, focused }) => {
          const icons: Record<string, string> = {
            GuardDashboard: 'home',
            GuardQRScanner: 'qr-code',
            GuardGatePass: 'log-in',
            GuardChecklist: 'clipboard',
          };

          const iconName = icons[route.name] ?? 'ellipse';

          return (
            <AnimatedTabIcon
              name={iconName}
              focused={focused}
              color={color}
              size={focused ? 28 : 24}
            />
          );
        },
      })}>
      <Tab.Screen
        name="GuardDashboard"
        component={GuardDashboard}
        options={{
          tabBarLabel: 'Dashboard',
          tabBarAccessibilityLabel: 'Dashboard',
        }}
      />
      <Tab.Screen
        name="GuardQRScanner"
        component={GuardQRScannerScreen}
        options={{
          tabBarLabel: 'Scan QR',
          tabBarAccessibilityLabel: 'Scan QR',
        }}
      />
      <Tab.Screen
        name="GuardGatePass"
        component={GuardGatePassScreen}
        options={{
          tabBarLabel: 'Gate Pass',
          tabBarAccessibilityLabel: 'Gate Pass',
        }}
      />
      <Tab.Screen
        name="GuardChecklist"
        component={GuardChecklistScreen}
        options={{
          tabBarLabel: 'Checklist',
          tabBarAccessibilityLabel: 'Checklist',
        }}
      />
    </Tab.Navigator>
  );
};

