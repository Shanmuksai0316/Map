/**
 * Raise Request Screen for Laundry Manager
 * Requirements:
 * - Header: "Raise Request" center aligned, back arrow left aligned
 * - Form fields: MAP ID, Student Name, Room Number, Total No. Clothes, Total Weight (kg), Description
 * - Submit button: "Raise Request"
 * - Confirmation card on success, redirect to homepage
 */

import React, { useState } from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  StyleSheet,
  ScrollView,
  Alert,
  KeyboardAvoidingView,
  Platform,
  ActivityIndicator,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { colors } from '../../../shared/theme/colors';
import { apiService } from '../../../shared/services/api.service';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface Props {
  navigation: any;
}

export const RaiseRequestScreen: React.FC<Props> = ({ navigation }) => {
  const [mapStudentId, setMapStudentId] = useState('');
  const [studentName, setStudentName] = useState('');
  const [roomNumber, setRoomNumber] = useState('');
  const [totalClothes, setTotalClothes] = useState('');
  const [totalWeight, setTotalWeight] = useState('');
  const [description, setDescription] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Validation state
  const [errors, setErrors] = useState<Record<string, string>>({});

  const validateForm = (): boolean => {
    const newErrors: Record<string, string> = {};

    if (!mapStudentId.trim()) {
      newErrors.mapStudentId = 'MAP ID is required';
    }

    if (!roomNumber.trim()) {
      newErrors.roomNumber = 'Room number is required';
    }

    if (!totalClothes.trim()) {
      newErrors.totalClothes = 'Total clothes count is required';
    } else if (parseInt(totalClothes, 10) < 1) {
      newErrors.totalClothes = 'Must be at least 1 item';
    }

    if (totalWeight.trim() && parseFloat(totalWeight) < 0) {
      newErrors.totalWeight = 'Weight cannot be negative';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async () => {
    if (!validateForm()) {
      return;
    }

    setIsSubmitting(true);
    try {
      await apiService.post('/mobile/laundry/requests/raise', {
        map_student_id: mapStudentId.trim(),
        room_number: roomNumber.trim(),
        item_count: parseInt(totalClothes, 10),
        weight_kg: totalWeight.trim() ? parseFloat(totalWeight) : null,
        description: description.trim(),
        special_instructions: description.trim(),
      });

      // Show success confirmation
      const successMessage = studentName.trim()
        ? `Laundry request for ${studentName} (MAP ID: ${mapStudentId}) has been created.`
        : `Laundry request for MAP ID ${mapStudentId} has been created.`;
      
      Alert.alert(
        'Request Raised Successfully',
        successMessage,
        [
          {
            text: 'OK',
            onPress: () => navigation.goBack(),
          },
        ]
      );
    } catch (error: any) {
      const errorMessage =
        error?.response?.data?.message ||
        error?.response?.data?.detail ||
        'Failed to raise laundry request';
      Alert.alert('Error', errorMessage);
    } finally {
      setIsSubmitting(false);
    }
  };

  const renderInput = (
    label: string,
    value: string,
    onChangeText: (text: string) => void,
    placeholder: string,
    errorKey: string,
    options?: {
      keyboardType?: 'default' | 'number-pad' | 'decimal-pad';
      multiline?: boolean;
      required?: boolean;
    }
  ) => (
    <View style={styles.inputGroup}>
      <Text style={styles.label}>
        {label}
        {options?.required && <Text style={styles.required}> *</Text>}
      </Text>
      <TextInput
        style={[
          styles.input,
          options?.multiline && styles.textArea,
          errors[errorKey] && styles.inputError,
        ]}
        value={value}
        onChangeText={(text) => {
          onChangeText(text);
          if (errors[errorKey]) {
            setErrors((prev) => ({ ...prev, [errorKey]: '' }));
          }
        }}
        placeholder={placeholder}
        placeholderTextColor={colors.textMuted}
        keyboardType={options?.keyboardType || 'default'}
        multiline={options?.multiline}
        numberOfLines={options?.multiline ? 4 : 1}
        textAlignVertical={options?.multiline ? 'top' : 'center'}
      />
      {errors[errorKey] && (
        <Text style={styles.errorText}>{errors[errorKey]}</Text>
      )}
    </View>
  );

  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Raise Request" />

      <ScrollView
        style={styles.content}
        showsVerticalScrollIndicator={false}
        keyboardShouldPersistTaps="handled"
      >
        {/* Form Card */}
        <View style={styles.formCard}>
          {renderInput(
            'MAP ID',
            mapStudentId,
            setMapStudentId,
            'Enter MAP ID (e.g., STD-12345)',
            'mapStudentId',
            { required: true }
          )}

          {renderInput(
            'Student Name',
            studentName,
            setStudentName,
            'Enter student name',
            'studentName',
            { required: false }
          )}

          {renderInput(
            'Room Number',
            roomNumber,
            setRoomNumber,
            'Enter room number',
            'roomNumber',
            { required: true }
          )}

          <View style={styles.row}>
            <View style={styles.halfInput}>
              {renderInput(
                'Total No. Clothes',
                totalClothes,
                setTotalClothes,
                '0',
                'totalClothes',
                { keyboardType: 'number-pad', required: true }
              )}
            </View>
            <View style={styles.halfInput}>
              {renderInput(
                'Total Weight (kg)',
                totalWeight,
                setTotalWeight,
                '0.0',
                'totalWeight',
                { keyboardType: 'decimal-pad' }
              )}
            </View>
          </View>

          {renderInput(
            'Description',
            description,
            setDescription,
            'Any special instructions or notes...',
            'description',
            { multiline: true }
          )}
        </View>

        <View style={styles.bottomPadding} />
      </ScrollView>

      {/* Submit Button */}
      <View style={[styles.footer, { paddingBottom: Math.max(insets.bottom, 16) }]}>
        <GradientButton
          style={[styles.submitButton, isSubmitting && styles.buttonDisabled]}
          onPress={handleSubmit}
          disabled={isSubmitting}
        >
          {isSubmitting ? (
            <ActivityIndicator size="small" color={colors.white} />
          ) : (
            <>
              <Icon name="check" size={20} color={colors.white} />
              <Text style={styles.submitButtonText}>Raise Request</Text>
            </>
          )}
        </GradientButton>
      </View>
    </KeyboardAvoidingView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  content: {
    flex: 1,
    padding: 16,
  },
  formCard: {
    backgroundColor: colors.surface,
    borderRadius: 16,
    padding: 20,
    borderWidth: 1,
    borderColor: colors.border,
  },
  inputGroup: {
    marginBottom: 20,
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.text,
    marginBottom: 8,
  },
  required: {
    color: colors.error,
  },
  input: {
    backgroundColor: colors.surfaceMuted,
    borderRadius: 12,
    padding: 14,
    fontSize: 15,
    color: colors.text,
    borderWidth: 1,
    borderColor: colors.border,
  },
  inputError: {
    borderColor: colors.error,
  },
  textArea: {
    minHeight: 100,
    textAlignVertical: 'top',
  },
  errorText: {
    fontSize: 12,
    color: colors.error,
    marginTop: 4,
  },
  row: {
    flexDirection: 'row',
    gap: 12,
  },
  halfInput: {
    flex: 1,
  },
  bottomPadding: {
    height: 100,
  },
  footer: {
    padding: 16,
    backgroundColor: colors.surface,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  submitButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#D79F24',
    paddingVertical: 16,
    borderRadius: 12,
    gap: 8,
  },
  submitButtonText: {
    color: colors.primary,
    fontSize: 16,
    fontWeight: '600',
  },
  buttonDisabled: {
    opacity: 0.6,
  },
});

export default RaiseRequestScreen;
