import React, { useState, useEffect, useMemo } from 'react';
import { View, Image, Text, StyleSheet } from 'react-native';
import { SvgUri } from 'react-native-svg';
import { resolveTenantLogoUrl } from '../utils/tenant-logo-url.util';

interface CollegeLogoProps {
  logoUrl?: string;
  collegeName?: string;
  size?: 'small' | 'medium' | 'large';
  showName?: boolean;
  /** When true, logo fills its container (e.g. 60% greeting card area). Use with a flex wrapper. */
  fillContainer?: boolean;
}

const SIZES = {
  small: 40,
  medium: 60,
  large: 80,
};

export const CollegeLogo: React.FC<CollegeLogoProps> = ({
  logoUrl,
  collegeName = 'College',
  size = 'medium',
  showName = false,
  fillContainer = false,
}) => {
  const dimension = SIZES[size];
  const [imageFailed, setImageFailed] = useState(false);
  const [fallbackImageFailed, setFallbackImageFailed] = useState(false);
  const normalizedLogoUrl = useMemo(() => resolveTenantLogoUrl(logoUrl), [logoUrl]);
  const isSvgLogo = useMemo(() => {
    if (!normalizedLogoUrl) {
      return false;
    }

    return /^data:image\/svg\+xml/i.test(normalizedLogoUrl) || /\.svg(?:[?#].*)?$/i.test(normalizedLogoUrl);
  }, [normalizedLogoUrl]);

  useEffect(() => {
    setImageFailed(false);
    setFallbackImageFailed(false);
  }, [normalizedLogoUrl]);

  const containerStyle = fillContainer ? [styles.container, styles.containerFill] : styles.container;
  const logoStyle = fillContainer
    ? [styles.logo, styles.logoFill]
    : [styles.logo, { width: dimension, height: dimension, borderRadius: dimension / 2 }];
  const placeholderStyle = fillContainer
    ? [styles.placeholder, styles.logoFill]
    : [styles.placeholder, { width: dimension, height: dimension, borderRadius: dimension / 2 }];

  const showImage = !!normalizedLogoUrl && !imageFailed;
  const showFallbackLogo = !showImage && !fallbackImageFailed;

  const renderPlaceholder = () => {
    const initials = collegeName
      .split(' ')
      .map(word => word[0])
      .join('')
      .substring(0, 2)
      .toUpperCase();

    return (
      <View style={placeholderStyle}>
        <Text style={[styles.initials, { fontSize: fillContainer ? 24 : dimension * 0.4 }]}>{initials}</Text>
      </View>
    );
  };

  return (
    <View style={containerStyle}>
      {showImage ? (
        isSvgLogo ? (
          <View style={logoStyle}>
            <SvgUri uri={normalizedLogoUrl as string} width="100%" height="100%" onError={() => setImageFailed(true)} />
          </View>
        ) : (
          <Image
            source={{ uri: normalizedLogoUrl as string }}
            style={logoStyle}
            resizeMode="contain"
            onError={() => setImageFailed(true)}
          />
        )
      ) : showFallbackLogo ? (
        <Image
          source={require('../assets/map-logo.png')}
          style={logoStyle}
          resizeMode="contain"
          onError={() => setFallbackImageFailed(true)}
        />
      ) : (
        renderPlaceholder()
      )}
      {showName && <Text style={styles.name}>{collegeName}</Text>}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    alignItems: 'center',
  },
  containerFill: {
    flex: 1,
    minWidth: 0,
    justifyContent: 'center',
    alignItems: 'stretch',
  },
  logo: {
    backgroundColor: '#FFFFFF',
    overflow: 'hidden',
  },
  logoFill: {
    width: '92%',
    height: '92%',
    maxWidth: '100%',
    maxHeight: '100%',
    borderRadius: 12,
    alignSelf: 'center',
  },
  placeholder: {
    backgroundColor: '#3B82F6',
    justifyContent: 'center',
    alignItems: 'center',
  },
  initials: {
    color: '#FFFFFF',
    fontWeight: '700',
  },
  name: {
    marginTop: 8,
    fontSize: 14,
    fontWeight: '600',
    color: '#1F2937',
    textAlign: 'center',
  },
});

export default CollegeLogo;
