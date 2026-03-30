import React, { useState } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  Alert,
} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { colors } from '../../theme/colors';
import { apiService } from '../../services/api.service';
import { APP_CONFIG } from '../../config/app.config';

interface RevealablePIIProps {
  label: string;
  value: string | null | undefined;
  studentId: number;
  piiType: 'phone' | 'guardian' | 'medical';
  onReveal?: (value: string) => void;
  containerStyle?: any;
  labelStyle?: any;
  valueStyle?: any;
  revealedDuration?: number; // Auto-hide after X ms (0 = manual hide only)
}

export const RevealablePII: React.FC<RevealablePIIProps> = ({
  label,
  value,
  studentId,
  piiType,
  onReveal,
  containerStyle,
  labelStyle,
  valueStyle,
  revealedDuration = 10000, // 10 seconds default
}) => {
  const [isRevealed, setIsRevealed] = useState(false);
  const [isVerifying, setIsVerifying] = useState(false);
  const [revealedValue, setRevealedValue] = useState<string | null>(null);

  if (!value) {
    return (
      <View style={[styles.container, containerStyle]}>
        <Text style={[styles.label, labelStyle]}>{label}:</Text>
        <Text style={[styles.value, styles.emptyValue, valueStyle]}>Not available</Text>
      </View>
    );
  }

  const maskedValue = '•'.repeat(Math.min(value.length, 10));

  const handleReveal = async () => {
    if (isRevealed) {
      setIsRevealed(false);
      setRevealedValue(null);
      return;
    }

    try {
      setIsVerifying(true);
      const response = await apiService.post<{
        success?: boolean;
        data?: { value?: string };
      }>(
        APP_CONFIG.ENDPOINTS.PII_REVEAL,
        {
          student_id: studentId,
          pii_type: piiType,
        }
      );

      if (response && response.data && response.data.value) {
        setRevealedValue(response.data.value);
        setIsRevealed(true);

        // Call onReveal callback
        if (onReveal) {
          onReveal(response.data.value);
        }

        // Auto-hide after duration
        if (revealedDuration > 0) {
          setTimeout(() => {
            setIsRevealed(false);
            setRevealedValue(null);
          }, revealedDuration);
        }
      } else {
        throw new Error('Failed to reveal PII');
      }
    } catch (error: any) {
      Alert.alert('Error', error.response?.data?.message || error.message || 'Failed to reveal information');
    } finally {
      setIsVerifying(false);
    }
  };

  return (
    <View style={[styles.container, containerStyle]}>
      <Text style={[styles.label, labelStyle]}>{label}:</Text>
      <TouchableOpacity
        style={styles.revealButton}
        onPress={handleReveal}
        disabled={isVerifying}
        activeOpacity={0.7}
      >
        {isRevealed && revealedValue ? (
          <View style={styles.revealedContainer}>
            <Text style={[styles.value, styles.revealedValue, valueStyle]}>{revealedValue}</Text>
            <Ionicons name="eye-off-outline" size={16} color={colors.success} style={styles.icon} />
            <Text style={styles.hideHint}>Tap to hide</Text>
          </View>
        ) : (
          <View style={styles.maskedContainer}>
            <Text style={[styles.value, styles.maskedValue, valueStyle]}>{maskedValue}</Text>
            <Ionicons name="eye-outline" size={16} color={colors.primary} style={styles.icon} />
            <Text style={styles.revealHint}>Tap to reveal</Text>
          </View>
        )}
      </TouchableOpacity>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    marginVertical: 8,
  },
  label: {
    fontSize: 14,
    fontWeight: '500',
    color: colors.textSecondary,
    marginBottom: 4,
  },
  revealButton: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 8,
  },
  maskedContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  revealedContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  value: {
    fontSize: 16,
    color: colors.text,
    flex: 1,
  },
  maskedValue: {
    fontFamily: 'monospace',
    letterSpacing: 2,
  },
  revealedValue: {
    color: colors.success,
    fontWeight: '600',
  },
  emptyValue: {
    color: colors.textMuted,
    fontStyle: 'italic',
  },
  icon: {
    marginLeft: 8,
  },
  revealHint: {
    fontSize: 12,
    color: colors.primary,
    marginLeft: 4,
  },
  hideHint: {
    fontSize: 12,
    color: colors.success,
    marginLeft: 4,
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  modalContent: {
    backgroundColor: colors.white,
    borderRadius: 16,
    padding: 24,
    width: '100%',
    maxWidth: 400,
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 16,
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: colors.text,
  },
  closeButton: {
    padding: 4,
  },
  modalDescription: {
    fontSize: 14,
    color: colors.textSecondary,
    marginBottom: 24,
    lineHeight: 20,
  },
  otpContainer: {
    marginBottom: 24,
  },
  otpInput: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 8,
    padding: 16,
    fontSize: 18,
    textAlign: 'center',
    letterSpacing: 4,
    fontFamily: 'monospace',
    marginBottom: 12,
  },
  otpInputError: {
    borderColor: colors.error,
  },
  errorText: {
    fontSize: 12,
    color: colors.error,
    marginBottom: 12,
    textAlign: 'center',
  },
  buttonContainer: {
    flexDirection: 'row',
    gap: 12,
  },
  button: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 12,
    paddingHorizontal: 16,
    borderRadius: 8,
    gap: 8,
  },
  requestButton: {
    backgroundColor: colors.info,
  },
  verifyButton: {
    backgroundColor: colors.primary,
  },
  buttonText: {
    color: colors.white,
    fontSize: 16,
    fontWeight: '600',
  },
  cancelButton: {
    paddingVertical: 12,
    alignItems: 'center',
  },
  cancelButtonText: {
    color: colors.textSecondary,
    fontSize: 16,
  },
});
