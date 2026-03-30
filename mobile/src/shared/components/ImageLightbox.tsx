import React, { useState } from 'react';
import {
  Modal,
  View,
  Image,
  TouchableOpacity,
  StyleSheet,
  Dimensions,
  ScrollView,
  Alert,
  Text,
} from 'react-native';
import { GradientButton } from './GradientButton';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { theme } from '../theme/theme';

interface ImageLightboxProps {
  visible: boolean;
  images: string[];
  initialIndex?: number;
  onClose: () => void;
}

export const ImageLightbox: React.FC<ImageLightboxProps> = ({
  visible,
  images,
  initialIndex = 0,
  onClose,
}) => {
  const [currentImageIndex, setCurrentImageIndex] = useState(initialIndex);

  const handleNextImage = () => {
    if (currentImageIndex < images.length - 1) {
      setCurrentImageIndex(currentImageIndex + 1);
    }
  };

  const handlePreviousImage = () => {
    if (currentImageIndex > 0) {
      setCurrentImageIndex(currentImageIndex - 1);
    }
  };

  const handleImagePress = () => {
    // Could add zoom functionality here
  };

  const handleDownloadImage = () => {
    const currentImage = images[currentImageIndex];
    Alert.alert(
      'Download Image',
      'Download functionality would be available here',
      [{ text: 'OK' }]
    );
  };

  if (!visible || images.length === 0) {
    return null;
  }

  return (
    <Modal
      visible={visible}
      transparent={true}
      animationType="fade"
      onRequestClose={onClose}
    >
      <View style={styles.overlay}>
        {/* Header */}
        <View style={styles.header}>
          <GradientButton style={styles.headerButton} onPress={onClose}>
            <Ionicons name="close" size={28} color={theme.colors.white} />
          </GradientButton>
          
          <Text style={styles.headerTitle}>
            Image {currentImageIndex + 1} of {images.length}
          </Text>
          
          <GradientButton style={styles.headerButton} onPress={handleDownloadImage}>
            <Ionicons name="download" size={24} color={theme.colors.white} />
          </GradientButton>
        </View>

        {/* Main Image */}
        <View style={styles.imageContainer}>
          <TouchableOpacity
            style={styles.imageTouchable}
            onPress={handleImagePress}
            activeOpacity={1}
          >
            <Image
              source={{ uri: images[currentImageIndex] }}
              style={styles.mainImage}
              resizeMode="contain"
            />
          </TouchableOpacity>
        </View>

        {/* Navigation Buttons */}
        {images.length > 1 && (
          <>
            <GradientButton
              style={[
                styles.navButton,
                styles.navButtonLeft,
                currentImageIndex === 0 && styles.navButtonDisabled,
              ]}
              onPress={handlePreviousImage}
              disabled={currentImageIndex === 0}
            >
              <Ionicons name="chevron-back" size={32} color={theme.colors.white} />
            </GradientButton>

            <GradientButton
              style={[
                styles.navButton,
                styles.navButtonRight,
                currentImageIndex === images.length - 1 && styles.navButtonDisabled,
              ]}
              onPress={handleNextImage}
              disabled={currentImageIndex === images.length - 1}
            >
              <Ionicons name="chevron-forward" size={32} color={theme.colors.white} />
            </GradientButton>
          </>
        )}

        {/* Thumbnail Gallery */}
        {images.length > 1 && (
          <View style={styles.thumbnailContainer}>
            <ScrollView
              horizontal
              showsHorizontalScrollIndicator={false}
              contentContainerStyle={styles.thumbnailScroll}
            >
              {images.map((img, index) => (
                <TouchableOpacity
                  key={index}
                  style={[
                    styles.thumbnail,
                    index === currentImageIndex && styles.thumbnailActive,
                  ]}
                  onPress={() => setCurrentImageIndex(index)}
                >
                  <Image source={{ uri: img }} style={styles.thumbnailImage} />
                </TouchableOpacity>
              ))}
            </ScrollView>
          </View>
        )}
      </View>
    </Modal>
  );
};

const { width: screenWidth, height: screenHeight } = Dimensions.get('window');

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.92)',
    justifyContent: 'flex-start',
    alignItems: 'stretch',
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingTop: 50,
    paddingBottom: 12,
    backgroundColor: 'rgba(0, 0, 0, 0.3)',
  },
  headerTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: theme.colors.white,
  },
  headerButton: {
    padding: 8,
  },
  imageContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  imageTouchable: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  mainImage: {
    width: screenWidth * 0.95,
    height: screenHeight * 0.75,
  },
  navButton: {
    position: 'absolute',
    top: '50%',
    transform: [{ translateY: -30 }],
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    borderRadius: 50,
    padding: 16,
  },
  navButtonLeft: {
    left: 16,
  },
  navButtonRight: {
    right: 16,
  },
  navButtonDisabled: {
    opacity: 0.3,
  },
  thumbnailContainer: {
    height: 80,
    backgroundColor: 'rgba(0, 0, 0, 0.3)',
    paddingVertical: 10,
  },
  thumbnailScroll: {
    paddingHorizontal: 16,
  },
  thumbnail: {
    width: 60,
    height: 60,
    borderRadius: 8,
    marginRight: 8,
    opacity: 0.7,
  },
  thumbnailActive: {
    opacity: 1,
    borderWidth: 2,
    borderColor: theme.colors.primary,
  },
  thumbnailImage: {
    width: '100%',
    height: '100%',
    borderRadius: 6,
  },
});