/**
 * PhotoUpload Component for Student App
 * 
 * Simple photo upload component that works with react-hook-form
 * Note: Full image picker functionality requires react-native-image-picker
 * This is a basic implementation that can be enhanced when image picker is enabled
 */

import React from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  Image,
  StyleSheet,
  Alert,
  Platform,
} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { theme } from '../../theme/theme';

interface PhotoUploadProps {
  photos: string[];
  onPhotosChange: (photos: string[]) => void;
  maxPhotos?: number;
  error?: string;
}

export const PhotoUpload: React.FC<PhotoUploadProps> = ({
  photos,
  onPhotosChange,
  maxPhotos = 3,
  error,
}) => {
  const handleAddPhoto = () => {
    // Placeholder implementation
    // TODO: Integrate react-native-image-picker when enabled
    Alert.alert(
      'Photo Upload',
      'Photo upload functionality will be available soon. For now, you can submit tickets without photos.',
      [{ text: 'OK' }]
    );
    
    // When image picker is enabled, use this:
    // Alert.alert(
    //   'Add Photo',
    //   'Choose photo source',
    //   [
    //     { text: 'Camera', onPress: () => openCamera() },
    //     { text: 'Gallery', onPress: () => openGallery() },
    //     { text: 'Cancel', style: 'cancel' },
    //   ]
    // );
  };

  const handleRemovePhoto = (index: number) => {
    const updatedPhotos = photos.filter((_, i) => i !== index);
    onPhotosChange(updatedPhotos);
  };

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.label}>
          Upload Photo / Document (Optional, up to {maxPhotos})
        </Text>
        {photos.length > 0 && (
          <Text style={styles.helperText}>
            {photos.length} / {maxPhotos} added
          </Text>
        )}
      </View>

      {photos.length > 0 && (
        <View style={styles.photoList}>
          {photos.map((photo, index) => (
            <View key={index} style={styles.photoCard}>
              <Image source={{ uri: photo }} style={styles.photoImage} />
              <TouchableOpacity
                style={styles.removeButton}
                onPress={() => handleRemovePhoto(index)}
                accessibilityLabel="Remove photo"
                accessibilityRole="button">
                <Ionicons name="close-circle" size={24} color={theme.colors.error} />
              </TouchableOpacity>
            </View>
          ))}
        </View>
      )}

      {photos.length < maxPhotos && (
        <TouchableOpacity
          style={[styles.uploadButton, error && styles.uploadButtonError]}
          onPress={handleAddPhoto}
          accessibilityLabel="Add photo"
          accessibilityRole="button">
          <Ionicons name="camera-outline" size={20} color={theme.colors.primary} />
          <Text style={styles.uploadButtonText}>Choose File</Text>
        </TouchableOpacity>
      )}

      {error && <Text style={styles.errorText}>{error}</Text>}

      <Text style={styles.infoText}>
        ℹ️ Photos are optional. You can submit tickets without them.
      </Text>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    marginBottom: theme.spacing.lg,
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: theme.spacing.sm,
  },
  label: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
  },
  helperText: {
    fontSize: theme.fontSize.xs,
    color: theme.colors.textMuted,
  },
  photoList: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: theme.spacing.sm,
    marginBottom: theme.spacing.sm,
  },
  photoCard: {
    width: 100,
    height: 100,
    borderRadius: theme.borderRadius.md,
    overflow: 'hidden',
    position: 'relative',
    backgroundColor: theme.colors.surface,
  },
  photoImage: {
    width: '100%',
    height: '100%',
    resizeMode: 'cover',
  },
  removeButton: {
    position: 'absolute',
    top: 4,
    right: 4,
    backgroundColor: theme.colors.white,
    borderRadius: 12,
  },
  uploadButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    padding: theme.spacing.md,
    borderWidth: 2,
    borderColor: theme.colors.primary,
    borderRadius: theme.borderRadius.md,
    borderStyle: 'dashed',
    gap: theme.spacing.xs,
    backgroundColor: theme.colors.white,
  },
  uploadButtonError: {
    borderColor: theme.colors.error,
  },
  uploadButtonText: {
    fontSize: theme.fontSize.md,
    color: theme.colors.primary,
    fontWeight: theme.fontWeight.semibold,
  },
  errorText: {
    color: theme.colors.error,
    fontSize: theme.fontSize.xs,
    marginTop: theme.spacing.xs,
  },
  infoText: {
    fontSize: theme.fontSize.xs,
    color: theme.colors.textMuted,
    marginTop: theme.spacing.xs,
    fontStyle: 'italic',
  },
});

