import React, { useState, useEffect } from 'react';
import { StatusBar, View, Text, Alert } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { RootNavigator } from './src/shared/navigation/RootNavigator';
import { ErrorBoundary } from './src/shared/components/ErrorBoundary';

function App(): React.JSX.Element {
  const [hasError, setHasError] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    console.log('🔥 App component rendering - checking if it works');
  }, []);

  // Error boundary for React component errors
  if (hasError) {
    return (
      <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: 'red' }}>
        <Text style={{ color: 'white', fontSize: 24, fontWeight: 'bold' }}>🚨 APP ERROR 🚨</Text>
        <Text style={{ color: 'yellow', fontSize: 16, marginTop: 10 }}>Error: {error}</Text>
      </View>
    );
  }

  try {
  return (
    <SafeAreaProvider>
      <ErrorBoundary>
        <StatusBar barStyle="dark-content" backgroundColor="#fff" />
        <RootNavigator />
      </ErrorBoundary>
    </SafeAreaProvider>
  );
  } catch (err: any) {
    console.error('App render error:', err);
    setHasError(true);
    setError(err.message);
    return (
      <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: 'red' }}>
        <Text style={{ color: 'white', fontSize: 24, fontWeight: 'bold' }}>🚨 APP CRASH 🚨</Text>
        <Text style={{ color: 'yellow', fontSize: 16, marginTop: 10 }}>{err.message}</Text>
      </View>
    );
  }
}

export default App;
