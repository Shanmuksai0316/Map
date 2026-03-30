import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  FlatList,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  TextInput,
  Linking,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { apiService } from '../../../shared/services/api.service';
import { theme } from '../../../shared/theme/theme';
import type { StaffMember } from '../../../shared/types';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface Props {
  navigation: any;
}

const getRoleColor = (role: string): string => {
  const colors: Record<string, string> = {
    'Rector': '#2563EB',
    'Warden': '#059669',
    'Guard': '#DC2626',
    'HK Supervisor': '#0891B2',
    'RM Supervisor': '#EA580C',
    'Sports Manager': '#16A34A',
    'Laundry Manager': '#9333EA',
  };
  return colors[role] || theme.colors.textSecondary;
};

const getDepartmentFromRole = (role: string): string => {
  const departments: Record<string, string> = {
    'Rector': 'Administration',
    'Warden': 'Hostel Management',
    'Guard': 'Security',
    'HK Supervisor': 'Housekeeping',
    'RM Supervisor': 'Maintenance',
    'Sports Manager': 'Sports & Recreation',
    'Laundry Manager': 'Laundry Services',
  };
  return departments[role] || role;
};

export const MyStaffScreen: React.FC<Props> = ({ navigation }) => {
  const [staff, setStaff] = useState<StaffMember[]>([]);
  const [filteredStaff, setFilteredStaff] = useState<StaffMember[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');

  const fetchStaff = useCallback(async () => {
    try {
      const pathsToTry = ['/mobile/campus-manager/staff', '/campus-manager/staff'];
      let data: StaffMember[] = [];

      for (const path of pathsToTry) {
        try {
          const response = await apiService.get<{ data: StaffMember[] }>(path);
          data = response.data || [];
          break;
        } catch (error: any) {
          const status = error?.response?.status;
          const isRetryable = status === 404 || status === 403;
          if (!isRetryable || pathsToTry.indexOf(path) === pathsToTry.length - 1) {
            throw error;
          }
        }
      }

      setStaff(data);
      setFilteredStaff(data);
    } catch (error) {
      console.error('Failed to fetch staff:', error);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchStaff();
  }, [fetchStaff]);

  useEffect(() => {
    let filtered = staff;

    if (searchQuery) {
      const query = searchQuery.toLowerCase();
      filtered = filtered.filter(
        (s) =>
          s.name.toLowerCase().includes(query) ||
          s.employee_id?.toLowerCase().includes(query) ||
          s.role.toLowerCase().includes(query)
      );
    }

    setFilteredStaff(filtered);
  }, [searchQuery, staff]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await fetchStaff();
    setRefreshing(false);
  }, [fetchStaff]);

  const handleCall = (phone: string) => {
    Linking.openURL(`tel:${phone}`);
  };

  const renderStaffItem = ({ item }: { item: StaffMember }) => {
    const roleColor = getRoleColor(item.role);
    const department = getDepartmentFromRole(item.role);

    return (
      <View style={styles.staffCard}>
        {/* Header Row: Employee ID and Status */}
        <View style={styles.staffHeader}>
          <View style={styles.employeeIdBadge}>
            <Text style={styles.employeeIdText}>{item.employee_id || `EMP${item.id}`}</Text>
          </View>
          <View style={[styles.statusIndicator, { backgroundColor: item.is_active ? theme.colors.success : theme.colors.textMuted }]} />
        </View>

        {/* Staff Name */}
        <Text style={styles.staffName}>{item.name}</Text>

        {/* Role + Department Badges */}
        <View style={styles.badgesRow}>
          <View style={[styles.roleBadge, { backgroundColor: roleColor + '20' }]}>
            <Text style={[styles.roleText, { color: roleColor }]}>{item.role}</Text>
          </View>
          <View style={[styles.departmentBadge, { backgroundColor: roleColor + '15' }]}>
            <Text style={[styles.departmentText, { color: roleColor }]}>{department}</Text>
          </View>
        </View>

        {/* Contact Row */}
        <View style={styles.contactRow}>
          <View style={styles.contactInfo}>
            <Icon name="phone" size={16} color={theme.colors.textSecondary} />
            <Text style={styles.contactText}>{item.phone || 'No phone'}</Text>
          </View>
          {item.phone && (
            <GradientButton
              style={styles.callButton}
              onPress={() => handleCall(item.phone!)}
            >
              <Icon name="phone" size={18} color={theme.colors.white} />
            </GradientButton>
          )}
        </View>
      </View>
    );
  };

  const renderTableHeader = () => (
    <View style={styles.tableHeader}>
      <Text style={[styles.tableHeaderText, { flex: 0.8 }]}>Employee ID</Text>
      <Text style={[styles.tableHeaderText, { flex: 1.2 }]}>Name</Text>
      <Text style={[styles.tableHeaderText, { flex: 1 }]}>Department</Text>
      <Text style={[styles.tableHeaderText, { flex: 0.8 }]}>Contact</Text>
    </View>
  );

  const renderEmptyState = () => (
    <View style={styles.emptyState}>
      <Icon name="account-group-outline" size={64} color={theme.colors.border} />
      <Text style={styles.emptyTitle}>No Staff Found</Text>
      <Text style={styles.emptySubtitle}>
        {searchQuery
          ? 'Try adjusting your search'
          : 'No staff members assigned to your campus'}
      </Text>
    </View>
  );

  return (
    <View style={styles.container}>
      <StaffScreenHeader
        onBack={() => navigation.goBack()}
        onNotificationsPress={() => navigation.navigate('Notifications')}  title="My Staff" />

      {/* Search */}
      <View style={styles.searchContainer}>
        <View style={styles.searchInputContainer}>
          <Icon name="magnify" size={20} color={theme.colors.textMuted} />
          <TextInput
            style={styles.searchInput}
            placeholder="Search by name, ID, or department..."
            value={searchQuery}
            onChangeText={setSearchQuery}
            placeholderTextColor={theme.colors.textMuted}
          />
          {searchQuery.length > 0 && (
            <TouchableOpacity onPress={() => setSearchQuery('')}>
              <Icon name="close-circle" size={18} color={theme.colors.textMuted} />
            </TouchableOpacity>
          )}
        </View>
      </View>

      {/* Staff List */}
      <FlatList
        data={filteredStaff}
        keyExtractor={(item) => String(item.id)}
        renderItem={renderStaffItem}
        ListHeaderComponent={filteredStaff.length > 0 ? renderTableHeader : null}
        ListEmptyComponent={!isLoading ? renderEmptyState : null}
        contentContainerStyle={styles.listContent}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
      />
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  header: {
    display: 'none',
  },
  backButton: {
    display: 'none',
  },
  headerTitle: {
    display: 'none',
  },
  headerSubtitle: {
    display: 'none',
  },
  searchContainer: {
    padding: 16,
    backgroundColor: theme.colors.card,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
  },
  searchInputContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: theme.colors.surfaceMuted,
    borderRadius: 12,
    paddingHorizontal: 14,
    paddingVertical: 10,
  },
  searchInput: {
    flex: 1,
    fontSize: 15,
    color: theme.colors.text,
    marginLeft: 10,
  },
  tableHeader: {
    flexDirection: 'row',
    backgroundColor: theme.colors.surfaceMuted,
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderRadius: 8,
    marginBottom: 12,
  },
  tableHeaderText: {
    fontSize: 12,
    fontWeight: '600',
    color: theme.colors.textSecondary,
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  listContent: {
    padding: 16,
    flexGrow: 1,
  },
  staffCard: {
    backgroundColor: theme.colors.card,
    padding: 16,
    borderRadius: 12,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  staffHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 10,
  },
  employeeIdBadge: {
    backgroundColor: theme.colors.surfaceMuted,
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 6,
  },
  employeeIdText: {
    fontSize: 12,
    fontWeight: '600',
    color: theme.colors.textSecondary,
    fontFamily: 'monospace',
  },
  statusIndicator: {
    width: 10,
    height: 10,
    borderRadius: 5,
  },
  staffName: {
    fontSize: 18,
    fontWeight: '600',
    color: theme.colors.text,
    marginBottom: 8,
  },
  badgesRow: {
    flexDirection: 'row',
    alignItems: 'center',
    flexWrap: 'wrap',
    gap: 8,
    marginBottom: 12,
  },
  roleBadge: {
    alignSelf: 'flex-start',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 16,
  },
  roleText: {
    fontSize: 12,
    fontWeight: '700',
  },
  departmentBadge: {
    alignSelf: 'flex-start',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 16,
  },
  departmentText: {
    fontSize: 13,
    fontWeight: '600',
  },
  contactRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: theme.colors.border,
  },
  contactInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  contactText: {
    fontSize: 15,
    color: theme.colors.text,
    fontFamily: 'monospace',
  },
  callButton: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: theme.colors.success,
    justifyContent: 'center',
    alignItems: 'center',
  },
  emptyState: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: 48,
  },
  emptyTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: theme.colors.textSecondary,
    marginTop: 16,
  },
  emptySubtitle: {
    fontSize: 14,
    color: theme.colors.textMuted,
    marginTop: 4,
    textAlign: 'center',
  },
});

export default MyStaffScreen;
