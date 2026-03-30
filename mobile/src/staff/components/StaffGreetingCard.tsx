import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { colors } from '../../shared/theme/colors';
import { theme } from '../../shared/theme/theme';

interface InfoItem {
  label: string;
  value: string;
}

interface StaffGreetingCardProps {
  greeting: string;
  name: string;
  infoItems?: InfoItem[];
}

export const StaffGreetingCard: React.FC<StaffGreetingCardProps> = ({
  greeting,
  name,
  infoItems = [],
}) => {
  return (
    <View style={styles.card}>
      <Text style={styles.greeting}>{greeting}</Text>
      <Text style={styles.name}>{name}</Text>
      {infoItems.length ? (
        <View style={styles.infoRow}>
          {infoItems.map((item) => (
            <View key={item.label} style={styles.infoColumn}>
              <Text style={styles.infoLabel}>{item.label}</Text>
              <Text style={styles.infoValue}>{item.value}</Text>
            </View>
          ))}
        </View>
      ) : null}
    </View>
  );
};

const styles = StyleSheet.create({
  card: {
    backgroundColor: colors.surface,
    marginHorizontal: 20,
    marginTop: -20,
    borderRadius: 20,
    padding: 20,
    shadowColor: '#000',
    shadowOpacity: 0.06,
    shadowOffset: { width: 0, height: 10 },
    shadowRadius: 20,
    elevation: 8,
  },
  greeting: {
    fontSize: 14,
    color: colors.textSecondary,
    marginBottom: 6,
  },
  name: {
    fontSize: 24,
    fontWeight: '700',
    color: colors.textPrimary,
    marginBottom: theme.spacing.md,
  },
  infoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 16,
  },
  infoColumn: {
    flex: 1,
    backgroundColor: colors.background,
    borderRadius: 12,
    padding: 12,
    borderWidth: 1,
    borderColor: colors.border,
  },
  infoLabel: {
    fontSize: 12,
    color: colors.textSecondary,
    textTransform: 'uppercase',
    letterSpacing: 0.5,
    marginBottom: 4,
  },
  infoValue: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.textPrimary,
  },
});

