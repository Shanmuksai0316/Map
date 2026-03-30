# React Native Debugging Guide

## Quick Access Methods

### Method 1: Shake Gesture (Physical Device)
1. **Shake your device** (or press `Cmd+D` on iOS Simulator / `Cmd+M` on Android Emulator)
2. Select **"Debug"** or **"Open Debugger"** from the menu

### Method 2: Keyboard Shortcuts
- **iOS Simulator**: `Cmd + D` (Mac) or `Ctrl + D` (Windows/Linux)
- **Android Emulator**: `Cmd + M` (Mac) or `Ctrl + M` (Windows/Linux)
- **Physical Device**: Shake gesture

### Method 3: Command Line
```bash
# Open React Native debugger
npx react-native start
# Then press 'j' to open debugger in browser
```

## Debug Menu Options

When you open the debug menu, you'll see:

1. **Debug** - Opens Chrome DevTools
2. **Reload** - Reloads the app
3. **Enable Hot Reloading** - Auto-reload on code changes
4. **Enable Fast Refresh** - Faster reload
5. **Show Inspector** - Element inspector
6. **Show Perf Monitor** - Performance monitoring

## Chrome DevTools

### Accessing Chrome DevTools
1. Open debug menu (shake device or `Cmd+D`)
2. Select **"Debug"**
3. Chrome will open automatically at `http://localhost:8081/debugger-ui/`
4. Or manually open: `http://localhost:8081/debugger-ui/`

### What You Can Do in Chrome DevTools
- **Console Tab**: View `console.log()` output, errors, warnings
- **Network Tab**: See all API requests/responses
- **Sources Tab**: Set breakpoints, debug JavaScript
- **Application Tab**: View storage, cookies, etc.

## React Native Debugger (Standalone App)

### Installation
```bash
# macOS
brew install --cask react-native-debugger

# Or download from:
# https://github.com/jhen0409/react-native-debugger/releases
```

### Usage
1. Install React Native Debugger app
2. Open the app
3. In your React Native app, open debug menu
4. Select **"Debug"** - it will connect to React Native Debugger

### Features
- Redux DevTools integration
- React DevTools
- Network inspector
- Console logs
- Breakpoints

## Flipper (Recommended for Advanced Debugging)

### Installation
```bash
# macOS
brew install --cask flipper

# Or download from:
# https://fbflipper.com/
```

### Setup
1. Install Flipper
2. Open Flipper
3. In your React Native app, open debug menu
4. Select **"Open Flipper"** (if available)

### Features
- Network inspector
- React DevTools
- Redux DevTools
- Logs viewer
- Layout inspector
- Performance profiler

## Console Logs

### View Logs in Terminal
```bash
# Start Metro bundler
npx react-native start

# Or with logs
npx react-native start --verbose
```

### View Logs in Device
- **iOS**: Xcode console
- **Android**: `adb logcat` or Android Studio Logcat

### View Logs in Chrome DevTools
1. Open debug menu → Debug
2. Open Chrome DevTools
3. Go to **Console** tab
4. All `console.log()` output appears here

## Network Debugging

### Method 1: Chrome DevTools Network Tab
1. Open debug menu → Debug
2. Open Chrome DevTools
3. Go to **Network** tab
4. See all API requests/responses

### Method 2: React Native Debugger
- Network requests appear in Network inspector

### Method 3: Flipper
- Network plugin shows all requests

### Method 4: Charles Proxy / Proxyman
- Intercept and inspect network traffic

## Debugging API Calls

### View Request Details
In Chrome DevTools Network tab, you can see:
- Request URL
- Request method (GET, POST, etc.)
- Request headers
- Request body
- Response status
- Response headers
- Response body

### Common Issues to Check
1. **400 Error**: Check request body format
2. **401 Error**: Check Authorization header
3. **404 Error**: Check API endpoint URL
4. **500 Error**: Check server logs

## Element Inspector

### Access Inspector
1. Open debug menu (shake device)
2. Select **"Show Inspector"**
3. Tap elements to see their properties

### React DevTools
1. Install React DevTools browser extension
2. Open debug menu → Debug
3. React DevTools will appear in Chrome

## Performance Debugging

### Perf Monitor
1. Open debug menu
2. Select **"Show Perf Monitor"**
3. See FPS, memory usage, etc.

### React Profiler
1. Install React DevTools
2. Open Profiler tab
3. Record and analyze performance

## Debugging Tips for Your 400 Error

### Step 1: Open Debug Menu
Shake device or press `Cmd+D` / `Cmd+M`

### Step 2: Enable Debugging
Select **"Debug"** from menu

### Step 3: Open Chrome DevTools
Chrome should open automatically, or go to `http://localhost:8081/debugger-ui/`

### Step 4: Check Console Tab
Look for:
- Error messages
- `console.log()` output from your code
- Network errors

### Step 5: Check Network Tab
1. Go to **Network** tab in Chrome DevTools
2. Try logging in again
3. Find the request to `/mobile/auth/send-otp` or `/mobile/auth/verify-otp`
4. Click on it to see:
   - **Headers**: Check `Content-Type`, `X-Tenant-Code`
   - **Payload**: Check request body format
   - **Response**: See error details

### Step 6: Check Request Payload
In Network tab, click on the failed request:
- **Request Payload** section shows what was sent
- Verify:
  - `phone` is a string (not number)
  - `phone` has no spaces/dashes
  - `otp` is a string (not number)
  - `otp` is exactly 6 digits
  - JSON is properly formatted

## Quick Commands

```bash
# Start Metro bundler with verbose logs
npx react-native start --verbose

# Clear Metro cache
npx react-native start --reset-cache

# View Android logs
adb logcat | grep ReactNativeJS

# View iOS logs (in Xcode)
# Open Xcode → Window → Devices and Simulators → View Device Logs
```

## Troubleshooting

### Debug Menu Not Appearing
- **Physical Device**: Make sure you're shaking it properly
- **Emulator**: Try `Cmd+D` or `Cmd+M`
- **Check**: Ensure app is in development mode (not production build)

### Chrome DevTools Not Opening
- Check if port 8081 is available
- Try manually opening: `http://localhost:8081/debugger-ui/`
- Check firewall settings

### Network Requests Not Showing
- Make sure you're using `apiService` (not direct fetch)
- Check if requests are being intercepted
- Verify API base URL is correct

### Logs Not Appearing
- Check if `console.log()` is being called
- Verify Metro bundler is running
- Check Chrome DevTools Console tab
- Try `console.warn()` or `console.error()` for visibility

## For Your Current 400 Error

### Immediate Steps:
1. **Shake device** or press `Cmd+D` / `Cmd+M`
2. Select **"Debug"**
3. Open Chrome DevTools
4. Go to **Network** tab
5. Try logging in
6. Find the failed request
7. Check:
   - Request Payload (what was sent)
   - Response (error details)
   - Headers (Content-Type, X-Tenant-Code)

This will show you exactly what's being sent and why it's failing validation.
