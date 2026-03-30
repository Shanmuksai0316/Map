import React, { useEffect, lazy, Suspense, useState } from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { View, ActivityIndicator, Text, TouchableOpacity } from 'react-native';
import { useAuthStore } from '../store/auth.store';
import { StorageService } from '../services/storage.service';
import { LoginScreen } from '../screens/auth/LoginScreen';
import { TenantSelectionScreen } from '../screens/auth/TenantSelectionScreen';
import { StudentTabsNavigator } from './student.tabs';
import { theme } from '../theme/theme';
import { SplashScreen } from '../components/SplashScreen';

// Lazy load screens for better performance
const GatePassScreen = lazy(() => import('../screens/student/GatePassScreen').then(module => ({ default: module.GatePassScreen })));
const ComplaintsScreen = lazy(() => import('../screens/student/ComplaintsScreen').then(module => ({ default: module.ComplaintsScreen })));
const TicketsScreen = lazy(() => import('../screens/student/TicketsScreen').then(module => ({ default: module.TicketsScreen })));
import { TicketDetailScreen as StudentTicketDetailScreen } from '../screens/student/TicketDetailScreen';
import { SportsBookingScreen } from '../screens/student/SportsBookingScreen';
import { LaundryRequestScreen } from '../screens/student/LaundryRequestScreen';
import { LeavePreviewScreen } from '../screens/student/LeavePreviewScreen';
import { LeaveDetailScreen } from '../screens/student/LeaveDetailScreen';
import { LeaveFormScreen } from '../screens/student/LeaveFormScreen';
import { SickLeavePreviewScreen } from '../screens/student/SickLeavePreviewScreen';
import { SickLeaveDetailScreen } from '../screens/student/SickLeaveDetailScreen';
import { SickLeaveFormScreen } from '../screens/student/SickLeaveFormScreen';
import { GuestEntryPreviewScreen } from '../screens/student/GuestEntryPreviewScreen';
import { GuestEntryDetailScreen } from '../screens/student/GuestEntryDetailScreen';
import { GuestEntryFormScreen } from '../screens/student/GuestEntryFormScreen';
import { RoomChangePreviewScreen } from '../screens/student/RoomChangePreviewScreen';
import { RoomChangeDetailScreen } from '../screens/student/RoomChangeDetailScreen';
import { RoomChangeFormScreen } from '../screens/student/RoomChangeFormScreen';
import { CampusManagerDashboard } from '../screens/staff/CampusManagerDashboard';
import { RectorDashboard } from '../screens/staff/RectorDashboard';
import { WardenTabsNavigator } from './warden.tabs';
import { WardenAttendanceDetailScreen } from '../screens/staff/WardenAttendanceDetailScreen';
import { WardenRequestDetailScreen } from '../screens/staff/WardenRequestDetailScreen';
import { WardenStudentDetailScreen } from '../screens/staff/WardenStudentDetailScreen';
import { GuardTabsNavigator } from './guard.tabs';
import { GuardQRScannerScreen } from '../screens/staff/GuardQRScannerScreen';
import { GuardGatePassScreen } from '../screens/staff/GuardGatePassScreen';
import { GuardHistoryScreen } from '../screens/staff/GuardHistoryScreen';
import { HKSupervisorTabsNavigator } from './hk-supervisor.tabs';
import { HKSupervisorRequestsScreen } from '../screens/staff/HKSupervisorRequestsScreen';
import { HKSupervisorChecklistScreen } from '../screens/staff/HKSupervisorChecklistScreen';
import { RMSupervisorTabsNavigator } from './rm-supervisor.tabs';
import { RMSupervisorRequestDetailScreen } from '../screens/staff/RMSupervisorRequestDetailScreen';
import { LaundryManagerTabsNavigator } from './laundry-manager.tabs';
import { LaundryManagerQRScannerScreen } from '../screens/staff/LaundryManagerQRScannerScreen';
import { LaundryManagerGatePassScreen } from '../screens/staff/LaundryManagerGatePassScreen';
import { RaiseRequestScreen } from '../screens/staff/laundry-manager/RaiseRequestScreen';
import { AnnouncementsScreen } from '../screens/shared/AnnouncementsScreen';
import { NotificationsScreen } from '../screens/shared/NotificationsScreen';
import { SportsManagerDashboard } from '../screens/staff/SportsManagerDashboard';
import { SportsManagerChecklistScreen } from '../screens/staff/SportsManagerChecklistScreen';
import { StaffProfileScreen } from '../screens/staff/StaffProfileScreen';
import { isStudentApp, APP_CONFIG } from '../config/app.config';
import { UnsupportedRoleScreen } from '../screens/auth/UnsupportedRoleScreen';
import { QRScannerScreen } from '../screens/staff/QRScannerScreen';
import { demoDeepLinkService } from '../services/demo-deeplink.service';
import { GatePassApprovalScreen } from '../screens/staff/GatePassApprovalScreen';
import { RectorInsightsScreen } from '../screens/staff/RectorInsightsScreen';
import { GuardVisitorManagementScreen } from '../screens/staff/GuardVisitorManagementScreen';
import { SecurityIncidentScreen } from '../screens/staff/SecurityIncidentScreen';
import { GateEntryScreen } from '../screens/staff/GateEntryScreen';
import { GateExitScreen } from '../screens/staff/GateExitScreen';
import { GatePassDetailScreen } from '../screens/staff/GatePassDetailScreen';
import { TicketListScreen } from '../screens/staff/TicketListScreen';
import { TicketDetailScreen } from '../screens/staff/TicketDetailScreen';
import { TicketCreateScreen } from '../screens/staff/TicketCreateScreen';
import { SupervisorChecklistDetailScreen } from '../screens/staff/SupervisorChecklistDetailScreen';
import { LaundryRequestListScreen } from '../screens/staff/LaundryRequestListScreen';
import { LaundryRequestDetailScreen } from '../screens/staff/LaundryRequestDetailScreen';
import { SportsBlockoutScreen } from '../screens/staff/SportsBlockoutScreen';
import { SportsFacilityMonitoringScreen } from '../screens/staff/SportsFacilityMonitoringScreen';
import { StudentManagementScreen } from '../screens/staff/StudentManagementScreen';
import { NoticeManagementScreen } from '../screens/staff/NoticeManagementScreen';
import { ReportsScreen } from '../screens/staff/ReportsScreen';
import { RoomAllocationScreen } from '../screens/staff/RoomAllocationScreen';

const Stack = createNativeStackNavigator();

const studentHeaderOptions = {
  presentation: 'card' as const,
  headerStyle: { backgroundColor: theme.colors.white },
  headerTintColor: theme.colors.primary,
  headerTitleStyle: { fontWeight: 'bold' as const, color: theme.colors.primary },
};

const FeatureDisabledScreen = ({ featureLabel, onBack }: { featureLabel: string; onBack: () => void }) => (
  <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center', padding: 24 }}>
    <Text style={{ fontSize: 18, fontWeight: '700', marginBottom: 12 }}>{featureLabel} is disabled</Text>
    <Text style={{ fontSize: 14, color: '#4B5563', textAlign: 'center', marginBottom: 20 }}>
      This feature is turned off for the current tenant. Please contact your administrator if you think this is an error.
    </Text>
    <TouchableOpacity
      onPress={onBack}
      style={{ backgroundColor: '#9333EA', paddingHorizontal: 20, paddingVertical: 12, borderRadius: 10 }}>
      <Text style={{ color: '#FFFFFF', fontWeight: '600' }}>Go Back</Text>
    </TouchableOpacity>
  </View>
);

export const RootNavigator = () => {
  const {
    isAuthenticated,
    loadStoredAuth,
    user,
    selectedTenant,
    roleValidation,
    clearRoleValidation,
    logout,
    setSelectedTenant,
    setDefaultTestTenant,
    loadFeatureFlags,
    isFeatureEnabled,
  } = useAuthStore();

  const [isInitializing, setIsInitializing] = useState(true);

  useEffect(() => {
    let cleanupFn: (() => void) | undefined;
    let timeoutId: NodeJS.Timeout | undefined;

    const initializeApp = async () => {
      console.log('[RootNavigator] Starting app initialization...');
      
      // Set timeout to prevent infinite loading (10 seconds)
      timeoutId = setTimeout(() => {
        console.warn('[RootNavigator] Initialization timeout - forcing completion after 10 seconds');
        setIsInitializing(false);
      }, 10000);

      try {
        console.log('[RootNavigator] Loading stored auth...');
        loadStoredAuth();
        console.log('[RootNavigator] Initializing deep link service...');
        cleanupFn = demoDeepLinkService.initialize();
        console.log('[RootNavigator] Deep link service initialized');

        // Only set default test tenant in DEV mode with local APIs
        // In production, tenant will be auto-detected after login
        const isProduction = APP_CONFIG.API_BASE_DOMAIN === 'mapservices.in';
        console.log('[RootNavigator] Checking if we need to set default test tenant...', { isProduction, selectedTenant: !!selectedTenant });
        if (__DEV__ && !selectedTenant && !isProduction) {
          console.log('[RootNavigator] Setting default test tenant...');
          setDefaultTestTenant();
          console.log('[RootNavigator] Default test tenant set');
        }
        console.log('[RootNavigator] App initialization completed successfully');
      } catch (error) {
        console.error('[RootNavigator] App initialization error:', error);
      } finally {
        // Clear timeout if initialization completes normally
        if (timeoutId) {
          clearTimeout(timeoutId);
        }
        console.log('[RootNavigator] Setting isInitializing to false');
        setIsInitializing(false);
      }
    };

    initializeApp();

    return () => {
      if (timeoutId) {
        clearTimeout(timeoutId);
      }
      if (cleanupFn) {
        cleanupFn();
      }
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    if (roleValidation.mismatch) {
      StorageService.clear();
    }
  }, [roleValidation.mismatch]);

  useEffect(() => {
    if (isAuthenticated && selectedTenant) {
      loadFeatureFlags?.();
    }
  }, [isAuthenticated, selectedTenant, loadFeatureFlags]);

  // Show splash screen while initializing
  if (isInitializing) {
    return <SplashScreen />;
  }

  const getStaffDashboard = () => {
    switch (user?.role) {
      case 'campus_manager':
        return CampusManagerDashboard;
      case 'rector':
        return RectorDashboard;
      case 'warden':
        return WardenTabsNavigator;
      case 'guard':
        return GuardTabsNavigator;
      case 'hk_supervisor':
        return HKSupervisorTabsNavigator;
      case 'rm_supervisor':
        return RMSupervisorTabsNavigator;
      case 'laundry_manager':
        return isFeatureEnabled?.('laundry_module_enabled')
          ? LaundryManagerTabsNavigator
          : () => (
              <FeatureDisabledScreen
                featureLabel="Laundry module"
                onBack={async () => {
                  await logout();
                }}
              />
            );
      case 'sports_manager':
        return SportsManagerDashboard;
      default:
        return CampusManagerDashboard; // Default fallback
    }
  };

  if (roleValidation.mismatch) {
    return (
      <UnsupportedRoleScreen
        userRole={roleValidation.actual ?? 'unknown'}
        onLogout={async () => {
          clearRoleValidation();
          await logout();
        }}
      />
    );
  }

  // Show tenant selection if not authenticated or no tenant selected
  if (!isAuthenticated || !selectedTenant) {
    return (
      <NavigationContainer>
        <Stack.Navigator
          screenOptions={{
            headerShown: false,
          }}>
          {!isAuthenticated ? (
            <Stack.Screen name="Login" component={LoginScreen} />
          ) : (
            <Stack.Screen name="TenantSelection">
              {() => (
                <TenantSelectionScreen
                  onTenantSelected={(tenant) => {
                    setSelectedTenant(tenant);
                  }}
                />
              )}
            </Stack.Screen>
          )}
        </Stack.Navigator>
      </NavigationContainer>
    );
  }

  return (
    <NavigationContainer>
      <Stack.Navigator
        screenOptions={{
          headerShown: false,
        }}>
        {!isAuthenticated ? (
          <Stack.Screen name="Login" component={LoginScreen} />
        ) : (
          <>
            {isStudentApp() ? (
              <>
                {/* Bottom Tab Navigator for main screens */}
                <Stack.Screen
                  name="StudentTabs"
                  component={StudentTabsNavigator}
                  options={{ headerShown: false }}
                />
                {/* Modal/Detail screens */}
                <Stack.Screen
                  name="GatePass"
                  options={{
                    title: 'Gate Pass',
                    ...studentHeaderOptions,
                  }}>
                  {(props) => (
                    <Suspense fallback={<View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}><ActivityIndicator size="large" color={theme.colors.primary} /></View>}>
                      <GatePassScreen {...props} />
                    </Suspense>
                  )}
                </Stack.Screen>
                <Stack.Screen
                  name="Complaints"
                  options={{
                    title: 'Complaints',
                    ...studentHeaderOptions,
                  }}>
                  {(props) => (
                    <Suspense fallback={<View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}><ActivityIndicator size="large" color={theme.colors.primary} /></View>}>
                      <ComplaintsScreen {...props} />
                    </Suspense>
                  )}
                </Stack.Screen>
                <Stack.Screen
                  name="Tickets"
                  options={{
                    title: 'Tickets',
                    ...studentHeaderOptions,
                  }}>
                  {(props) => (
                    <Suspense fallback={<View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}><ActivityIndicator size="large" color={theme.colors.primary} /></View>}>
                      <TicketsScreen {...props} />
                    </Suspense>
                  )}
                </Stack.Screen>
                <Stack.Screen
                  name="TicketDetail"
                  component={StudentTicketDetailScreen}
                  options={{
                    title: 'Ticket Details',
                    ...studentHeaderOptions,
                  }}
                />
                <Stack.Screen
                  name="SportsBooking"
                  component={SportsBookingScreen}
                  options={{
                    title: 'Sports Booking',
                    ...studentHeaderOptions,
                  }}
                />
                <Stack.Screen
                  name="LaundryRequest"
                  component={LaundryRequestScreen}
                  options={{
                    title: 'Laundry Service',
                    ...studentHeaderOptions,
                  }}
                />
                <Stack.Screen
                  name="LeavePreview"
                  component={LeavePreviewScreen}
                  options={{
                    title: 'Leaves',
                    ...studentHeaderOptions,
                  }}
                />
                <Stack.Screen
                  name="LeaveDetail"
                  component={LeaveDetailScreen}
                  options={{
                    title: 'Leave Details',
                    ...studentHeaderOptions,
                  }}
                />
                <Stack.Screen
                  name="LeaveForm"
                  component={LeaveFormScreen}
                  options={{
                    title: 'Leave Request',
                    ...studentHeaderOptions,
                  }}
                />
                <Stack.Screen
                  name="SickLeavePreview"
                  component={SickLeavePreviewScreen}
                  options={{
                    title: 'Sick Leave',
                    ...studentHeaderOptions,
                  }}
                />
                <Stack.Screen
                  name="SickLeaveDetail"
                  component={SickLeaveDetailScreen}
                  options={{
                    title: 'Sick Leave Details',
                    ...studentHeaderOptions,
                  }}
                />
                <Stack.Screen
                  name="SickLeaveForm"
                  component={SickLeaveFormScreen}
                  options={{
                    title: 'Sick Leave Request',
                    ...studentHeaderOptions,
                  }}
                />
                <Stack.Screen
                  name="GuestEntryPreview"
                  component={GuestEntryPreviewScreen}
                  options={{
                    title: 'Guest Entry',
                    ...studentHeaderOptions,
                  }}
                />
                <Stack.Screen
                  name="GuestEntryDetail"
                  component={GuestEntryDetailScreen}
                  options={{
                    title: 'Guest Entry Details',
                    ...studentHeaderOptions,
                  }}
                />
                <Stack.Screen
                  name="GuestEntryForm"
                  component={GuestEntryFormScreen}
                  options={{
                    title: 'Guest Entry Request',
                    ...studentHeaderOptions,
                  }}
                />
                <Stack.Screen
                  name="RoomChangePreview"
                  component={RoomChangePreviewScreen}
                  options={{
                    title: 'Room Change',
                    ...studentHeaderOptions,
                  }}
                />
                <Stack.Screen
                  name="RoomChangeDetail"
                  component={RoomChangeDetailScreen}
                  options={{
                    title: 'Room Change Details',
                    ...studentHeaderOptions,
                  }}
                />
                <Stack.Screen
                  name="RoomChangeForm"
                  component={RoomChangeFormScreen}
                  options={{
                    title: 'Room Change Request',
                    ...studentHeaderOptions,
                  }}
                />
              </>
            ) : (
              <>
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
                  name="RaiseRequest"
                  component={RaiseRequestScreen}
                  options={{ title: 'Raise Laundry Request' }}
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
                {/* HK Supervisor Screens */}
                <Stack.Screen
                  name="HKSupervisorRequests"
                  component={HKSupervisorRequestsScreen}
                  options={{ title: 'Requests' }}
                />
                <Stack.Screen
                  name="HKSupervisorChecklist"
                  component={HKSupervisorChecklistScreen}
                  options={{ title: 'Checklist' }}
                />
                {/* Guard Screens */}
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
                  name="GuardHistory"
                  component={GuardHistoryScreen}
                  options={{ title: 'History' }}
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
                {/* Add more staff screens as needed */}
              </>
            )}
          </>
        )}
      </Stack.Navigator>
    </NavigationContainer>
  );
};

