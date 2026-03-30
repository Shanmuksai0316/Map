# Build APK and Run Tests - Step by Step

## Current Status
Your device is not currently detected. Follow these steps:

## Step 1: Connect Your Device

1. **On your Android phone:**
   - Settings → About Phone → Tap "Build Number" 7 times
   - Settings → Developer Options → Enable "USB Debugging"
   - Connect phone to computer via USB
   - When prompted on phone, tap "Allow USB Debugging"

2. **Verify connection:**
   ```powershell
   adb devices
   ```
   You should see your device ID listed.

## Step 2: Build the APK

### Method 1: Using React Native (Easiest)
```powershell
cd "c:\Users\Nagraj Y R\OneDrive\Desktop\mapmars8\mapmars\mobile"
npm install
npm run android:student
```
This will build AND install automatically if device is connected.

### Method 2: Build APK Only
```powershell
cd "c:\Users\Nagraj Y R\OneDrive\Desktop\mapmars8\mapmars\mobile\android"
.\gradlew.bat assembleStudentProductionDebug
```
APK will be at: `android\app\build\outputs\apk\studentProduction\debug\app-student-production-debug.apk`

### Method 3: Using the Script
```powershell
cd "c:\Users\Nagraj Y R\OneDrive\Desktop\mapmars8\mapmars\mobile"
.\build-and-install-student.ps1
```

## Step 3: Install APK

Once APK is built, install it:
```powershell
adb install -r android\app\build\outputs\apk\studentProduction\debug\app-student-production-debug.apk
```

Or if device is connected, React Native will install automatically.

## Step 4: Run Maestro Tests

### Install Maestro (if not installed)
```powershell
# Windows (PowerShell)
iwr https://get.maestro.mobile.dev -useb | iex
```

### Run Tests
```powershell
cd "c:\Users\Nagraj Y R\OneDrive\Desktop\mapmars8\mapmars\mobile\maestro"
maestro test student/00-student-complete-test.yaml --env phone="YOUR_PHONE" --env otp="123456" --env tenant_code="PPCU"
```

Or use the script:
```powershell
cd "c:\Users\Nagraj Y R\OneDrive\Desktop\mapmars8\mapmars\mobile"
.\run-maestro-tests.ps1 -Phone "YOUR_PHONE" -Otp "123456" -TenantCode "PPCU"
```

## Quick Commands Reference

```powershell
# Check device
adb devices

# Restart ADB
adb kill-server
adb start-server

# Install APK
adb install -r path\to\app.apk

# Uninstall app
adb uninstall com.mapmars.hmsstudent

# View logs
adb logcat | Select-String "ReactNativeJS"

# Run specific test
maestro test student/02-student-login-test.yaml
```

## Troubleshooting

### "No devices/emulators found"
- Check USB cable
- Enable USB debugging
- Try different USB port
- Run `adb kill-server` then `adb start-server`

### Build fails with Java error
- Install Java JDK 17 or 21
- Set JAVA_HOME environment variable
- Or use React Native CLI which handles this

### "Maestro not found"
Install Maestro:
```powershell
iwr https://get.maestro.mobile.dev -useb | iex
```

### App crashes on launch
- Check logs: `adb logcat | Select-String "ReactNativeJS"`
- Verify API endpoints are accessible
- Check tenant code is correct
