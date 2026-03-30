import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  StyleSheet,
  ScrollView,
  Alert,
  KeyboardAvoidingView,
  Platform,
  Image,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { launchImageLibrary, Asset } from 'react-native-image-picker';
import { apiService } from '../../../shared/services/api.service';
import { theme } from '../../../shared/theme/theme';
import { imageToBase64 } from '../../../shared/utils/imageUtils';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface Props {
  navigation: any;
}

interface Hostel {
  id: number;
  name: string;
}

export const PostNoticeScreen: React.FC<Props> = ({ navigation }) => {
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [selectedImages, setSelectedImages] = useState<Asset[]>([]);
  const [hostelId, setHostelId] = useState<number | null>(null);
  const [hostels, setHostels] = useState<Hostel[]>([]);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [uploadingImages, setUploadingImages] = useState(false);

  useEffect(() => {
    fetchHostels();
  }, []);

  const fetchHostels = async () => {
    try {
      const response = await apiService.get<{ data: Hostel[] }>('/mobile/hostels');
      setHostels(response.data || []);
    } catch (error) {
      console.error('Failed to fetch hostels:', error);
    }
  };

  const handlePickImages = async () => {
    try {
      const remainingSlots = 4 - selectedImages.length;
      if (remainingSlots <= 0) {
        Alert.alert('Limit Reached', 'You can add up to 4 images');
        return;
      }

      const result = await launchImageLibrary({
        mediaType: 'photo',
        quality: 0.8,
        selectionLimit: remainingSlots,
      });

      if (result.assets && result.assets.length > 0) {
        setSelectedImages(prev => [...prev, ...result.assets!].slice(0, 4));
      }
    } catch (error) {
      console.error('Failed to pick images:', error);
      Alert.alert('Error', 'Failed to select images');
    }
  };

  const handleRemoveImage = (index: number) => {
    setSelectedImages(prev => prev.filter((_, i) => i !== index));
  };


  const handleSubmit = async () => {
    if (!title.trim()) {
      Alert.alert('Error', 'Please enter a title');
      return;
    }
    if (!description.trim()) {
      Alert.alert('Error', 'Please enter notice content');
      return;
    }
    if (title.length > 255) {
      Alert.alert('Error', 'Title must be 255 characters or less');
      return;
    }
    if (description.length > 5000) {
      Alert.alert('Error', 'Content must be 5000 characters or less');
      return;
    }

    setIsSubmitting(true);
    setUploadingImages(true);

    try {
      // Convert images to base64
      const imageBase64Array: string[] = [];
      for (const image of selectedImages) {
        if (image.uri) {
          const base64 = await imageToBase64(image.uri);
          if (base64) {
            imageBase64Array.push(base64);
          }
        }
      }

      setUploadingImages(false);

      // Prepare request body
      const requestBody: any = {
        title: title.trim(),
        content: description.trim(),
      };

      if (imageBase64Array.length > 0) {
        requestBody.images = imageBase64Array;
      }

      if (hostelId) {
        requestBody.hostel_id = hostelId;
        requestBody.audience = 'specific_hostel';
      } else {
        requestBody.audience = 'all_students';
      }

      await apiService.post('/mobile/notices', requestBody);
      
      Alert.alert(
        'Success', 
        'Notice posted successfully', 
        [{ text: 'OK', onPress: () => navigation.goBack() }]
      );
    } catch (error: any) {
      console.error('Failed to post notice:', error);
      const errorMessage = error?.response?.data?.detail || error?.message || 'Failed to post notice';
      Alert.alert('Error', errorMessage);
    } finally {
      setIsSubmitting(false);
      setUploadingImages(false);
    }
  };


  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Post Notice" />

      <ScrollView
        style={styles.content}
        showsVerticalScrollIndicator={false}
        keyboardShouldPersistTaps="handled"
        keyboardDismissMode="on-drag"
      >
        {/* Notice Content */}
        <View style={styles.inputGroup}>
          <Text style={styles.label}>Notice Title *</Text>
          <TextInput
            style={styles.titleInput}
            value={title}
            onChangeText={setTitle}
            placeholder="Enter notice title"
            placeholderTextColor={theme.colors.textMuted}
            maxLength={255}
          />
          <Text style={styles.charCount}>{title.length}/255</Text>
        </View>

        <View style={styles.inputGroup}>
          <Text style={styles.label}>Notice Content *</Text>
          <TextInput
            style={styles.descriptionInput}
            value={description}
            onChangeText={setDescription}
            placeholder="Enter detailed notice content..."
            placeholderTextColor={theme.colors.textMuted}
            multiline
            numberOfLines={6}
            textAlignVertical="top"
            maxLength={5000}
          />
          <Text style={styles.charCount}>{description.length}/5000</Text>
        </View>


        {/* Images Upload (Optional, 1-4 images) */}
        <View style={styles.inputGroup}>
          <Text style={styles.label}>
            Images (Optional) {selectedImages.length > 0 && `(${selectedImages.length}/4)`}
          </Text>
          {selectedImages.length > 0 && (
            <View style={styles.imagesGrid}>
              {selectedImages.map((image, index) => (
                <View key={index} style={styles.imagePreviewContainer}>
                  <Image source={{ uri: image.uri }} style={styles.imagePreview} />
                  <GradientButton
                    style={styles.removeImageButton}
                    onPress={() => handleRemoveImage(index)}
                  >
                    <Icon name="close-circle" size={24} color={theme.colors.error} />
                  </GradientButton>
                </View>
              ))}
            </View>
          )}
          {selectedImages.length < 4 && (
            <TouchableOpacity style={styles.imageUploadBox} onPress={handlePickImages}>
              <View style={styles.imagePlaceholder}>
                <Icon name="image-plus" size={40} color={theme.colors.textMuted} />
                <Text style={styles.imagePlaceholderText}>
                  Tap to add image {selectedImages.length > 0 && `(${4 - selectedImages.length} remaining)`}
                </Text>
              </View>
            </TouchableOpacity>
          )}
        </View>

        {/* Hostel Selection (Optional) */}
        {hostels.length > 0 && (
          <View style={styles.inputGroup}>
            <Text style={styles.label}>Target Hostel (Optional)</Text>
            <View style={styles.hostelGrid}>
              <TouchableOpacity
                style={[
                  styles.hostelChip,
                  hostelId === null && styles.hostelChipActive,
                ]}
                onPress={() => setHostelId(null)}
              >
                <Icon
                  name={hostelId === null ? 'checkbox-marked' : 'checkbox-blank-outline'}
                  size={18}
                  color={hostelId === null ? theme.colors.white : theme.colors.textSecondary}
                />
                <Text
                  style={[
                    styles.hostelChipText,
                    hostelId === null && styles.hostelChipTextActive,
                  ]}
                >
                  All Hostels
                </Text>
              </TouchableOpacity>
              {hostels.map((hostel) => (
                <TouchableOpacity
                  key={hostel.id}
                  style={[
                    styles.hostelChip,
                    hostelId === hostel.id && styles.hostelChipActive,
                  ]}
                  onPress={() => setHostelId(hostel.id)}
                >
                  <Icon
                    name={hostelId === hostel.id ? 'checkbox-marked' : 'checkbox-blank-outline'}
                    size={18}
                    color={hostelId === hostel.id ? theme.colors.white : theme.colors.textSecondary}
                  />
                  <Text
                    style={[
                      styles.hostelChipText,
                      hostelId === hostel.id && styles.hostelChipTextActive,
                    ]}
                  >
                    {hostel.name}
                  </Text>
                </TouchableOpacity>
              ))}
            </View>
          </View>
        )}

        {/* Submit Button */}
        <GradientButton
          style={[styles.submitMainButton, isSubmitting && styles.submitButtonDisabled]}
          onPress={handleSubmit}
          disabled={isSubmitting}
        >
          <Icon
            name="send"
            size={22}
            color={theme.colors.white}
          />
          <Text style={styles.submitMainButtonText}>
            {uploadingImages
              ? 'Processing images...'
              : isSubmitting
              ? 'Posting...'
              : 'Post Notice'}
          </Text>
        </GradientButton>

        <View style={styles.bottomPadding} />
      </ScrollView>

    </KeyboardAvoidingView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  submitButtonDisabled: {
    opacity: 0.6,
  },
  content: {
    flex: 1,
    padding: 16,
  },
  inputGroup: {
    marginBottom: 24,
  },
  labelRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: theme.colors.text,
    marginBottom: 8,
  },
  selectAllText: {
    fontSize: 14,
    fontWeight: '500',
    color: theme.colors.primary,
  },
  titleInput: {
    backgroundColor: theme.colors.card,
    borderRadius: 12,
    padding: 16,
    fontSize: 16,
    color: theme.colors.text,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  descriptionInput: {
    backgroundColor: theme.colors.card,
    borderRadius: 12,
    padding: 16,
    fontSize: 15,
    color: theme.colors.text,
    borderWidth: 1,
    borderColor: theme.colors.border,
    minHeight: 140,
  },
  charCount: {
    fontSize: 12,
    color: theme.colors.textMuted,
    textAlign: 'right',
    marginTop: 4,
  },
  hostelGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  hostelChip: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: theme.colors.surfaceMuted,
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderRadius: 20,
    gap: 6,
  },
  hostelChipActive: {
    backgroundColor: theme.colors.primary,
  },
  hostelChipText: {
    fontSize: 14,
    fontWeight: '500',
    color: theme.colors.textSecondary,
  },
  hostelChipTextActive: {
    color: theme.colors.white,
  },
  imageUploadBox: {
    backgroundColor: theme.colors.surfaceMuted,
    borderRadius: 12,
    borderWidth: 2,
    borderColor: theme.colors.border,
    borderStyle: 'dashed',
    overflow: 'hidden',
  },
  imagePlaceholder: {
    padding: 40,
    alignItems: 'center',
    justifyContent: 'center',
  },
  imagePlaceholderText: {
    fontSize: 14,
    color: theme.colors.textMuted,
    marginTop: 8,
  },
  imagePreviewContainer: {
    position: 'relative',
    width: '48%',
    borderRadius: 8,
    overflow: 'hidden',
  },
  imagesGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    marginBottom: 12,
  },
  imagePreview: {
    width: '100%',
    height: 200,
    resizeMode: 'cover',
    borderRadius: 8,
  },
  removeImageButton: {
    position: 'absolute',
    top: 8,
    right: 8,
    backgroundColor: theme.colors.white,
    borderRadius: 12,
  },
  optionsGrid: {
    flexDirection: 'row',
    gap: 12,
  },
  optionCard: {
    flex: 1,
    backgroundColor: theme.colors.card,
    padding: 16,
    borderRadius: 12,
    alignItems: 'center',
    borderWidth: 2,
    borderColor: theme.colors.border,
  },
  optionCardActive: {
    backgroundColor: theme.colors.surfaceElevated,
  },
  optionLabel: {
    fontSize: 13,
    fontWeight: '500',
    color: theme.colors.textSecondary,
    marginTop: 8,
  },
  postOptionsRow: {
    flexDirection: 'row',
    gap: 12,
  },
  postOptionButton: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: theme.colors.surfaceMuted,
    paddingVertical: 14,
    borderRadius: 12,
    gap: 8,
  },
  postOptionButtonActive: {
    backgroundColor: theme.colors.primary,
  },
  postOptionText: {
    fontSize: 15,
    fontWeight: '600',
    color: theme.colors.textSecondary,
  },
  postOptionTextActive: {
    color: theme.colors.white,
  },
  scheduleContainer: {
    flexDirection: 'row',
    gap: 12,
    marginTop: 16,
  },
  dateTimeButton: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: theme.colors.card,
    paddingVertical: 14,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: theme.colors.border,
    gap: 8,
  },
  dateTimeText: {
    fontSize: 14,
    fontWeight: '500',
    color: theme.colors.text,
  },
  submitMainButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#D79F24',
    paddingVertical: 16,
    borderRadius: 12,
    gap: 10,
    marginTop: 8,
    ...theme.shadows.medium,
  },
  submitMainButtonText: {
    fontSize: 16,
    fontWeight: '700',
    color: theme.colors.primary,
  },
  bottomPadding: {
    height: 40,
  },
});

export default PostNoticeScreen;
