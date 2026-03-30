import React, { useState } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  ViewStyle,
  TextStyle,
  ActivityIndicator,
} from 'react-native';
import { GradientButton } from './GradientButton';
import Ionicons from 'react-native-vector-icons/Ionicons';

interface TapToRevealFieldProps {
  label: string;
  value: string;
  maskLength?: number;
  maskChar?: string;
  requireVerification?: boolean;
  onVerificationRequired?: () => Promise<boolean>;
  containerStyle?: ViewStyle;
  labelStyle?: TextStyle;
  valueStyle?: TextStyle;
  revealedDuration?: number; // Auto-hide after X ms (0 = manual hide only)
}

export const TapToRevealField: React.FC<TapToRevealFieldProps> = ({
  label,
  value,
  maskLength = value.length,
  maskChar = '•',
  requireVerification = false,
  onVerificationRequired,
  containerStyle,
  labelStyle,
  valueStyle,
  revealedDuration = 5000,
}) => {
  const [isRevealed, setIsRevealed] = useState(false);
  const [isVerifying, setIsVerifying] = useState(false);

  const maskedValue = maskChar.repeat(Math.min(maskLength, value.length));

  const handleReveal = async () => {
    if (isRevealed) {
      setIsRevealed(false);
      return;
    }

    // If extra verification is required, verify first
    if (requireVerification && onVerificationRequired) {
      setIsVerifying(true);
      try {
        const verified = await onVerificationRequired();
        if (verified) {
          setIsRevealed(true);
          
          // Auto-hide after duration
          if (revealedDuration > 0) {
            setTimeout(() => {
              setIsRevealed(false);
            }, revealedDuration);
          }
        }
      } catch (error) {
        console.error('Step-up verification failed:', error);
      } finally {
        setIsVerifying(false);
      }
    } else {
      // No extra verification required, reveal immediately
      setIsRevealed(true);
      
      if (revealedDuration > 0) {
        setTimeout(() => {
          setIsRevealed(false);
        }, revealedDuration);
      }
    }
  };

  return (
    <View style={[styles.container, containerStyle]}>
      <Text style={[styles.label, labelStyle]}>{label}</Text>
      
      <GradientButton
        style={[
          styles.revealButton,
          isRevealed && styles.revealButtonActive,
        ]}
        onPress={handleReveal}
        disabled={isVerifying}>
        {isVerifying ? (
          <View style={styles.verifyingContainer}>
            <ActivityIndicator size="small" color="#1976d2" />
            <Text style={styles.verifyingText}>Verifying...</Text>
          </View>
        ) : (
          <>
            <Text style={[styles.value, valueStyle, isRevealed && styles.revealedValue]}>
              {isRevealed ? value : maskedValue}
            </Text>
            <View style={styles.tapHint}>
              <Text style={styles.tapHintIcon}>
                {isRevealed ? '🔓' : '🔒'}
              </Text>
              <Text style={styles.tapHintText}>
                {isRevealed ? 'Tap to hide' : 'Tap to reveal'}
              </Text>
            </View>
          </>
        )}
      </GradientButton>

      {requireVerification && !isRevealed && (
        <View style={styles.securityBadge}>
          <Ionicons name="lock-closed-outline" size={14} color="#FF9800" style={{ marginRight: 6 }} />
          <Text style={styles.securityBadgeText}>OTP verification required</Text>
        </View>
      )}

      {isRevealed && revealedDuration > 0 && (
        <Text style={styles.autoHideHint}>
          Will auto-hide in {Math.ceil(revealedDuration / 1000)}s
        </Text>
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    marginVertical: 12,
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: '#666',
    marginBottom: 8,
  },
  revealButton: {
    backgroundColor: '#f5f5f5',
    borderWidth: 1,
    borderColor: '#e0e0e0',
    borderRadius: 12,
    padding: 16,
    minHeight: 60,
  },
  revealButtonActive: {
    backgroundColor: '#e3f2fd',
    borderColor: '#1976d2',
  },
  value: {
    fontSize: 16,
    fontWeight: '500',
    color: '#333',
    letterSpacing: 2,
    marginBottom: 8,
  },
  revealedValue: {
    color: '#1976d2',
    letterSpacing: 0,
  },
  tapHint: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  tapHintIcon: {
    fontSize: 14,
  },
  tapHintText: {
    fontSize: 12,
    color: '#999',
    fontStyle: 'italic',
  },
  verifyingContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 12,
    paddingVertical: 8,
  },
  verifyingText: {
    fontSize: 14,
    color: '#1976d2',
    fontWeight: '500',
  },
  securityBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: 8,
    paddingHorizontal: 12,
    paddingVertical: 6,
    backgroundColor: '#fff3e0',
    borderRadius: 8,
    alignSelf: 'flex-start',
  },
  securityBadgeText: {
    fontSize: 12,
    color: '#e65100',
    fontWeight: '500',
  },
  autoHideHint: {
    fontSize: 11,
    color: '#999',
    fontStyle: 'italic',
    marginTop: 6,
  },
});

export default TapToRevealField;
