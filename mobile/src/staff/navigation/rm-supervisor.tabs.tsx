import React from 'react';
import { View } from 'react-native';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { theme } from '../../shared/theme/theme';
import { AnimatedTabIcon } from '../../shared/components/AnimatedTabIcon';
import { RMSupervisorDashboard } from '../screens/rm-supervisor/RMSupervisorDashboard';
import { RMSupervisorRequestsScreen } from '../screens/rm-supervisor/RMSupervisorRequestsScreen';
import { SupervisorChecklistDetailScreen } from '../screens/shared/SupervisorChecklistDetailScreen';

const Tab = createBottomTabNavigator();

export const RMSupervisorTabsNavigator = () => {
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
            RMSupervisorDashboard: 'home',
            RMSupervisorRequests: 'notifications',
            RMSupervisorChecklist: 'clipboard',
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
        name="RMSupervisorDashboard"
        component={RMSupervisorDashboard}
        options={{
          tabBarLabel: 'Dashboard',
          tabBarAccessibilityLabel: 'Dashboard',
        }}
      />
      <Tab.Screen
        name="RMSupervisorRequests"
        component={RMSupervisorRequestsScreen}
        options={{
          tabBarLabel: 'Requests',
          tabBarAccessibilityLabel: 'Requests',
        }}
      />
      <Tab.Screen
        name="RMSupervisorChecklist"
        component={SupervisorChecklistDetailScreen}
        options={{
          tabBarLabel: 'Checklist',
          tabBarAccessibilityLabel: 'Checklist',
        }}
      />
    </Tab.Navigator>
  );
};

