import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  FlatList,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  Alert,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { apiService } from '../../../shared/services/api.service';
import { colors } from '../../../shared/theme/colors';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface SickLeave {
  id: number;
  student_name: string;
  student_id: string;
  room_number: string;
  symptoms: string;
  reported_at: string;
  acknowledged_at?: string;
  acknowledged_by?: string;
  status: string;
  severity?: 'low' | 'medium' | 'high';
  requires_medical_attention?: boolean;
}

interface Props {
  navigation: any;
}

export const SickLeavesScreen: React.FC<Props> = ({ navigation }) => {
  const [sickLeaves, setSickLeaves] = useState<SickLeave[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [acknowledging, setAcknowledging] = useState<number | null>(null);

  const fetchSickLeaves = useCallback(async () => {
    try {
      const response = await apiService.get<any>('/warden/sick-leaves/pending');
      // apiService returns data directly, but backend may wrap it in { data: ... }
      setSickLeaves(response?.data || response || []);
    } catch (error) {
      console.error('Failed to fetch sick leaves:', error);
      // Show empty state - no mock data in production
      setSickLeaves([]);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchSickLeaves();
  }, [fetchSickLeaves]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await fetchSickLeaves();
    setRefreshing(false);
  }, [fetchSickLeaves]);

  const handleAcknowledge = async (sickLeave: SickLeave) => {
    setAcknowledging(sickLeave.id);
    try {
      await apiService.post(`/sick-leaves/${sickLeave.id}/acknowledge`);
      Alert.alert('Success', 'Sick leave acknowledged');
      fetchSickLeaves();
    } catch (error) {
      Alert.alert('Error', 'Failed to acknowledge sick leave');
    } finally {
      setAcknowledging(null);
    }
  };

  const getSeverityColor = (severity?: string) => {
    switch (severity) {
      case 'high':
        return colors.error;
      case 'medium':
        return colors.warning;
      case 'low':
        return colors.success;
      default:
        return colors.textSecondary;
    }
  };

  const getSeverityLabel = (severity?: string) => {
    switch (severity) {
      case 'high':
        return 'High';
      case 'medium':
        return 'Medium';
      case 'low':
        return 'Low';
      default:
        return 'Unknown';
    }
  };

  const formatTimeAgo = (dateString: string) => {
    const now = new Date();
    const date = new Date(dateString);
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);

    if (diffMins < 60) {
      return `${diffMins} min${diffMins !== 1 ? 's' : ''} ago`;
    } else if (diffHours < 24) {
      return `${diffHours} hour${diffHours !== 1 ? 's' : ''} ago`;
    } else {
      return date.toLocaleDateString([], { day: 'numeric', month: 'short' });
    }
  };

  const renderSickLeave = ({ item }: { item: SickLeave }) => (
    <View style={styles.sickLeaveCard}>
      <View style={styles.cardHeader}>
        <View style={styles.studentInfo}>
          <View
            style={[
              styles.avatar,
              { backgroundColor: getSeverityColor(item.severity) },
            ]}
          >
            <Icon name="account-alert" size={22} color={colors.white} />
          </View>
          <View style={styles.studentDetails}>
            <Text style={styles.studentName}>{item.student_name}</Text>
            <Text style={styles.studentMeta}>
              {item.student_id} • Room {item.room_number}
            </Text>
          </View>
        </View>
        <View
          style={[
            styles.severityBadge,
            { backgroundColor: getSeverityColor(item.severity) + '20' },
          ]}
        >
          <View
            style={[
              styles.severityDot,
              { backgroundColor: getSeverityColor(item.severity) },
            ]}
          />
          <Text
            style={[
              styles.severityText,
              { color: getSeverityColor(item.severity) },
            ]}
          >
            {getSeverityLabel(item.severity)}
          </Text>
        </View>
      </View>

      <View style={styles.symptomsSection}>
        <View style={styles.symptomHeader}>
          <Icon name="clipboard-text-outline" size={16} color={colors.textSecondary} />
          <Text style={styles.symptomsLabel}>Symptoms</Text>
        </View>
        <Text style={styles.symptomsText}>{item.symptoms}</Text>
      </View>

      {item.requires_medical_attention && (
        <View style={styles.medicalAlert}>
          <Icon name="hospital-box" size={18} color={colors.error} />
          <Text style={styles.medicalAlertText}>
            May require medical attention
          </Text>
        </View>
      )}

      <View style={styles.cardFooter}>
        <View style={styles.timeInfo}>
          <Icon name="clock-outline" size={14} color={colors.textMuted} />
          <Text style={styles.timeText}>
            Reported {formatTimeAgo(item.reported_at)}
          </Text>
        </View>
        <GradientButton
          style={[
            styles.acknowledgeButton,
            acknowledging === item.id && styles.buttonDisabled,
          ]}
          onPress={() => handleAcknowledge(item)}
          disabled={acknowledging === item.id}
        >
          <Icon
            name={acknowledging === item.id ? 'loading' : 'check'}
            size={18}
            color={colors.white}
          />
          <Text style={styles.acknowledgeButtonText}>
            {acknowledging === item.id ? 'Processing...' : 'Acknowledge'}
          </Text>
        </GradientButton>
      </View>
    </View>
  );

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Sick Leaves" />
      <FlatList
        data={sickLeaves}
        renderItem={renderSickLeave}
        keyExtractor={item => item.id.toString()}
        contentContainerStyle={styles.listContent}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
        ListEmptyComponent={
          <View style={styles.emptyState}>
            <Icon name="emoticon-happy-outline" size={64} color={colors.success} />
            <Text style={styles.emptyText}>No sick reports</Text>
            <Text style={styles.emptySubtext}>
              All students are healthy!
            </Text>
          </View>
        }
      />
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
    paddingBottom: 20,
    paddingHorizontal: 20,
    flexDirection: 'row',
    alignItems: 'center',
  },
  headerTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: colors.white,
    flex: 1,
  },
  headerBadge: {
    backgroundColor: 'rgba(255,255,255,0.2)',
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderRadius: 12,
  },
  headerBadgeText: {
    color: colors.white,
    fontSize: 14,
    fontWeight: '600',
  },
  listContent: {
    padding: 16,
  },
  sickLeaveCard: {
    backgroundColor: colors.white,
    borderRadius: 16,
    padding: 16,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: colors.border,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 12,
  },
  studentInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  avatar: {
    width: 44,
    height: 44,
    borderRadius: 22,
    justifyContent: 'center',
    alignItems: 'center',
  },
  studentDetails: {
    marginLeft: 12,
    flex: 1,
  },
  studentName: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.text,
  },
  studentMeta: {
    fontSize: 13,
    color: colors.textSecondary,
    marginTop: 2,
  },
  severityBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 8,
  },
  severityDot: {
    width: 6,
    height: 6,
    borderRadius: 3,
    marginRight: 6,
  },
  severityText: {
    fontSize: 12,
    fontWeight: '600',
  },
  symptomsSection: {
    backgroundColor: colors.background,
    padding: 12,
    borderRadius: 8,
    marginBottom: 12,
  },
  symptomHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 6,
  },
  symptomsLabel: {
    fontSize: 11,
    color: colors.textSecondary,
    textTransform: 'uppercase',
    marginLeft: 6,
  },
  symptomsText: {
    fontSize: 14,
    color: colors.text,
    lineHeight: 20,
  },
  medicalAlert: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.errorLight,
    padding: 10,
    borderRadius: 8,
    marginBottom: 12,
  },
  medicalAlertText: {
    color: colors.error,
    fontSize: 13,
    fontWeight: '500',
    marginLeft: 8,
  },
  cardFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: colors.divider,
  },
  timeInfo: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  timeText: {
    fontSize: 12,
    color: colors.textMuted,
    marginLeft: 6,
  },
  acknowledgeButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.primary,
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: 10,
  },
  acknowledgeButtonText: {
    color: colors.white,
    fontSize: 14,
    fontWeight: '600',
    marginLeft: 6,
  },
  buttonDisabled: {
    opacity: 0.6,
  },
  emptyState: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 60,
  },
  emptyText: {
    fontSize: 18,
    fontWeight: '600',
    color: colors.text,
    marginTop: 16,
  },
  emptySubtext: {
    fontSize: 14,
    color: colors.textSecondary,
    marginTop: 4,
  },
});

export default SickLeavesScreen;
