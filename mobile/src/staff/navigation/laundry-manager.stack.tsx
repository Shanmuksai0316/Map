/**
 * Laundry Manager Stack Navigator
 * No bottom tabs - uses stack navigation only as per requirements
 * Screens: Dashboard, Raise Request, Active Requests, Profile, Notice Board
 */

import React from 'react';
import { createNativeStackNavigator } from '@react-navigation/native-stack';

// Laundry Manager screens
import { LaundryManagerDashboard } from '../screens/laundry-manager/LaundryManagerDashboard';
import { RaiseRequestScreen } from '../screens/laundry-manager/RaiseRequestScreen';
import { LaundryRequestListScreen } from '../screens/laundry-manager/LaundryRequestListScreen';
import { LaundryRequestDetailScreen } from '../screens/laundry-manager/LaundryRequestDetailScreen';
import { LaundryManagerProfileScreen } from '../screens/laundry-manager/LaundryManagerProfileScreen';
import { NoticeDetailScreen } from '../../student/screens/NoticeDetailScreen';
import { CommBoxScreen as StaffCommBoxScreen } from '../screens/campus-manager/CommBoxScreen';

const Stack = createNativeStackNavigator();

export const LaundryManagerStackNavigator = () => {
  return (
    <Stack.Navigator
      screenOptions={{
        headerShown: false,
      }}>
      <Stack.Screen
        name="LaundryManagerDashboard"
        component={LaundryManagerDashboard}
        options={{ title: 'Dashboard' }}
      />
      <Stack.Screen
        name="RaiseRequest"
        component={RaiseRequestScreen}
        options={{ title: 'Raise Request' }}
      />
      <Stack.Screen
        name="LaundryRequestList"
        component={LaundryRequestListScreen}
        options={{ title: 'Laundry Requests' }}
      />
      <Stack.Screen
        name="LaundryRequestDetail"
        component={LaundryRequestDetailScreen}
        options={{ title: 'Request Details' }}
      />
      <Stack.Screen
        name="Profile"
        component={LaundryManagerProfileScreen}
        options={{ title: 'Profile' }}
      />
      <Stack.Screen
        name="CommBox"
        component={StaffCommBoxScreen}
        options={{ title: 'Notice Board' }}
      />
      <Stack.Screen
        name="NoticeDetail"
        component={NoticeDetailScreen}
        options={{ title: 'Notice' }}
      />
    </Stack.Navigator>
  );
};
