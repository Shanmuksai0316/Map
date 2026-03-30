import React from 'react';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { StudentDashboardScreen } from '../screens/DashboardScreen';
import { AttendanceScreen } from '../screens/AttendanceScreen';
import { ProfileScreen } from '../screens/ProfileScreen';
import { SocialMediaScreen } from '../screens/SocialMediaScreen';
import { ProfileDetailsScreen } from '../screens/ProfileDetailsScreen';
import { RequestsHubScreen } from '../screens/RequestsHubScreen';
import { EmergencyScreen } from '../screens/EmergencyScreen';
import { CommBoxScreen } from '../screens/CommBoxScreen';
import { NoticeDetailScreen } from '../screens/NoticeDetailScreen';
import { RequestHistoryScreen } from '../screens/RequestHistoryScreen';
import { FeedbackScreen } from '../screens/FeedbackScreen';
import { OutpassScreen } from '../screens/OutpassScreen';
import { ComplaintsScreen } from '../screens/ComplaintsScreen';
import { TicketsScreen } from '../screens/TicketsScreen';
import { TicketDetailScreen } from '../screens/TicketDetailScreen';
import { SportsBookingScreen } from '../screens/SportsBookingScreen';
import { LaundryRequestScreen } from '../screens/LaundryRequestScreen';
import { ParcelListScreen } from '../screens/ParcelListScreen';
import { LeavePreviewScreen } from '../screens/LeavePreviewScreen';
import { LeaveDetailScreen } from '../screens/LeaveDetailScreen';
import { LeaveFormScreen } from '../screens/LeaveFormScreen';
import { SickLeavePreviewScreen } from '../screens/SickLeavePreviewScreen';
import { SickLeaveDetailScreen } from '../screens/SickLeaveDetailScreen';
import { SickLeaveFormScreen } from '../screens/SickLeaveFormScreen';
import { GuestEntryPreviewScreen } from '../screens/GuestEntryPreviewScreen';
import { GuestEntryDetailScreen } from '../screens/GuestEntryDetailScreen';
import { GuestEntryFormScreen } from '../screens/GuestEntryFormScreen';
import { RoomChangePreviewScreen } from '../screens/RoomChangePreviewScreen';
import { RoomChangeDetailScreen } from '../screens/RoomChangeDetailScreen';
import { RoomChangeFormScreen } from '../screens/RoomChangeFormScreen';
import { AnnouncementsScreen } from '../../shared/components/AnnouncementsScreen';
import { NotificationsScreen } from '../../shared/components/NotificationsScreen';
import { StudentPersistentFooter } from '../components/StudentPersistentFooter';

const Tab = createBottomTabNavigator();
const HomeStack = createNativeStackNavigator();
const AttendanceStack = createNativeStackNavigator();
const SocialStack = createNativeStackNavigator();
const ProfileStack = createNativeStackNavigator();

const stackScreenOptions = { headerShown: false } as const;

const HomeStackScreen = () => (
  <HomeStack.Navigator screenOptions={stackScreenOptions}>
    <HomeStack.Screen name="Dashboard" component={StudentDashboardScreen} />
    <HomeStack.Screen name="RequestsHub" component={RequestsHubScreen} />
    <HomeStack.Screen name="Emergency" component={EmergencyScreen} />
    <HomeStack.Screen name="CommBox" component={CommBoxScreen} />
    <HomeStack.Screen name="NoticeDetail" component={NoticeDetailScreen} />
    <HomeStack.Screen name="RequestHistory" component={RequestHistoryScreen} />
    <HomeStack.Screen name="Feedback" component={FeedbackScreen} />
    <HomeStack.Screen name="GatePass" component={OutpassScreen} />
    <HomeStack.Screen name="Complaints" component={ComplaintsScreen} />
    <HomeStack.Screen name="Tickets" component={TicketsScreen} />
    <HomeStack.Screen name="TicketDetail" component={TicketDetailScreen} />
    <HomeStack.Screen name="SportsBooking" component={SportsBookingScreen} />
    <HomeStack.Screen name="LaundryRequest" component={LaundryRequestScreen} />
    <HomeStack.Screen name="ParcelList" component={ParcelListScreen} />
    <HomeStack.Screen name="LeavePreview" component={LeavePreviewScreen} />
    <HomeStack.Screen name="LeaveDetail" component={LeaveDetailScreen} />
    <HomeStack.Screen name="LeaveForm" component={LeaveFormScreen} />
    <HomeStack.Screen name="SickLeavePreview" component={SickLeavePreviewScreen} />
    <HomeStack.Screen name="SickLeaveDetail" component={SickLeaveDetailScreen} />
    <HomeStack.Screen name="SickLeaveForm" component={SickLeaveFormScreen} />
    <HomeStack.Screen name="GuestEntryPreview" component={GuestEntryPreviewScreen} />
    <HomeStack.Screen name="GuestEntryDetail" component={GuestEntryDetailScreen} />
    <HomeStack.Screen name="GuestEntryForm" component={GuestEntryFormScreen} />
    <HomeStack.Screen name="Announcements" component={AnnouncementsScreen} />
    <HomeStack.Screen name="Notifications" component={NotificationsScreen} />
  </HomeStack.Navigator>
);

const AttendanceStackScreen = () => (
  <AttendanceStack.Navigator screenOptions={stackScreenOptions}>
    <AttendanceStack.Screen name="Attendance" component={AttendanceScreen} />
  </AttendanceStack.Navigator>
);

const SocialStackScreen = () => (
  <SocialStack.Navigator screenOptions={stackScreenOptions}>
    <SocialStack.Screen name="SocialMedia" component={SocialMediaScreen} />
  </SocialStack.Navigator>
);

const ProfileStackScreen = () => (
  <ProfileStack.Navigator screenOptions={stackScreenOptions}>
    <ProfileStack.Screen name="Profile" component={ProfileScreen} />
    <ProfileStack.Screen name="ProfileDetails" component={ProfileDetailsScreen} />
    <ProfileStack.Screen name="RoomChangePreview" component={RoomChangePreviewScreen} />
    <ProfileStack.Screen name="RoomChangeDetail" component={RoomChangeDetailScreen} />
    <ProfileStack.Screen name="RoomChangeForm" component={RoomChangeFormScreen} />
  </ProfileStack.Navigator>
);

export const StudentTabsNavigator = () => {
  return (
    <Tab.Navigator
      screenOptions={{
        headerShown: false,
      }}
      tabBar={(props) => <StudentPersistentFooter {...props} />}>
      <Tab.Screen
        name="Home"
        component={HomeStackScreen}
        options={{
          tabBarLabel: 'Home',
          headerShown: false,
          tabBarButtonTestID: 'footer-tab-home',
          tabBarAccessibilityLabel: 'Home tab',
        }}
      />
      <Tab.Screen
        name="Attendance"
        component={AttendanceStackScreen}
        options={{
          tabBarLabel: 'Attendance',
          tabBarButtonTestID: 'footer-tab-attendance',
          tabBarAccessibilityLabel: 'Attendance tab',
        }}
      />
      <Tab.Screen
        name="SocialMedia"
        component={SocialStackScreen}
        options={{
          tabBarLabel: 'Social Media',
          headerTitle: 'Social Media',
          tabBarButtonTestID: 'footer-tab-socialmedia',
          tabBarAccessibilityLabel: 'Social Media tab',
        }}
      />
      <Tab.Screen
        name="Profile"
        component={ProfileStackScreen}
        options={{
          tabBarLabel: 'Profile',
          tabBarButtonTestID: 'footer-tab-profile',
          tabBarAccessibilityLabel: 'Profile tab',
        }}
      />
    </Tab.Navigator>
  );
};
