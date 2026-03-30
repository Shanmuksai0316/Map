import React, { useState } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  Modal,
  TextInput,
  Alert,
  ActivityIndicator,
} from 'react-native';
import { GradientButton } from '../../shared/components/GradientButton';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { SvgXml } from 'react-native-svg';
import { theme } from '../../shared/theme/theme';
import { apiService } from '../../shared/services/api.service';
import { APP_CONFIG } from '../../shared/config/app.config';
import { useAuthStore } from '../../shared/store/auth.store';
import { studentRequestsHubIcons } from '../../shared/assets/dashboard-icons/student-requests-hub-icons';

type EmergencyType = 'need_assistance' | 'contact_doctor' | 'incident';

export const EmergencyScreen = ({ navigation }: any) => {
  const insets = useSafeAreaInsets();
  const { user } = useAuthStore();
  const [emergencyType, setEmergencyType] = useState<EmergencyType | null>(null);
  const [showMedicalModal, setShowMedicalModal] = useState(false);
  const [showIncidentModal, setShowIncidentModal] = useState(false);
  const [description, setDescription] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const HEADER_ROW_HEIGHT = 52;
  const HEADER_PADDING_TOP = Math.max(insets.top, 10);
  const HEADER_PADDING_BOTTOM = 6;

  const handleMedicalSubmit = async (type: 'need_assistance' | 'contact_doctor') => {
    setSubmitting(true);
    try {
      // Only send the type; backend derives student + room details
      await apiService.post(APP_CONFIG.ENDPOINTS.STUDENT_EMERGENCY_MEDICAL, {
        type,
      });

      Alert.alert(
        'Emergency Reported',
        type === 'need_assistance'
          ? 'Your request has been sent to the warden and campus manager. Help is on the way.'
          : 'Your request has been sent to the warden and campus manager. A doctor will contact you shortly.',
        [{ text: 'OK', onPress: () => navigation.goBack() }]
      );
    } catch (error) {
      Alert.alert('Error', 'Failed to submit. Please try again or contact warden directly.');
    } finally {
      setSubmitting(false);
      setShowMedicalModal(false);
    }
  };

  const handleIncidentSubmit = async () => {
    if (!description.trim()) {
      Alert.alert('Error', 'Please provide a description');
      return;
    }

    setSubmitting(true);
    try {
      await apiService.post(APP_CONFIG.ENDPOINTS.STUDENT_EMERGENCY_INCIDENT, {
        description: description.trim(),
      });

      Alert.alert(
        'Submitted',
        'Your incident has been reported. The authorities have been notified.',
        [{ text: 'OK', onPress: () => navigation.goBack() }]
      );
    } catch (error) {
      Alert.alert('Error', 'Failed to submit. Please try again or contact warden directly.');
    } finally {
      setSubmitting(false);
      setShowIncidentModal(false);
    }
  };

  return (
    <View style={styles.container}>
      {/* Header */}
      <View
        style={[
          styles.header,
          {
            paddingTop: HEADER_PADDING_TOP,
            paddingBottom: HEADER_PADDING_BOTTOM,
            minHeight: HEADER_PADDING_TOP + HEADER_ROW_HEIGHT + HEADER_PADDING_BOTTOM,
          },
        ]}>
        <View style={[styles.headerRow, { height: HEADER_ROW_HEIGHT }]}>
          <TouchableOpacity
            style={styles.backButton}
            onPress={() => (navigation?.canGoBack?.() ? navigation.goBack() : navigation.navigate('Home'))}
            accessibilityLabel="Go back">
            <Ionicons name="arrow-back" size={24} color={theme.colors.white} />
          </TouchableOpacity>
          <Text style={styles.headerTitle}>Emergency</Text>
          <View style={styles.headerSpacer} />
        </View>
      </View>

      <View style={styles.content}>
        <Text style={styles.subtitle}>
          Select the type of emergency to report
        </Text>

        {/* Main buttons */}
        <TouchableOpacity
          style={[styles.emergencyCard, styles.medicalCard]}
          onPress={() => {
            setEmergencyType(null);
            setShowMedicalModal(true);
          }}
          disabled={submitting}>
          <View style={styles.emergencyIcon}>
            <SvgXml xml={studentRequestsHubIcons.medical} width={40} height={40} />
          </View>
          <Text style={styles.emergencyTitle}>Medical Emergency</Text>
          <Text style={styles.emergencyDescription}>
            Quickly request assistance or contact a doctor
          </Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={[styles.emergencyCard, styles.incidentCard]}
          onPress={() => {
            setEmergencyType('incident');
            setDescription('');
            setShowIncidentModal(true);
          }}
          disabled={submitting}>
          <View style={styles.emergencyIcon}>
            <SvgXml xml={studentRequestsHubIcons.repairMaintenance} width={40} height={40} />
          </View>
          <Text style={styles.emergencyTitle}>Incident</Text>
          <Text style={styles.emergencyDescription}>
            Report a security incident or safety concern
          </Text>
        </TouchableOpacity>
      </View>

      {/* Medical options modal */}
      <Modal
        visible={showMedicalModal}
        animationType="slide"
        presentationStyle="pageSheet"
        onRequestClose={() => !submitting && setShowMedicalModal(false)}>
        <View style={styles.modalContainer}>
          <View style={[styles.modalHeader, { paddingTop: Math.max(insets.top, 16) }]}>
            <Text style={styles.modalTitle}>Medical Emergency</Text>
            <TouchableOpacity
              onPress={() => !submitting && setShowMedicalModal(false)}
              disabled={submitting}>
              <Ionicons name="arrow-back" size={24} color={theme.colors.textSecondary} />
            </TouchableOpacity>
          </View>

          <View style={styles.modalContent}>
            <Text style={styles.subtitle}>
              Choose the type of medical help you need
            </Text>

            <TouchableOpacity
              style={[styles.emergencyCard, styles.medicalCard]}
              onPress={() => handleMedicalSubmit('need_assistance')}
              disabled={submitting}>
              <View style={styles.emergencyIcon}>
                <SvgXml xml={studentRequestsHubIcons.medical} width={40} height={40} />
              </View>
              <Text style={styles.emergencyTitle}>Need Assistance</Text>
              <Text style={styles.emergencyDescription}>
                Request immediate assistance from support staff
              </Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={[styles.emergencyCard, styles.medicalCard]}
              onPress={() => handleMedicalSubmit('contact_doctor')}
              disabled={submitting}>
              <View style={styles.emergencyIcon}>
                <SvgXml xml={studentRequestsHubIcons.medical} width={40} height={40} />
              </View>
              <Text style={styles.emergencyTitle}>Contact Doctor</Text>
              <Text style={styles.emergencyDescription}>
                Request medical consultation with a doctor
              </Text>
            </TouchableOpacity>
          </View>
        </View>
      </Modal>

      {/* Incident Report Modal */}
      <Modal
        visible={showIncidentModal}
        animationType="slide"
        presentationStyle="pageSheet"
        onRequestClose={() => !submitting && setShowIncidentModal(false)}>
        <View style={styles.modalContainer}>
          <View style={[styles.modalHeader, { paddingTop: Math.max(insets.top, 16) }]}>
            <Text style={styles.modalTitle}>Report Incident</Text>
            <TouchableOpacity
              onPress={() => !submitting && setShowIncidentModal(false)}
              disabled={submitting}>
              <Ionicons name="arrow-back" size={24} color={theme.colors.textSecondary} />
            </TouchableOpacity>
          </View>

          <View style={styles.modalContent}>
            <Text style={styles.inputLabel}>Description *</Text>
            <TextInput
              style={styles.textArea}
              placeholder="Describe the incident..."
              multiline
              numberOfLines={6}
              value={description}
              onChangeText={setDescription}
              editable={!submitting}
            />

            <View style={styles.modalActions}>
              <GradientButton
                style={styles.cancelButton}
                onPress={() => setDescription('')}
                disabled={submitting}>
                <Text style={styles.cancelButtonText}>Clear</Text>
              </GradientButton>

              <GradientButton
                style={[styles.submitButton, submitting && styles.submitButtonDisabled]}
                onPress={handleIncidentSubmit}
                disabled={submitting}>
                {submitting ? (
                  <ActivityIndicator color="#fff" />
                ) : (
                  <Text style={styles.submitButtonText}>Submit</Text>
                )}
              </GradientButton>
            </View>
          </View>
        </View>
      </Modal>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.white,
  },
  header: {
    backgroundColor: '#DC2626',
    paddingHorizontal: 16,
    ...theme.shadows.medium,
  },
  headerRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  backButton: {
    padding: 8,
  },
  headerTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: theme.colors.white,
  },
  headerSpacer: {
    width: 40,
  },
  content: {
    flex: 1,
    padding: 20,
  },
  subtitle: {
    fontSize: 16,
    color: theme.colors.textSecondary,
    textAlign: 'center',
    marginBottom: 32,
  },
  emergencyCard: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.borderRadius.lg,
    padding: 24,
    alignItems: 'center',
    marginBottom: 16,
    borderWidth: 2,
    ...theme.shadows.medium,
  },
  medicalCard: {
    borderColor: '#DC2626',
  },
  incidentCard: {
    borderColor: '#F59E0B',
  },
  emergencyIcon: {
    width: 72,
    height: 72,
    borderRadius: 36,
    backgroundColor: '#FEE2E2',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 16,
  },
  emergencyTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: theme.colors.text,
    marginBottom: 8,
  },
  emergencyDescription: {
    fontSize: 14,
    color: theme.colors.textSecondary,
    textAlign: 'center',
  },
  modalContainer: {
    flex: 1,
    backgroundColor: theme.colors.white,
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 20,
    paddingTop: 60,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
  },
  modalTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: theme.colors.text,
  },
  modalContent: {
    flex: 1,
    padding: 20,
  },
  inputLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: theme.colors.text,
    marginBottom: 8,
  },
  textArea: {
    backgroundColor: theme.colors.card,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.borderRadius.md,
    padding: 16,
    fontSize: 16,
    minHeight: 150,
    textAlignVertical: 'top',
  },
  modalActions: {
    flexDirection: 'row',
    gap: 12,
    marginTop: 24,
  },
  cancelButton: {
    flex: 1,
    padding: 16,
    borderRadius: theme.borderRadius.md,
    backgroundColor: theme.colors.surface,
    alignItems: 'center',
  },
  cancelButtonText: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.textSecondary,
  },
  submitButton: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 16,
    borderRadius: theme.borderRadius.lg,
    backgroundColor: '#D79F24',
    ...theme.shadows.medium,
  },
  submitButtonDisabled: {
    opacity: 0.6,
  },
  submitButtonText: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.primary,
  },
});
