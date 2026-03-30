import React from 'react';
import { render, waitFor, fireEvent } from '@testing-library/react-native';
import { GuardDashboard } from '../../screens/guard/GuardDashboard';
import { useAuthStore } from '../../shared/store/auth.store';
import { useNotificationStore } from '../../shared/store/notification.store';
import { useChecklistStore } from '../../shared/store/checklist.store';
import { api } from '../../services/api';

jest.mock('../../shared/store/auth.store');
jest.mock('../../shared/store/notification.store');
jest.mock('../../shared/store/checklist.store');
jest.mock('../../services/api');

const mockNavigation = {
  navigate: jest.fn(),
};

describe('GuardDashboard', () => {
  beforeEach(() => {
    jest.clearAllMocks();

    (useAuthStore as jest.Mock).mockReturnValue({
      user: { name: 'Test Guard' },
      tenant: { name: 'Test College', logo_url: null },
    });

    (useNotificationStore as jest.Mock).mockReturnValue({
      unreadCount: 3,
      fetchUnreadCount: jest.fn(),
    });

    (useChecklistStore as jest.Mock).mockReturnValue({
      pendingTasks: 5,
      fetchTodayChecklist: jest.fn(),
    });

    (api.get as jest.Mock).mockResolvedValue({
      data: {
        data: {
          pending_verifications: 3,
          verified_today: 12,
          checklist_progress: 65,
          active_outpasses: 8,
        },
      },
    });
  });

  it('renders dashboard correctly', async () => {
    const { getByText } = render(<GuardDashboard navigation={mockNavigation} />);

    await waitFor(() => {
      expect(getByText('Security Guard')).toBeTruthy();
      expect(getByText('Test Guard')).toBeTruthy();
    });
  });

  it('displays stats correctly', async () => {
    const { getByText } = render(<GuardDashboard navigation={mockNavigation} />);

    await waitFor(() => {
      expect(getByText('3')).toBeTruthy(); // pending verifications
      expect(getByText('Pending Verifications')).toBeTruthy();
    });
  });

  it('navigates to Verify Time screen', async () => {
    const { getByText } = render(<GuardDashboard navigation={mockNavigation} />);

    await waitFor(() => {
      fireEvent.press(getByText('Verify Time'));
    });

    expect(mockNavigation.navigate).toHaveBeenCalledWith('VerifyTime');
  });

  it('navigates to Checklist screen', async () => {
    const { getByText } = render(<GuardDashboard navigation={mockNavigation} />);

    await waitFor(() => {
      fireEvent.press(getByText('My Checklist'));
    });

    expect(mockNavigation.navigate).toHaveBeenCalledWith('GuardChecklist');
  });

  it('shows correct greeting based on time', async () => {
    // Mock morning time
    jest.spyOn(Date.prototype, 'getHours').mockReturnValue(10);

    const { getByText } = render(<GuardDashboard navigation={mockNavigation} />);

    await waitFor(() => {
      expect(getByText('Good Morning,')).toBeTruthy();
    });
  });

  it('shows checklist progress', async () => {
    const { getByText } = render(<GuardDashboard navigation={mockNavigation} />);

    await waitFor(() => {
      expect(getByText("Today's Checklist")).toBeTruthy();
      expect(getByText('65% Complete')).toBeTruthy();
    });
  });
});

