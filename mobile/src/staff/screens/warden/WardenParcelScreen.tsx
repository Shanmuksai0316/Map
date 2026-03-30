/**
 * Warden Parcel Screen
 * - Log parcel: search students by room number or phone, select student, inform (creates parcel + notifies student with 4-digit code)
 * - Receive parcel: list pending parcels, tap one, enter 4-digit code from student to mark received
 */
import React, { useCallback, useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  TextInput,
  ActivityIndicator,
  Alert,
  RefreshControl,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import { apiService } from '../../../shared/services/api.service';
import { StorageService } from '../../../shared/services/storage.service';
import { APP_CONFIG } from '../../../shared/config/app.config';
import { tenantService } from '../../../shared/services/tenant.service';
import { colors } from '../../../shared/theme/colors';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

type StudentOption = { id: string; name: string; phone?: string; room_no: string; bed_no: string; hostel_id: number; hostel_name: string };
type ParcelItem = {
  id: string;
  student_id: string;
  student_name: string;
  room_number: string | null;
  status: string;
  notes: string | null;
  informed_at: string | null;
  received_at: string | null;
  hostel_name: string;
};

export const WardenParcelScreen: React.FC<{ navigation: any }> = ({ navigation }) => {
  const [activeTab, setActiveTab] = useState<'log' | 'receive'>('log');
  const [searchRoom, setSearchRoom] = useState('');
  const [searchPhone, setSearchPhone] = useState('');
  const [students, setStudents] = useState<StudentOption[]>([]);
  const [searching, setSearching] = useState(false);
  const [selectedStudent, setSelectedStudent] = useState<StudentOption | null>(null);
  const [notes, setNotes] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [parcels, setParcels] = useState<ParcelItem[]>([]);
  const [loadingParcels, setLoadingParcels] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [receiveParcelId, setReceiveParcelId] = useState<string | null>(null);
  const [receiveCode, setReceiveCode] = useState('');
  const [submittingReceive, setSubmittingReceive] = useState(false);

  const fetchParcels = useCallback(async () => {
    setLoadingParcels(true);
    try {
      const res = await apiService.get<{ data?: ParcelItem[] } | ParcelItem[]>('/mobile/warden/parcels');
      const payload = res as any;
      const list = Array.isArray(payload?.data) ? payload.data : Array.isArray(payload) ? payload : [];
      setParcels(list);
    } catch (e) {
      console.error('Fetch parcels error', e);
      setParcels([]);
    } finally {
      setLoadingParcels(false);
    }
  }, []);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    if (activeTab === 'receive') await fetchParcels();
    setRefreshing(false);
  }, [activeTab, fetchParcels]);

  const searchStudents = useCallback(async () => {
    if (!searchRoom.trim() && !searchPhone.trim()) {
      Alert.alert('Enter search', 'Enter room number or phone number to search.');
      return;
    }
    setSearching(true);
    setStudents([]);
    setSelectedStudent(null);
    try {
      const params = new URLSearchParams();
      if (searchRoom.trim()) params.set('room_no', searchRoom.trim());
      if (searchPhone.trim()) params.set('phone', searchPhone.trim());
      const res = await apiService.get<{ data: StudentOption[] }>(
        `/mobile/warden/parcels/students-search?${params.toString()}`
      );
      const payload = res as any;
      let list = Array.isArray(payload?.data) ? payload.data : Array.isArray(payload) ? payload : [];

      if (list.length === 0) {
        const token =
          StorageService.getSync(APP_CONFIG.STORAGE_KEYS.AUTH_TOKEN) ||
          (await StorageService.get(APP_CONFIG.STORAGE_KEYS.AUTH_TOKEN));
        const authHeader = typeof token === 'string' && token.trim().length > 0 ? `Bearer ${token}` : '';
        const tenant = tenantService.getSelectedTenant();
        const candidateBaseUrls = Array.from(
          new Set([
            tenant?.apiUrl,
            tenant?.domain ? `${APP_CONFIG.API_PROTOCOL}://${tenant.domain.replace(/^https?:\/\//i, '')}/api/v1` : undefined,
            APP_CONFIG.CENTRAL_API_URL,
          ].filter(Boolean) as string[])
        );

        for (const baseUrl of candidateBaseUrls) {
          try {
            const fallbackRes = await fetch(`${baseUrl}/mobile/warden/parcels/students-search?${params.toString()}`, {
              headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                ...(authHeader ? { Authorization: authHeader } : {}),
                ...(tenant?.code ? { 'X-Tenant-Code': tenant.code } : {}),
              },
            });

            if (!fallbackRes.ok) {
              continue;
            }

            const payload = await fallbackRes.json();
            const fallbackList = payload?.data ?? [];
            if (Array.isArray(fallbackList) && fallbackList.length > 0) {
              list = fallbackList;
              break;
            }
          } catch {
            // Ignore fallback failures and try next base URL.
          }
        }
      }

      setStudents(list);
      if (list.length === 0) Alert.alert('No students', 'No students found for this search.');
    } catch (e: any) {
      console.error('Search students error', e);
      Alert.alert('Error', e?.response?.data?.detail ?? 'Failed to search students.');
    } finally {
      setSearching(false);
    }
  }, [searchRoom, searchPhone]);

  const submitInform = useCallback(async () => {
    if (!selectedStudent) {
      Alert.alert('Select student', 'Select a student to inform.');
      return;
    }
    setSubmitting(true);
    try {
      await apiService.post('/mobile/warden/parcels', {
        student_id: parseInt(selectedStudent.id, 10),
        hostel_id: selectedStudent.hostel_id,
        room_number: selectedStudent.room_no !== 'N/A' ? selectedStudent.room_no : searchRoom.trim() || null,
        notes: notes.trim() || null,
      });
      Alert.alert('Student informed', 'The student has been notified with a 4-digit code. They can collect the parcel by showing the code to you.');
      setSelectedStudent(null);
      setNotes('');
      setSearchRoom('');
      setSearchPhone('');
      setStudents([]);
    } catch (e: any) {
      const msg = e?.response?.data?.detail ?? e?.response?.data?.message ?? e?.message ?? 'Failed to inform student.';
      Alert.alert('Error', msg);
    } finally {
      setSubmitting(false);
    }
  }, [selectedStudent, notes, searchRoom, searchPhone]);

  const submitReceive = useCallback(async () => {
    if (!receiveParcelId || receiveCode.length !== 4) {
      Alert.alert('Invalid', 'Enter the 4-digit code shown by the student.');
      return;
    }
    setSubmittingReceive(true);
    try {
      await apiService.post(`/mobile/warden/parcels/${receiveParcelId}/receive`, { code: receiveCode });
      Alert.alert('Parcel received', 'Parcel has been marked as received. Student has been notified.');
      setReceiveParcelId(null);
      setReceiveCode('');
      fetchParcels();
    } catch (e: any) {
      const msg = e?.response?.data?.detail ?? 'Invalid code or request failed.';
      Alert.alert('Error', msg);
    } finally {
      setSubmittingReceive(false);
    }
  }, [receiveParcelId, receiveCode, fetchParcels]);

  React.useEffect(() => {
    if (activeTab === 'receive') fetchParcels();
  }, [activeTab, fetchParcels]);

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Parcels" />

      <View style={styles.tabs}>
        <TouchableOpacity
          style={[styles.tab, activeTab === 'log' && styles.tabActive]}
          onPress={() => setActiveTab('log')}
        >
          <Text style={[styles.tabText, activeTab === 'log' && styles.tabTextActive]}>Log parcel</Text>
        </TouchableOpacity>
        <TouchableOpacity
          style={[styles.tab, activeTab === 'receive' && styles.tabActive]}
          onPress={() => setActiveTab('receive')}
        >
          <Text style={[styles.tabText, activeTab === 'receive' && styles.tabTextActive]}>Receive</Text>
        </TouchableOpacity>
      </View>

      <ScrollView
        style={styles.scroll}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={colors.primary} />}
      >
        {activeTab === 'log' && (
          <>
            <Text style={styles.sectionLabel}>Search by room or phone</Text>
            <TextInput
              style={styles.input}
              placeholder="Room number"
              value={searchRoom}
              onChangeText={setSearchRoom}
              placeholderTextColor="#999"
            />
            <TextInput
              style={styles.input}
              placeholder="Phone number"
              value={searchPhone}
              onChangeText={setSearchPhone}
              keyboardType="phone-pad"
              placeholderTextColor="#999"
            />
            <GradientButton style={styles.primaryButton} onPress={searchStudents} disabled={searching}>
              {searching ? <ActivityIndicator color="#fff" /> : <Text style={styles.primaryButtonText}>Search</Text>}
            </GradientButton>

            {students.length > 0 && (
              <>
                <Text style={styles.sectionLabel}>Select student to inform</Text>
                {students.map((s) => (
                  <TouchableOpacity
                    key={s.id}
                    style={[styles.studentCard, selectedStudent?.id === s.id && styles.studentCardSelected]}
                    onPress={() => setSelectedStudent(s)}
                  >
                    <Text style={styles.studentName}>{s.name}</Text>
                    <Text style={styles.studentMeta}>Room: {s.room_no} · {s.phone || '—'}</Text>
                  </TouchableOpacity>
                ))}
                {selectedStudent && (
                  <>
                    <TextInput
                      style={[styles.input, styles.notesInput]}
                      placeholder="Notes (optional)"
                      value={notes}
                      onChangeText={setNotes}
                      placeholderTextColor="#999"
                    />
                    <GradientButton style={styles.primaryButton} onPress={submitInform} disabled={submitting}>
                      {submitting ? <ActivityIndicator color="#fff" /> : <Text style={styles.primaryButtonText}>Inform student</Text>}
                    </GradientButton>
                  </>
                )}
              </>
            )}
          </>
        )}

        {activeTab === 'receive' && (
          <>
            {receiveParcelId ? (
              <View style={styles.receiveForm}>
                <Text style={styles.sectionLabel}>Enter 4-digit code from student</Text>
                <TextInput
                  style={styles.codeInput}
                  placeholder="0000"
                  value={receiveCode}
                  onChangeText={(t) => setReceiveCode(t.replace(/\D/g, '').slice(0, 4))}
                  keyboardType="number-pad"
                  maxLength={4}
                  placeholderTextColor="#999"
                />
                <View style={styles.receiveActions}>
                  <GradientButton style={styles.secondaryButton} onPress={() => { setReceiveParcelId(null); setReceiveCode(''); }}>
                    <Text style={styles.secondaryButtonText}>Cancel</Text>
                  </GradientButton>
                  <GradientButton style={styles.primaryButton} onPress={submitReceive} disabled={submittingReceive || receiveCode.length !== 4}>
                    {submittingReceive ? <ActivityIndicator color="#fff" /> : <Text style={styles.primaryButtonText}>Mark received</Text>}
                  </GradientButton>
                </View>
              </View>
            ) : (
              <>
                <Text style={styles.sectionLabel}>Pending parcels (show code to warden)</Text>
                {loadingParcels ? (
                  <ActivityIndicator style={styles.loader} color={colors.primary} />
                ) : parcels.length === 0 ? (
                  <Text style={styles.emptyText}>No pending parcels.</Text>
                ) : (
                  parcels.map((p) => (
                    <TouchableOpacity
                      key={p.id}
                      style={styles.parcelCard}
                      onPress={() => setReceiveParcelId(p.id)}
                    >
                      <Text style={styles.parcelStudent}>{p.student_name}</Text>
                      <Text style={styles.parcelMeta}>Room: {p.room_number || '—'} · Tap to enter code</Text>
                    </TouchableOpacity>
                  ))
                )}
              </>
            )}
          </>
        )}
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f5f5' },
  tabs: { flexDirection: 'row', backgroundColor: '#fff', paddingHorizontal: 16, paddingBottom: 8 },
  tab: { paddingVertical: 10, paddingHorizontal: 16, marginRight: 8 },
  tabActive: { borderBottomWidth: 2, borderBottomColor: colors.primary },
  tabText: { fontSize: 15, color: '#666' },
  tabTextActive: { color: colors.primary, fontWeight: '600' },
  scroll: { flex: 1, padding: 16 },
  sectionLabel: { fontSize: 14, color: '#666', marginBottom: 8, marginTop: 16 },
  input: { backgroundColor: '#fff', borderRadius: 8, padding: 12, marginBottom: 12, fontSize: 16 },
  notesInput: { minHeight: 60 },
  codeInput: { backgroundColor: '#fff', borderRadius: 8, padding: 16, marginBottom: 16, fontSize: 24, letterSpacing: 8, textAlign: 'center' },
  primaryButton: { backgroundColor: colors.primary, borderRadius: 8, padding: 14, alignItems: 'center', marginTop: 8 },
  primaryButtonText: { color: '#fff', fontSize: 16, fontWeight: '600' },
  secondaryButton: { padding: 14, alignItems: 'center' },
  secondaryButtonText: { color: colors.primary, fontSize: 16 },
  receiveForm: { marginTop: 16 },
  receiveActions: { flexDirection: 'row', justifyContent: 'space-between', marginTop: 12 },
  studentCard: { backgroundColor: '#fff', padding: 14, borderRadius: 8, marginBottom: 8 },
  studentCardSelected: { borderWidth: 2, borderColor: colors.primary },
  studentName: { fontSize: 16, fontWeight: '600', color: '#333' },
  studentMeta: { fontSize: 13, color: '#666', marginTop: 4 },
  parcelCard: { backgroundColor: '#fff', padding: 14, borderRadius: 8, marginBottom: 8 },
  parcelStudent: { fontSize: 16, fontWeight: '600', color: '#333' },
  parcelMeta: { fontSize: 13, color: '#666', marginTop: 4 },
  loader: { marginVertical: 24 },
  emptyText: { color: '#666', marginTop: 16 },
});

export default WardenParcelScreen;
