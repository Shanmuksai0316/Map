import React from 'react';
import {
  View,
  Text,
  Modal,
  TouchableOpacity,
  StyleSheet,
  Dimensions,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { theme } from '../theme/theme';
import { GradientButton } from './GradientButton';
import LinearGradient from 'react-native-linear-gradient';

interface ProfilePopupProps {
  visible: boolean;
  onClose: () => void;
  name: string;
  role: string;
  phone?: string;
  email?: string;
  employeeId?: string;
  onEditProfile?: () => void;
  onChangePassword?: () => void;
  onViewHistory?: () => void;
  onLogout: () => void;
}

const { width: SCREEN_WIDTH } = Dimensions.get('window');

export const ProfilePopup: React.FC<ProfilePopupProps> = ({
  visible,
  onClose,
  name,
  role,
  phone,
  email,
  employeeId,
  onEditProfile,
  onChangePassword,
  onViewHistory,
  onLogout,
}) => {
  const roleColor = theme.colors.accent;

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
        <View style={styles.container}>
          <LinearGradient
            colors={['rgba(255,255,255,0.75)', 'rgba(255,255,255,0)']}
            start={{ x: 0, y: 0 }}
            end={{ x: 1, y: 0 }}
            style={styles.topSheen}
            pointerEvents="none"
          />
          {/* Header */}
          <View style={[styles.header, { backgroundColor: roleColor }]}>
            <View style={styles.avatarContainer}>
              <Text style={styles.avatarText}>
                {name.split(' ').map(n => n[0]).join('').substring(0, 2)}
              </Text>
            </View>
            <Text style={styles.name}>{name}</Text>
            <View style={styles.roleContainer}>
              <Text style={styles.roleText}>{role}</Text>
            </View>
          </View>

          {/* Details */}
          <View style={styles.details}>
            {phone && (
              <View style={styles.detailRow}>
                <View style={styles.detailIcon}>
                  <Icon name="phone" size={18} color={theme.colors.primary} />
                </View>
                <Text style={styles.detailText}>{phone}</Text>
              </View>
            )}
            {email && (
              <View style={styles.detailRow}>
                <View style={styles.detailIcon}>
                  <Icon name="email" size={18} color={theme.colors.primary} />
                </View>
                <Text style={styles.detailText}>{email}</Text>
              </View>
            )}
            {employeeId && (
              <View style={styles.detailRow}>
                <View style={styles.detailIcon}>
                  <Icon name="badge-account" size={18} color={theme.colors.primary} />
                </View>
                <Text style={styles.detailText}>ID: {employeeId}</Text>
              </View>
            )}
          </View>

          {/* Actions */}
          <View style={styles.actions}>
            {onEditProfile && (
              <GradientButton style={styles.actionButton} onPress={onEditProfile}>
                <View style={styles.actionIcon}>
                  <Icon name="account-edit" size={18} color={theme.colors.primary} />
                </View>
                <Text style={styles.actionText}>Edit Profile</Text>
              </GradientButton>
            )}
            
            {onChangePassword && (
              <GradientButton style={styles.actionButton} onPress={onChangePassword}>
                <View style={styles.actionIcon}>
                  <Icon name="lock-reset" size={18} color={theme.colors.primary} />
                </View>
                <Text style={styles.actionText}>Change Password</Text>
              </GradientButton>
            )}

            {onViewHistory && (
              <GradientButton style={styles.actionButton} onPress={onViewHistory}>
                <View style={styles.actionIcon}>
                  <Icon name="history" size={18} color={theme.colors.primary} />
                </View>
                <Text style={styles.actionText}>View History</Text>
              </GradientButton>
            )}

            <GradientButton
              style={[styles.actionButton, styles.logoutButton]}
              onPress={onLogout}
            >
              <View style={[styles.actionIcon, styles.logoutIcon]}>
                <Icon name="logout" size={18} color={theme.colors.error} />
              </View>
              <Text style={[styles.actionText, styles.logoutText]}>Logout</Text>
            </GradientButton>
          </View>
        </View>
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
  container: {
    backgroundColor: theme.colors.white,
    borderRadius: theme.borderRadius.lg,
    width: SCREEN_WIDTH - 48,
    overflow: 'hidden',
    borderWidth: 0,
    shadowColor: theme.colors.black,
    shadowOpacity: 0.18,
    shadowRadius: 14,
    shadowOffset: { width: 0, height: 8 },
    elevation: 10,
  },
  topSheen: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    height: 8,
    borderTopLeftRadius: theme.borderRadius.lg,
    borderTopRightRadius: theme.borderRadius.lg,
  },
  header: {
    padding: 24,
    alignItems: 'center',
  },
  avatarContainer: {
    width: 72,
    height: 72,
    borderRadius: 36,
    backgroundColor: theme.colors.white,
    borderWidth: 2,
    borderColor: theme.colors.primary,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 12,
    shadowColor: theme.colors.black,
    shadowOpacity: 0.15,
    shadowRadius: 8,
    shadowOffset: { width: 0, height: 4 },
    elevation: 6,
  },
  avatarText: {
    fontSize: 28,
    fontWeight: '800',
    color: theme.colors.primary,
  },
  name: {
    fontSize: 20,
    fontWeight: '700',
    color: theme.colors.primaryDark,
    marginBottom: 8,
  },
  roleContainer: {
    backgroundColor: theme.colors.white,
    paddingHorizontal: 16,
    paddingVertical: 6,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: theme.colors.primary,
  },
  roleText: {
    fontSize: 13,
    fontWeight: '700',
    color: theme.colors.primary,
    letterSpacing: 0.4,
  },
  details: {
    padding: 20,
    backgroundColor: theme.colors.surfaceMuted,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
  },
  detailRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 12,
  },
  detailIcon: {
    width: 28,
    height: 28,
    borderRadius: 14,
    backgroundColor: theme.colors.white,
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  detailText: {
    marginLeft: 12,
    fontSize: 15,
    color: theme.colors.primary,
  },
  actions: {
    padding: 14,
  },
  actionButton: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 12,
    paddingHorizontal: 14,
    borderRadius: 12,
    marginBottom: 8,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.white,
  },
  actionIcon: {
    width: 30,
    height: 30,
    borderRadius: 15,
    backgroundColor: theme.colors.accentMuted,
    justifyContent: 'center',
    alignItems: 'center',
  },
  actionText: {
    marginLeft: 12,
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.primary,
  },
  logoutButton: {
    marginTop: 8,
    backgroundColor: theme.colors.errorLight,
    borderColor: theme.colors.errorLight,
  },
  logoutIcon: {
    backgroundColor: theme.colors.white,
    borderWidth: 1,
    borderColor: theme.colors.error,
  },
  logoutText: {
    color: theme.colors.error,
  },
});

export default ProfilePopup;
