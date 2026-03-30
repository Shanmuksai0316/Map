import React from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  Modal,
  Alert,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { useAuthStore } from '../../../shared/store/auth.store';
import { theme } from '../../../shared/theme/theme';
import { GradientButton } from '../../../shared/components/GradientButton';

interface Props {
  visible: boolean;
  onClose: () => void;
}

export const ProfilePopup: React.FC<Props> = ({ visible, onClose }) => {
  const { user, selectedTenant, logout } = useAuthStore();

  const handleLogout = () => {
    Alert.alert(
      'Logout',
      'Are you sure you want to logout?',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Logout',
          style: 'destructive',
          onPress: async () => {
            try {
              await logout();
              onClose();
            } catch {
              Alert.alert('Error', 'Failed to logout');
            }
          },
        },
      ]
    );
  };

  return (
    <Modal
      visible={visible}
      animationType="fade"
      transparent
      onRequestClose={onClose}
    >
      <TouchableOpacity
        style={styles.overlay}
        activeOpacity={1}
        onPress={onClose}
      >
        <TouchableOpacity
          activeOpacity={1}
          onPress={() => {}}
          style={styles.popupContainer}
        >
          <View style={styles.popup}>
            {/* Close Button */}
            <GradientButton style={styles.closeButton} onPress={onClose}>
              <Icon name="close" size={24} color={theme.colors.textSecondary} />
            </GradientButton>

            {/* Avatar and Name */}
            <View style={styles.avatarSection}>
              <View style={styles.avatar}>
                <Icon name="account" size={40} color={theme.colors.white} />
              </View>
              <Text style={styles.userName}>{user?.name || 'Campus Manager'}</Text>
              <Text style={styles.userRole}>{user?.role || 'Campus Manager'}</Text>
            </View>

            {/* Divider */}
            <View style={styles.divider} />

            {/* Details Section */}
            <View style={styles.detailsSection}>
              <Text style={styles.sectionTitle}>Details</Text>

              {/* Unique ID */}
              <View style={styles.detailRow}>
                <View style={styles.detailIconContainer}>
                  <Icon name="badge-account" size={20} color={theme.colors.primary} />
                </View>
                <View style={styles.detailContent}>
                  <Text style={styles.detailLabel}>Unique ID</Text>
                  <Text style={styles.detailValue}>
                    {user?.employee_id || `EMP${user?.id || '000'}`}
                  </Text>
                </View>
              </View>

              {/* Phone Number */}
              <View style={styles.detailRow}>
                <View style={styles.detailIconContainer}>
                  <Icon name="phone" size={20} color={theme.colors.primary} />
                </View>
                <View style={styles.detailContent}>
                  <Text style={styles.detailLabel}>Phone Number</Text>
                  <Text style={styles.detailValue}>
                    {user?.phone || 'Not provided'}
                  </Text>
                </View>
              </View>

              {/* Assigned College */}
              <View style={styles.detailRow}>
                <View style={styles.detailIconContainer}>
                  <Icon name="office-building" size={20} color={theme.colors.primary} />
                </View>
                <View style={styles.detailContent}>
                  <Text style={styles.detailLabel}>Assigned College</Text>
                  <Text style={styles.detailValue}>
                    {selectedTenant?.name || 'Not assigned'}
                  </Text>
                </View>
              </View>
            </View>

            {/* Logout Button */}
            <GradientButton style={styles.logoutButton} onPress={handleLogout}>
              <Icon name="logout" size={20} color={theme.colors.error} />
              <Text style={styles.logoutText}>Logout</Text>
            </GradientButton>
          </View>
        </TouchableOpacity>
      </TouchableOpacity>
    </Modal>
  );
};

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 24,
  },
  popupContainer: {
    width: '100%',
    maxWidth: 340,
  },
  popup: {
    backgroundColor: theme.colors.background,
    borderRadius: 20,
    padding: 24,
    position: 'relative',
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  closeButton: {
    position: 'absolute',
    top: 16,
    right: 16,
    zIndex: 1,
    padding: 6,
    borderRadius: 999,
  },
  avatarSection: {
    alignItems: 'center',
    marginBottom: 20,
    paddingTop: 10,
  },
  avatar: {
    width: 80,
    height: 80,
    borderRadius: 40,
    backgroundColor: theme.colors.primary,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 16,
  },
  userName: {
    fontSize: 20,
    fontWeight: '700',
    color: theme.colors.text,
    textAlign: 'center',
  },
  userRole: {
    fontSize: 14,
    color: theme.colors.textSecondary,
    marginTop: 4,
  },
  divider: {
    height: 1,
    backgroundColor: theme.colors.border,
    marginBottom: 20,
  },
  detailsSection: {
    marginBottom: 24,
  },
  sectionTitle: {
    fontSize: 12,
    fontWeight: '600',
    color: theme.colors.textSecondary,
    textTransform: 'uppercase',
    letterSpacing: 1,
    marginBottom: 16,
  },
  detailRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 16,
  },
  detailIconContainer: {
    width: 40,
    height: 40,
    borderRadius: 10,
    backgroundColor: `${theme.colors.primary}15`,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 14,
  },
  detailContent: {
    flex: 1,
  },
  detailLabel: {
    fontSize: 12,
    color: theme.colors.textSecondary,
    marginBottom: 2,
  },
  detailValue: {
    fontSize: 15,
    fontWeight: '500',
    color: theme.colors.text,
  },
  logoutButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: theme.colors.accent,
    paddingVertical: 14,
    borderRadius: 12,
    gap: 8,
  },
  logoutText: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.primary,
  },
});

export default ProfilePopup;
