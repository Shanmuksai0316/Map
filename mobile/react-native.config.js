module.exports = {
  project: {
    android: {},
    ios: {},
  },
  assets: ['./assets/fonts/'],
  dependencies: {
    // Disable problematic packages that we don't need for testing
    // Note: NetInfo is now enabled for offline queue functionality
    'react-native-keychain': {
      platforms: {
        android: null,
      },
    },
    '@sentry/react-native': {
      platforms: {
        android: null,
      },
    },
    'react-native-fs': {
      platforms: {
        android: null,
      },
    },
    'react-native-permissions': {
      platforms: {
        android: null,
      },
    },
    'react-native-secure-screen': {
      platforms: {
        android: null,
      },
    },
  },
};
