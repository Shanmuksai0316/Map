import React from 'react';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { View, Text, StyleSheet } from 'react-native';
import { useEmergencyStore } from '../../shared/store/emergency.store';
import { theme } from '../../shared/theme/theme';

// Import screens
import { CampusManagerDashboard } from '../screens/campus-manager/CampusManagerDashboard';
import { RequestsHubScreen } from '../screens/campus-manager/RequestsHubScreen';
import { EmergencyScreen } from '../screens/campus-manager/EmergencyScreen';
import { MyStaffScreen } from '../screens/campus-manager/MyStaffScreen';
import { CommBoxScreen } from '../screens/campus-manager/CommBoxScreen';

// Stack screens
import { CampusManagerChecklistScreen } from '../screens/campus-manager/CampusManagerChecklistScreen';
import { StaffChecklistScreen } from '../screens/campus-manager/StaffChecklistScreen';
import { EmergencyMedicalScreen } from '../screens/campus-manager/EmergencyMedicalScreen';
import { EmergencyIncidentsScreen } from '../screens/campus-manager/EmergencyIncidentsScreen';
import { PostNoticeScreen } from '../screens/campus-manager/PostNoticeScreen';
import { CampusManagerProfileScreen } from '../screens/campus-manager/CampusManagerProfileScreen';

const Tab = createBottomTabNavigator();
const Stack = createNativeStackNavigator();

const COLORS = {
  primary: theme.colors.primary,
  inactive: theme.colors.textMuted,
  background: theme.colors.white,
  badge: theme.colors.error,
};

// Badge component
const TabBadge = ({ count }: { count: number }) => {
  if (count === 0) return null;
  return (
    <View style={styles.badge}>
      <Text style={styles.badgeText}>{count > 99 ? '99+' : count}</Text>
    </View>
  );
};

// Dashboard Stack
const DashboardStack = () => (
  <Stack.Navigator screenOptions={{ headerShown: false }}>
    <Stack.Screen name="DashboardMain" component={CampusManagerDashboard} />
    <Stack.Screen name="MyChecklist" component={CampusManagerChecklistScreen} />
    <Stack.Screen name="StaffChecklist" component={StaffChecklistScreen} />
    <Stack.Screen name="Notifications" component={CommBoxScreen} />
    <Stack.Screen name="Profile" component={CampusManagerProfileScreen} />
  </Stack.Navigator>
);

// Requests Stack
const RequestsStack = () => (
  <Stack.Navigator screenOptions={{ headerShown: false }}>
    <Stack.Screen name="RequestsMain" component={RequestsHubScreen} />
  </Stack.Navigator>
);

// Emergency Stack
const EmergencyStack = () => (
  <Stack.Navigator screenOptions={{ headerShown: false }}>
    <Stack.Screen name="EmergencyMain" component={EmergencyScreen} />
    <Stack.Screen name="MedicalEmergencies" component={EmergencyMedicalScreen} />
    <Stack.Screen name="Incidents" component={EmergencyIncidentsScreen} />
  </Stack.Navigator>
);

// Staff Stack
const StaffStack = () => (
  <Stack.Navigator screenOptions={{ headerShown: false }}>
    <Stack.Screen name="StaffMain" component={MyStaffScreen} />
  </Stack.Navigator>
);

// Notice Board Stack
const CommBoxStack = () => (
  <Stack.Navigator screenOptions={{ headerShown: false }}>
    <Stack.Screen name="CommBoxMain" component={CommBoxScreen} />
    <Stack.Screen name="PostNotice" component={PostNoticeScreen} />
  </Stack.Navigator>
);

export const CampusManagerTabs = () => {
  const unacknowledgedCount = useEmergencyStore((state) => state.unacknowledgedCount);

  return (
    <Tab.Navigator
      screenOptions={{
        headerShown: false,
        tabBarStyle: styles.tabBar,
        tabBarActiveTintColor: COLORS.primary,
        tabBarInactiveTintColor: COLORS.inactive,
        tabBarLabelStyle: styles.tabBarLabel,
      }}
    >
      <Tab.Screen
        name="Dashboard"
        component={DashboardStack}
        options={{
          tabBarIcon: ({ color, focused }) => (
            <Icon
              name={focused ? 'view-dashboard' : 'view-dashboard-outline'}
              size={24}
              color={color}
            />
          ),
        }}
      />
      <Tab.Screen
        name="Requests"
        component={RequestsStack}
        options={{
          tabBarIcon: ({ color, focused }) => (
            <Icon
              name={focused ? 'clipboard-list' : 'clipboard-list-outline'}
              size={24}
              color={color}
            />
          ),
        }}
      />
      <Tab.Screen
        name="Emergency"
        component={EmergencyStack}
        options={{
          tabBarIcon: ({ color, focused }) => (
            <View>
              <Icon
                name={focused ? 'alert-circle' : 'alert-circle-outline'}
                size={24}
                color={unacknowledgedCount > 0 ? COLORS.badge : color}
              />
              <TabBadge count={unacknowledgedCount} />
            </View>
          ),
        }}
      />
      <Tab.Screen
        name="My Staff"
        component={StaffStack}
        options={{
          tabBarIcon: ({ color, focused }) => (
            <Icon
              name={focused ? 'account-group' : 'account-group-outline'}
              size={24}
              color={color}
            />
          ),
        }}
      />
      <Tab.Screen
        name="Notice Board"
        component={CommBoxStack}
        options={{
          tabBarBadge: undefined,
          tabBarIcon: ({ color, focused }) => (
            <Icon
              name={focused ? 'message-text' : 'message-text-outline'}
              size={24}
              color={color}
            />
          ),
        }}
      />
    </Tab.Navigator>
  );
};

const styles = StyleSheet.create({
  tabBar: {
    backgroundColor: COLORS.background,
    borderTopWidth: 1,
    borderTopColor: theme.colors.border,
    paddingTop: 8,
    paddingBottom: 8,
    height: 64,
  },
  tabBarLabel: {
    fontSize: 11,
    fontWeight: '500',
    marginTop: 4,
  },
  badge: {
    position: 'absolute',
    top: -4,
    right: -8,
    backgroundColor: COLORS.badge,
    borderRadius: 10,
    minWidth: 18,
    height: 18,
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 4,
  },
  badgeText: {
    color: theme.colors.white,
    fontSize: 10,
    fontWeight: '700',
  },
});

export default CampusManagerTabs;
