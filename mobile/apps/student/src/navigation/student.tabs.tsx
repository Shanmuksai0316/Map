import React from 'react';
import { View } from 'react-native';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { theme } from '../theme/theme';
import { StudentDashboardScreen } from '../screens/DashboardScreen';
import { AttendanceScreen } from '../screens/AttendanceScreen';
import { ProfileScreen } from '../screens/ProfileScreen';
import { SocialMediaScreen } from '../screens/SocialMediaScreen';
import { FooterNavigation } from '../components/FooterNavigation';

const Tab = createBottomTabNavigator();

export const StudentTabsNavigator = () => {
  const insets = useSafeAreaInsets();
  
  return (
    <Tab.Navigator
      screenOptions={{
        headerShown: true,
        headerStyle: {
          backgroundColor: theme.colors.white,
        },
        headerTintColor: theme.colors.primary,
        headerTitleStyle: {
          fontWeight: theme.fontWeight.bold,
          fontSize: theme.fontSize.xl,
          letterSpacing: 0.15,
          color: theme.colors.primary,
        },
      }}
      tabBar={(props) => <FooterNavigation {...props} />}>
      <Tab.Screen
        name="Home"
        component={StudentDashboardScreen}
        options={{
          tabBarLabel: 'Home',
          headerShown: false,
        }}
      />
      <Tab.Screen
        name="Attendance"
        component={AttendanceScreen}
        options={{
          tabBarLabel: 'Attendance',
          headerShown: false,
        }}
      />
      <Tab.Screen
        name="SocialMedia"
        component={SocialMediaScreen}
        options={{
          tabBarLabel: 'Social Media',
          headerTitle: 'Social Media',
        }}
      />
      <Tab.Screen
        name="Profile"
        component={ProfileScreen}
        options={{
          tabBarLabel: 'Profile',
          headerShown: false,
        }}
      />
    </Tab.Navigator>
  );
};

