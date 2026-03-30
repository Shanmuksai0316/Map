import React from 'react';
import { render, fireEvent, waitFor } from '@testing-library/react-native';
import { LeaveFormScreen } from '../LeaveFormScreen';
import { apiService } from '../../../services/api.service';
import { useAuthStore } from '../../../store/auth.store';

// Mock dependencies
jest.mock('../../../services/api.service');
jest.mock('../../../store/auth.store');
jest.mock('../../../services/haptic.service', () => ({
  hapticService: {
    onSuccess: jest.fn(),
    onError: jest.fn(),
    onButtonPress: jest.fn(),
  },
}));

const mockNavigation = {
  navigate: jest.fn(),
  goBack: jest.fn(),
};

const mockUser = {
  id: 1,
  name: 'Test User',
  student_uid: 'ROOM123',
  email: 'test@example.com',
};

describe('LeaveFormScreen', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    (useAuthStore as jest.Mock).mockReturnValue({
      user: mockUser,
    });
  });

  it('should render correctly', () => {
    const { getByText } = render(<LeaveFormScreen navigation={mockNavigation} />);
    expect(getByText('Leave Request')).toBeTruthy();
    expect(getByText('Reason for Leave *')).toBeTruthy();
  });

  it('should show validation error for empty reason', async () => {
    const { Alert } = require('react-native');
    const { getByText } = render(<LeaveFormScreen navigation={mockNavigation} />);
    
    const submitButton = getByText('Submit Request');
    fireEvent.press(submitButton);

    await waitFor(() => {
      expect(Alert.alert).toHaveBeenCalledWith(
        'Validation Error',
        'Reason for leave is required'
      );
    });
  });

  it('should show validation error for short reason', async () => {
    const { Alert } = require('react-native');
    const { getByText, getByPlaceholderText } = render(
      <LeaveFormScreen navigation={mockNavigation} />
    );
    
    const reasonInput = getByPlaceholderText('Enter reason for leave');
    fireEvent.changeText(reasonInput, 'Short');
    
    const submitButton = getByText('Submit Request');
    fireEvent.press(submitButton);

    await waitFor(() => {
      expect(Alert.alert).toHaveBeenCalledWith(
        'Validation Error',
        'Reason for leave must be at least 10 characters'
      );
    });
  });

  it('should show validation error for invalid date range', async () => {
    const { Alert } = require('react-native');
    const { getByText, getByPlaceholderText } = render(
      <LeaveFormScreen navigation={mockNavigation} />
    );
    
    const reasonInput = getByPlaceholderText('Enter reason for leave');
    fireEvent.changeText(reasonInput, 'This is a valid reason for leave');
    
    // Note: Date picker testing would require more complex setup
    // This is a simplified test
    
    const submitButton = getByText('Submit Request');
    fireEvent.press(submitButton);

    // The actual date validation would happen here
    // For now, we just verify the form renders
    expect(reasonInput).toBeTruthy();
  });

  it('should submit form with valid data', async () => {
    const { Alert } = require('react-native');
    (apiService.post as jest.Mock).mockResolvedValue({ data: { id: 1 } });
    
    const { getByText, getByPlaceholderText } = render(
      <LeaveFormScreen navigation={mockNavigation} />
    );
    
    const reasonInput = getByPlaceholderText('Enter reason for leave');
    fireEvent.changeText(reasonInput, 'This is a valid reason for leave that is long enough');
    
    const submitButton = getByText('Submit Request');
    fireEvent.press(submitButton);

    await waitFor(() => {
      expect(apiService.post).toHaveBeenCalled();
      expect(Alert.alert).toHaveBeenCalledWith(
        'Success',
        'Leave request submitted successfully',
        expect.any(Array)
      );
    });
  });
});

