import React from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { colors } from '../../shared/theme/colors';
import { theme } from '../../shared/theme/theme';

export interface StaffQuickAction {
  key: string;
  label: string;
  description?: string;
  icon: string;
  color?: string;
  onPress: () => void;
}

interface StaffQuickActionsProps {
  title?: string;
  actions: StaffQuickAction[];
}

export const StaffQuickActions: React.FC<StaffQuickActionsProps> = ({
  title = 'Actions',
  actions,
}) => {
  if (!actions.length) {
    return null;
  }

  return (
    <View style={styles.wrapper}>
      <Text style={styles.sectionTitle}>{title}</Text>
      <View style={styles.grid}>
        {actions.map((action) => (
          <TouchableOpacity
            key={action.key}
            style={styles.card}
            onPress={action.onPress}
            accessibilityRole="button"
            accessibilityLabel={action.label}>
            <View
              style={[
                styles.iconContainer,
                { backgroundColor: action.color ?? theme.colors.accent },
              ]}>
              <Ionicons
                name={action.icon}
                size={24}
                color={theme.colors.white}
              />
            </View>
            <Text style={styles.label}>{action.label}</Text>
            {action.description ? (
              <Text style={styles.description}>{action.description}</Text>
            ) : null}
          </TouchableOpacity>
        ))}
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  wrapper: {
    marginTop: 16,
    paddingHorizontal: 20,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: 14,
    textAlign: 'center',
  },
  grid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
  },
  card: {
    flexBasis: '48%',
    backgroundColor: colors.surface,
    borderRadius: 16,
    paddingVertical: 16,
    paddingHorizontal: 12,
    borderWidth: 1,
    borderColor: colors.border,
    alignItems: 'center',
    ...theme.shadows.small,
  },
  iconContainer: {
    width: 44,
    height: 44,
    borderRadius: 12,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 12,
  },
  label: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.textPrimary,
    textAlign: 'center',
  },
  description: {
    fontSize: 13,
    color: colors.textSecondary,
    marginTop: 4,
  },
});
