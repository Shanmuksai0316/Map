import React, { useEffect } from 'react';
import { GuardLeaveDetailScreen } from './GuardLeaveDetailScreen';

interface Leave {
  id: number;
  student_name: string;
  room_number?: string;
  reason?: string;
  status: string;
  from_date: string;
  to_date: string;
  actual_departure_time?: string | null;
}

export const GuardLeaveDetailPage = ({ navigation, route }: any) => {
  const leave: Leave | undefined = route?.params?.leave;

  useEffect(() => {
    if (!leave) {
      navigation?.goBack?.();
    }
  }, [leave, navigation]);

  if (!leave) return null;

  return (
    <GuardLeaveDetailScreen
      visible={true}
      leave={leave}
      onClose={() => navigation?.goBack?.()}
      onMarkedComplete={() => navigation?.goBack?.()}
    />
  );
};
