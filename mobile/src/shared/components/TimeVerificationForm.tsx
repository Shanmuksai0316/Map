import React, { useState } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  TextInput,
} from 'react-native';
import { GradientButton } from './GradientButton';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import DateTimePicker from '@react-native-community/datetimepicker';

interface TimeVerificationFormProps {
  label: string;
  currentTime?: string;
  onTimeSet: (time: Date) => void;
  disabled?: boolean;
  isCompleted?: boolean;
}

export const TimeVerificationForm: React.FC<TimeVerificationFormProps> = ({
  label,
  currentTime,
  onTimeSet,
  disabled = false,
  isCompleted = false,
}) => {
  const [showPicker, setShowPicker] = useState(false);
  const [selectedTime, setSelectedTime] = useState<Date>(
    currentTime ? new Date(currentTime) : new Date()
  );

  const handleTimeChange = (event: any, date?: Date) => {
    setShowPicker(false);
    if (date) {
      setSelectedTime(date);
      onTimeSet(date);
    }
  };

  const formatTime = (date: Date) => {
    return date.toLocaleTimeString('en-US', {
      hour: '2-digit',
      minute: '2-digit',
      hour12: true,
    });
  };

  const formatDate = (date: Date) => {
    return date.toLocaleDateString('en-US', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
  };

  return (
    <View style={styles.container}>
      <Text style={styles.label}>{label}</Text>
      
      <View style={styles.inputRow}>
        <TouchableOpacity
          style={[
            styles.timeInput,
            disabled && styles.disabled,
            isCompleted && styles.completed,
          ]}
          onPress={() => !disabled && setShowPicker(true)}
          disabled={disabled}
        >
          <Icon
            name={isCompleted ? 'check-circle' : 'clock-outline'}
            size={20}
            color={isCompleted ? '#10B981' : '#6B7280'}
          />
          <View style={styles.timeTextContainer}>
            <Text style={[styles.timeText, isCompleted && styles.completedText]}>
              {currentTime ? formatTime(new Date(currentTime)) : formatTime(selectedTime)}
            </Text>
            <Text style={styles.dateText}>
              {currentTime ? formatDate(new Date(currentTime)) : formatDate(selectedTime)}
            </Text>
          </View>
        </TouchableOpacity>

        {!disabled && !isCompleted && (
          <GradientButton
            style={styles.setButton}
            onPress={() => onTimeSet(selectedTime)}
          >
            <Text style={styles.setButtonText}>Set</Text>
          </GradientButton>
        )}
      </View>

      {showPicker && (
        <DateTimePicker
          value={selectedTime}
          mode="time"
          display="spinner"
          onChange={handleTimeChange}
        />
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    marginBottom: 16,
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: '#374151',
    marginBottom: 8,
  },
  inputRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  timeInput: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#F9FAFB',
    borderRadius: 12,
    padding: 14,
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  disabled: {
    opacity: 0.6,
  },
  completed: {
    backgroundColor: '#ECFDF5',
    borderColor: '#10B981',
  },
  timeTextContainer: {
    marginLeft: 12,
  },
  timeText: {
    fontSize: 16,
    fontWeight: '600',
    color: '#1F2937',
  },
  completedText: {
    color: '#10B981',
  },
  dateText: {
    fontSize: 12,
    color: '#6B7280',
    marginTop: 2,
  },
  setButton: {
    backgroundColor: '#3B82F6',
    paddingHorizontal: 20,
    paddingVertical: 14,
    borderRadius: 12,
  },
  setButtonText: {
    color: '#FFFFFF',
    fontSize: 14,
    fontWeight: '600',
  },
});

export default TimeVerificationForm;

