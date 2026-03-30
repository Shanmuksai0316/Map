import React, { useState } from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { Calendar as RNCalendar, DateData, LocaleConfig } from 'react-native-calendars';
import { theme } from '../theme/theme';
import { format } from 'date-fns';

// Configure calendar locale
LocaleConfig.locales['en'] = {
  monthNames: [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December'
  ],
  monthNamesShort: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
  dayNames: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
  dayNamesShort: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
  today: 'Today',
};
LocaleConfig.defaultLocale = 'en';

interface AttendanceDay {
  [date: string]: {
    marked?: boolean;
    selected?: boolean;
    selectedColor?: string;
    markedColor?: string;
    dotColor?: string;
    status?: 'present' | 'absent' | 'late';
  };
}

interface CalendarProps {
  selectedDate?: Date;
  onDateSelect: (date: Date) => void;
  attendanceData?: Array<{
    date: string;
    status: 'present' | 'absent' | 'late';
  }>;
  markedDates?: AttendanceDay;
  minDate?: Date;
  maxDate?: Date;
  style?: any;
}

export const Calendar: React.FC<CalendarProps> = ({
  selectedDate,
  onDateSelect,
  attendanceData = [],
  markedDates,
  minDate,
  maxDate,
  style,
}) => {
  const [currentMonth, setCurrentMonth] = useState(selectedDate || new Date());

  // Convert attendance data to marked dates format
  const generateMarkedDates = (): AttendanceDay => {
    if (markedDates) return markedDates;

    const marked: AttendanceDay = {};

    attendanceData.forEach((record) => {
      const dateKey = record.date;
      const status = record.status;

      marked[dateKey] = {
        marked: true,
        status,
        ...(status === 'present' && {
          markedColor: theme.colors.success,
          dotColor: theme.colors.success,
        }),
        ...(status === 'absent' && {
          markedColor: theme.colors.error,
          dotColor: theme.colors.error,
        }),
        ...(status === 'late' && {
          markedColor: theme.colors.warning,
          dotColor: theme.colors.warning,
        }),
      };
    });

    return marked;
  };

  const handleDayPress = (day: DateData) => {
    const selected = new Date(day.year, day.month - 1, day.day);
    onDateSelect(selected);
  };

  const handleMonthChange = (month: DateData) => {
    setCurrentMonth(new Date(month.year, month.month - 1, month.day));
  };

  const getSelectedDateString = () => {
    if (!selectedDate) return undefined;
    return format(selectedDate, 'yyyy-MM-dd');
  };

  return (
    <View style={[styles.container, style]}>
      <RNCalendar
        // Current selected date
        current={format(currentMonth, 'yyyy-MM-dd')}

        // Date selection
        onDayPress={handleDayPress}
        onMonthChange={handleMonthChange}

        // Marked dates (attendance data)
        markedDates={{
          ...generateMarkedDates(),
          ...(getSelectedDateString() && {
            [getSelectedDateString()]: {
              selected: true,
              selectedColor: theme.colors.primary,
            },
          }),
        }}

        // Date range limits
        minDate={minDate ? format(minDate, 'yyyy-MM-dd') : undefined}
        maxDate={maxDate ? format(maxDate, 'yyyy-MM-dd') : undefined}

        // Styling
        theme={{
          backgroundColor: theme.colors.card,
          calendarBackground: theme.colors.card,
          textSectionTitleColor: theme.colors.text,
          selectedDayBackgroundColor: theme.colors.primary,
          selectedDayTextColor: theme.colors.white,
          todayTextColor: theme.colors.primary,
          dayTextColor: theme.colors.text,
          textDisabledColor: theme.colors.textMuted,
          dotColor: theme.colors.primary,
          selectedDotColor: theme.colors.white,
          arrowColor: theme.colors.primary,
          disabledArrowColor: theme.colors.textMuted,
          monthTextColor: theme.colors.text,
          indicatorColor: theme.colors.primary,
          textDayFontFamily: 'System',
          textMonthFontFamily: 'System',
          textDayHeaderFontFamily: 'System',
          textDayFontWeight: '400',
          textMonthFontWeight: '600',
          textDayHeaderFontWeight: '500',
          textDayFontSize: 16,
          textMonthFontSize: 18,
          textDayHeaderFontSize: 14,
        }}

        // Additional options
        enableSwipeMonths={true}
        showScrollIndicator={false}
        firstDay={1} // Monday first

        // Custom header
        renderHeader={(date) => (
          <View style={styles.header}>
            <Text style={styles.headerText}>
              {format(date, 'MMMM yyyy')}
            </Text>
          </View>
        )}
      />
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.borderRadius.lg,
    padding: theme.spacing.sm,
    ...theme.shadows.small,
  },
  header: {
    paddingVertical: theme.spacing.md,
    alignItems: 'center',
  },
  headerText: {
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
  },
});
