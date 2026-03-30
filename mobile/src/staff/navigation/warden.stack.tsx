import React from 'react';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { WardenDashboard } from '../screens/warden/WardenDashboard';
import { WardenProfileScreen } from '../screens/warden/WardenProfileScreen';
import { NoticeDetailScreen } from '../../student/screens/NoticeDetailScreen';
import { CommBoxScreen as StaffCommBoxScreen } from '../screens/campus-manager/CommBoxScreen';
import { WardenChecklistScreen } from '../screens/warden/WardenChecklistScreen';
import { WardenRequestsScreen } from '../screens/warden/WardenRequestsScreen';
import { WardenRequestDetailScreen } from '../screens/warden/WardenRequestDetailScreen';
import { WardenAttendanceScreen } from '../screens/warden/WardenAttendanceScreen';
import { WardenStudentListScreen } from '../screens/warden/WardenStudentListScreen';
import { WardenStudentDetailScreen } from '../screens/warden/WardenStudentDetailScreen';
import { PostNoticeScreen } from '../screens/campus-manager/PostNoticeScreen';
import { NoticeManagementScreen } from '../screens/campus-manager/NoticeManagementScreen';
import { EmergencyScreen } from '../screens/campus-manager/EmergencyScreen';
import { EmergencyMedicalScreen } from '../screens/campus-manager/EmergencyMedicalScreen';
import { EmergencyIncidentsScreen } from '../screens/campus-manager/EmergencyIncidentsScreen';
import { WardenParcelScreen } from '../screens/warden/WardenParcelScreen';

const Stack = createNativeStackNavigator();

export const WardenStackNavigator = () => {
  return (
    <Stack.Navigator screenOptions={{ headerShown: false }}>
      <Stack.Screen name="WardenDashboard" component={WardenDashboard} />
      <Stack.Screen name="WardenProfile" component={WardenProfileScreen} />
      <Stack.Screen name="CommBox" component={StaffCommBoxScreen} />
      <Stack.Screen name="WardenCommBox" component={StaffCommBoxScreen} />
      <Stack.Screen name="NoticeDetail" component={NoticeDetailScreen} options={{ title: 'Notice' }} />
      <Stack.Screen name="WardenChecklist" component={WardenChecklistScreen} />
      <Stack.Screen name="WardenRequests" component={WardenRequestsScreen} />
      <Stack.Screen name="WardenRequestDetail" component={WardenRequestDetailScreen} />
      <Stack.Screen name="WardenAttendance" component={WardenAttendanceScreen} />
      <Stack.Screen name="WardenStudentList" component={WardenStudentListScreen} />
      <Stack.Screen name="WardenStudentDetail" component={WardenStudentDetailScreen} />
      <Stack.Screen name="WardenParcel" component={WardenParcelScreen} options={{ title: 'Parcels' }} />
      <Stack.Screen name="PostNotice" component={PostNoticeScreen} />
      <Stack.Screen name="NoticeManagement" component={NoticeManagementScreen} />
      <Stack.Screen name="WardenEmergency" component={EmergencyScreen} options={{ title: 'Emergency' }} />
      <Stack.Screen name="MedicalEmergencies" component={EmergencyMedicalScreen} options={{ title: 'Medical Emergencies' }} />
      <Stack.Screen name="Incidents" component={EmergencyIncidentsScreen} options={{ title: 'Incidents' }} />
    </Stack.Navigator>
  );
};
