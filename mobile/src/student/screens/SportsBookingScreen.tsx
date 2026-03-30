import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  Alert,
  Modal,
  TextInput,
} from 'react-native';
import { GradientButton } from '../../shared/components/GradientButton';
import { useAuthStore } from '../../shared/store/auth.store';
import { apiService } from '../../shared/services/api.service';
import { APP_CONFIG } from '../../shared/config/app.config';
import { format, addDays, addHours, isBefore, isAfter, differenceInMinutes } from 'date-fns';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { theme } from '../../shared/theme/theme';
import { errorHandler } from '../../shared/utils/errorHandler';
import { ErrorState, LoadingState, OverviewCard } from '../../shared/components';

interface SportsBooking {
  id: number;
  facility_name: string;
  facility_type: string;
  booking_date: string;
  start_time: string;
  end_time: string;
  status: 'upcoming' | 'completed' | 'cancelled' | 'no_show';
  created_at: string;
}

interface Facility {
  id: number;
  name: string;
  type: string;
  available: boolean;
}

interface TimeSlot {
  time: string;
  available: boolean;
  blocked: boolean;
}

export const SportsBookingScreen = ({ navigation }: any) => {
  const insets = useSafeAreaInsets();
  const { user } = useAuthStore();
  const [bookings, setBookings] = useState<SportsBooking[]>([]);
  const [facilities, setFacilities] = useState<Facility[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [showBookingModal, setShowBookingModal] = useState(false);
  const [selectedFacility, setSelectedFacility] = useState<Facility | null>(null);
  const [selectedDate, setSelectedDate] = useState<Date>(new Date());
  const [selectedTimeSlot, setSelectedTimeSlot] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [facilitiesError, setFacilitiesError] = useState<string | null>(null);

  const fetchBookings = async () => {
    try {
      setError(null);
      setLoading(true);
      const response = await apiService.get<{ data: SportsBooking[] }>(
        `${APP_CONFIG.ENDPOINTS.SPORTS}/bookings`
      );
      setBookings(response.data || []);
    } catch (err) {
      const errorDetails = errorHandler.handleError(err);
      setError(errorDetails.message);
      setBookings([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  const fetchFacilities = async () => {
    try {
      setFacilitiesError(null);
      const response = await apiService.get<{ data: Facility[] }>(
        `${APP_CONFIG.ENDPOINTS.SPORTS}/facilities`
      );
      setFacilities(response.data || []);
    } catch (err) {
      const errorDetails = errorHandler.handleError(err);
      setFacilitiesError(errorDetails.message);
      setFacilities([]);
    }
  };

  useEffect(() => {
    fetchBookings();
    fetchFacilities();
  }, []);

  const onRefresh = () => {
    setRefreshing(true);
    fetchBookings();
    fetchFacilities();
  };

  const generateTimeSlots = (): TimeSlot[] => {
    const slots: TimeSlot[] = [];
    const startHour = 6; // 06:00
    const endHour = 22; // 22:00
    
    for (let hour = startHour; hour < endHour; hour++) {
      const time = `${hour.toString().padStart(2, '0')}:00`;
      slots.push({
        time,
        available: true, // In real app, check against existing bookings
        blocked: false,
      });
    }
    
    return slots;
  };

  const getAvailableDates = () => {
    const dates = [];
    for (let i = 0; i < 7; i++) {
      dates.push(addDays(new Date(), i));
    }
    return dates;
  };

  const hasActiveBooking = () => {
    return bookings.some(b => b.status === 'upcoming');
  };

  const canCancelBooking = (booking: SportsBooking) => {
    const bookingDateTime = new Date(`${booking.booking_date}T${booking.start_time}`);
    const now = new Date();
    const minutesUntilBooking = differenceInMinutes(bookingDateTime, now);
    return minutesUntilBooking > 60; // Can cancel if more than 1 hour away
  };

  const handleBookSlot = async () => {
    if (!selectedFacility || !selectedTimeSlot) {
      Alert.alert('Error', 'Please select a facility and time slot');
      return;
    }

    if (hasActiveBooking()) {
      Alert.alert('Error', 'You already have an active upcoming booking');
      return;
    }

    try {
      await apiService.post(`${APP_CONFIG.ENDPOINTS.SPORTS}/bookings`, {
        facility_id: selectedFacility.id,
        booking_date: format(selectedDate, 'yyyy-MM-dd'),
        start_time: selectedTimeSlot,
        end_time: `${parseInt(selectedTimeSlot.split(':')[0]) + 1}:00`,
      });

      Alert.alert('Success', 'Booking confirmed successfully!');
      setShowBookingModal(false);
      setSelectedFacility(null);
      setSelectedTimeSlot(null);
      fetchBookings();
    } catch (error) {
      Alert.alert('Error', 'Failed to create booking');
    }
  };

  const handleCancelBooking = async (bookingId: number) => {
    Alert.alert(
      'Cancel Booking',
      'Are you sure you want to cancel this booking?',
      [
        { text: 'No', style: 'cancel' },
        {
          text: 'Yes, Cancel',
          style: 'destructive',
          onPress: async () => {
            try {
              await apiService.delete(`${APP_CONFIG.ENDPOINTS.SPORTS}/bookings/${bookingId}`);
              Alert.alert('Success', 'Booking cancelled successfully');
              fetchBookings();
            } catch (error) {
              Alert.alert('Error', 'Failed to cancel booking');
            }
          },
        },
      ]
    );
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'upcoming':
        return theme.colors.success;
      case 'completed':
        return theme.colors.info;
      case 'cancelled':
        return theme.colors.gray;
      case 'no_show':
        return theme.colors.danger;
      default:
        return theme.colors.gray;
    }
  };

  const HEADER_ROW_HEIGHT = 52;
  const HEADER_PADDING_TOP = Math.max(insets.top, 10);
  const HEADER_PADDING_BOTTOM = 6;

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'upcoming':
        return 'checkmark-circle';
      case 'completed':
        return 'checkmark-circle';
      case 'cancelled':
        return 'close-circle-outline';
      case 'no_show':
        return 'alert-circle-outline';
      default:
        return 'help-circle-outline';
    }
  };

  const getFacilityIcon = (type: string) => {
    switch (type.toLowerCase()) {
      case 'basketball':
        return 'basketball-outline';
      case 'tennis':
        return 'tennisball-outline';
      case 'badminton':
        return 'tennisball-outline'; // Badminton uses tennis icon
      case 'football':
        return 'football-outline';
      case 'cricket':
        return 'baseball-outline';
      default:
        return 'walk-outline';
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
            <Ionicons name="arrow-back" size={24} color={theme.colors.white} />
          </TouchableOpacity>
          <Text style={styles.headerTitle}>Sports Booking</Text>
          <GradientButton
            style={styles.createButton}
            onPress={() => setShowBookingModal(true)}>
            <Ionicons name="add" size={18} color={theme.colors.primary} />
            <Text style={styles.createButtonText}>New</Text>
          </GradientButton>
        </View>
      </View>

      <ScrollView
        style={styles.content}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>
        {/* Overview Card */}
        <OverviewCard
          title="Sports Booking Overview"
          icon="football-outline"
          stats={[
            { 
              label: 'Upcoming', 
              value: bookings.filter(b => b.status === 'upcoming').length, 
              icon: 'calendar-outline',
              color: theme.colors.success 
            },
            { 
              label: 'Completed', 
              value: bookings.filter(b => b.status === 'completed').length, 
              icon: 'checkmark-circle-outline',
              color: theme.colors.info 
            },
            { 
              label: 'Facilities', 
              value: facilities.length, 
              icon: 'barbell-outline',
              color: theme.colors.primary 
            },
          ]}
        />

        {/* Info Banner */}
        <View style={styles.infoBanner}>
          <Ionicons name="information-circle-outline" size={24} color={theme.colors.info} />
          <View style={styles.infoContent}>
            <Text style={styles.infoText}>
              • Booking hours: 06:00 - 22:00
            </Text>
            <Text style={styles.infoText}>
              • 60-minute slots
            </Text>
            <Text style={styles.infoText}>
              • 1 active booking allowed
            </Text>
            <Text style={styles.infoText}>
              • Cancel at least 1 hour before
            </Text>
          </View>
        </View>

        {/* My Bookings */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>My Bookings</Text>
          {loading ? (
            <LoadingState message="Loading bookings..." />
          ) : error ? (
            <ErrorState error={error} onRetry={fetchBookings} />
          ) : bookings.length === 0 ? (
            <View style={styles.emptyState}>
              <Ionicons
                name="barbell-outline"
                size={64}
                color={theme.colors.textSecondary}
                style={styles.emptyIcon}
              />
              <Text style={styles.emptyTitle}>No Bookings</Text>
              <Text style={styles.emptySubtitle}>
                Your sports bookings will appear here
              </Text>
              <GradientButton
                style={styles.emptyButton}
                onPress={() => setShowBookingModal(true)}>
                <Text style={styles.emptyButtonText}>Book First Slot</Text>
              </GradientButton>
            </View>
          ) : (
            bookings.map((booking) => (
              <View key={booking.id} style={styles.bookingCard}>
                <View style={styles.bookingHeader}>
                  <View style={styles.bookingInfo}>
                    <Ionicons
                      name="basketball-outline"
                      size={20}
                      color={theme.colors.primary}
                      style={styles.bookingIcon}
                    />
                    <View>
                      <Text style={styles.bookingTitle}>{booking.facility_name}</Text>
                      <Text style={styles.bookingSubtitle}>{booking.facility_type}</Text>
                    </View>
                  </View>
                  <View
                    style={[
                      styles.statusBadge,
                      { backgroundColor: getStatusColor(booking.status) },
                    ]}>
                    <Ionicons
                      name={getStatusIcon(booking.status)}
                      size={16}
                      color={theme.colors.white}
                      style={styles.statusIcon}
                    />
                    <Text style={styles.statusText}>
                      {booking.status.toUpperCase()}
                    </Text>
                  </View>
                </View>

                <View style={styles.bookingDetails}>
                  <View style={styles.detailRow}>
                    <View style={{ flexDirection: 'row', alignItems: 'center', flex: 1 }}>
                      <Ionicons name="calendar-outline" size={16} color={theme.colors.textSecondary} style={{ marginRight: 8 }} />
                      <Text style={styles.detailLabel}>Date:</Text>
                    </View>
                    <Text style={styles.detailValue}>
                      {format(new Date(booking.booking_date), 'MMMM dd, yyyy')}
                    </Text>
                  </View>
                  <View style={styles.detailRow}>
                    <View style={{ flexDirection: 'row', alignItems: 'center', flex: 1 }}>
                      <Ionicons name="time-outline" size={16} color={theme.colors.textSecondary} style={{ marginRight: 8 }} />
                      <Text style={styles.detailLabel}>Time:</Text>
                    </View>
                    <Text style={styles.detailValue}>
                      {booking.start_time} - {booking.end_time}
                    </Text>
                  </View>
                </View>

                {booking.status === 'upcoming' && canCancelBooking(booking) && (
                  <GradientButton
                    style={styles.cancelButton}
                    onPress={() => handleCancelBooking(booking.id)}>
                    <Text style={styles.cancelButtonText}>Cancel Booking</Text>
                  </GradientButton>
                )}
              </View>
            ))
          )}
        </View>
      </ScrollView>

      {/* Booking Modal */}
      <Modal
        visible={showBookingModal}
        animationType="slide"
        presentationStyle="pageSheet"
        onRequestClose={() => setShowBookingModal(false)}>
        <View style={styles.modalContainer}>
          <View style={styles.modalHeader}>
            <Text style={styles.modalTitle}>New Booking</Text>
            <TouchableOpacity onPress={() => setShowBookingModal(false)}>
              <Ionicons name="arrow-back" size={24} color={theme.colors.textSecondary} />
            </TouchableOpacity>
          </View>

          <ScrollView style={styles.modalContent}>
            {/* Select Facility */}
            <Text style={styles.inputLabel}>Select Facility</Text>
            {facilitiesError ? (
              <View style={styles.facilityErrorState}>
                <Ionicons name="alert-circle-outline" size={24} color={theme.colors.error} />
                <Text style={styles.facilityErrorText}>{facilitiesError}</Text>
                <GradientButton onPress={fetchFacilities} style={styles.retryButton}>
                  <Text style={styles.retryButtonText}>Retry</Text>
                </GradientButton>
              </View>
            ) : facilities.length === 0 ? (
              <View style={styles.noFacilitiesState}>
                <Ionicons name="basketball-outline" size={48} color={theme.colors.textMuted} />
                <Text style={styles.noFacilitiesText}>No facilities available</Text>
                <Text style={styles.noFacilitiesSubtext}>Please check back later</Text>
              </View>
            ) : (
              <View style={styles.facilitiesGrid}>
                {facilities.map((facility) => (
                  <TouchableOpacity
                    key={facility.id}
                    style={[
                      styles.facilityCard,
                      selectedFacility?.id === facility.id && styles.facilityCardSelected,
                      !facility.available && styles.facilityCardUnavailable,
                    ]}
                    onPress={() => facility.available && setSelectedFacility(facility)}
                    disabled={!facility.available}>
                    <Ionicons
                      name={getFacilityIcon(facility.type)}
                      size={32}
                      color={facility.available ? theme.colors.primary : theme.colors.textMuted}
                    />
                    <Text style={[
                      styles.facilityCardName,
                      !facility.available && styles.facilityCardNameUnavailable
                    ]}>{facility.name}</Text>
                    <Text style={styles.facilityCardType}>{facility.type}</Text>
                    {!facility.available && (
                      <Text style={styles.unavailableTag}>Unavailable</Text>
                    )}
                  </TouchableOpacity>
                ))}
              </View>
            )}

            {/* Select Date */}
            <Text style={styles.inputLabel}>Select Date</Text>
            <ScrollView horizontal showsHorizontalScrollIndicator={false}>
              <View style={styles.datesContainer}>
                {getAvailableDates().map((date, index) => (
                  <TouchableOpacity
                    key={index}
                    style={[
                      styles.dateCard,
                      format(selectedDate, 'yyyy-MM-dd') === format(date, 'yyyy-MM-dd') &&
                        styles.dateCardSelected,
                    ]}
                    onPress={() => setSelectedDate(date)}>
                    <Text style={styles.dateDay}>{format(date, 'EEE')}</Text>
                    <Text style={styles.dateNumber}>{format(date, 'dd')}</Text>
                    <Text style={styles.dateMonth}>{format(date, 'MMM')}</Text>
                  </TouchableOpacity>
                ))}
              </View>
            </ScrollView>

            {/* Select Time Slot */}
            <Text style={styles.inputLabel}>Select Time Slot (60 min)</Text>
            <View style={styles.timeSlotsGrid}>
              {generateTimeSlots().map((slot) => (
                <TouchableOpacity
                  key={slot.time}
                  style={[
                    styles.timeSlotCard,
                    selectedTimeSlot === slot.time && styles.timeSlotCardSelected,
                    !slot.available && styles.timeSlotCardUnavailable,
                  ]}
                  onPress={() => slot.available && setSelectedTimeSlot(slot.time)}
                  disabled={!slot.available}>
                  <Text
                    style={[
                      styles.timeSlotText,
                      selectedTimeSlot === slot.time && styles.timeSlotTextSelected,
                    ]}>
                    {slot.time}
                  </Text>
                </TouchableOpacity>
              ))}
            </View>

            <GradientButton
              style={styles.confirmButton}
              onPress={handleBookSlot}>
              <Text style={styles.confirmButtonText}>Confirm Booking</Text>
            </GradientButton>
          </ScrollView>
        </View>
      </Modal>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.white,
  },
  header: {
    backgroundColor: theme.colors.primary,
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
    color: theme.colors.white,
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
  },
  createButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.xs,
    backgroundColor: theme.colors.white,
    paddingHorizontal: theme.spacing.lg,
    paddingVertical: theme.spacing.sm,
    borderRadius: theme.borderRadius.md,
  },
  createButtonText: {
    color: theme.colors.primary,
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
  },
  headerTitle: {
    color: theme.colors.white,
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
  },
  newBookingButton: {
    backgroundColor: 'rgba(255,255,255,0.2)',
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 8,
  },
  newBookingButtonText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
  },
  content: {
    flex: 1,
    padding: theme.spacing.md,
  },
  infoBanner: {
    backgroundColor: '#E3F2FD',
    borderRadius: theme.borderRadius.sm,
    padding: theme.spacing.md,
    flexDirection: 'row',
    marginBottom: theme.spacing.lg,
  },
  infoIcon: {
    fontSize: theme.fontSize.xxl,
    marginRight: theme.spacing.sm,
  },
  infoContent: {
    flex: 1,
  },
  infoText: {
    fontSize: theme.fontSize.sm,
    color: '#1976D2',
    marginBottom: theme.spacing.xs,
  },
  section: {
    marginBottom: theme.spacing.lg,
  },
  sectionTitle: {
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
    marginBottom: theme.spacing.sm,
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
    marginBottom: theme.spacing.lg,
  },
  bookingCard: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.borderRadius.lg,
    padding: theme.spacing.md,
    marginBottom: theme.spacing.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    ...theme.shadows.small,
  },
  bookingHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: theme.spacing.sm,
  },
  bookingInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.sm,
  },
  bookingIcon: {
    marginRight: theme.spacing.xs,
  },
  bookingTitle: {
    fontSize: theme.fontSize.lg,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
  },
  bookingSubtitle: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
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
  bookingDetails: {
    marginTop: theme.spacing.sm,
    gap: theme.spacing.xs,
  },
  detailRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  detailLabel: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
  },
  detailValue: {
    fontSize: theme.fontSize.sm,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
  },
  cancelButton: {
    marginTop: theme.spacing.sm,
    backgroundColor: theme.colors.error,
    paddingVertical: theme.spacing.sm,
    borderRadius: theme.borderRadius.sm,
    alignItems: 'center',
  },
  cancelButtonText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.sm,
    fontWeight: theme.fontWeight.semibold,
  },
  modalContainer: {
    flex: 1,
    backgroundColor: theme.colors.card,
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: theme.spacing.lg,
    paddingTop: theme.spacing.xl * 2,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.divider,
  },
  modalTitle: {
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
  },
  modalClose: {
    fontSize: theme.fontSize.xxxl,
    color: theme.colors.textSecondary,
  },
  modalContent: {
    flex: 1,
    padding: theme.spacing.lg,
  },
  inputLabel: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
    marginBottom: theme.spacing.sm,
    marginTop: theme.spacing.md,
  },
  facilitiesGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: theme.spacing.sm,
    marginBottom: theme.spacing.md,
  },
  facilityCard: {
    width: '31%',
    backgroundColor: theme.colors.surface,
    borderRadius: theme.borderRadius.sm,
    padding: theme.spacing.sm,
    alignItems: 'center',
    borderWidth: 2,
    borderColor: 'transparent',
  },
  facilityCardSelected: {
    backgroundColor: '#E8F5E9',
    borderColor: theme.colors.success,
  },
  facilityCardIcon: {
    fontSize: theme.fontSize.xxxl,
    marginBottom: theme.spacing.sm,
  },
  facilityCardName: {
    fontSize: theme.fontSize.xs,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
    textAlign: 'center',
    marginBottom: theme.spacing.xs,
  },
  facilityCardType: {
    fontSize: theme.fontSize.xxs,
    color: theme.colors.textSecondary,
  },
  datesContainer: {
    flexDirection: 'row',
    gap: theme.spacing.sm,
    marginBottom: theme.spacing.md,
  },
  dateCard: {
    backgroundColor: theme.colors.surface,
    borderRadius: theme.borderRadius.sm,
    padding: theme.spacing.sm,
    alignItems: 'center',
    minWidth: 70,
    borderWidth: 2,
    borderColor: 'transparent',
  },
  dateCardSelected: {
    backgroundColor: '#E8F5E9',
    borderColor: theme.colors.success,
  },
  dateDay: {
    fontSize: theme.fontSize.xs,
    color: theme.colors.textSecondary,
    marginBottom: theme.spacing.xs,
  },
  dateNumber: {
    fontSize: theme.fontSize.xxl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
    marginBottom: theme.spacing.xs,
  },
  dateMonth: {
    fontSize: theme.fontSize.xs,
    color: theme.colors.textSecondary,
  },
  timeSlotsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: theme.spacing.sm,
    marginBottom: theme.spacing.md,
  },
  timeSlotCard: {
    width: '22%',
    backgroundColor: theme.colors.surface,
    borderRadius: theme.borderRadius.sm,
    paddingVertical: theme.spacing.sm,
    alignItems: 'center',
    borderWidth: 2,
    borderColor: 'transparent',
  },
  timeSlotCardSelected: {
    backgroundColor: '#E8F5E9',
    borderColor: theme.colors.success,
  },
  timeSlotCardUnavailable: {
    backgroundColor: theme.colors.divider,
    opacity: 0.5,
  },
  timeSlotText: {
    fontSize: theme.fontSize.sm,
    fontWeight: theme.fontWeight.medium,
    color: theme.colors.text,
  },
  timeSlotTextSelected: {
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.success,
  },
  confirmButton: {
    backgroundColor: theme.colors.primary, // Military green instead of success green
    padding: theme.spacing.md,
    borderRadius: theme.borderRadius.sm,
    alignItems: 'center',
    marginBottom: theme.spacing.lg,
  },
  facilityErrorState: {
    backgroundColor: '#FFEBEE',
    borderRadius: theme.borderRadius.md,
    padding: theme.spacing.lg,
    alignItems: 'center',
    marginBottom: theme.spacing.md,
  },
  facilityErrorText: {
    color: theme.colors.error,
    fontSize: theme.fontSize.sm,
    marginTop: theme.spacing.sm,
    textAlign: 'center',
  },
  retryButton: {
    marginTop: theme.spacing.md,
    backgroundColor: theme.colors.primary,
    paddingVertical: theme.spacing.sm,
    paddingHorizontal: theme.spacing.lg,
    borderRadius: theme.borderRadius.sm,
  },
  retryButtonText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.sm,
    fontWeight: theme.fontWeight.semibold,
  },
  noFacilitiesState: {
    backgroundColor: theme.colors.surface,
    borderRadius: theme.borderRadius.md,
    padding: theme.spacing.xl,
    alignItems: 'center',
    marginBottom: theme.spacing.md,
  },
  noFacilitiesText: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
    marginTop: theme.spacing.md,
  },
  noFacilitiesSubtext: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textMuted,
    marginTop: theme.spacing.xs,
  },
  facilityCardUnavailable: {
    opacity: 0.5,
    backgroundColor: theme.colors.divider,
  },
  facilityCardNameUnavailable: {
    color: theme.colors.textMuted,
  },
  unavailableTag: {
    fontSize: theme.fontSize.xxs,
    color: theme.colors.error,
    marginTop: theme.spacing.xs,
  },
  confirmButtonText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
  },
  emptyButton: {
    marginTop: theme.spacing.lg,
    backgroundColor: theme.colors.primary,
    paddingVertical: theme.spacing.sm,
    paddingHorizontal: theme.spacing.lg,
    borderRadius: theme.borderRadius.sm,
  },
  emptyButtonText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
  },
});
