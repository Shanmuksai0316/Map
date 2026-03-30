import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  TextInput,
} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useAuthStore } from '../../store/auth.store';
import { apiService } from '../../services/api.service';
import { APP_CONFIG } from '../../config/app.config';
import { colors } from '../../theme/colors';
import { format } from 'date-fns';
import { errorHandler } from '../../utils/errorHandler';
import { ErrorState } from '../../components/shared/ErrorState';
import { ActivityIndicator } from 'react-native';

interface GateLog {
  id: number;
  student_name: string;
  roll_number?: string;
  direction: 'Exit' | 'Entry';
  timestamp: string;
  guard_name: string;
  pass_id?: number;
  status: string;
}

export const GuardHistoryScreen = ({ navigation }: any) => {
  const { user } = useAuthStore();
  const [logs, setLogs] = useState<GateLog[]>([]);
  const [filteredLogs, setFilteredLogs] = useState<GateLog[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<any>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [dateFilter, setDateFilter] = useState<'today' | 'yesterday' | 'week' | 'month' | 'custom'>('today');

  const fetchHistory = async () => {
    try {
      setError(null);
      const response = await apiService.get<{ data: GateLog[] }>(
        `${APP_CONFIG.ENDPOINTS.GATE_ENTRIES}?limit=100&sort=timestamp:desc`
      );
      setLogs(response.data || []);
      setFilteredLogs(response.data || []);
    } catch (err) {
      console.error('History fetch error:', err);
      const errorDetails = errorHandler.handleError(err);
      setError(errorDetails);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchHistory();
  }, []);

  useEffect(() => {
    let filtered = logs;

    // Apply date filter
    const now = new Date();
    filtered = filtered.filter((log) => {
      const logDate = new Date(log.timestamp);
      switch (dateFilter) {
        case 'today':
          return logDate.toDateString() === now.toDateString();
        case 'yesterday':
          const yesterday = new Date(now);
          yesterday.setDate(yesterday.getDate() - 1);
          return logDate.toDateString() === yesterday.toDateString();
        case 'week':
          const weekAgo = new Date(now);
          weekAgo.setDate(weekAgo.getDate() - 7);
          return logDate >= weekAgo;
        case 'month':
          const monthAgo = new Date(now);
          monthAgo.setMonth(monthAgo.getMonth() - 1);
          return logDate >= monthAgo;
        default:
          return true;
      }
    });

    // Apply search filter
    if (searchQuery.trim()) {
      filtered = filtered.filter(
        (log) =>
          log.student_name.toLowerCase().includes(searchQuery.toLowerCase()) ||
          log.roll_number?.toLowerCase().includes(searchQuery.toLowerCase())
      );
    }

    setFilteredLogs(filtered);
  }, [searchQuery, dateFilter, logs]);

  const groupedLogs = filteredLogs.reduce((acc, log) => {
    const date = format(new Date(log.timestamp), 'MMM dd, yyyy');
    if (!acc[date]) {
      acc[date] = [];
    }
    acc[date].push(log);
    return acc;
  }, {} as Record<string, GateLog[]>);

  const onRefresh = () => {
    setRefreshing(true);
    fetchHistory();
  };

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.headerTitle}>History</Text>
      </View>

      <View style={styles.filters}>
        <View style={styles.searchContainer}>
          <Ionicons name="search" size={20} color={colors.textMuted} style={styles.searchIcon} />
          <TextInput
            style={styles.searchInput}
            value={searchQuery}
            onChangeText={setSearchQuery}
            placeholder="Search by student name or roll number"
            placeholderTextColor={colors.textMuted}
          />
        </View>

        <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.dateFilters}>
          {(['today', 'yesterday', 'week', 'month'] as const).map((filter) => (
            <TouchableOpacity
              key={filter}
              style={[
                styles.filterButton,
                dateFilter === filter && styles.filterButtonActive,
              ]}
              onPress={() => setDateFilter(filter)}>
              <Text
                style={[
                  styles.filterButtonText,
                  dateFilter === filter && styles.filterButtonTextActive,
                ]}>
                {filter.charAt(0).toUpperCase() + filter.slice(1)}
              </Text>
            </TouchableOpacity>
          ))}
        </ScrollView>
      </View>

      <ScrollView
        style={styles.content}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}>
        {loading ? (
          <View style={styles.loadingContainer}>
            <ActivityIndicator size="large" color={colors.primary} />
            <Text style={styles.loadingText}>Loading history...</Text>
          </View>
        ) : error ? (
          <ErrorState error={error} onRetry={fetchHistory} />
        ) : Object.keys(groupedLogs).length === 0 ? (
          <View style={styles.emptyState}>
            <Ionicons name="time-outline" size={64} color={colors.textMuted} />
            <Text style={styles.emptyText}>No history found</Text>
            <Text style={styles.emptySubtext}>
              {searchQuery ? 'Try a different search term' : 'No gate entries recorded for the selected period'}
            </Text>
          </View>
        ) : (
          Object.entries(groupedLogs).map(([date, dateLogs]) => (
            <View key={date} style={styles.dateGroup}>
              <Text style={styles.dateHeader}>{date}</Text>
              {dateLogs.map((log) => (
                <View key={log.id} style={styles.logCard}>
                  <View style={styles.logHeader}>
                    <View>
                      <Text style={styles.logStudent}>{log.student_name}</Text>
                      {log.roll_number && (
                        <Text style={styles.logRollNumber}>{log.roll_number}</Text>
                      )}
                    </View>
                    <View
                      style={[
                        styles.directionBadge,
                        { backgroundColor: log.direction === 'Entry' ? '#4CAF50' : '#FF9800' },
                      ]}>
                      <Text style={styles.directionText}>{log.direction}</Text>
                    </View>
                  </View>
                  <View style={styles.logDetails}>
                    <Text style={styles.logDetail}>
                      <Text style={styles.logDetailLabel}>Time:</Text> {format(new Date(log.timestamp), 'HH:mm')}
                    </Text>
                    {log.guard_name && (
                      <Text style={styles.logDetail}>
                        <Text style={styles.logDetailLabel}>Guard:</Text> {log.guard_name}
                      </Text>
                    )}
                    {log.pass_id && (
                      <Text style={styles.logDetail}>
                        <Text style={styles.logDetailLabel}>Pass ID:</Text> {log.pass_id}
                      </Text>
                    )}
                    <Text style={styles.logDetail}>
                      <Text style={styles.logDetailLabel}>Status:</Text> {log.status}
                    </Text>
                  </View>
                </View>
              ))}
            </View>
          ))
        )}
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  header: {
    backgroundColor: colors.primary,
    padding: 20,
    paddingTop: 60,
  },
  headerTitle: {
    color: colors.surface,
    fontSize: 24,
    fontWeight: 'bold',
  },
  filters: {
    backgroundColor: colors.surface,
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  searchContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.background,
    borderRadius: 8,
    paddingHorizontal: 12,
    marginBottom: 12,
  },
  searchIcon: {
    marginRight: 8,
  },
  searchInput: {
    flex: 1,
    paddingVertical: 10,
    fontSize: 16,
    color: colors.textPrimary,
  },
  dateFilters: {
    marginTop: 8,
  },
  filterButton: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: colors.background,
    marginRight: 8,
    borderWidth: 1,
    borderColor: colors.border,
  },
  filterButtonActive: {
    backgroundColor: colors.primary,
    borderColor: colors.primary,
  },
  filterButtonText: {
    fontSize: 14,
    color: colors.textPrimary,
    fontWeight: '500',
  },
  filterButtonTextActive: {
    color: colors.surface,
  },
  content: {
    flex: 1,
  },
  dateGroup: {
    marginBottom: 24,
  },
  dateHeader: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.textPrimary,
    paddingHorizontal: 20,
    paddingVertical: 12,
    backgroundColor: colors.surface,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  logCard: {
    backgroundColor: colors.surface,
    padding: 16,
    marginHorizontal: 20,
    marginTop: 8,
    borderRadius: 12,
    elevation: 1,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.1,
    shadowRadius: 2,
  },
  logHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 12,
  },
  logStudent: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: 4,
  },
  logRollNumber: {
    fontSize: 14,
    color: colors.textMuted,
  },
  directionBadge: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
  },
  directionText: {
    color: colors.surface,
    fontSize: 10,
    fontWeight: '600',
  },
  logDetails: {
    gap: 4,
  },
  logDetail: {
    fontSize: 12,
    color: colors.textMuted,
  },
  logDetailLabel: {
    fontWeight: '600',
    color: colors.textPrimary,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
    marginTop: 100,
  },
  loadingText: {
    fontSize: 16,
    color: colors.textSecondary,
    marginTop: 16,
  },
  emptyState: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
    marginTop: 100,
  },
  emptyText: {
    fontSize: 18,
    fontWeight: 'bold',
    color: colors.text,
    marginTop: 16,
    marginBottom: 8,
  },
  emptySubtext: {
    fontSize: 14,
    color: colors.textMuted,
    textAlign: 'center',
  },
});

