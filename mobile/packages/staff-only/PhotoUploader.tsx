/**
 * PhotoUploader Component
 * 
 * Allows uploading up to 3 photos for security incidents or tickets.
 * Gracefully degrades if photo upload fails (allows text-only submission).
 */

import React from 'react';
import { View, Text, TouchableOpacity, Image, StyleSheet, Alert, Platform } from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { launchImageLibrary, launchCamera, ImagePickerResponse } from 'react-native-image-picker';
import { colors } from '../../theme/colors';

interface Photo {
  uri: string;
  name: string;
  type: string;
}

interface PhotoUploaderProps {
  photos: Photo[];
  onPhotosChange: (photos: Photo[]) => void;
  maxPhotos?: number;
}

export const PhotoUploader: React.FC<PhotoUploaderProps> = ({
  photos,
  onPhotosChange,
  maxPhotos = 3,
}) => {
  const handleAddPhoto = () => {
    Alert.alert(
      'Add Photo',
      'Choose photo source',
      [
        {
          text: 'Camera',
          onPress: () => openCamera(),
        },
        {
          text: 'Gallery',
          onPress: () => openGallery(),
        },
        {
          text: 'Cancel',
          style: 'cancel',
        },
      ]
    );
  };

  const openCamera = async () => {
    try {
      const result = await launchCamera({
        mediaType: 'photo',
        quality: 0.8,
        saveToPhotos: false,
      });
      handleImagePick(result);
    } catch (error) {
      console.error('Camera error:', error);
      Alert.alert('Error', 'Failed to open camera. You can still submit without photos.');
    }
  };

  const openGallery = async () => {
    try {
      const result = await launchImageLibrary({
        mediaType: 'photo',
        quality: 0.8,
        selectionLimit: maxPhotos - photos.length,
      });
      handleImagePick(result);
    } catch (error) {
      console.error('Gallery error:', error);
      Alert.alert('Error', 'Failed to open gallery. You can still submit without photos.');
    }
  };

  const handleImagePick = (result: ImagePickerResponse) => {
    if (result.didCancel) {
      return;
    }

    if (result.errorCode) {
      Alert.alert('Error', result.errorMessage || 'Failed to select photo');
      return;
    }

    if (result.assets && result.assets.length > 0) {
      const newPhotos: Photo[] = result.assets
        .filter(asset => asset.uri && asset.fileName && asset.type)
        .map(asset => ({
          uri: asset.uri!,
          name: asset.fileName!,
          type: asset.type || 'image/jpeg',
        }));

      onPhotosChange([...photos, ...newPhotos].slice(0, maxPhotos));
    }
  };

  const handleRemovePhoto = (index: number) => {
    const updatedPhotos = photos.filter((_, i) => i !== index);
    onPhotosChange(updatedPhotos);
  };

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.label}>Photos (Optional, up to {maxPhotos})</Text>
        <Text style={styles.helperText}>
          {photos.length} / {maxPhotos} added
        </Text>
      </View>

      {photos.length > 0 && (
        <View style={styles.photoList}>
          {photos.map((photo, index) => (
            <View key={index} style={styles.photoCard}>
              <Image source={{ uri: photo.uri }} style={styles.photoImage} />
              <TouchableOpacity
                style={styles.removeButton}
                onPress={() => handleRemovePhoto(index)}>
                <Ionicons name="close-circle" size={24} color={colors.error} />
              </TouchableOpacity>
            </View>
          ))}
        </View>
      )}

      {photos.length < maxPhotos && (
        <TouchableOpacity style={styles.addButton} onPress={handleAddPhoto}>
          <Ionicons name="camera-outline" size={24} color={colors.primary} />
          <Text style={styles.addButtonText}>Add Photo</Text>
        </TouchableOpacity>
      )}

      <Text style={styles.infoText}>
        ℹ️ Photos are optional. You can submit without them if upload fails.
      </Text>
    </View>
  );
};

const styles = StyleSheet.create({
  container: { marginBottom: 16 },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  label: { fontSize: 14, fontWeight: '600', color: colors.text },
  helperText: { fontSize: 12, color: colors.textMuted },
  photoList: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
    marginBottom: 12,
  },
  photoCard: {
    width: 100,
    height: 100,
    borderRadius: 8,
    overflow: 'hidden',
    position: 'relative',
  },
  photoImage: { width: '100%', height: '100%', resizeMode: 'cover' },
  removeButton: {
    position: 'absolute',
    top: 4,
    right: 4,
    backgroundColor: colors.white,
    borderRadius: 12,
  },
  addButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 16,
    borderWidth: 2,
    borderColor: colors.primary,
    borderRadius: 8,
    borderStyle: 'dashed',
    gap: 8,
  },
  addButtonText: { fontSize: 14, color: colors.primary, fontWeight: '600' },
  infoText: {
    fontSize: 12,
    color: colors.textMuted,
    marginTop: 8,
    fontStyle: 'italic',
  },
});

