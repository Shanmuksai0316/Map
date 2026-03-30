import React, { useEffect } from 'react';
import { View, ViewProps } from 'react-native';
import { enableSecureScreen, disableSecureScreen } from '../utils/secure-screen.util';

interface SecureScreenProps extends ViewProps {
  children: React.ReactNode;
  enabled?: boolean;
}

/**
 * SecureScreen component that blocks screenshots when enabled
 * Used for OTP input screens and PII reveal screens per MASVS L2 requirements
 */
export const SecureScreen: React.FC<SecureScreenProps> = ({
  children,
  enabled = true,
  ...viewProps
}) => {
  useEffect(() => {
    if (enabled) {
      enableSecureScreen();
    }

    return () => {
      if (enabled) {
        disableSecureScreen();
      }
    };
  }, [enabled]);

  return <View {...viewProps}>{children}</View>;
};

