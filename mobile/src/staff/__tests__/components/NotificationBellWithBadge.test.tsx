import React from 'react';
import { render, fireEvent } from '@testing-library/react-native';
import { NotificationBellWithBadge } from '../../shared/components/NotificationBellWithBadge';

describe('NotificationBellWithBadge', () => {
  it('renders correctly without badge', () => {
    const { getByTestId } = render(
      <NotificationBellWithBadge count={0} onPress={() => {}} />
    );
    expect(getByTestId('notification-bell')).toBeTruthy();
  });

  it('renders badge when count > 0', () => {
    const { getByText } = render(
      <NotificationBellWithBadge count={5} onPress={() => {}} />
    );
    expect(getByText('5')).toBeTruthy();
  });

  it('shows 99+ when count exceeds 99', () => {
    const { getByText } = render(
      <NotificationBellWithBadge count={150} onPress={() => {}} />
    );
    expect(getByText('99+')).toBeTruthy();
  });

  it('calls onPress when pressed', () => {
    const mockOnPress = jest.fn();
    const { getByTestId } = render(
      <NotificationBellWithBadge count={3} onPress={mockOnPress} />
    );
    fireEvent.press(getByTestId('notification-bell'));
    expect(mockOnPress).toHaveBeenCalled();
  });
});

