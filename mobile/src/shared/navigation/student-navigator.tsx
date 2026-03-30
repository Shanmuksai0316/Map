/**
 * Student App Navigator
 * Contains only student-specific screens and navigation
 */

import React, { useEffect } from 'react';
import { pushNotificationService } from '../services/push-notification.service';
import { rootNavigationRef } from './navigation-ref';
import { StudentTabsNavigator } from '../../student/navigation/student.tabs';

type StudentTabKey = 'Home' | 'Attendance' | 'SocialMedia' | 'Profile';

type StudentNavigationTarget = {
  tab: StudentTabKey;
  screen?: string;
};

const STUDENT_SCREEN_MAP: Record<string, StudentNavigationTarget> = {
  // Home stack
  Dashboard: { tab: 'Home', screen: 'Dashboard' },
  RequestsHub: { tab: 'Home', screen: 'RequestsHub' },
  Emergency: { tab: 'Home', screen: 'Emergency' },
  CommBox: { tab: 'Home', screen: 'CommBox' },
  NoticeDetail: { tab: 'Home', screen: 'NoticeDetail' },
  RequestHistory: { tab: 'Home', screen: 'RequestHistory' },
  Feedback: { tab: 'Home', screen: 'Feedback' },
  GatePass: { tab: 'Home', screen: 'GatePass' },
  Complaints: { tab: 'Home', screen: 'Complaints' },
  Tickets: { tab: 'Home', screen: 'Tickets' },
  TicketDetail: { tab: 'Home', screen: 'TicketDetail' },
  SportsBooking: { tab: 'Home', screen: 'SportsBooking' },
  LaundryRequest: { tab: 'Home', screen: 'LaundryRequest' },
  ParcelList: { tab: 'Home', screen: 'ParcelList' },
  LeavePreview: { tab: 'Home', screen: 'LeavePreview' },
  LeaveDetail: { tab: 'Home', screen: 'LeaveDetail' },
  LeaveForm: { tab: 'Home', screen: 'LeaveForm' },
  SickLeavePreview: { tab: 'Home', screen: 'SickLeavePreview' },
  SickLeaveDetail: { tab: 'Home', screen: 'SickLeaveDetail' },
  SickLeaveForm: { tab: 'Home', screen: 'SickLeaveForm' },
  GuestEntryPreview: { tab: 'Home', screen: 'GuestEntryPreview' },
  GuestEntryDetail: { tab: 'Home', screen: 'GuestEntryDetail' },
  GuestEntryForm: { tab: 'Home', screen: 'GuestEntryForm' },
  Announcements: { tab: 'Home', screen: 'Announcements' },
  Notifications: { tab: 'Home', screen: 'Notifications' },

  // Attendance stack
  Attendance: { tab: 'Attendance', screen: 'Attendance' },

  // Social stack
  SocialMedia: { tab: 'SocialMedia', screen: 'SocialMedia' },

  // Profile stack
  Profile: { tab: 'Profile', screen: 'Profile' },
  ProfileDetails: { tab: 'Profile', screen: 'ProfileDetails' },
  RoomChangePreview: { tab: 'Profile', screen: 'RoomChangePreview' },
  RoomChangeDetail: { tab: 'Profile', screen: 'RoomChangeDetail' },
  RoomChangeForm: { tab: 'Profile', screen: 'RoomChangeForm' },
};

const navigateToStudentScreen = (screen: string, params?: object) => {
  const target = STUDENT_SCREEN_MAP[screen];

  if (!target) {
    rootNavigationRef.navigate('Home');
    return;
  }

  if (target.screen) {
    rootNavigationRef.navigate(target.tab, { screen: target.screen, params });
    return;
  }

  rootNavigationRef.navigate(target.tab, params as any);
};

export const StudentNavigator = () => {
  useEffect(() => {
    let cancelled = false;

    const interval = setInterval(() => {
      if (cancelled) return;
      if (!rootNavigationRef.isReady()) return;

      const navigationRef = {
        current: {
          navigate: (screen: string, params?: object) =>
            navigateToStudentScreen(screen, params),
        },
      };

      pushNotificationService.setNavigationRef(navigationRef);
      const pending = pushNotificationService.getPendingNotificationScreen();
      if (pending) {
        navigateToStudentScreen(pending);
        pushNotificationService.clearPendingNotificationScreen();
      }

      clearInterval(interval);
    }, 100);

    return () => {
      cancelled = true;
      clearInterval(interval);
    };
  }, []);

  return <StudentTabsNavigator />;
};
