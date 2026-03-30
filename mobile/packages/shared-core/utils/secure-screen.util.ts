/**
 * Secure Screen Utility
 * Prevents screenshots and screen recording on sensitive screens (OTP, PII)
 * 
 * For iOS: Uses native module if available
 * For Android: Uses WindowManager.LayoutParams.FLAG_SECURE
 */

import { Platform, NativeModules } from 'react-native';

const { SecureScreenModule } = NativeModules;

/**
 * Enable screenshot blocking on current screen
 */
export const enableSecureScreen = (): void => {
  if (Platform.OS === 'android') {
    // Android: Use native module if available
    if (SecureScreenModule && SecureScreenModule.setSecureFlag) {
      SecureScreenModule.setSecureFlag(true);
    } else if (SecureScreenModule && SecureScreenModule.enableSecureScreen) {
      SecureScreenModule.enableSecureScreen();
    } else {
      // Fallback: Log warning if native module not available
      console.warn('[SecureScreen] Native module not available. Screenshot blocking may not work.');
    }
  } else if (Platform.OS === 'ios') {
    // iOS: Use native module if available
    if (SecureScreenModule && SecureScreenModule.preventScreenshot) {
      SecureScreenModule.preventScreenshot(true);
    } else {
      console.warn('[SecureScreen] Native module not available. Screenshot blocking may not work.');
    }
  }
};

/**
 * Disable screenshot blocking (restore normal behavior)
 */
export const disableSecureScreen = (): void => {
  if (Platform.OS === 'android') {
    if (SecureScreenModule && SecureScreenModule.setSecureFlag) {
      SecureScreenModule.setSecureFlag(false);
    } else if (SecureScreenModule && SecureScreenModule.disableSecureScreen) {
      SecureScreenModule.disableSecureScreen();
    }
  } else if (Platform.OS === 'ios') {
    if (SecureScreenModule && SecureScreenModule.preventScreenshot) {
      SecureScreenModule.preventScreenshot(false);
    }
  }
};

