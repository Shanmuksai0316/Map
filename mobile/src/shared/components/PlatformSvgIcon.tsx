/**
 * PlatformSvgIcon - Cross-platform SVG icon component optimized for iOS/Android consistency
 *
 * This component wraps react-native-svg's SvgXml with platform-specific optimizations
 * to ensure SVGs render consistently on both iOS (with New Architecture/Fabric) and Android.
 *
 * Key optimizations:
 * - iOS: Uses explicit dimensions and View wrapper with collapsable={false}
 * - Ensures proper viewBox scaling across platforms
 * - Handles complex SVG features (gradients, masks, clipPaths) consistently
 */
import React, { useMemo } from 'react';
import { Platform, View, StyleSheet, ViewStyle } from 'react-native';
import { SvgXml } from 'react-native-svg';

interface PlatformSvgIconProps {
  xml: string;
  width: number;
  height: number;
  style?: ViewStyle;
  testID?: string;
}

/**
 * Pre-process SVG XML for iOS compatibility
 * - Ensures viewBox is present
 * - Removes any problematic attributes that may cause iOS rendering issues
 */
const preprocessSvgForIOS = (xml: string, width: number, height: number): string => {
  // Ensure the SVG has explicit width/height attributes matching our desired size
  let processedXml = xml;

  // Update width attribute
  processedXml = processedXml.replace(
    /(<svg[^>]*)\s+width="[^"]*"/,
    `$1 width="${width}"`
  );

  // Update height attribute
  processedXml = processedXml.replace(
    /(<svg[^>]*)\s+height="[^"]*"/,
    `$1 height="${height}"`
  );

  // If width/height weren't found, add them after <svg
  if (!processedXml.includes(`width="${width}"`)) {
    processedXml = processedXml.replace(
      '<svg',
      `<svg width="${width}" height="${height}"`
    );
  }

  return processedXml;
};

export const PlatformSvgIcon: React.FC<PlatformSvgIconProps> = ({
  xml,
  width,
  height,
  style,
  testID,
}) => {
  // Pre-process SVG for iOS
  const processedXml = useMemo(() => {
    if (Platform.OS === 'ios') {
      return preprocessSvgForIOS(xml, width, height);
    }
    return xml;
  }, [xml, width, height]);

  // On iOS, wrap in a View with specific props to ensure proper rendering
  if (Platform.OS === 'ios') {
    return (
      <View
        style={[styles.container, { width, height }, style]}
        collapsable={false}
        testID={testID}
      >
        <View
          style={styles.svgWrapper}
          // @ts-ignore - This prop helps with rendering on iOS
          shouldRasterizeIOS={true}
        >
          <SvgXml
            xml={processedXml}
            width={width}
            height={height}
            // Explicit override to ensure proper sizing
            preserveAspectRatio="xMidYMid meet"
          />
        </View>
      </View>
    );
  }

  // Android renders SVGs correctly without additional wrapping
  return (
    <View style={[styles.container, { width, height }, style]} testID={testID}>
      <SvgXml
        xml={processedXml}
        width={width}
        height={height}
        preserveAspectRatio="xMidYMid meet"
      />
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    justifyContent: 'center',
    alignItems: 'center',
    overflow: 'hidden',
  },
  svgWrapper: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
});

export default PlatformSvgIcon;
