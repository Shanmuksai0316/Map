import React from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  ScrollView,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { useEmergencyStore } from '../../../shared/store/emergency.store';
import { theme } from '../../../shared/theme/theme';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface Props {
  navigation: any;
}

export const EmergencyScreen: React.FC<Props> = ({ navigation }) => {
  const { unacknowledgedCount } = useEmergencyStore();

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Emergency" />
      <ScrollView style={styles.content} showsVerticalScrollIndicator={false}>
        {/* Alert Banner */}
        {unacknowledgedCount > 0 && (
          <View style={styles.alertBanner}>
            <Icon name="alert" size={24} color="#FFFFFF" />
            <View style={styles.alertContent}>
              <Text style={styles.alertTitle}>
                {unacknowledgedCount} Unacknowledged Alert{unacknowledgedCount > 1 ? 's' : ''}
              </Text>
              <Text style={styles.alertSubtitle}>
                Please review and acknowledge immediately
              </Text>
            </View>
          </View>
        )}

        {/* Emergency Categories */}
        <View style={styles.categoriesSection}>
          <Text style={styles.sectionTitle}>Emergency Categories</Text>

          {/* Medical Emergencies */}
          <TouchableOpacity
            style={styles.categoryCard}
            onPress={() => navigation.navigate('MedicalEmergencies')}
          >
            <View style={[styles.categoryIcon, { backgroundColor: '#FEE2E2' }]}>
              <Icon name="medical-bag" size={28} color="#EF4444" />
            </View>
            <View style={styles.categoryContent}>
              <Text style={styles.categoryTitle}>Medical Emergencies</Text>
              <Text style={styles.categoryDescription}>
                Students requiring medical attention
              </Text>
            </View>
            <Icon name="chevron-right" size={24} color="#9CA3AF" />
          </TouchableOpacity>

          {/* Security Incidents */}
          <TouchableOpacity
            style={styles.categoryCard}
            onPress={() => navigation.navigate('Incidents')}
          >
            <View style={[styles.categoryIcon, { backgroundColor: '#FEF3C7' }]}>
              <Icon name="shield-alert" size={28} color="#F59E0B" />
            </View>
            <View style={styles.categoryContent}>
              <Text style={styles.categoryTitle}>Security Incidents</Text>
              <Text style={styles.categoryDescription}>
                Late returns, missed attendance, security alerts
              </Text>
            </View>
            <View style={styles.badgeContainer}>
              {unacknowledgedCount > 0 && (
                <View style={styles.badge}>
                  <Text style={styles.badgeText}>{unacknowledgedCount}</Text>
                </View>
              )}
              <Icon name="chevron-right" size={24} color="#9CA3AF" />
            </View>
          </TouchableOpacity>
        </View>

        <View style={styles.bottomPadding} />
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  subHeader: {
    paddingHorizontal: 16,
    paddingTop: 12,
    paddingBottom: 8,
  },
  subHeaderTitle: {
    fontSize: 22,
    fontWeight: '700',
    color: theme.colors.textHeading,
  },
  subHeaderSubtitle: {
    fontSize: 14,
    color: theme.colors.textMuted,
    marginTop: 4,
  },
  content: {
    flex: 1,
  },
  alertBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#EF4444',
    margin: 16,
    padding: 16,
    borderRadius: 12,
  },
  alertContent: {
    marginLeft: 12,
    flex: 1,
  },
  alertTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: '#FFFFFF',
  },
  alertSubtitle: {
    fontSize: 13,
    color: 'rgba(255, 255, 255, 0.9)',
    marginTop: 2,
  },
  categoriesSection: {
    padding: 16,
    paddingTop: 8,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: '#1F2937',
    marginBottom: 12,
  },
  categoryCard: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#FFFFFF',
    padding: 16,
    borderRadius: 12,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  categoryIcon: {
    width: 56,
    height: 56,
    borderRadius: 14,
    justifyContent: 'center',
    alignItems: 'center',
  },
  categoryContent: {
    flex: 1,
    marginLeft: 14,
  },
  categoryTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: '#1F2937',
  },
  categoryDescription: {
    fontSize: 13,
    color: '#6B7280',
    marginTop: 2,
  },
  badgeContainer: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  badge: {
    backgroundColor: '#EF4444',
    borderRadius: 12,
    paddingHorizontal: 8,
    paddingVertical: 4,
    marginRight: 8,
  },
  badgeText: {
    color: '#FFFFFF',
    fontSize: 12,
    fontWeight: '700',
  },
  bottomPadding: {
    height: 100,
  },
});

export default EmergencyScreen;
