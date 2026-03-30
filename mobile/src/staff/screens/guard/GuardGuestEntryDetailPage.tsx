import React, { useEffect } from 'react';
import { GuardGuestEntryDetailScreen } from './GuardGuestEntryDetailScreen';

interface GuestEntry {
  id: number;
  student_name: string;
  student_id?: string;
  room_number?: string;
  status: string;
  visitor_name?: string;
  guest_name?: string;
  guest_relationship?: string;
  guest_phone?: string;
  number_of_guests?: number;
  visit_date?: string;
  time?: string;
  reason?: string;
}

export const GuardGuestEntryDetailPage = ({ navigation, route }: any) => {
  const guest: GuestEntry | undefined = route?.params?.guest;

  useEffect(() => {
    if (!guest) {
      navigation?.goBack?.();
    }
  }, [guest, navigation]);

  if (!guest) return null;

  return (
    <GuardGuestEntryDetailScreen
      visible={true}
      guest={guest}
      onClose={() => navigation?.goBack?.()}
      onMarkEntryComplete={() => navigation?.goBack?.()}
    />
  );
};
