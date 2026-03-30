/**
 * Security Incident Screen
 * 
 * Allows Guards to create security incidents with optional photos.
 * Supports offline mode - incidents will sync when network is available.
 */

import React, { useState } from 'react';
import {
  View,
  Text,
  ScrollView,
  TextInput,
  TouchableOpacity,
  StyleSheet,
  Alert,
  ActivityIndicator,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { apiService } from '../../../shared/services/api.service';
import { useOfflineQueue } from '../../../shared/hooks/useOfflineQueue';
import { PhotoUpload } from '../../../student/components/PhotoUpload';
import { colors } from '../../../shared/theme/colors';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface Photo {
  uri: string;
  name: string;
  type: string;
}

export const SecurityIncidentScreen = ({ navigation }: any) => {
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [severity, setSeverity] = useState<'low' | 'medium' | 'high' | 'critical'>('medium');
  const [location, setLocation] = useState<'hostel' | 'gate' | 'common_area'>('gate');
  const [photos, setPhotos] = useState<Photo[]>([]);
  const [submitting, setSubmitting] = useState(false);
  const { addAction, isOnline } = useOfflineQueue();

  const handleSubmit = async () => {
    // Validation
    if (!title.trim()) {
      Alert.alert('Error', 'Please enter a title for the incident');
      return;
    }

    if (!description.trim() || description.trim().length < 20) {
      Alert.alert('Error', 'Description must be at least 20 characters');
      return;
    }

    // Confirm submission
    Alert.alert(
      'Submit Security Incident',
      `Severity: ${severity.toUpperCase()}\nLocation: ${location}\nPhotos: ${photos.length}\n\nSubmit incident report?`,
      [
        { text: 'Cancel', style: 'cancel' },
        { text: 'Submit', onPress: () => processSubmit() },
      ]
    );
  };

  const processSubmit = async () => {
    setSubmitting(true);

    try {
      const payload = {
        title: title.trim(),
        description: description.trim(),
        severity,
        location,
        photo_count: photos.length,
      };

      if (isOnline) {
        // Try online submission with photos
        try {
          // Create incident first
          const response = await apiService.post('/security/incidents', payload);
          const incidentId = response.data.id;

          // Upload photos if any (with graceful failure)
          if (photos.length > 0) {
            try {
              await uploadPhotos(incidentId, photos);
              Alert.alert(
                'Success',
                `Security incident #${incidentId} reported with ${photos.length} photo(s).\n\nCampus Manager and Rector have been notified.`,
                [{ text: 'OK', onPress: () => navigation.goBack() }]
              );
            } catch (photoError) {
              console.warn('Photo upload failed:', photoError);
              Alert.alert(
                'Partial Success',
                `Incident #${incidentId} reported, but photo upload failed.\n\nYou can add photos later if needed.`,
                [{ text: 'OK', onPress: () => navigation.goBack() }]
              );
            }
          } else {
            Alert.alert(
              'Success',
              `Security incident #${incidentId} reported.\n\nCampus Manager and Rector have been notified.`,
              [{ text: 'OK', onPress: () => navigation.goBack() }]
            );
          }
        } catch (error: any) {
          console.warn('Online incident creation failed, queuing offline:', error);
          await queueOffline(payload);
        }
      } else {
        // Offline mode - queue without photos
        await queueOffline(payload);
      }
    } catch (error) {
      console.error('Incident submission failed:', error);
      Alert.alert('Error', 'Failed to submit incident. Please try again.');
    } finally {
      setSubmitting(false);
    }
  };

  const uploadPhotos = async (incidentId: number, photos: Photo[]) => {
    // Upload photos to S3 via presigned URLs
    for (const photo of photos) {
      const formData = new FormData();
      formData.append('photo', {
        uri: photo.uri,
        name: photo.name,
        type: photo.type,
      } as any);

      await apiService.post(`/security/incidents/${incidentId}/photos`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
    }
  };

  const queueOffline = async (payload: any) => {
    await addAction('security_incident', payload);

    Alert.alert(
      'Queued for Sync',
      'Security incident has been queued offline.\n\nIt will sync when network is available.\n\nNote: Photos cannot be queued offline and will not be submitted.',
      [{ text: 'OK', onPress: () => navigation.goBack() }]
    );
  };

  const SeverityButton = ({ level, label }: { level: typeof severity; label: string }) => (
    <GradientButton
      style={[
        styles.severityButton,
        severity === level && styles.severityButtonActive,
        level === 'critical' && severity === level && { backgroundColor: colors.error },
        level === 'high' && severity === level && { backgroundColor: '#FF5722' },
        level === 'medium' && severity === level && { backgroundColor: colors.warning },
        level === 'low' && severity === level && { backgroundColor: colors.info },
      ]}
      onPress={() => setSeverity(level)}>
      <Text style={[styles.severityText, severity === level && styles.severityTextActive]}>
        {label}
      </Text>
    </GradientButton>
  );

  const LocationButton = ({ loc, label, icon }: { loc: typeof location; label: string; icon: string }) => (
    <GradientButton
      style={[styles.locationButton, location === loc && styles.locationButtonActive]}
      onPress={() => setLocation(loc)}>
      <Ionicons name={icon as any} size={20} color={location === loc ? colors.white : colors.textMuted} />
      <Text style={[styles.locationText, location === loc && styles.locationTextActive]}>
        {label}
      </Text>
    </GradientButton>
  );

  return (
    <View style={styles.container}>
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Security Incidents" />

      <ScrollView style={styles.content}>
        {/* Warning Banner */}
        <View style={styles.warningBanner}>
          <Ionicons name="shield-checkmark-outline" size={20} color={colors.error} />
          <Text style={styles.warningText}>
            Report security incidents immediately
          </Text>
        </View>

        {/* Title */}
        <View style={styles.field}>
          <Text style={styles.label}>Incident Title *</Text>
          <TextInput
            style={styles.input}
            value={title}
            onChangeText={setTitle}
            placeholder="e.g., Unauthorized person near hostel"
            placeholderTextColor={colors.textMuted}
            editable={!submitting}
          />
        </View>

        {/* Description */}
        <View style={styles.field}>
          <Text style={styles.label}>Description * (min 20 chars)</Text>
          <TextInput
            style={[styles.input, styles.textArea]}
            value={description}
            onChangeText={setDescription}
            placeholder="Describe the incident in detail..."
            placeholderTextColor={colors.textMuted}
            multiline
            numberOfLines={5}
            textAlignVertical="top"
            editable={!submitting}
          />
          <Text style={styles.charCount}>{description.length} / 1000 characters</Text>
        </View>

        {/* Severity */}
        <View style={styles.field}>
          <Text style={styles.label}>Severity *</Text>
          <View style={styles.severityGrid}>
            <SeverityButton level="low" label="Low" />
            <SeverityButton level="medium" label="Medium" />
            <SeverityButton level="high" label="High" />
            <SeverityButton level="critical" label="Critical" />
          </View>
        </View>

        {/* Location */}
        <View style={styles.field}>
          <Text style={styles.label}>Location *</Text>
          <View style={styles.locationGrid}>
            <LocationButton loc="hostel" label="Hostel" icon="home-outline" />
            <LocationButton loc="gate" label="Gate" icon="enter-outline" />
            <LocationButton loc="common_area" label="Common Area" icon="people-outline" />
          </View>
        </View>

        {/* Photos */}
        <PhotoUpload photos={photos} onPhotosChange={setPhotos} maxPhotos={3} />

        {/* Submit Button */}
        <GradientButton
          style={[styles.submitButton, submitting && styles.submitButtonDisabled]}
          onPress={handleSubmit}
          disabled={submitting}>
          {submitting ? (
            <ActivityIndicator color={colors.white} />
          ) : (
            <>
              <Ionicons name="shield-checkmark-outline" size={20} color={colors.white} />
              <Text style={styles.submitButtonText}>Submit Incident Report</Text>
            </>
          )}
        </GradientButton>

        {/* Info */}
        <View style={styles.infoBox}>
          <Ionicons name="information-circle-outline" size={16} color={colors.info} />
          <Text style={styles.infoText}>
            Campus Manager and Rector will be notified immediately.
          </Text>
        </View>
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  content: { flex: 1, padding: 16 },
  warningBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#FEF3F2',
    padding: 12,
    borderRadius: 8,
    borderLeftWidth: 4,
    borderLeftColor: colors.error,
    marginBottom: 20,
  },
  warningText: { flex: 1, marginLeft: 8, fontSize: 14, color: colors.error, fontWeight: '600' },
  field: { marginBottom: 20 },
  label: { fontSize: 14, fontWeight: '600', color: colors.text, marginBottom: 8 },
  input: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 8,
    padding: 12,
    fontSize: 16,
    color: colors.text,
    backgroundColor: colors.white,
  },
  textArea: { minHeight: 120, textAlignVertical: 'top' },
  charCount: { fontSize: 12, color: colors.textMuted, marginTop: 4, textAlign: 'right' },
  severityGrid: { flexDirection: 'row', gap: 8 },
  severityButton: {
    flex: 1,
    padding: 12,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
    alignItems: 'center',
  },
  severityButtonActive: { borderColor: 'transparent' },
  severityText: { fontSize: 13, color: colors.textMuted, fontWeight: '600' },
  severityTextActive: { color: colors.white },
  locationGrid: { flexDirection: 'row', gap: 8 },
  locationButton: {
    flex: 1,
    flexDirection: 'column',
    alignItems: 'center',
    gap: 6,
    padding: 12,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
  },
  locationButtonActive: { backgroundColor: colors.primary, borderColor: colors.primary },
  locationText: { fontSize: 12, color: colors.textMuted, fontWeight: '600' },
  locationTextActive: { color: colors.white },
  submitButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.error,
    padding: 16,
    borderRadius: 8,
    marginTop: 20,
    gap: 8,
  },
  submitButtonDisabled: { opacity: 0.6 },
  submitButtonText: { color: colors.white, fontSize: 16, fontWeight: 'bold' },
  infoBox: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#EFF8FF',
    padding: 12,
    borderRadius: 8,
    marginTop: 12,
    gap: 8,
  },
  infoText: { flex: 1, fontSize: 12, color: colors.info },
});
