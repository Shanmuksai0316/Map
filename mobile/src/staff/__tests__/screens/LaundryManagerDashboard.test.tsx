import React from 'react';
import { render, waitFor, fireEvent } from '@testing-library/react-native';
import { LaundryManagerDashboard } from '../../screens/laundry-manager/LaundryManagerDashboard';
import { useAuthStore } from '../../shared/store/auth.store';
import { useNotificationStore } from '../../shared/store/notification.store';
import { api } from '../../services/api';

jest.mock('../../shared/store/auth.store');
jest.mock('../../shared/store/notification.store');
jest.mock('../../services/api');

const mockNavigation = {
  navigate: jest.fn(),
};

describe('LaundryManagerDashboard', () => {
  beforeEach(() => {
    jest.clearAllMocks();

    (useAuthStore as jest.Mock).mockReturnValue({
      user: { name: 'Test Manager' },
      tenant: { name: 'Test College', logo_url: null },
    });

    (useNotificationStore as jest.Mock).mockReturnValue({
      unreadCount: 1,
      fetchUnreadCount: jest.fn(),
    });

    (api.get as jest.Mock).mockResolvedValue({
      data: {
        data: {
          pending_laundry_requests: 8,
          in_progress_laundry_requests: 5,
          completed_today_laundry: 22,
          ready_for_pickup_laundry: 6,
        },
      },
    });
  });

  it('renders dashboard correctly', async () => {
    const { getByText } = render(
      <LaundryManagerDashboard navigation={mockNavigation} />
    );

    await waitFor(() => {
      expect(getByText('Laundry Manager')).toBeTruthy();
      expect(getByText('Test Manager')).toBeTruthy();
    });
  });

  it('displays laundry stats', async () => {
    const { getByText } = render(
      <LaundryManagerDashboard navigation={mockNavigation} />
    );

    await waitFor(() => {
      expect(getByText('Pending')).toBeTruthy();
      expect(getByText('In Progress')).toBeTruthy();
      expect(getByText('Ready for Pickup')).toBeTruthy();
      expect(getByText('Completed Today')).toBeTruthy();
    });
  });

  it('navigates to raise request screen', async () => {
    const { getByText } = render(
      <LaundryManagerDashboard navigation={mockNavigation} />
    );

    await waitFor(() => {
      fireEvent.press(getByText('Raise Request'));
    });

    expect(mockNavigation.navigate).toHaveBeenCalledWith('RaiseRequest');
  });

  it('navigates to active requests screen', async () => {
    const { getByText } = render(
      <LaundryManagerDashboard navigation={mockNavigation} />
    );

    await waitFor(() => {
      fireEvent.press(getByText('Active Requests'));
    });

    expect(mockNavigation.navigate).toHaveBeenCalledWith('RequestList');
  });
});

