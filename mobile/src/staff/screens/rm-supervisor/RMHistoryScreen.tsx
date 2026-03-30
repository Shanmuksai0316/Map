import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { theme } from '../../../shared/theme/theme';
import { apiService } from '../../../shared/services/api.service';
import { Request } from '../../../shared/types';
import { APP_CONFIG } from '../../../shared/config/app.config';
import { formatDistanceToNow } from 'date-fns';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface Props {
  navigation: any;
}

const COMPLETED_STATUSES = ['resolved', 'closed', 'completed'];

export const RMHistoryScreen: React.FC<Props> = ({ navigation }) => {
  const [requests, setRequests] = useState<Request[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const fetchHistory = async () => {
    try {
      const response = await apiService.get<{ data: Request[] }>(
        APP_CONFIG.ENDPOINTS.SUPERVISOR_TICKETS
      );
      const data = (response as any)?.data?.data ?? (response as any)?.data ?? [];
      const completed = (Array.isArray(data) ? data : [])
        .filter((r: any) => {
          const type = String(r?.type ?? r?.category ?? '').toLowerCase();
          return type === 'repair_maintenance' || type === 'maintenance' || type === 'repair';
        })
        .filter((r: Request) => COMPLETED_STATUSES.includes((r.status || '').toLowerCase()))
        .sort((a: Request, b: Request) =>
          new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
        );
      setRequests(completed);
    } catch {
      setRequests([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchHistory();
  }, []);

  const onRefresh = () => {
    setRefreshing(true);
    fetchHistory();
  };

  const getStatusColor = (status: string) => {
    switch ((status || '').toLowerCase()) {
      case 'open':
      case 'pending':
        return theme.colors.warning;
      case 'in_progress':
        return theme.colors.info;
      case 'resolved':
      case 'closed':
      case 'completed':
        return theme.colors.success;
      default:
        return theme.colors.textMuted;
    }
  };

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="History" />

      <ScrollView
        style={styles.scrollView}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
      >
        <View style={styles.content}>
          {requests.length === 0 && !loading && (
            <View style={styles.emptyState}>
              <Icon name="history" size={64} color={theme.colors.textMuted} />
              <Text style={styles.emptyStateText}>No completed requests yet</Text>
            </View>
          )}
          {requests.map((request) => (
            <View key={request.id} style={styles.requestCard}>
              <View style={styles.cardHeader}>
                <View style={styles.requestInfo}>
                  <Text style={styles.requestId}>Request #{request.id}</Text>
                  <View style={styles.requestTitleRow}>
                    <Text style={styles.requestIssue} numberOfLines={1}>
                      {request.title || request.issue || 'No Title'}
                    </Text>
                  </View>
                  <Text style={styles.requestStudent}>
                    {request.student_name} • Room {request.room_number || 'N/A'}
                  </Text>
                  <Text style={styles.requestTime}>
                    {formatDistanceToNow(new Date(request.created_at), { addSuffix: true })}
                  </Text>
                </View>
                <View
                  style={[
                    styles.statusBadge,
                    { backgroundColor: getStatusColor(request.status) },
                  ]}
                >
                  <Text style={styles.statusText}>COMPLETED</Text>
                </View>
              </View>
            </View>
          ))}
        </View>
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  scrollView: { flex: 1 },
  content: { padding: 16 },
  requestCard: {
    backgroundColor: theme.colors.card,
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  cardHeader: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
  },
  requestInfo: { flex: 1 },
  requestId: {
    fontSize: 12,
    color: theme.colors.textSecondary,
    marginBottom: 4,
  },
  requestTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 4,
  },
  requestIssue: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.text,
    flex: 1,
  },
  requestStudent: {
    fontSize: 14,
    color: theme.colors.textSecondary,
    marginBottom: 2,
  },
  requestTime: {
    fontSize: 12,
    color: theme.colors.textMuted,
  },
  statusBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 8,
  },
  statusText: {
    fontSize: 11,
    fontWeight: '600',
    color: theme.colors.white,
  },
  emptyState: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 60,
  },
  emptyStateText: {
    fontSize: 16,
    color: theme.colors.textMuted,
    marginTop: 16,
    textAlign: 'center',
  },
});

export default RMHistoryScreen;
