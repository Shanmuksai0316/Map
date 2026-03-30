import React from 'react';
import { View, TouchableOpacity } from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { BadgeIndicator } from './BadgeIndicator';
import { theme } from '../theme/theme';
import { hapticService } from '../services/haptic.service';

interface AnimatedTabIconProps {
  name: string;
  focused: boolean;
  color: string;
  size?: number;
  badgeCount?: number;
  badgeVariant?: 'primary' | 'secondary' | 'success' | 'warning' | 'error';
  onPress?: () => void;
}

export const AnimatedTabIcon: React.FC<AnimatedTabIconProps> = ({
  name,
  focused,
  color,
  size = 24,
  badgeCount,
  badgeVariant = 'primary',
  onPress,
}) => {
  const handlePress = () => {
    // Trigger haptic feedback
    hapticService.onTabPress();
    
    // Call the original onPress
    onPress?.();
  };

  return (
    <TouchableOpacity onPress={handlePress} style={{ padding: 4 }}>
      <View style={{ position: 'relative' }}>
        <View style={{ transform: [{ scale: focused ? 1.15 : 1 }] }}>
          <Ionicons
            name={name}
            size={size}
            color={color}
            style={{
              shadowColor: focused ? theme.colors.primary : 'transparent',
              shadowOffset: { width: 0, height: 2 },
              shadowOpacity: focused ? 0.3 : 0,
              shadowRadius: 4,
            }}
          />
        </View>
        {badgeCount !== undefined && badgeCount > 0 && (
          <BadgeIndicator
            count={badgeCount}
            variant={badgeVariant}
            size="small"
          />
        )}
      </View>
    </TouchableOpacity>
  );
};
