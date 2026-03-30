import React from 'react';
import { TouchableOpacity, View, Text, StyleSheet } from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';

interface NotificationBellWithBadgeProps {
  count: number;
  onPress: () => void;
  color?: string;
  size?: number;
}

export const NotificationBellWithBadge: React.FC<NotificationBellWithBadgeProps> = ({
  count,
  onPress,
  color = '#FFFFFF',
  size = 24,
}) => {
  return (
    <TouchableOpacity onPress={onPress} style={styles.container}>
      <Icon name="bell-outline" size={size} color={color} />
      {count > 0 && (
        <View style={styles.badge}>
          <Text style={styles.badgeText}>
            {count > 99 ? '99+' : count}
          </Text>
        </View>
      )}
    </TouchableOpacity>
  );
};

const styles = StyleSheet.create({
  container: {
    position: 'relative',
    padding: 8,
  },
  badge: {
    position: 'absolute',
    top: 2,
    right: 2,
    backgroundColor: '#EF4444',
    borderRadius: 10,
    minWidth: 18,
    height: 18,
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 4,
  },
  badgeText: {
    color: '#FFFFFF',
    fontSize: 10,
    fontWeight: '700',
  },
});

export default NotificationBellWithBadge;

