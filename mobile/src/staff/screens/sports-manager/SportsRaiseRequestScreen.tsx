import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  TextInput,
  ActivityIndicator,
  Alert,
} from 'react-native';
import { format, addDays } from 'date-fns';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { apiService } from '../../../shared/services/api.service';
import { theme } from '../../../shared/theme/theme';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface Court {
  id: number;
  name: string;
  category?: string;
  is_active?: boolean;
}

interface Slot {
  start: string;
  end: string;
}

export const SportsRaiseRequestScreen = ({ navigation }: any) => {
  const [courts, setCourts] = useState<Court[]>([]);
  const [selectedCourt, setSelectedCourt] = useState<Court | null>(null);
  const [dateTab, setDateTab] = useState<'today' | 'tomorrow'>('today');
  const [slots, setSlots] = useState<Slot[]>([]);
  const [loadingSlots, setLoadingSlots] = useState(false);
  const [studentName, setStudentName] = useState('');
  const [selectedSlot, setSelectedSlot] = useState<Slot | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const todayStr = format(new Date(), 'yyyy-MM-dd');
  const tomorrowStr = format(addDays(new Date(), 1), 'yyyy-MM-dd');
  const selectedDate = dateTab === 'today' ? todayStr : tomorrowStr;

  const fetchCourts = useCallback(async () => {
    try {
      const res = await apiService.get<{ data: Court[] }>('/sports/courts');
      setCourts(res?.data ?? []);
      if (!selectedCourt && (res?.data?.length ?? 0) > 0) {
        setSelectedCourt(res!.data![0]);
      }
    } catch {
      setCourts([]);
    }
  }, [selectedCourt]);

  useEffect(() => {
    fetchCourts();
  }, []);

  useEffect(() => {
    if (!selectedCourt) {
      setSlots([]);
      return;
    }
    let cancelled = false;
    setLoadingSlots(true);
    setSlots([]);
    apiService
      .get<{ data: { available_slots?: Slot[] } }>(
        `/sports/facilities/${selectedCourt.id}/availability`,
        { params: { date: selectedDate, duration: 60 } }
      )
      .then((res) => {
        if (!cancelled) {
          setSlots(res?.data?.available_slots ?? []);
        }
      })
      .catch(() => {
        if (!cancelled) setSlots([]);
      })
      .finally(() => {
        if (!cancelled) setLoadingSlots(false);
      });
    return () => {
      cancelled = true;
    };
  }, [selectedCourt, selectedDate]);

  const handleRaiseRequest = async () => {
    const name = studentName.trim();
    if (!name) {
      Alert.alert('Required', 'Enter student name');
      return;
    }
    if (!selectedCourt || !selectedSlot) {
      Alert.alert('Required', 'Select court and time slot');
      return;
    }
    try {
      setSubmitting(true);
      await apiService.post('/sports/raise-booking', {
        facility_id: selectedCourt.id,
        date: selectedDate,
        slot_start: selectedSlot.start,
        duration_minutes: 60,
        student_name: name,
      });
      Alert.alert('Success', 'Booking raised. Student will be notified under Sports tile.', [
        { text: 'OK', onPress: () => navigation.goBack() },
      ]);
      setStudentName('');
      setSelectedSlot(null);
    } catch (err: any) {
      const msg = err?.response?.data?.error || err?.message || 'Failed to raise request';
      Alert.alert('Error', msg);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Raise Request" />
      <ScrollView style={styles.scroll} contentContainerStyle={styles.content}>
        <Text style={styles.label}>Court</Text>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.courtScroll}>
          {courts.map((c) => (
            <TouchableOpacity
              key={c.id}
              style={[styles.chip, selectedCourt?.id === c.id && styles.chipActive]}
              onPress={() => {
                setSelectedCourt(c);
                setSelectedSlot(null);
              }}
            >
              <Text style={[styles.chipText, selectedCourt?.id === c.id && styles.chipTextActive]}>{c.name}</Text>
            </TouchableOpacity>
          ))}
        </ScrollView>

        <Text style={styles.label}>Date</Text>
        <View style={styles.dateTabs}>
          <TouchableOpacity
            style={[styles.dateTab, dateTab === 'today' && styles.dateTabActive]}
            onPress={() => setDateTab('today')}
          >
            <Text style={[styles.dateTabText, dateTab === 'today' && styles.dateTabTextActive]}>Today</Text>
            <Text style={styles.dateSub}>{format(new Date(), 'EEE, MMM d')}</Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={[styles.dateTab, dateTab === 'tomorrow' && styles.dateTabActive]}
            onPress={() => setDateTab('tomorrow')}
          >
            <Text style={[styles.dateTabText, dateTab === 'tomorrow' && styles.dateTabTextActive]}>Tomorrow</Text>
            <Text style={styles.dateSub}>{format(addDays(new Date(), 1), 'EEE, MMM d')}</Text>
          </TouchableOpacity>
        </View>

        <Text style={styles.label}>Time slot</Text>
        {loadingSlots ? (
          <ActivityIndicator size="small" color={theme.colors.primary} style={styles.slotLoader} />
        ) : (
          <View style={styles.slotGrid}>
            {slots.map((slot) => (
              <TouchableOpacity
                key={`${slot.start}-${slot.end}`}
                style={[styles.slotBtn, selectedSlot?.start === slot.start && styles.slotBtnActive]}
                onPress={() => setSelectedSlot(slot)}
              >
                <Text style={[styles.slotText, selectedSlot?.start === slot.start && styles.slotTextActive]}>
                  {slot.start} - {slot.end}
                </Text>
              </TouchableOpacity>
            ))}
          </View>
        )}
        {!loadingSlots && slots.length === 0 && selectedCourt && (
          <Text style={styles.noSlots}>No slots available for this date</Text>
        )}

        <Text style={styles.label}>Student name</Text>
        <TextInput
          style={styles.input}
          placeholder="Enter student name"
          placeholderTextColor={theme.colors.textMuted}
          value={studentName}
          onChangeText={setStudentName}
          autoCapitalize="words"
        />

        <TouchableOpacity
          style={[styles.submitBtn, submitting && styles.submitBtnDisabled]}
          onPress={handleRaiseRequest}
          disabled={submitting}
        >
          {submitting ? (
            <ActivityIndicator color="#FFF" />
          ) : (
            <Text style={styles.submitText}>Raise request</Text>
          )}
        </TouchableOpacity>
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: theme.colors.background },
  subHeader: {
    paddingHorizontal: 16,
    paddingTop: 12,
    paddingBottom: 8,
  },
  subHeaderTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: theme.colors.textHeading,
  },
  scroll: { flex: 1 },
  content: { padding: 16, paddingBottom: 32 },
  label: { fontSize: 14, fontWeight: '600', color: theme.colors.textSecondary, marginBottom: 8, marginTop: 16 },
  courtScroll: { marginBottom: 8 },
  chip: {
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: 20,
    backgroundColor: theme.colors.surfaceMuted,
    marginRight: 8,
  },
  chipActive: { backgroundColor: theme.colors.primary },
  chipText: { fontSize: 14, fontWeight: '500', color: theme.colors.text },
  chipTextActive: { color: '#FFF' },
  dateTabs: { flexDirection: 'row', gap: 12, marginBottom: 8 },
  dateTab: {
    flex: 1,
    padding: 12,
    borderRadius: 12,
    backgroundColor: theme.colors.surface,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  dateTabActive: { borderColor: theme.colors.primary, backgroundColor: theme.colors.primary + '15' },
  dateTabText: { fontSize: 16, fontWeight: '600', color: theme.colors.text },
  dateTabTextActive: { color: theme.colors.primary },
  dateSub: { fontSize: 12, color: theme.colors.textMuted, marginTop: 2 },
  slotGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  slotBtn: {
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderRadius: 8,
    backgroundColor: theme.colors.surface,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  slotBtnActive: { borderColor: theme.colors.primary, backgroundColor: theme.colors.primary + '20' },
  slotText: { fontSize: 14, color: theme.colors.text },
  slotTextActive: { color: theme.colors.primary, fontWeight: '600' },
  slotLoader: { padding: 24 },
  noSlots: { fontSize: 14, color: theme.colors.textMuted, marginTop: 8 },
  input: {
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: 12,
    paddingHorizontal: 16,
    paddingVertical: 12,
    fontSize: 16,
    color: theme.colors.text,
    backgroundColor: theme.colors.surface,
  },
  submitBtn: {
    marginTop: 24,
    padding: 16,
    borderRadius: 12,
    backgroundColor: theme.colors.primary,
    alignItems: 'center',
  },
  submitBtnDisabled: { opacity: 0.7 },
  submitText: { fontSize: 16, fontWeight: '600', color: '#FFF' },
});

export default SportsRaiseRequestScreen;
