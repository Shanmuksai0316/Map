import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  FlatList,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import DateTimePicker from '@react-native-community/datetimepicker';
import { apiService } from '../../../shared/services/api.service';
import { colors } from '../../../shared/theme/colors';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';
import { GradientButton } from '../../../shared/components/GradientButton';

interface HistoryItem {
  id: number;
  type: 'outpass' | 'leave' | 'sick_leave' | 'guest_entry';
  student_name: string;
  action: string;
  created_at: string;
  status: string;
}

interface Props {
  navigation: any;
}

export const WardenHistoryScreen: React.FC<Props> = ({ navigation }) => {
  const [history, setHistory] = useState<HistoryItem[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [selectedDate, setSelectedDate] = useState(new Date());
  const [showDatePicker, setShowDatePicker] = useState(false);
  const [filter, setFilter] = useState<string>('all');

  const fetchHistory = useCallback(async () => {
    try {
      const response = await apiService.get<any>('/warden/history', {
        params: {
          date: selectedDate.toISOString().split('T')[0],
          type: filter !== 'all' ? filter : undefined,
        },
      });
      // apiService returns data directly, but backend may wrap it in { data: ... }
      setHistory(response?.data || response || []);
    } catch (error) {
      console.error('Failed to fetch history:', error);
      // Show empty state - no mock data in production
      setHistory([]);
    }
  }, [selectedDate, filter]);

  useEffect(() => {
    fetchHistory();
  }, [fetchHistory]);

  const onRefresh = async () => {
    setRefreshing(true);
    await fetchHistory();
    setRefreshing(false);
  };

  const getTypeIcon = (type: string) => {
    switch (type) {
      case 'outpass': return 'file-document-outline';
      case 'leave': return 'calendar-clock';
      case 'sick_leave': return 'hospital-box-outline';
      case 'guest_entry': return 'account-multiple-plus';
      default: return 'file-outline';
    }
  };

  const getTypeColor = (type: string) => {
    switch (type) {
      case 'outpass': return colors.info;
      case 'leave': return colors.primary;
      case 'sick_leave': return colors.error;
      case 'guest_entry': return colors.success;
      default: return colors.textSecondary;
    }
  };

  const formatTime = (dateString: string) => {
    return new Date(dateString).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  };

  const renderItem = ({ item }: { item: HistoryItem }) => (
    <View style={styles.card}>
      <View style={[styles.iconContainer, { backgroundColor: getTypeColor(item.type) + '20' }]}>
        <Icon name={getTypeIcon(item.type)} size={20} color={getTypeColor(item.type)} />
      </View>
      <View style={styles.cardContent}>
        <Text style={styles.action}>{item.action}</Text>
        <Text style={styles.studentName}>{item.student_name}</Text>
        <Text style={styles.time}>{formatTime(item.created_at)}</Text>
      </View>
    </View>
  );

  const filterOptions = ['all', 'outpass', 'leave', 'sick_leave', 'guest_entry'];

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="History" />
      <View style={styles.dateSelector}>
        <GradientButton style={styles.dateButton} onPress={() => setShowDatePicker(true)}>
          <Icon name="calendar" size={20} color={colors.primary} />
          <Text style={styles.dateText}>{selectedDate.toLocaleDateString([], { day: 'numeric', month: 'short', year: 'numeric' })}</Text>
          <Icon name="chevron-down" size={20} color={colors.textSecondary} />
        </GradientButton>
      </View>

      {showDatePicker && (
        <DateTimePicker
          value={selectedDate}
          mode="date"
          onChange={(event, date) => {
            setShowDatePicker(false);
            if (date) setSelectedDate(date);
          }}
        />
      )}

      <View style={styles.filterContainer}>
        <FlatList
          horizontal
          data={filterOptions}
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.filterList}
          renderItem={({ item }) => (
            <TouchableOpacity
              style={[styles.filterButton, filter === item && styles.filterActive]}
              onPress={() => setFilter(item)}
            >
              <Text style={[styles.filterText, filter === item && styles.filterTextActive]}>
                {item === 'all' ? 'All' : item.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase())}
              </Text>
            </TouchableOpacity>
          )}
          keyExtractor={item => item}
        />
      </View>

      <FlatList
        data={history}
        renderItem={renderItem}
        keyExtractor={item => item.id.toString()}
        contentContainerStyle={styles.listContent}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
        ListEmptyComponent={
          <View style={styles.emptyState}>
            <Icon name="history" size={64} color={colors.border} />
            <Text style={styles.emptyText}>No history for this date</Text>
          </View>
        }
      />
    </View>
  );
};

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  subHeader: { paddingHorizontal: 16, paddingTop: 12, paddingBottom: 8 },
  subHeaderTitle: { fontSize: 20, fontWeight: '700', color: colors.textHeading },
  dateSelector: { padding: 16 },
  dateButton: { flexDirection: 'row', alignItems: 'center', backgroundColor: colors.white, padding: 12, borderRadius: 12, borderWidth: 1, borderColor: colors.border },
  dateText: { flex: 1, fontSize: 15, fontWeight: '500', color: colors.text, marginLeft: 8 },
  filterContainer: { paddingBottom: 8 },
  filterList: { paddingHorizontal: 16, gap: 8 },
  filterButton: { paddingHorizontal: 16, paddingVertical: 8, borderRadius: 20, backgroundColor: colors.white, borderWidth: 1, borderColor: colors.border },
  filterActive: { backgroundColor: colors.primary, borderColor: colors.primary },
  filterText: { fontSize: 13, color: colors.textSecondary, fontWeight: '500' },
  filterTextActive: { color: colors.white },
  listContent: { padding: 16, paddingTop: 8 },
  card: { flexDirection: 'row', alignItems: 'center', backgroundColor: colors.white, borderRadius: 12, padding: 12, marginBottom: 8, borderWidth: 1, borderColor: colors.border },
  iconContainer: { width: 40, height: 40, borderRadius: 20, justifyContent: 'center', alignItems: 'center', marginRight: 12 },
  cardContent: { flex: 1 },
  action: { fontSize: 14, fontWeight: '600', color: colors.text },
  studentName: { fontSize: 13, color: colors.textSecondary, marginTop: 2 },
  time: { fontSize: 12, color: colors.textMuted, marginTop: 4 },
  emptyState: { alignItems: 'center', justifyContent: 'center', paddingVertical: 60 },
  emptyText: { fontSize: 16, color: colors.textMuted, marginTop: 16 },
});

export default WardenHistoryScreen;
