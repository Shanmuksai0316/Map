import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  Alert,
} from 'react-native';
import { GradientButton } from '../../shared/components/GradientButton';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useAuthStore } from '../../shared/store/auth.store';
import { apiService } from '../../shared/services/api.service';
import { Payment } from '../../types';
import { APP_CONFIG } from '../../shared/config/app.config';
import { format } from 'date-fns';
import { theme } from '../../shared/theme/theme';
import { errorHandler } from '../../shared/utils/errorHandler';
import { ErrorState, LoadingState } from '../../shared/components';

interface PaymentStatus {
  hostel_fee_paid: boolean;
  payment_mode?: string;
  payment_amount?: number;
  payment_date?: string;
  payment_reference?: string;
  payment_notes?: string;
  hostel_name?: string;
  academic_year?: string;
}

export const PaymentStatusScreen = ({ navigation }: any) => {
  const insets = useSafeAreaInsets();
  const { user } = useAuthStore();
  const [paymentStatus, setPaymentStatus] = useState<PaymentStatus | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const HEADER_ROW_HEIGHT = 52;
  const HEADER_PADDING_TOP = Math.max(insets.top, 10);
  const HEADER_PADDING_BOTTOM = 6;

  const fetchPaymentStatus = async () => {
    try {
      setError(null);
      setLoading(true);
      const response = await apiService.get<{ data: PaymentStatus }>(
        `${APP_CONFIG.ENDPOINTS.PROFILE}/payment-status`
      );
      setPaymentStatus(response.data || null);
    } catch (err) {
      const errorDetails = errorHandler.handleError(err);
      setError(errorDetails.message);
      setPaymentStatus(null);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchPaymentStatus();
  }, []);

  const onRefresh = () => {
    setRefreshing(true);
    fetchPaymentStatus();
  };

  const getPaymentModeLabel = (mode?: string) => {
    switch (mode) {
      case 'cash':
        return 'Cash';
      case 'upi':
        return 'UPI';
      case 'card':
        return 'Card';
      case 'bank':
        return 'Bank Transfer';
      case 'cheque':
        return 'Cheque';
      default:
        return 'Not Specified';
    }
  };

  const getPaymentModeIcon = (mode?: string) => {
    switch (mode) {
      case 'cash':
        return 'cash-outline';
      case 'upi':
        return 'phone-portrait-outline';
      case 'card':
        return 'card-outline';
      case 'bank':
        return 'business-outline';
      case 'cheque':
        return 'document-text-outline';
      default:
        return 'wallet-outline';
    }
  };

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'paid':
        return 'checkmark-circle';
      case 'pending':
        return 'time-outline';
      case 'failed':
        return 'close-circle';
      case 'refunded':
        return 'refresh-circle';
      default:
        return 'help-circle-outline';
    }
  };

  const getPaymentMethodIcon = (method: string) => {
    switch (method) {
      case 'upi':
        return 'wallet-outline';
      case 'card':
        return 'card-outline';
      case 'cash':
        return 'cash-outline';
      default:
        return 'cash-outline';
    }
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'paid':
        return theme.colors.success;
      case 'pending':
        return theme.colors.warning;
      case 'failed':
        return theme.colors.error;
      case 'refunded':
        return theme.colors.info;
      default:
        return theme.colors.textMuted;
    }
  };

  return (
    <View style={styles.container}>
      {/* Header */}
      <View
        style={[
          styles.header,
          {
            paddingTop: HEADER_PADDING_TOP,
            paddingBottom: HEADER_PADDING_BOTTOM,
            minHeight: HEADER_PADDING_TOP + HEADER_ROW_HEIGHT + HEADER_PADDING_BOTTOM,
          },
        ]}>
        <View style={[styles.headerRow, { height: HEADER_ROW_HEIGHT }]}>
          <TouchableOpacity
            style={styles.backButton}
            onPress={() => (navigation?.canGoBack?.() ? navigation.goBack() : navigation.navigate('Home'))}
            accessibilityLabel="Go back">
            <Ionicons name="arrow-back" size={24} color={theme.colors.primary} />
          </TouchableOpacity>
          <Text style={styles.headerTitle}>Payment Status</Text>
          <View style={styles.placeholder} />
        </View>
      </View>

      <ScrollView
        style={styles.content}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>
        {loading ? (
          <LoadingState message="Loading payment status..." />
        ) : error ? (
          <ErrorState error={error} onRetry={fetchPaymentStatus} />
        ) : paymentStatus ? (
          <>
            {/* Payment Status Card */}
            <View
              style={[
                styles.statusCard,
                {
                  backgroundColor: paymentStatus.hostel_fee_paid
                    ? '#E8F5E9'
                    : '#FFEBEE',
                },
              ]}>
              <View style={styles.statusIconContainer}>
                <Ionicons 
                  name={paymentStatus.hostel_fee_paid ? 'checkmark-circle' : 'warning'} 
                  size={48} 
                  color={paymentStatus.hostel_fee_paid ? theme.colors.success : theme.colors.warning}
                />
              </View>
              <Text style={styles.statusTitle}>
                {paymentStatus.hostel_fee_paid
                  ? 'Payment Completed'
                  : 'Payment Pending'}
              </Text>
              <Text style={styles.statusSubtitle}>
                {paymentStatus.hostel_fee_paid
                  ? 'Your hostel fee payment has been recorded'
                  : 'Please complete your hostel fee payment'}
              </Text>
            </View>

            {paymentStatus.hostel_fee_paid && (
              <>
                {/* Payment Details Card */}
                <View style={styles.detailsCard}>
                  <Text style={styles.sectionTitle}>Payment Details</Text>

                  <View style={styles.detailRow}>
                    <Text style={styles.detailLabel}>Academic Year:</Text>
                    <Text style={styles.detailValue}>
                      {paymentStatus.academic_year || 'N/A'}
                    </Text>
                  </View>

                  <View style={styles.detailRow}>
                    <Text style={styles.detailLabel}>Hostel:</Text>
                    <Text style={styles.detailValue}>
                      {paymentStatus.hostel_name || 'N/A'}
                    </Text>
                  </View>

                  <View style={styles.detailRow}>
                    <Text style={styles.detailLabel}>Amount Paid:</Text>
                    <Text style={[styles.detailValue, styles.amountText]}>
                      ₹{paymentStatus.payment_amount?.toLocaleString('en-IN') || '0'}
                    </Text>
                  </View>

                  <View style={styles.detailRow}>
                    <Text style={styles.detailLabel}>Payment Mode:</Text>
                    <View style={styles.paymentModeContainer}>
                      <Ionicons 
                        name={getPaymentModeIcon(paymentStatus.payment_mode)} 
                        size={20} 
                        color={theme.colors.text} 
                        style={{ marginRight: 8 }}
                      />
                      <Text style={styles.detailValue}>
                        {getPaymentModeLabel(paymentStatus.payment_mode)}
                      </Text>
                    </View>
                  </View>

                  {paymentStatus.payment_date && (
                    <View style={styles.detailRow}>
                      <Text style={styles.detailLabel}>Payment Date:</Text>
                      <Text style={styles.detailValue}>
                        {format(new Date(paymentStatus.payment_date), 'MMMM dd, yyyy')}
                      </Text>
                    </View>
                  )}

                  {paymentStatus.payment_reference && (
                    <View style={styles.detailRow}>
                      <Text style={styles.detailLabel}>Reference No:</Text>
                      <Text style={[styles.detailValue, styles.referenceText]}>
                        {paymentStatus.payment_reference}
                      </Text>
                    </View>
                  )}

                  {paymentStatus.payment_notes && (
                    <View style={styles.notesContainer}>
                      <Text style={styles.notesLabel}>Notes:</Text>
                      <Text style={styles.notesText}>
                        {paymentStatus.payment_notes}
                      </Text>
                    </View>
                  )}
                </View>

                {/* Important Notice */}
                <View style={styles.noticeCard}>
                  <Ionicons name="information-circle-outline" size={24} color={theme.colors.info} />
                  <View style={styles.noticeContent}>
                    <Text style={styles.noticeTitle}>Important Information</Text>
                    <Text style={styles.noticeText}>
                      • This is an offline payment tracking system
                    </Text>
                    <Text style={styles.noticeText}>
                      • Payment details are recorded by the administration
                    </Text>
                    <Text style={styles.noticeText}>
                      • For any discrepancies, please contact the campus office
                    </Text>
                    <Text style={styles.noticeText}>
                      • Official receipts are issued by the college administration
                    </Text>
                  </View>
                </View>
              </>
            )}

            {!paymentStatus.hostel_fee_paid && (
              <View style={styles.pendingCard}>
                <Text style={styles.pendingTitle}>Payment Instructions</Text>
                <Text style={styles.pendingText}>
                  Please complete your hostel fee payment through one of the following
                  methods:
                </Text>

                <View style={styles.paymentMethodsContainer}>
                  <View style={styles.paymentMethod}>
                    <Ionicons name="card-outline" size={32} color={theme.colors.primary} />
                    <Text style={styles.methodTitle}>Bank Transfer</Text>
                    <Text style={styles.methodDescription}>
                      Transfer to college account
                    </Text>
                  </View>

                  <View style={styles.paymentMethod}>
                    <Ionicons name="card-outline" size={32} color={theme.colors.primary} />
                    <Text style={styles.methodTitle}>Card Payment</Text>
                    <Text style={styles.methodDescription}>
                      Pay at campus office
                    </Text>
                  </View>

                  <View style={styles.paymentMethod}>
                    <Ionicons name="cash-outline" size={32} color={theme.colors.success} />
                    <Text style={styles.methodTitle}>Cash</Text>
                    <Text style={styles.methodDescription}>
                      Pay at accounts section
                    </Text>
                  </View>

                  <View style={styles.paymentMethod}>
                    <Ionicons name="document-text-outline" size={32} color={theme.colors.warning} />
                    <Text style={styles.methodTitle}>Cheque</Text>
                    <Text style={styles.methodDescription}>
                      Submit at campus office
                    </Text>
                  </View>
                </View>

                <View style={styles.contactCard}>
                  <Text style={styles.contactTitle}>Need Help?</Text>
                  <Text style={styles.contactText}>
                    Contact the campus office for payment assistance
                  </Text>
                  <GradientButton
                    style={styles.contactButton}
                    onPress={() =>
                      Alert.alert(
                        'Contact Information',
                        'Campus Office\nPhone: +91-9876543210\nEmail: accounts@college.edu'
                      )
                    }>
                    <Text style={styles.contactButtonText}>Contact Office</Text>
                  </GradientButton>
                </View>
              </View>
            )}
          </>
        ) : null}
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.white,
  },
  header: {
    backgroundColor: theme.colors.white,
    paddingHorizontal: theme.spacing.lg,
    ...theme.shadows.medium,
  },
  headerRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  backButton: {
    padding: theme.spacing.xs,
  },
  headerTitle: {
    color: theme.colors.primary,
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
  },
  placeholder: {
    width: 60,
  },
  content: {
    flex: 1,
    padding: theme.spacing.md,
  },
  statusCard: {
    borderRadius: theme.borderRadius.lg,
    padding: theme.spacing.lg,
    alignItems: 'center',
    marginBottom: theme.spacing.lg,
    ...theme.shadows.small,
  },
  statusIconContainer: {
    marginBottom: theme.spacing.md,
  },
  statusIcon: {
    fontSize: 64,
  },
  statusTitle: {
    fontSize: theme.fontSize.xxl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
    marginBottom: theme.spacing.sm,
  },
  statusSubtitle: {
    fontSize: theme.fontSize.md,
    color: theme.colors.textSecondary,
    textAlign: 'center',
  },
  detailsCard: {
    backgroundColor: theme.colors.white,
    borderRadius: theme.borderRadius.sm,
    padding: theme.spacing.lg,
    marginBottom: theme.spacing.md,
    ...theme.shadows.small,
  },
  sectionTitle: {
    fontSize: theme.fontSize.lg,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
    marginBottom: theme.spacing.md,
    paddingBottom: theme.spacing.sm,
    borderBottomWidth: 2,
    borderBottomColor: theme.colors.success,
  },
  detailRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: theme.spacing.sm,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.divider,
  },
  detailLabel: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
    fontWeight: theme.fontWeight.medium,
  },
  detailValue: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.text,
    fontWeight: theme.fontWeight.semibold,
    flex: 1,
    textAlign: 'right',
    marginLeft: theme.spacing.md,
  },
  amountText: {
    fontSize: theme.fontSize.lg,
    color: theme.colors.success,
    fontWeight: theme.fontWeight.bold,
  },
  paymentModeContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.sm,
  },
  paymentModeIcon: {
    fontSize: theme.fontSize.lg,
  },
  referenceText: {
    fontFamily: 'monospace',
    fontSize: theme.fontSize.xs,
  },
  notesContainer: {
    marginTop: theme.spacing.md,
    paddingTop: theme.spacing.md,
    borderTopWidth: 2,
    borderTopColor: theme.colors.divider,
  },
  notesLabel: {
    fontSize: theme.fontSize.sm,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.textSecondary,
    marginBottom: theme.spacing.sm,
  },
  notesText: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.text,
    lineHeight: 20,
  },
  noticeCard: {
    backgroundColor: '#E3F2FD',
    borderRadius: theme.borderRadius.sm,
    padding: theme.spacing.md,
    flexDirection: 'row',
    marginBottom: theme.spacing.md,
  },
  noticeIcon: {
    fontSize: theme.fontSize.xxl,
    marginRight: theme.spacing.sm,
  },
  noticeContent: {
    flex: 1,
  },
  noticeTitle: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.bold,
    color: '#1976D2',
    marginBottom: theme.spacing.sm,
  },
  noticeText: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.text,
    marginBottom: theme.spacing.xs,
    lineHeight: 18,
  },
  pendingCard: {
    backgroundColor: theme.colors.white,
    borderRadius: theme.borderRadius.sm,
    padding: theme.spacing.lg,
    marginBottom: theme.spacing.md,
  },
  pendingTitle: {
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
    marginBottom: theme.spacing.sm,
  },
  pendingText: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
    marginBottom: theme.spacing.lg,
    lineHeight: 20,
  },
  paymentMethodsContainer: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: theme.spacing.sm,
    marginBottom: theme.spacing.lg,
  },
  paymentMethod: {
    width: '48%',
    backgroundColor: theme.colors.surface,
    borderRadius: theme.borderRadius.sm,
    padding: theme.spacing.md,
    alignItems: 'center',
  },
  methodIcon: {
    fontSize: theme.fontSize.xxxl,
    marginBottom: theme.spacing.sm,
  },
  methodTitle: {
    fontSize: theme.fontSize.sm,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
    marginBottom: theme.spacing.xs,
  },
  methodDescription: {
    fontSize: theme.fontSize.xs,
    color: theme.colors.textSecondary,
    textAlign: 'center',
  },
  contactCard: {
    backgroundColor: '#FFF3E0',
    borderRadius: theme.borderRadius.sm,
    padding: theme.spacing.md,
    alignItems: 'center',
  },
  contactTitle: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.bold,
    color: '#E65100',
    marginBottom: theme.spacing.sm,
  },
  contactText: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
    textAlign: 'center',
    marginBottom: theme.spacing.md,
  },
  contactButton: {
    backgroundColor: theme.colors.warning,
    paddingHorizontal: theme.spacing.lg,
    paddingVertical: theme.spacing.sm,
    borderRadius: theme.borderRadius.sm,
  },
  contactButtonText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.sm,
    fontWeight: theme.fontWeight.semibold,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: theme.spacing.xxl * 1.5,
  },
  loadingText: {
    fontSize: theme.fontSize.md,
    color: theme.colors.textSecondary,
  },
  emptyState: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: theme.spacing.xxl,
  },
  emptyIcon: {
    marginBottom: theme.spacing.md,
  },
  emptyTitle: {
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
    marginBottom: theme.spacing.sm,
  },
  emptySubtitle: {
    fontSize: theme.fontSize.md,
    color: theme.colors.textSecondary,
    textAlign: 'center',
  },
  paymentCard: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.borderRadius.lg,
    padding: theme.spacing.md,
    marginBottom: theme.spacing.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    ...theme.shadows.small,
  },
  paymentHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: theme.spacing.sm,
  },
  paymentAmountContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.sm,
  },
  paymentAmount: {
    fontSize: theme.fontSize.xxl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
  },
  paymentIcon: {
    marginRight: theme.spacing.xs,
  },
  statusBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: theme.spacing.sm,
    paddingVertical: theme.spacing.xs,
    borderRadius: theme.borderRadius.xl,
  },
  statusIcon: {
    marginRight: theme.spacing.xs,
  },
  statusText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.xs,
    fontWeight: theme.fontWeight.semibold,
  },
  paymentMetaRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: theme.spacing.xs,
  },
  metaIconContainer: {
    width: 24,
    alignItems: 'center',
  },
  paymentLabel: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
    marginRight: theme.spacing.xs,
  },
  paymentValue: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.text,
    flex: 1,
    textAlign: 'right',
  },
  paymentNotes: {
    marginTop: theme.spacing.sm,
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
  },
});
