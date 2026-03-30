import React from 'react';
import { View } from 'react-native';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { theme } from '../../shared/theme/theme';
import { AnimatedTabIcon } from '../../shared/components/AnimatedTabIcon';
import { WardenDashboard } from '../screens/warden/WardenDashboard';
import { WardenAttendanceRoomsScreen } from '../screens/warden/WardenAttendanceRoomsScreen';
import { WardenChecklistScreen } from '../screens/warden/WardenChecklistScreen';
import { WardenRequestsScreen } from '../screens/warden/WardenRequestsScreen';
import { WardenStudentsScreen } from '../screens/warden/WardenStudentsScreen';

const Tab = createBottomTabNavigator();

export const WardenTabsNavigator = () => {
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
          borderTopWidth: 0, // Remove border for cleaner look
          paddingBottom: Math.max(insets.bottom, theme.spacing.sm),
          paddingTop: theme.spacing.sm,
          height: 70 + Math.max(insets.bottom - theme.spacing.sm, 0), // Slightly taller for better touch targets
          elevation: 12, // Enhanced elevation for depth
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
            WardenDashboard: 'home',
            WardenAttendance: 'calendar',
            WardenChecklist: 'clipboard',
            WardenRequests: 'notifications',
            WardenStudents: 'people',
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
        name="WardenDashboard"
        component={WardenDashboard}
        options={{
          tabBarLabel: 'Dashboard',
          tabBarAccessibilityLabel: 'Dashboard',
        }}
      />
      <Tab.Screen
        name="WardenAttendance"
        component={WardenAttendanceRoomsScreen}
        options={{
          tabBarLabel: 'Attendance',
          tabBarAccessibilityLabel: 'Attendance',
        }}
      />
      <Tab.Screen
        name="WardenChecklist"
        component={WardenChecklistScreen}
        options={{
          tabBarLabel: 'Checklist',
          tabBarAccessibilityLabel: 'Checklist',
        }}
      />
      <Tab.Screen
        name="WardenRequests"
        component={WardenRequestsScreen}
        options={{
          tabBarLabel: 'Requests',
          tabBarAccessibilityLabel: 'Requests',
        }}
      />
      <Tab.Screen
        name="WardenStudents"
        component={WardenStudentsScreen}
        options={{
          tabBarLabel: 'Students',
          tabBarAccessibilityLabel: 'Students',
        }}
      />
    </Tab.Navigator>
  );
};



