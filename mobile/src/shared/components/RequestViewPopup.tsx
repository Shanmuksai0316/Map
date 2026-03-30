import React from 'react';
import {
  View,
  Text,
  Modal,
  TouchableOpacity,
  StyleSheet,
  ScrollView,
  Dimensions,
} from 'react-native';
import { GradientButton } from './GradientButton';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { theme } from '../theme/theme';

interface RequestField {
  label: string;
  value: string | number | undefined;
}

interface RequestViewPopupProps {
  visible: boolean;
  onClose: () => void;
  title: string;
  fields: RequestField[];
  status?: string;
  statusColor?: string;
  actions?: {
    label: string;
    color: string;
    onPress: () => void;
  }[];
}

const { height: SCREEN_HEIGHT } = Dimensions.get('window');

export const RequestViewPopup: React.FC<RequestViewPopupProps> = ({
  visible,
  onClose,
  title,
  fields,
  status,
  statusColor = '#6B7280',
  actions,
}) => {
  return (
    <Modal
      visible={visible}
      animationType="slide"
      transparent
      onRequestClose={onClose}
    >
      <View style={styles.overlay}>
        <View style={styles.container}>
          {/* Header */}
          <View style={styles.header}>
            <Text style={styles.title}>{title}</Text>
            <GradientButton onPress={onClose} style={styles.closeButton}>
              <Icon name="close" size={24} color={theme.colors.primary} />
            </GradientButton>
          </View>

          {/* Status Badge */}
          {status && (
            <View style={[styles.statusBadge, { backgroundColor: statusColor + '20' }]}>
              <Text style={[styles.statusText, { color: statusColor }]}>
                {status.toUpperCase()}
              </Text>
            </View>
          )}

          {/* Fields */}
          <ScrollView style={styles.fieldsContainer} showsVerticalScrollIndicator={false}>
            {fields.map((field, index) => (
              <View key={index} style={styles.field}>
                <Text style={styles.fieldLabel}>{field.label}</Text>
                <Text style={styles.fieldValue}>{field.value ?? 'N/A'}</Text>
              </View>
            ))}
          </ScrollView>

          {/* Actions */}
          {actions && actions.length > 0 && (
            <View style={styles.actionsContainer}>
              {actions.map((action, index) => (
                <GradientButton
                  key={index}
                  style={[styles.actionButton, { backgroundColor: theme.colors.accent }]}
                  onPress={action.onPress}
                >
                  <Text style={styles.actionButtonText}>{action.label}</Text>
                </GradientButton>
              ))}
            </View>
          )}
        </View>
      </View>
    </Modal>
  );
};

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'flex-end',
  },
  container: {
    backgroundColor: theme.colors.white,
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    maxHeight: SCREEN_HEIGHT * 0.8,
    paddingBottom: 34, // Safe area
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 20,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
  },
  title: {
    fontSize: 18,
    fontWeight: '700',
    color: theme.colors.primary,
    flex: 1,
  },
  closeButton: {
    padding: 4,
  },
  statusBadge: {
    alignSelf: 'flex-start',
    marginHorizontal: 20,
    marginTop: 12,
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 20,
  },
  statusText: {
    fontSize: 12,
    fontWeight: '600',
  },
  fieldsContainer: {
    paddingHorizontal: 20,
    paddingTop: 16,
  },
  field: {
    marginBottom: 16,
  },
  fieldLabel: {
    fontSize: 12,
    fontWeight: '500',
    color: theme.colors.textSecondary,
    marginBottom: 4,
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  fieldValue: {
    fontSize: 16,
    fontWeight: '500',
    color: theme.colors.primary,
  },
  actionsContainer: {
    flexDirection: 'row',
    paddingHorizontal: 20,
    paddingTop: 16,
    gap: 12,
  },
  actionButton: {
    flex: 1,
    paddingVertical: 14,
    borderRadius: 12,
    alignItems: 'center',
  },
  actionButtonText: {
    color: theme.colors.primary,
    fontSize: 16,
    fontWeight: '700',
  },
});

export default RequestViewPopup;
