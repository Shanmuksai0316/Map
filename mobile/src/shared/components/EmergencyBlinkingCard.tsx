import React, { useEffect, useRef } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  Animated,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';

interface EmergencyBlinkingCardProps {
  title: string;
  description: string;
  count: number;
  onPress: () => void;
  onAcknowledge?: () => void;
  acknowledged?: boolean;
}

export const EmergencyBlinkingCard: React.FC<EmergencyBlinkingCardProps> = ({
  title,
  description,
  count,
  onPress,
  onAcknowledge,
  acknowledged = false,
}) => {
  const blinkAnim = useRef(new Animated.Value(1)).current;

  useEffect(() => {
    if (!acknowledged && count > 0) {
      const blink = Animated.sequence([
        Animated.timing(blinkAnim, {
          toValue: 0.4,
          duration: 500,
          useNativeDriver: true,
        }),
        Animated.timing(blinkAnim, {
          toValue: 1,
          duration: 500,
          useNativeDriver: true,
        }),
      ]);

      const loop = Animated.loop(blink);
      loop.start();

      return () => loop.stop();
    } else {
      blinkAnim.setValue(1);
    }
  }, [acknowledged, count, blinkAnim]);

  if (count === 0 && acknowledged) {
    return null;
  }

  return (
    <TouchableOpacity onPress={onPress} activeOpacity={0.8}>
      <Animated.View
        style={[
          styles.container,
          !acknowledged && count > 0 && { opacity: blinkAnim },
        ]}
      >
        <View style={styles.iconContainer}>
          <Icon
            name={acknowledged ? 'check-circle' : 'alert-circle'}
            size={32}
            color={acknowledged ? '#10B981' : '#EF4444'}
          />
        </View>

        <View style={styles.content}>
          <View style={styles.header}>
            <Text style={styles.title}>{title}</Text>
            {count > 0 && !acknowledged && (
              <View style={styles.badge}>
                <Text style={styles.badgeText}>{count}</Text>
              </View>
            )}
          </View>
          <Text style={styles.description} numberOfLines={2}>
            {description}
          </Text>
        </View>

        {onAcknowledge && !acknowledged && count > 0 && (
          <TouchableOpacity
            style={styles.acknowledgeButton}
            onPress={onAcknowledge}
          >
            <Text style={styles.acknowledgeText}>ACK</Text>
          </TouchableOpacity>
        )}

        <Icon name="chevron-right" size={24} color="#9CA3AF" />
      </Animated.View>
    </TouchableOpacity>
  );
};

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#FEF2F2',
    borderRadius: 16,
    padding: 16,
    marginHorizontal: 16,
    marginVertical: 8,
    borderWidth: 1,
    borderColor: '#FECACA',
  },
  iconContainer: {
    marginRight: 12,
  },
  content: {
    flex: 1,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 4,
  },
  title: {
    fontSize: 16,
    fontWeight: '700',
    color: '#991B1B',
  },
  badge: {
    marginLeft: 8,
    backgroundColor: '#EF4444',
    borderRadius: 12,
    paddingHorizontal: 8,
    paddingVertical: 2,
  },
  badgeText: {
    color: '#FFFFFF',
    fontSize: 12,
    fontWeight: '700',
  },
  description: {
    fontSize: 14,
    color: '#B91C1C',
  },
  acknowledgeButton: {
    backgroundColor: '#EF4444',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 8,
    marginRight: 8,
  },
  acknowledgeText: {
    color: '#FFFFFF',
    fontSize: 12,
    fontWeight: '700',
  },
});

export default EmergencyBlinkingCard;

