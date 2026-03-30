/**
 * KebabMenu Component
 * 
 * A three-dot menu component with dropdown options
 */

import React, { useState } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  Modal,
  TouchableWithoutFeedback,
} from 'react-native';
import { GradientButton } from '../GradientButton';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { colors } from '../../theme/colors';

interface MenuOption {
  label: string;
  icon: string;
  onPress: () => void;
  destructive?: boolean;
}

interface KebabMenuProps {
  options: MenuOption[];
}

export const KebabMenu: React.FC<KebabMenuProps> = ({ options }) => {
  const [visible, setVisible] = useState(false);

  const handleOptionPress = (option: MenuOption) => {
    setVisible(false);
    option.onPress();
  };

  return (
    <>
      <GradientButton
        style={styles.menuButton}
        onPress={() => setVisible(true)}
        hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}>
        <Ionicons name="ellipsis-vertical" size={24} color={colors.surface} />
      </GradientButton>

      <Modal
        visible={visible}
        transparent
        animationType="fade"
        onRequestClose={() => setVisible(false)}>
        <TouchableWithoutFeedback onPress={() => setVisible(false)}>
          <View style={styles.modalOverlay}>
            <TouchableWithoutFeedback onPress={(e) => e.stopPropagation()}>
              <View style={styles.menuContainer}>
                {options.map((option, index) => (
                  <TouchableOpacity
                    key={index}
                    style={[
                      styles.menuOption,
                      option.destructive && styles.menuOptionDestructive,
                      index === options.length - 1 && styles.menuOptionLast,
                    ]}
                    onPress={() => handleOptionPress(option)}>
                    <Ionicons
                      name={option.icon}
                      size={20}
                      color={option.destructive ? colors.error : colors.textPrimary}
                      style={styles.menuIcon}
                    />
                    <Text
                      style={[
                        styles.menuOptionText,
                        option.destructive && styles.menuOptionTextDestructive,
                      ]}>
                      {option.label}
                    </Text>
                  </TouchableOpacity>
                ))}
              </View>
            </TouchableWithoutFeedback>
          </View>
        </TouchableWithoutFeedback>
      </Modal>
    </>
  );
};

const styles = StyleSheet.create({
  menuButton: {
    padding: 8,
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'flex-end',
  },
  menuContainer: {
    backgroundColor: colors.surface,
    borderTopLeftRadius: 16,
    borderTopRightRadius: 16,
    paddingBottom: 20,
    paddingTop: 8,
  },
  menuOption: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 16,
    paddingHorizontal: 20,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  menuOptionLast: {
    borderBottomWidth: 0,
  },
  menuOptionDestructive: {
    // Destructive styling handled in text
  },
  menuIcon: {
    marginRight: 12,
  },
  menuOptionText: {
    fontSize: 16,
    color: colors.textPrimary,
    fontWeight: '500',
  },
  menuOptionTextDestructive: {
    color: colors.error,
  },
});

