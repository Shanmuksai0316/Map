import React, { useState } from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import DatePicker from 'react-native-date-picker';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { theme } from '../theme/theme';

interface DatePickerProps {
  label: string;
  value: Date | string;
  onChange: (date: Date) => void;
  placeholder?: string;
  minimumDate?: Date;
  maximumDate?: Date;
  mode?: 'date' | 'datetime' | 'time';
  error?: string;
  required?: boolean;
}

export const CustomDatePicker: React.FC<DatePickerProps> = ({
  label,
  value,
  onChange,
  placeholder = 'Select date',
  minimumDate,
  maximumDate,
  mode = 'date',
  error,
  required = false,
}) => {
  const [isOpen, setIsOpen] = useState(false);
  const [selectedDate, setSelectedDate] = useState<Date>(
    typeof value === 'string' ? new Date(value) : value || new Date()
  );

  const handleConfirm = (date: Date) => {
    setSelectedDate(date);
    onChange(date);
    setIsOpen(false);
  };

  const formatDate = (date: Date): string => {
    if (mode === 'time') {
      return date.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
      });
    }
    return date.toLocaleDateString('en-CA'); // YYYY-MM-DD format
  };

  const displayValue = selectedDate ? formatDate(selectedDate) : '';

  return (
    <View style={styles.container}>
      <Text style={styles.label}>
        {label}
        {required && <Text style={styles.required}> *</Text>}
      </Text>

      <TouchableOpacity
        style={[styles.input, error && styles.inputError]}
        onPress={() => setIsOpen(true)}
        activeOpacity={0.8}>
        <View style={styles.inputContent}>
          <Text style={[styles.inputText, !displayValue && styles.placeholder]}>
            {displayValue || placeholder}
          </Text>
          <Ionicons
            name="calendar-outline"
            size={20}
            color={displayValue ? theme.colors.text : theme.colors.textMuted}
          />
        </View>
      </TouchableOpacity>

      {error && <Text style={styles.errorText}>{error}</Text>}

      <DatePicker
        modal
        mode={mode}
        open={isOpen}
        date={selectedDate}
        onConfirm={handleConfirm}
        onCancel={() => setIsOpen(false)}
        minimumDate={minimumDate}
        maximumDate={maximumDate}
        textColor={theme.colors.text}
        accentColor={theme.colors.primary}
        style={styles.datePicker}
      />
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    marginBottom: theme.spacing.lg,
  },
  label: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
    marginBottom: theme.spacing.sm,
  },
  required: {
    color: theme.colors.error,
  },
  input: {
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.borderRadius.md,
    backgroundColor: theme.colors.white,
    padding: theme.spacing.md,
  },
  inputError: {
    borderColor: theme.colors.error,
  },
  inputContent: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  inputText: {
    fontSize: theme.fontSize.md,
    color: theme.colors.text,
    flex: 1,
  },
  placeholder: {
    color: theme.colors.textMuted,
  },
  errorText: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.error,
    marginTop: theme.spacing.xs,
  },
  datePicker: {
    backgroundColor: theme.colors.card,
  },
});
