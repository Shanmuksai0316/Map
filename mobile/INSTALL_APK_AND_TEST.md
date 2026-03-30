# Install APK and Run Tests - Quick Guide

## Your Device is Connected! ✅
Device ID: `8872022c`

## Step 1: Build APK

### Option A: Android Studio (Recommended - No Java Issues)
1. Open **Android Studio**
2. **File → Open** → `mapmars/mobile/android`
3. Wait for Gradle sync
4. **Build → Build Bundle(s) / APK(s) → Build APK(s)**
5. Select variant: **studentProductionDebug**
6. APK location: `android/app/build/outputs/apk/studentProduction/debug/app-student-production-debug.apk`

### Option B: Install JDK 17+ First
1. Download: https://adoptium.net/temurin/releases/?version=17
2. Install JDK 17
3. Set JAVA_HOME:
   ```powershell
   $env:JAVA_HOME = "C:\Program Files\Eclipse Adoptium\jdk-17.x.x-hotspot"
   ```
4. Build:
   ```powershell
   cd mapmars/mobile/android
   .\gradlew.bat assembleStudentProductionDebug
   ```

## Step 2: Install APK

Once APK is built:
```powershell
cd mapmars/mobile
adb install -r android\app\build\outputs\apk\studentProduction\debug\app-student-production-debug.apk
```

Or if APK is in a different location:
```powershell
adb install -r "path\to\app-student-production-debug.apk"
```

## Step 3: Run Maestro Tests

```powershell
cd mapmars/mobile/maestro
maestro test student/00-student-complete-test.yaml --env phone="YOUR_PHONE" --env otp="123456" --env tenant_code="PPCU"
```

Or use the script:
```powershell
cd mapmars/mobile
.\run-maestro-tests.ps1 -Phone "YOUR_PHONE" -Otp "123456" -TenantCode "PPCU"
```

## Quick Commands

```powershell
# Check device
adb devices

# Install APK
adb install -r path\to\app.apk

# Uninstall if needed
adb uninstall com.mapmars.hmsstudent

# View logs
adb logcat | Select-String "ReactNativeJS"

# Run specific test
maestro test student/02-student-login-test.yaml
```

## Test Credentials

Update these in test commands:
- **Phone**: Your test student phone number
- **OTP**: `123456` (bypass code)
- **Tenant Code**: `PPCU` (or your tenant)
