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
import { apiService } from '../../../shared/services/api.service';
import { theme } from '../../../shared/theme/theme';
import { format } from 'date-fns';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface SportsRequest {
  id: number;
  court_name: string;
  sport_type: string;
  student_name: string;
  request_id?: string;
  date: string;
  time: string;
  status: string;
}

interface Props {
  navigation: any;
}

const SPORTS_OPTIONS = ['All Sports', 'Basketball', 'Football', 'Tennis', 'Badminton', 'Cricket', 'Volleyball', 'Table Tennis'];
const DATE_OPTIONS = ['All Dates', 'Today', 'Tomorrow', 'This Week', 'This Month'];

export const SportsManagerActiveRequestsScreen: React.FC<Props> = ({ navigation }) => {
  const [requests, setRequests] = useState<SportsRequest[]>([]);
  const [filteredRequests, setFilteredRequests] = useState<SportsRequest[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [selectedSport, setSelectedSport] = useState('All Sports');
  const [selectedDate, setSelectedDate] = useState('All Dates');

  const fetchRequests = async () => {
    try {
      // TODO: Replace with actual API endpoint when available
      // const response = await apiService.get('/sports/active-requests');
      // setRequests(response.data.data || []);
      
      // Mock data for now
      const mockRequests: SportsRequest[] = [
        {
          id: 1,
          court_name: 'Basketball Court 1',
          sport_type: 'Basketball',
          student_name: 'John Doe',
          request_id: 'REQ001',
          date: new Date().toISOString(),
          time: '10:00 AM',
          status: 'active',
        },
        {
          id: 2,
          court_name: 'Tennis Court 2',
          sport_type: 'Tennis',
          student_name: 'Jane Smith',
          request_id: 'REQ002',
          date: new Date().toISOString(),
          time: '2:00 PM',
          status: 'active',
        },
      ];
      setRequests(mockRequests);
      setFilteredRequests(mockRequests);
    } catch (error) {
      console.error('Failed to fetch active requests:', error);
      setRequests([]);
      setFilteredRequests([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchRequests();
  }, []);

  useEffect(() => {
    let filtered = [...requests];

    // Filter by sport
    if (selectedSport !== 'All Sports') {
      filtered = filtered.filter(req => req.sport_type === selectedSport);
    }

    // Filter by date
    if (selectedDate !== 'All Dates') {
      const now = new Date();
      filtered = filtered.filter(req => {
        const reqDate = new Date(req.date);
        switch (selectedDate) {
          case 'Today':
            return reqDate.toDateString() === now.toDateString();
          case 'Tomorrow':
            const tomorrow = new Date(now);
            tomorrow.setDate(tomorrow.getDate() + 1);
            return reqDate.toDateString() === tomorrow.toDateString();
          case 'This Week':
            const weekAgo = new Date(now);
            weekAgo.setDate(weekAgo.getDate() - 7);
            return reqDate >= weekAgo;
          case 'This Month':
            return reqDate.getMonth() === now.getMonth() && reqDate.getFullYear() === now.getFullYear();
          default:
            return true;
        }
      });
    }

    setFilteredRequests(filtered);
  }, [selectedSport, selectedDate, requests]);

  const onRefresh = () => {
    setRefreshing(true);
    fetchRequests();
  };

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Active Requests" />

      <ScrollView
        style={styles.scrollView}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
      >
        {/* Filter Section */}
        <View style={styles.filterSection}>
          {/* All Sports Filter */}
          <View style={styles.filterGroup}>
            <Text style={styles.filterLabel}>All Sports</Text>
            <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.filterScroll}>
              {SPORTS_OPTIONS.map((sport) => (
                <TouchableOpacity
                  key={sport}
                  style={[
                    styles.filterChip,
                    selectedSport === sport && styles.filterChipActive,
                  ]}
                  onPress={() => setSelectedSport(sport)}
                >
                  <Text
                    style={[
                      styles.filterChipText,
                      selectedSport === sport && styles.filterChipTextActive,
                    ]}
                  >
                    {sport}
                  </Text>
                </TouchableOpacity>
              ))}
            </ScrollView>
          </View>

          {/* All Dates Filter */}
          <View style={styles.filterGroup}>
            <Text style={styles.filterLabel}>All Dates</Text>
            <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.filterScroll}>
              {DATE_OPTIONS.map((date) => (
                <TouchableOpacity
                  key={date}
                  style={[
                    styles.filterChip,
                    selectedDate === date && styles.filterChipActive,
                  ]}
                  onPress={() => setSelectedDate(date)}
                >
                  <Text
                    style={[
                      styles.filterChipText,
                      selectedDate === date && styles.filterChipTextActive,
                    ]}
                  >
                    {date}
                  </Text>
                </TouchableOpacity>
              ))}
            </ScrollView>
          </View>

          {/* Total Active Request Card */}
          <View style={styles.totalCard}>
            <Text style={styles.totalLabel}>Total Active Request</Text>
            <Text style={styles.totalValue}>{filteredRequests.length}</Text>
          </View>
        </View>

        {/* Sports Request Cards */}
        <View style={styles.content}>
          {filteredRequests.map((request) => (
            <View key={request.id} style={styles.requestCard}>
              <View style={styles.cardHeader}>
                <Text style={styles.courtName}>{request.court_name}</Text>
                <View style={styles.sportBadge}>
                  <Text style={styles.sportText}>{request.sport_type}</Text>
                </View>
              </View>
              
              <View style={styles.cardBody}>
                <View style={styles.studentRow}>
                  <Icon name="account" size={16} color={theme.colors.textSecondary} />
                  <Text style={styles.studentName}>{request.student_name}</Text>
                  {request.request_id && (
                    <Text style={styles.requestId}>({request.request_id})</Text>
                  )}
                </View>
                
                <View style={styles.dateTimeRow}>
                  <Icon name="calendar" size={16} color={theme.colors.textSecondary} />
                  <Text style={styles.dateTimeText}>
                    {format(new Date(request.date), 'MMM dd, yyyy')} • {request.time}
                  </Text>
                </View>
              </View>
            </View>
          ))}

          {filteredRequests.length === 0 && !loading && (
            <View style={styles.emptyState}>
              <Icon name="clipboard-outline" size={64} color={theme.colors.textMuted} />
              <Text style={styles.emptyStateText}>No Active Requests</Text>
              <Text style={styles.emptyStateSubtitle}>
                There are no active sports requests at the moment.
              </Text>
            </View>
          )}
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
  scrollView: {
    flex: 1,
  },
  filterSection: {
    backgroundColor: theme.colors.white,
    padding: 16,
    marginBottom: 8,
    ...theme.shadows.small,
  },
  filterGroup: {
    marginBottom: 16,
  },
  filterLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: theme.colors.textHeading,
    marginBottom: 8,
  },
  filterScroll: {
    marginHorizontal: -4,
  },
  filterChip: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: theme.colors.background,
    marginRight: 8,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  filterChipActive: {
    backgroundColor: theme.colors.primary,
    borderColor: theme.colors.primary,
  },
  filterChipText: {
    fontSize: 14,
    fontWeight: '500',
    color: theme.colors.text,
  },
  filterChipTextActive: {
    color: theme.colors.white,
  },
  totalCard: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: theme.colors.accentMuted,
    padding: 16,
    borderRadius: 12,
    borderLeftWidth: 4,
    borderLeftColor: theme.colors.primary,
  },
  totalLabel: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.textHeading,
  },
  totalValue: {
    fontSize: 24,
    fontWeight: '700',
    color: theme.colors.primary,
  },
  content: {
    padding: 16,
  },
  requestCard: {
    backgroundColor: theme.colors.white,
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    ...theme.shadows.medium,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  courtName: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.textHeading,
    flex: 1,
  },
  sportBadge: {
    backgroundColor: theme.colors.primary,
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderRadius: 8,
  },
  sportText: {
    fontSize: 12,
    fontWeight: '600',
    color: theme.colors.white,
  },
  cardBody: {
    gap: 8,
  },
  studentRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  studentName: {
    fontSize: 14,
    fontWeight: '500',
    color: theme.colors.text,
  },
  requestId: {
    fontSize: 12,
    color: theme.colors.textSecondary,
  },
  dateTimeRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  dateTimeText: {
    fontSize: 13,
    color: theme.colors.textSecondary,
  },
  emptyState: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 60,
  },
  emptyStateText: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.textHeading,
    marginTop: 16,
    marginBottom: 4,
  },
  emptyStateSubtitle: {
    fontSize: 14,
    color: theme.colors.textSecondary,
    textAlign: 'center',
  },
});

export default SportsManagerActiveRequestsScreen;
