import React from 'react';
import { render, fireEvent } from '@testing-library/react-native';
import { EmergencyBlinkingCard } from '../../shared/components/EmergencyBlinkingCard';
import type { EmergencyIncident } from '../../shared/types';

describe('EmergencyBlinkingCard', () => {
  const mockEmergency: EmergencyIncident = {
    id: 1,
    type: 'medical',
    title: 'Medical Emergency',
    description: 'Student needs immediate attention',
    created_at: new Date().toISOString(),
    acknowledged_at: null,
    student_name: 'John Doe',
    room_number: '305',
  };

  it('renders emergency details correctly', () => {
    const { getByText } = render(
      <EmergencyBlinkingCard emergency={mockEmergency} onPress={() => {}} />
    );
    expect(getByText('Medical Emergency')).toBeTruthy();
    expect(getByText('John Doe')).toBeTruthy();
    expect(getByText('Room 305')).toBeTruthy();
  });

  it('calls onPress when pressed', () => {
    const mockOnPress = jest.fn();
    const { getByTestId } = render(
      <EmergencyBlinkingCard emergency={mockEmergency} onPress={mockOnPress} />
    );
    fireEvent.press(getByTestId('emergency-card'));
    expect(mockOnPress).toHaveBeenCalled();
  });

  it('shows different styles for medical vs incident type', () => {
    const incidentEmergency: EmergencyIncident = {
      ...mockEmergency,
      type: 'incident',
      title: 'Security Incident',
    };

    const { getByText, rerender } = render(
      <EmergencyBlinkingCard emergency={mockEmergency} onPress={() => {}} />
    );
    expect(getByText('Medical Emergency')).toBeTruthy();

    rerender(
      <EmergencyBlinkingCard emergency={incidentEmergency} onPress={() => {}} />
    );
    expect(getByText('Security Incident')).toBeTruthy();
  });
});

