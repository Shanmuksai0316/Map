/**
 * @format
 */

import { AppRegistry, LogBox } from 'react-native';
import App from './App';
import { name as appName } from './app.json';

if (__DEV__) {
  LogBox.ignoreAllLogs(true);
  // Keep store screenshot runs clean from dev warning/error overlays.
  console.warn = () => {};
  console.error = () => {};
}

AppRegistry.registerComponent(appName, () => App);
