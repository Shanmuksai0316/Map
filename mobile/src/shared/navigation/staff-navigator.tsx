/**
 * Staff App Navigator
 * Contains only staff-specific screens and navigation
 */

import React, { useRef } from 'react';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { useAuthStore } from '../store/auth.store';
import { pushNotificationService } from '../services/push-notification.service';

// Staff dashboards
import { CampusManagerDashboard } from '../../staff/screens/campus-manager/CampusManagerDashboard';
import { RectorDashboard } from '../../staff/screens/rector/RectorDashboard';
import { SportsManagerDashboard } from '../../staff/screens/sports-manager/SportsManagerDashboard';
import { SportsManagerProfileScreen } from '../../staff/screens/sports-manager/SportsManagerProfileScreen';
import { SportsManagerActiveRequestsScreen } from '../../staff/screens/sports-manager/SportsManagerActiveRequestsScreen';
import { CourtSetupScreen } from '../../staff/screens/sports-manager/CourtSetupScreen';
import { SportsRaiseRequestScreen } from '../../staff/screens/sports-manager/SportsRaiseRequestScreen';

// Rector screens
import { OutpassListScreen as RectorOutpassListScreen } from '../../staff/screens/rector/OutpassListScreen';
import { OutpassDetailScreen as RectorOutpassDetailScreen } from '../../staff/screens/rector/OutpassDetailScreen';
import { LeaveListScreen as RectorLeaveListScreen } from '../../staff/screens/rector/LeaveListScreen';
import { GuestEntryListScreen as RectorGuestEntryListScreen } from '../../staff/screens/rector/GuestEntryListScreen';
import { RectorGuestEntryDetailScreen } from '../../staff/screens/rector/RectorGuestEntryDetailScreen';
import { RectorLeaveDetailScreen } from '../../staff/screens/rector/RectorLeaveDetailScreen';
import { RectorProfileScreen } from '../../staff/screens/rector/RectorProfileScreen';

// Staff tab navigators
import { WardenStackNavigator } from '../../staff/navigation/warden.stack';
import { LaundryManagerStackNavigator } from '../../staff/navigation/laundry-manager.stack';

// Staff detail screens
import { WardenAttendanceDetailScreen } from '../../staff/screens/warden/WardenAttendanceDetailScreen';
import { WardenRequestDetailScreen } from '../../staff/screens/warden/WardenRequestDetailScreen';
import { WardenStudentDetailScreen } from '../../staff/screens/warden/WardenStudentDetailScreen';
import { GuardQRScannerScreen } from '../../staff/screens/guard/GuardQRScannerScreen';
import { GuardGatePassScreen } from '../../staff/screens/guard/GuardGatePassScreen';
import { GuardHistoryScreen } from '../../staff/screens/guard/GuardHistoryScreen';
import { GuardDashboard } from '../../staff/screens/guard/GuardDashboard';
import { GuardProfileScreen } from '../../staff/screens/guard/GuardProfileScreen';
import { GuardChecklistScreen } from '../../staff/screens/guard/GuardChecklistScreen';
import { HKSupervisorDashboard } from '../../staff/screens/hk-supervisor/HKSupervisorDashboard';
import { HKSupervisorRequestsScreen } from '../../staff/screens/hk-supervisor/HKSupervisorRequestsScreen';
import { HKProfileScreen } from '../../staff/screens/hk-supervisor/HKProfileScreen';
import { HKHistoryScreen } from '../../staff/screens/hk-supervisor/HKHistoryScreen';
import { RMSupervisorDashboard } from '../../staff/screens/rm-supervisor/RMSupervisorDashboard';
import { RMSupervisorRequestsScreen } from '../../staff/screens/rm-supervisor/RMSupervisorRequestsScreen';
import { RMProfileScreen } from '../../staff/screens/rm-supervisor/RMProfileScreen';
import { RMHistoryScreen } from '../../staff/screens/rm-supervisor/RMHistoryScreen';
import { RMSupervisorRequestDetailScreen } from '../../staff/screens/rm-supervisor/RMSupervisorRequestDetailScreen';
import { LaundryManagerQRScannerScreen } from '../../staff/screens/laundry-manager/LaundryManagerQRScannerScreen';
import { LaundryManagerGatePassScreen } from '../../staff/screens/laundry-manager/LaundryManagerGatePassScreen';

// Shared staff screens
import { AnnouncementsScreen } from '../components/AnnouncementsScreen';
import { NotificationsScreen } from '../components/NotificationsScreen';
import { NoticeDetailScreen } from '../../student/screens/NoticeDetailScreen';
import { StaffProfileScreen } from '../../staff/screens/shared/StaffProfileScreen';
import { QRScannerScreen } from '../../staff/screens/shared/QRScannerScreen';
import { GatePassApprovalScreen } from '../../staff/screens/shared/GatePassApprovalScreen';
import { RectorInsightsScreen } from '../../staff/screens/rector/RectorInsightsScreen';
import { GuardVisitorManagementScreen } from '../../staff/screens/guard/GuardVisitorManagementScreen';
import { SecurityIncidentScreen } from '../../staff/screens/guard/SecurityIncidentScreen';
import { GateEntryScreen } from '../../staff/screens/guard/GateEntryScreen';
import { GateExitScreen } from '../../staff/screens/guard/GateExitScreen';
import { GatePassDetailScreen } from '../../staff/screens/guard/GatePassDetailScreen';
import { GuardOutpassDetailStandaloneScreen } from '../../staff/screens/guard/GuardOutpassDetailStandaloneScreen';
import { GuardLeaveListScreen } from '../../staff/screens/guard/GuardLeaveListScreen';
import { GuardOutpassListScreen } from '../../staff/screens/guard/GuardOutpassListScreen';
import { GuardGuestEntryListScreen } from '../../staff/screens/guard/GuardGuestEntryListScreen';
import { GuardLeaveDetailPage } from '../../staff/screens/guard/GuardLeaveDetailPage';
import { GuardGuestEntryDetailPage } from '../../staff/screens/guard/GuardGuestEntryDetailPage';
import { TicketListScreen } from '../../staff/screens/shared/TicketListScreen';
import { TicketDetailScreen } from '../../staff/screens/shared/TicketDetailScreen';
import { TicketCreateScreen } from '../../staff/screens/shared/TicketCreateScreen';
import { SupervisorChecklistDetailScreen } from '../../staff/screens/shared/SupervisorChecklistDetailScreen';
import { LaundryRequestListScreen } from '../../staff/screens/laundry-manager/LaundryRequestListScreen';
import { LaundryRequestDetailScreen } from '../../staff/screens/laundry-manager/LaundryRequestDetailScreen';
import { SportsBlockoutScreen } from '../../staff/screens/sports-manager/SportsBlockoutScreen';
import { SportsFacilityMonitoringScreen } from '../../staff/screens/sports-manager/SportsFacilityMonitoringScreen';
import { SportsManagerChecklistScreen } from '../../staff/screens/sports-manager/SportsManagerChecklistScreen';
import { StudentManagementScreen } from '../../staff/screens/campus-manager/StudentManagementScreen';
import { NoticeManagementScreen } from '../../staff/screens/campus-manager/NoticeManagementScreen';
import { PostNoticeScreen } from '../../staff/screens/campus-manager/PostNoticeScreen';
import { ReportsScreen } from '../../staff/screens/campus-manager/ReportsScreen';
import { RoomAllocationScreen } from '../../staff/screens/campus-manager/RoomAllocationScreen';
import { CommBoxScreen as StaffCommBoxScreen } from '../../staff/screens/campus-manager/CommBoxScreen';
import { EmergencyScreen } from '../../staff/screens/campus-manager/EmergencyScreen';
import { EmergencyMedicalScreen } from '../../staff/screens/campus-manager/EmergencyMedicalScreen';
import { EmergencyIncidentsScreen } from '../../staff/screens/campus-manager/EmergencyIncidentsScreen';
import { MyStaffScreen } from '../../staff/screens/campus-manager/MyStaffScreen';
import { RequestsHubScreen } from '../../staff/screens/campus-manager/RequestsHubScreen';
import { CampusManagerChecklistScreen } from '../../staff/screens/campus-manager/CampusManagerChecklistScreen';
import { StaffChecklistScreen } from '../../staff/screens/campus-manager/StaffChecklistScreen';

const Stack = createNativeStackNavigator();

/**
 * Normalize role name from backend format to mobile app format
 * Backend returns: "Guard", "Campus Manager", "HK Supervisor", etc.
 * Mobile expects: "guard", "campus_manager", "hk_supervisor", etc.
 */
const normalizeRole = (role: string | null | undefined): string | null => {
  if (!role) return null;
  
  // Convert to lowercase and replace spaces with underscores
  const normalized = role.toLowerCase().replace(/\s+/g, '_');
  
  // Map specific role variations
  const roleMap: Record<string, string> = {
    'campus_manager': 'campus_manager',
    'campus manager': 'campus_manager',
    'rector': 'rector',
    'warden': 'warden',
    'guard': 'guard',
    'hk_supervisor': 'hk_supervisor',
    'hk supervisor': 'hk_supervisor',
    'rm_supervisor': 'rm_supervisor',
    'rm supervisor': 'rm_supervisor',
    'laundry_manager': 'laundry_manager',
    'laundry manager': 'laundry_manager',
    'sports_manager': 'sports_manager',
    'sports manager': 'sports_manager',
  };
  
  return roleMap[normalized] || normalized;
};

export const StaffNavigator = () => {
  const { user } = useAuthStore();
  const stackRef = useRef<any>(null);

  const onNavigatorReady = () => {
    pushNotificationService.setNavigationRef(stackRef);
    const pending = pushNotificationService.getPendingNotificationScreen();
    if (pending && stackRef.current) {
      try {
        stackRef.current.navigate(pending);
        pushNotificationService.clearPendingNotificationScreen();
      } catch (e) {
        console.warn('[StaffNavigator] Push open navigate failed', e);
      }
    }
  };

  const getStaffDashboard = () => {
    const normalizedRole = normalizeRole(user?.role);

    const effectiveRole = normalizedRole;
    
    switch (effectiveRole) {
      case 'campus_manager':
        return CampusManagerDashboard;
      case 'rector':
        return RectorDashboard;
      case 'warden':
        return WardenStackNavigator;
      case 'guard':
        // Use single dashboard screen (no bottom tabs) for Security Guard
        return GuardDashboard;
      case 'hk_supervisor':
        // Use single dashboard screen (no bottom tabs) for HK Supervisor
        return HKSupervisorDashboard;
      case 'rm_supervisor':
        // Use single dashboard screen (no bottom tabs) for RM Supervisor
        return RMSupervisorDashboard;
      case 'laundry_manager':
        return LaundryManagerStackNavigator;
      case 'sports_manager':
        return SportsManagerDashboard;
      default:
        console.warn('[StaffNavigator] Unknown role, defaulting to Campus Manager:', {
          originalRole: user?.role,
          normalizedRole,
        });
        return CampusManagerDashboard; // Default fallback
    }
  };

  return (
    <Stack.Navigator
      ref={stackRef}
      onReady={onNavigatorReady}
      screenOptions={{
        headerShown: false,
      }}>
      <Stack.Screen
        name="StaffDashboard"
        component={getStaffDashboard()}
        options={{ title: 'Dashboard' }}
      />
      <Stack.Screen
        name="QRScanner"
        component={QRScannerScreen}
        options={{ title: 'QR Scanner' }}
      />
      <Stack.Screen
        name="GatePassApprovals"
        component={GatePassApprovalScreen}
        options={{ title: 'Gate Pass Approvals' }}
      />
      <Stack.Screen
        name="RectorInsights"
        component={RectorInsightsScreen}
        options={{ title: 'Student Insights' }}
      />
      <Stack.Screen
        name="WardenAttendanceDetail"
        component={WardenAttendanceDetailScreen}
        options={{ title: 'Room Attendance' }}
      />
      <Stack.Screen
        name="WardenRequestDetail"
        component={WardenRequestDetailScreen}
        options={{ title: 'Request Details' }}
      />
      <Stack.Screen
        name="WardenStudentDetail"
        component={WardenStudentDetailScreen}
        options={{ title: 'Student Details' }}
      />
      <Stack.Screen
        name="RMSupervisorRequestDetail"
        component={RMSupervisorRequestDetailScreen}
        options={{ title: 'Request Details' }}
      />
      <Stack.Screen
        name="Profile"
        component={StaffProfileScreen}
        options={{ title: 'Profile' }}
      />
      <Stack.Screen
        name="Checklist"
        component={SportsManagerChecklistScreen}
        options={{ title: 'Daily Checklist' }}
      />
      <Stack.Screen
        name="ChecklistDetail"
        component={SupervisorChecklistDetailScreen}
        options={{ title: "Today's Checklist" }}
      />
      <Stack.Screen
        name="GuardVisitorManagement"
        component={GuardVisitorManagementScreen}
        options={{ title: 'Visitor Management' }}
      />
      <Stack.Screen
        name="SecurityIncident"
        component={SecurityIncidentScreen}
        options={{ title: 'Security Incident' }}
      />
      <Stack.Screen
        name="GateEntry"
        component={GateEntryScreen}
        options={{ title: 'Gate Entry' }}
      />
      <Stack.Screen
        name="GateExit"
        component={GateExitScreen}
        options={{ title: 'Gate Exit' }}
      />
      <Stack.Screen
        name="GatePassDetail"
        component={GatePassDetailScreen}
        options={{ title: 'Gate Pass Details' }}
      />
      <Stack.Screen
        name="TicketList"
        component={TicketListScreen}
        options={{ title: 'Tickets' }}
      />
      <Stack.Screen
        name="TicketDetail"
        component={TicketDetailScreen}
        options={{ title: 'Ticket Details' }}
      />
      <Stack.Screen
        name="TicketCreate"
        component={TicketCreateScreen}
        options={{ title: 'Create Ticket' }}
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
        name="SportsBlockouts"
        component={SportsBlockoutScreen}
        options={{ title: 'Facility Blockouts' }}
      />
      <Stack.Screen
        name="SportsFacilityMonitoring"
        component={SportsFacilityMonitoringScreen}
        options={{ title: 'Facility Monitoring' }}
      />
      {/* Campus Manager Screens */}
      <Stack.Screen
        name="StudentManagement"
        component={StudentManagementScreen}
        options={{ title: 'Student Management' }}
      />
      <Stack.Screen
        name="NoticeManagement"
        component={NoticeManagementScreen}
        options={{ title: 'Notice Management' }}
      />
      <Stack.Screen
        name="Reports"
        component={ReportsScreen}
        options={{ title: 'Reports & Analytics' }}
      />
      <Stack.Screen
        name="RoomAllocation"
        component={RoomAllocationScreen}
        options={{ title: 'Room Allocation' }}
      />
      <Stack.Screen
        name="CommBox"
        component={StaffCommBoxScreen}
        options={{ title: 'Notice Board' }}
      />
      <Stack.Screen
        name="PostNotice"
        component={PostNoticeScreen}
        options={{ title: 'Post Notice' }}
      />
      <Stack.Screen
        name="NoticesCommBox"
        component={StaffCommBoxScreen}
        initialParams={{ hideFilter: true }}
        options={{ title: 'Notice Board' }}
      />
      <Stack.Screen
        name="NoticeDetail"
        component={NoticeDetailScreen}
        options={{ title: 'Notice' }}
      />
      <Stack.Screen
        name="Emergency"
        component={EmergencyScreen}
        options={{ title: 'Emergency' }}
      />
      <Stack.Screen
        name="MedicalEmergencies"
        component={EmergencyMedicalScreen}
        options={{ title: 'Medical Emergencies' }}
      />
      <Stack.Screen
        name="Incidents"
        component={EmergencyIncidentsScreen}
        options={{ title: 'Security Incidents' }}
      />
      <Stack.Screen
        name="MyStaff"
        component={MyStaffScreen}
        options={{ title: 'My Staff' }}
      />
      <Stack.Screen
        name="RequestsHub"
        component={RequestsHubScreen}
        options={{ title: 'Requests Hub' }}
      />
      <Stack.Screen
        name="CampusManagerChecklist"
        component={CampusManagerChecklistScreen}
        options={{ title: 'Checklists' }}
      />
      <Stack.Screen
        name="StaffChecklist"
        component={StaffChecklistScreen}
        options={{ title: 'Staff Checklists' }}
      />
      {/* HK Supervisor Screens */}
      <Stack.Screen
        name="HKRequests"
        component={HKSupervisorRequestsScreen}
        options={{ title: 'Requests' }}
      />
      <Stack.Screen
        name="HKChecklist"
        component={SupervisorChecklistDetailScreen}
        options={{ title: 'Checklist' }}
      />
      <Stack.Screen
        name="HKProfile"
        component={HKProfileScreen}
        options={{ title: 'Profile' }}
      />
      <Stack.Screen
        name="HKCommBox"
        component={StaffCommBoxScreen}
        options={{ title: 'Notice Board' }}
      />
      <Stack.Screen
        name="HKHistory"
        component={HKHistoryScreen}
        options={{ title: 'History' }}
      />
      {/* RM Supervisor Screens */}
      <Stack.Screen
        name="RMRequests"
        component={RMSupervisorRequestsScreen}
        options={{ title: 'Requests' }}
      />
      <Stack.Screen
        name="RMChecklist"
        component={SupervisorChecklistDetailScreen}
        options={{ title: 'Checklist' }}
      />
      <Stack.Screen
        name="RMProfile"
        component={RMProfileScreen}
        options={{ title: 'Profile' }}
      />
      <Stack.Screen
        name="RMCommBox"
        component={StaffCommBoxScreen}
        options={{ title: 'Notice Board' }}
      />
      <Stack.Screen
        name="RMHistory"
        component={RMHistoryScreen}
        options={{ title: 'History' }}
      />
      {/* Guard Screens */}
      <Stack.Screen
        name="GuardChecklist"
        component={GuardChecklistScreen}
        options={{ title: 'Checklist' }}
      />
      <Stack.Screen
        name="GuardQRScanner"
        component={GuardQRScannerScreen}
        options={{ title: 'Scan QR' }}
      />
      <Stack.Screen
        name="GuardGatePass"
        component={GuardGatePassScreen}
        options={{ title: 'Gate Pass' }}
      />
      <Stack.Screen
        name="GuardLeaveList"
        component={GuardLeaveListScreen}
        options={{ title: 'Leave' }}
      />
      <Stack.Screen
        name="GuardOutpassList"
        component={GuardOutpassListScreen}
        options={{ title: 'Outpass' }}
      />
      <Stack.Screen
        name="GuardGuestEntryList"
        component={GuardGuestEntryListScreen}
        options={{ title: 'Guest Entry' }}
      />
      <Stack.Screen
        name="GuardLeaveDetail"
        component={GuardLeaveDetailPage}
        options={{ title: 'Leave Details' }}
      />
      <Stack.Screen
        name="GuardGuestEntryDetail"
        component={GuardGuestEntryDetailPage}
        options={{ title: 'Guest Entry Details' }}
      />
      <Stack.Screen
        name="GuardHistory"
        component={GuardHistoryScreen}
        options={{ title: 'History' }}
      />
      <Stack.Screen
        name="GuardProfile"
        component={GuardProfileScreen}
        options={{ title: 'Profile' }}
      />
      <Stack.Screen
        name="GuardOutpassDetail"
        component={GuardOutpassDetailStandaloneScreen}
        options={{ title: 'Outpass Details' }}
      />
      {/* Laundry Manager Screens */}
      <Stack.Screen
        name="LaundryManagerQRScanner"
        component={LaundryManagerQRScannerScreen}
        options={{ title: 'Scan QR' }}
      />
      <Stack.Screen
        name="LaundryManagerGatePass"
        component={LaundryManagerGatePassScreen}
        options={{ title: 'Gate Pass' }}
      />
      <Stack.Screen
        name="LaundryManagerHistory"
        component={GuardHistoryScreen}
        options={{ title: 'History' }}
      />
      {/* Rector Screens */}
      <Stack.Screen
        name="RectorOutpassList"
        component={RectorOutpassListScreen}
        options={{ title: 'Outpass Requests' }}
      />
      <Stack.Screen
        name="RectorOutpassDetail"
        component={RectorOutpassDetailScreen}
        options={{ title: 'Outpass Details' }}
      />
      <Stack.Screen
        name="RectorLeaveList"
        component={RectorLeaveListScreen}
        options={{ title: 'Leave Requests' }}
      />
      <Stack.Screen
        name="RectorGuestEntryList"
        component={RectorGuestEntryListScreen}
        options={{ title: 'Guest Entry' }}
      />
      <Stack.Screen
        name="RectorGuestEntryDetail"
        component={RectorGuestEntryDetailScreen}
        options={{ title: 'Guest Entry Details' }}
      />
      <Stack.Screen
        name="RectorLeaveDetail"
        component={RectorLeaveDetailScreen}
        options={{ title: 'Leave Details' }}
      />
      <Stack.Screen
        name="RectorProfile"
        component={RectorProfileScreen}
        options={{ title: 'Profile' }}
      />
      <Stack.Screen
        name="RectorCommBox"
        component={StaffCommBoxScreen}
        options={{ title: 'Notice Board' }}
      />
      {/* Sports Manager Screens */}
      <Stack.Screen
        name="SportsProfile"
        component={SportsManagerProfileScreen}
        options={{ title: 'Profile' }}
      />
      <Stack.Screen
        name="SportsRaiseRequest"
        component={SportsRaiseRequestScreen}
        options={{ title: 'Raise Request' }}
      />
      <Stack.Screen
        name="SportsActiveRequests"
        component={SportsManagerActiveRequestsScreen}
        options={{ title: 'Active Requests' }}
      />
      <Stack.Screen
        name="CourtSetup"
        component={CourtSetupScreen}
        options={{ title: 'Court Setup' }}
      />
      <Stack.Screen
        name="SportsCommBox"
        component={StaffCommBoxScreen}
        options={{ title: 'Notice Board' }}
      />
      {/* Shared Screens */}
      <Stack.Screen
        name="Announcements"
        component={AnnouncementsScreen}
        options={{ title: 'Announcements' }}
      />
      <Stack.Screen
        name="Notifications"
        component={NotificationsScreen}
        options={{ title: 'Notifications' }}
      />
    </Stack.Navigator>
  );
};
