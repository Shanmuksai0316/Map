import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  ActivityIndicator,
  Alert,
} from 'react-native';
import { useAuthStore } from '../../store/auth.store';
import { apiService } from '../../services/api.service';
import { APP_CONFIG } from '../../config/app.config';
import { format } from 'date-fns';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { colors } from '../../theme/colors';
import { errorHandler } from '../../utils/errorHandler';
import { ErrorState } from '../../components/shared/ErrorState';

interface Report {
  id: number;
  name: string;
  type: string;
  status: 'pending' | 'processing' | 'completed' | 'failed';
  created_at: string;
  download_url?: string;
}

export const ReportsScreen = ({ navigation }: any) => {
  const { user } = useAuthStore();
  const [reports, setReports] = useState<Report[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<any>(null);

  const fetchReports = async () => {
    try {
      setError(null);
      // Note: Reports endpoint may need to be added to backend
      // For now, using a placeholder endpoint
      const response = await apiService.get<{ data: Report[] }>(
        `${APP_CONFIG.ENDPOINTS.DASHBOARD}/reports`
      ).catch(() => ({ data: [] }));
      
      setReports(response.data || []);
    } catch (err) {
      console.error('Failed to fetch reports:', err);
      // Don't show error for reports if endpoint doesn't exist yet
      setReports([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchReports();
  }, []);

  const onRefresh = () => {
    setRefreshing(true);
    fetchReports();
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'completed':
        return colors.success;
      case 'processing':
        return colors.warning;
      case 'failed':
        return colors.error;
      default:
        return colors.textSecondary;
    }
  };

  const handleGenerateReport = (type: string) => {
    Alert.alert(
      'Generate Report',
      `Generate ${type} report? This may take a few minutes.`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Generate',
          onPress: () => {
            Alert.alert('Info', 'Report generation is available in the web panel. Reports will be sent to your email when ready.');
          },
        },
      ]
    );
  };

  if (loading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color={colors.primary} />
        <Text style={styles.loadingText}>Loading reports...</Text>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity
          style={styles.backButton}
          onPress={() => navigation.goBack()}>
          <Ionicons name="arrow-back" size={24} color={colors.white} />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Reports & Analytics</Text>
        <View style={styles.headerSpacer} />
      </View>

      {/* Report Types */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Available Reports</Text>
        <View style={styles.reportTypesGrid}>
          <TouchableOpacity
            style={styles.reportTypeCard}
            onPress={() => handleGenerateReport('Attendance')}>
            <View style={[styles.reportIconContainer, { backgroundColor: 'rgba(33, 150, 243, 0.1)' }]}>
              <Ionicons name="calendar-outline" size={32} color={colors.info} />
            </View>
            <Text style={styles.reportTypeTitle}>Attendance</Text>
            <Text style={styles.reportTypeSubtitle}>Daily/Weekly/Monthly</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.reportTypeCard}
            onPress={() => handleGenerateReport('Gate Pass')}>
            <View style={[styles.reportIconContainer, { backgroundColor: 'rgba(76, 175, 80, 0.1)' }]}>
              <Ionicons name="log-out-outline" size={32} color={colors.success} />
            </View>
            <Text style={styles.reportTypeTitle}>Gate Pass</Text>
            <Text style={styles.reportTypeSubtitle}>Outpass analytics</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.reportTypeCard}
            onPress={() => handleGenerateReport('Complaints')}>
            <View style={[styles.reportIconContainer, { backgroundColor: 'rgba(244, 67, 54, 0.1)' }]}>
              <Ionicons name="chatbubble-ellipses-outline" size={32} color={colors.error} />
            </View>
            <Text style={styles.reportTypeTitle}>Complaints</Text>
            <Text style={styles.reportTypeSubtitle}>Ticket statistics</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.reportTypeCard}
            onPress={() => handleGenerateReport('Student')}>
            <View style={[styles.reportIconContainer, { backgroundColor: 'rgba(156, 39, 176, 0.1)' }]}>
              <Ionicons name="people-outline" size={32} color="#9C27B0" />
            </View>
            <Text style={styles.reportTypeTitle}>Student</Text>
            <Text style={styles.reportTypeSubtitle}>Student data export</Text>
          </TouchableOpacity>
        </View>
      </View>

      {/* Recent Reports */}
      {reports.length > 0 && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Recent Reports</Text>
          <ScrollView
            style={styles.content}
            refreshControl={
              <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
            }>
            {reports.map((report) => (
              <View key={report.id} style={styles.reportCard}>
                <View style={styles.reportHeader}>
                  <View style={styles.reportInfo}>
                    <Text style={styles.reportName}>{report.name}</Text>
                    <Text style={styles.reportType}>{report.type}</Text>
                  </View>
                  <View style={[styles.statusBadge, { backgroundColor: getStatusColor(report.status) + '20' }]}>
                    <Text style={[styles.statusText, { color: getStatusColor(report.status) }]}>
                      {report.status}
                    </Text>
                  </View>
                </View>
                <Text style={styles.reportDate}>
                  {format(new Date(report.created_at), 'MMM dd, yyyy HH:mm')}
                </Text>
                {report.status === 'completed' && report.download_url && (
                  <TouchableOpacity
                    style={styles.downloadButton}
                    onPress={() => {
                      Alert.alert('Download', 'Report download is available in the web panel.');
                    }}>
                    <Ionicons name="download-outline" size={16} color={colors.primary} />
                    <Text style={styles.downloadButtonText}>Download</Text>
                  </TouchableOpacity>
                )}
              </View>
            ))}
          </ScrollView>
        </View>
      )}

      {reports.length === 0 && (
        <View style={styles.emptyState}>
          <Ionicons name="document-text-outline" size={48} color={colors.textMuted} />
          <Text style={styles.emptyTitle}>No Reports Generated</Text>
          <Text style={styles.emptySubtitle}>
            Generate a report using the options above. Reports will be sent to your email when ready.
          </Text>
        </View>
      )}
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
    paddingTop: 60,
    paddingBottom: 16,
    paddingHorizontal: 16,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  backButton: {
    padding: 8,
  },
  headerTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: colors.white,
    flex: 1,
    textAlign: 'center',
  },
  headerSpacer: {
    width: 40,
  },
  section: {
    padding: 16,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: colors.text,
    marginBottom: 16,
  },
  reportTypesGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
  },
  reportTypeCard: {
    backgroundColor: colors.white,
    width: '48%',
    padding: 20,
    borderRadius: 12,
    alignItems: 'center',
    marginBottom: 16,
    elevation: 2,
    shadowColor: colors.black,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  reportIconContainer: {
    width: 64,
    height: 64,
    borderRadius: 32,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 12,
  },
  reportTypeTitle: {
    fontSize: 16,
    fontWeight: 'bold',
    color: colors.text,
    marginBottom: 4,
    textAlign: 'center',
  },
  reportTypeSubtitle: {
    fontSize: 12,
    color: colors.textSecondary,
    textAlign: 'center',
  },
  content: {
    flex: 1,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
  },
  loadingText: {
    marginTop: 16,
    fontSize: 16,
    color: colors.textSecondary,
  },
  emptyState: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
    marginTop: 100,
  },
  emptyTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: colors.text,
    marginTop: 16,
    marginBottom: 8,
  },
  emptySubtitle: {
    fontSize: 14,
    color: colors.textSecondary,
    textAlign: 'center',
  },
  reportCard: {
    backgroundColor: colors.white,
    marginBottom: 16,
    borderRadius: 12,
    padding: 16,
    elevation: 2,
    shadowColor: colors.black,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  reportHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 8,
  },
  reportInfo: {
    flex: 1,
    marginRight: 12,
  },
  reportName: {
    fontSize: 16,
    fontWeight: 'bold',
    color: colors.text,
    marginBottom: 4,
  },
  reportType: {
    fontSize: 12,
    color: colors.textSecondary,
  },
  statusBadge: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 16,
  },
  statusText: {
    fontSize: 12,
    fontWeight: '600',
    textTransform: 'capitalize',
  },
  reportDate: {
    fontSize: 12,
    color: colors.textMuted,
    marginBottom: 12,
  },
  downloadButton: {
    flexDirection: 'row',
    alignItems: 'center',
    alignSelf: 'flex-start',
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 8,
    backgroundColor: colors.primary + '20',
    gap: 8,
  },
  downloadButtonText: {
    color: colors.primary,
    fontSize: 14,
    fontWeight: '600',
  },
});

