# Quick Start: Build, Install & Test Student App

## Step 1: Connect Your Device

1. **Enable USB Debugging** on your Android device:
   - Go to Settings → About Phone
   - Tap "Build Number" 7 times to enable Developer Options
   - Go to Settings → Developer Options
   - Enable "USB Debugging"

2. **Connect device via USB** and verify:
   ```powershell
   adb devices
   ```
   You should see your device listed.

## Step 2: Build and Install APK

### Option A: Using PowerShell Script (Recommended)
```powershell
cd mapmars/mobile
.\build-and-install-student.ps1
```

### Option B: Using React Native CLI
```powershell
cd mapmars/mobile
npm install
npm run android:student
```

### Option C: Manual Build
```powershell
cd mapmars/mobile/android
.\gradlew.bat assembleStudentProductionDebug
cd ..
adb install android\app\build\outputs\apk\studentProduction\debug\app-student-production-debug.apk
```

## Step 3: Run Maestro Tests

### Option A: Using PowerShell Script (Recommended)
```powershell
cd mapmars/mobile
.\run-maestro-tests.ps1
```

### Option B: Manual Run
```powershell
cd mapmars/mobile/maestro
maestro test student/00-student-complete-test.yaml --env phone="YOUR_PHONE" --env otp="123456" --env tenant_code="PPCU"
```

## Troubleshooting

### Device Not Detected
- Check USB cable connection
- Enable USB debugging
- Try different USB port
- Run `adb kill-server` then `adb start-server`

### Build Fails
- Ensure Java/JDK is installed
- Check Android SDK is configured
- Try: `npm install` first
- Check `android/local.properties` for SDK path

### Installation Fails
- Uninstall existing app first: `adb uninstall com.mapmars.hmsstudent`
- Check device has enough storage
- Enable "Install via USB" in Developer Options

### Maestro Not Found
```powershell
# Install Maestro
curl -fsSL "https://get.maestro.mobile.dev" | bash

# Or download from: https://maestro.mobile.dev/getting-started/installing-maestro
```

## Test Credentials

Update these in the test file or use `--env` flags:
- **Phone**: Your test student phone number
- **OTP**: `123456` (bypass code for testing)
- **Tenant Code**: `PPCU` (or your tenant code)

## Quick Commands

```powershell
# Check device
adb devices

# Install APK manually
adb install -r path\to\app.apk

# Uninstall app
adb uninstall com.mapmars.hmsstudent

# View app logs
adb logcat | findstr "ReactNativeJS"

# Run specific test
cd mapmars/mobile/maestro
maestro test student/02-student-login-test.yaml
```
