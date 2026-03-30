// Jest setup file
// Note: react-native-gesture-handler/jestSetup is optional

// Mock react-native modules
jest.mock('react-native', () => {
  const RN = jest.requireActual('react-native');
  // Avoid TurboModule issues by not spreading the entire RN object
  return {
    Alert: {
      alert: jest.fn(),
    },
    Platform: {
      OS: 'ios',
      select: jest.fn((obj) => obj.ios || obj.default),
    },
    View: RN.View,
    Text: RN.Text,
    ScrollView: RN.ScrollView,
    TouchableOpacity: RN.TouchableOpacity,
    StyleSheet: RN.StyleSheet,
    RefreshControl: RN.RefreshControl,
    Modal: RN.Modal,
    TextInput: RN.TextInput,
    Switch: RN.Switch,
    Image: RN.Image,
    ActivityIndicator: RN.ActivityIndicator,
  };
});

// Mock @react-native-community modules
jest.mock('@react-native-community/datetimepicker', () => {
  return jest.fn();
});

jest.mock('@react-native-community/netinfo', () => {
  return {
    fetch: jest.fn(() => Promise.resolve({ isConnected: true })),
    addEventListener: jest.fn(),
    removeEventListener: jest.fn(),
  };
});

// Mock react-native-vector-icons
jest.mock('react-native-vector-icons/Ionicons', () => 'Ionicons');

// Mock react-native-haptic-feedback
jest.mock('react-native-haptic-feedback', () => ({
  trigger: jest.fn(),
}));

// Mock AsyncStorage (cross-platform storage)
jest.mock('@react-native-async-storage/async-storage', () => {
  const storage: Record<string, string> = {};
  return {
    default: {
      setItem: jest.fn((key: string, value: string) => {
        storage[key] = value;
        return Promise.resolve();
      }),
      getItem: jest.fn((key: string) => {
        return Promise.resolve(storage[key] || null);
      }),
      removeItem: jest.fn((key: string) => {
        delete storage[key];
        return Promise.resolve();
      }),
      clear: jest.fn(() => {
        Object.keys(storage).forEach(key => delete storage[key]);
        return Promise.resolve();
      }),
      getAllKeys: jest.fn(() => Promise.resolve(Object.keys(storage))),
    },
  };
});

// Silence console warnings in tests
global.console = {
  ...console,
  warn: jest.fn(),
  error: jest.fn(),
};

