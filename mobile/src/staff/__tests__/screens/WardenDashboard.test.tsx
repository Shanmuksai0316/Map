import React from 'react';
import { render, waitFor, fireEvent } from '@testing-library/react-native';
import { WardenDashboard } from '../../screens/warden/WardenDashboard';
import { useAuthStore } from '../../shared/store/auth.store';
import { useNotificationStore } from '../../shared/store/notification.store';
import { api } from '../../services/api';

jest.mock('../../shared/store/auth.store');
jest.mock('../../shared/store/notification.store');
jest.mock('../../services/api');

const mockNavigation = {
  navigate: jest.fn(),
};

describe('WardenDashboard', () => {
  beforeEach(() => {
    jest.clearAllMocks();

    (useAuthStore as jest.Mock).mockReturnValue({
      user: { name: 'Test Warden' },
      tenant: { name: 'Test College', logo_url: null },
    });

    (useNotificationStore as jest.Mock).mockReturnValue({
      unreadCount: 2,
      fetchUnreadCount: jest.fn(),
    });

    (api.get as jest.Mock).mockImplementation((url) => {
      if (url === '/dashboard') {
        return Promise.resolve({
          data: {
            data: {
              pending_outpass_approvals: 5,
              pending_leave_approvals: 3,
              pending_sick_leaves: 2,
              students_on_leave: 12,
              students_checked_out: 8,
              total_students: 150,
              present_students: 130,
              attendance_rate: 87,
            },
          },
        });
      }
      if (url === '/emergencies/unread') {
        return Promise.resolve({
          data: { data: [] },
        });
      }
      return Promise.resolve({ data: { data: {} } });
    });
  });

  it('renders dashboard correctly', async () => {
    const { getByText } = render(<WardenDashboard navigation={mockNavigation} />);

    await waitFor(() => {
      expect(getByText('Warden Portal')).toBeTruthy();
      expect(getByText('Test Warden')).toBeTruthy();
    });
  });

  it('displays attendance summary', async () => {
    const { getByText } = render(<WardenDashboard navigation={mockNavigation} />);

    await waitFor(() => {
      expect(getByText('Hostel Attendance')).toBeTruthy();
      expect(getByText('Present')).toBeTruthy();
      expect(getByText('On Leave')).toBeTruthy();
      expect(getByText('87% Attendance Rate')).toBeTruthy();
    });
  });

  it('displays pending approvals counts', async () => {
    const { getByText } = render(<WardenDashboard navigation={mockNavigation} />);

    await waitFor(() => {
      expect(getByText('Pending Approvals')).toBeTruthy();
      expect(getByText('Outpasses')).toBeTruthy();
      expect(getByText('Leaves')).toBeTruthy();
    });
  });

  it('navigates to outpass approvals', async () => {
    const { getByText } = render(<WardenDashboard navigation={mockNavigation} />);

    await waitFor(() => {
      fireEvent.press(getByText('Outpass Approvals'));
    });

    expect(mockNavigation.navigate).toHaveBeenCalledWith('OutpassApprovals');
  });

  it('navigates to leave approvals', async () => {
    const { getByText } = render(<WardenDashboard navigation={mockNavigation} />);

    await waitFor(() => {
      fireEvent.press(getByText('Leave Approvals'));
    });

    expect(mockNavigation.navigate).toHaveBeenCalledWith('LeaveApprovals');
  });

  it('navigates to sick leaves', async () => {
    const { getByText } = render(<WardenDashboard navigation={mockNavigation} />);

    await waitFor(() => {
      fireEvent.press(getByText('Sick Leaves'));
    });

    expect(mockNavigation.navigate).toHaveBeenCalledWith('SickLeaves');
  });

  it('shows emergency card when emergencies exist', async () => {
    (api.get as jest.Mock).mockImplementation((url) => {
      if (url === '/emergencies/unread') {
        return Promise.resolve({
          data: {
            data: [
              {
                id: 1,
                type: 'medical',
                title: 'Medical Emergency',
                description: 'Student needs help',
                created_at: new Date().toISOString(),
                acknowledged_at: null,
                student_name: 'John Doe',
                room_number: '305',
              },
            ],
          },
        });
      }
      return Promise.resolve({ data: { data: {} } });
    });

    const { getByText } = render(<WardenDashboard navigation={mockNavigation} />);

    await waitFor(() => {
      expect(getByText('Medical Emergency')).toBeTruthy();
    });
  });
});

