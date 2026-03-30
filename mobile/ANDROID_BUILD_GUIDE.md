# Android Build Configuration Guide
## MAP HMS Mobile Application

This guide will help you set up and build the Android APK for both Student and Staff applications on your local machine.

---

## 📋 Table of Contents

1. [Prerequisites](#prerequisites)
2. [Environment Setup](#environment-setup)
3. [Project Configuration](#project-configuration)
4. [Build Variants](#build-variants)
5. [Building the APK](#building-the-apk)
6. [Troubleshooting](#troubleshooting)
7. [Important Notes](#important-notes)

---

## 🔧 Prerequisites

### Required Software

1. **Node.js** (v20 or higher)
   ```bash
   node --version  # Should be >= 20
   ```

2. **Java Development Kit (JDK) 17**
   - **Important:** The project requires JDK 17 (Temurin/OpenJDK recommended)
   - Download from: https://adoptium.net/temurin/releases/?version=17
   - Verify installation:
     ```bash
     java -version  # Should show version 17.x.x
     ```

3. **Android Studio** (Latest stable version)
   - Download from: https://developer.android.com/studio
   - Install Android SDK Platform 36
   - Install Android SDK Build-Tools 36.0.0
   - Install NDK version 26.1.10909125
   - Install Kotlin plugin (version 2.1.20)

4. **Android SDK Command Line Tools**
   - Ensure `ANDROID_HOME` environment variable is set
   - Add to your shell profile (`~/.zshrc` or `~/.bashrc`):
     ```bash
     export ANDROID_HOME=$HOME/Library/Android/sdk  # macOS
     # OR
     export ANDROID_HOME=$HOME/Android/Sdk  # Linux
     
     export PATH=$PATH:$ANDROID_HOME/emulator
     export PATH=$PATH:$ANDROID_HOME/platform-tools
     export PATH=$PATH:$ANDROID_HOME/tools
     export PATH=$PATH:$ANDROID_HOME/tools/bin
     ```

5. **Gradle** (v9.0.0)
   - Included via Gradle Wrapper (no manual installation needed)

---

## 🛠️ Environment Setup

### 1. Clone and Navigate to Project

```bash
cd /path/to/MAP
cd mobile
```

### 2. Install Node Dependencies

```bash
npm install
```

**Note:** This may take several minutes on first run.

### 3. Configure Java Home

The project expects JDK 17 at a specific path. You have two options:

#### Option A: Update `gradle.properties` (Recommended for different systems)

Edit `mobile/android/gradle.properties` and update line 14:

```properties
# For macOS (default in repo)
org.gradle.java.home=/Library/Java/JavaVirtualMachines/temurin-17.jdk/Contents/Home

# For Linux, update to your JDK 17 path:
# org.gradle.java.home=/usr/lib/jvm/java-17-openjdk-amd64

# For Windows:
# org.gradle.java.home=C:\\Program Files\\Java\\jdk-17
```

**OR** remove this line entirely to let Gradle use `JAVA_HOME` environment variable:

```bash
# Set JAVA_HOME in your shell profile
export JAVA_HOME=$(/usr/libexec/java_home -v 17)  # macOS
# OR
export JAVA_HOME=/usr/lib/jvm/java-17-openjdk-amd64  # Linux
```

### 4. Verify Android SDK Components

Ensure you have installed via Android Studio SDK Manager:
- ✅ Android SDK Platform 36
- ✅ Android SDK Build-Tools 36.0.0
- ✅ NDK 26.1.10909125
- ✅ Android SDK Command-line Tools

---

## 📦 Project Configuration

### Build Configuration Files

The Android build uses the following key files:

1. **`android/build.gradle`** - Root build configuration
2. **`android/app/build.gradle`** - App-level build configuration with flavors
3. **`android/gradle.properties`** - Gradle properties and JVM settings
4. **`android/settings.gradle`** - Project settings
5. **`android/app/src/student/google-services.json`** - Firebase config for Student app
6. **`android/app/src/staff/google-services.json`** - Firebase config for Staff app
7. **`android/app/debug.keystore`** - Debug signing keystore (already included)

### Build Variants

The app uses **two flavor dimensions**:

#### App Type Dimension (`app`)
- **`student`** - Student application (Package: `com.maphms.student`)
- **`staff`** - Staff application (Package: `com.mapmars.hmsstaff`)

#### Environment Dimension (`environment`)
- **`local`** - Development environment (API: `http://api.localhost:8000`)
- **`staging`** - Staging environment (API: `https://api.staging.mapservices.in`)
- **`production`** - Production environment (API: `https://api.mapservices.in`)

### Combined Variants

Available build variants (for testing, use Debug builds):

| Variant | App Type | Environment | Package ID | Use Case |
|---------|----------|-------------|------------|----------|
| `studentLocalDebug` | Student | Local | `com.maphms.student` | Local development |
| `studentStagingDebug` | Student | Staging | `com.maphms.student.staging` | QA testing |
| `studentProductionDebug` | Student | Production | `com.maphms.student` | Production testing |
| `staffLocalDebug` | Staff | Local | `com.mapmars.hmsstaff` | Local development |
| `staffStagingDebug` | Staff | Staging | `com.mapmars.hmsstaff.staging` | QA testing |
| `staffProductionDebug` | Staff | Production | `com.mapmars.hmsstaff` | Production testing |

---

## 🏗️ Building the APK

### Method 1: Using Gradle Commands (Recommended)

#### For Student App (Debug - Testing)

```bash
cd mobile/android

# Student - Local Debug
./gradlew assembleStudentLocalDebug

# Student - Staging Debug
./gradlew assembleStudentStagingDebug

# Student - Production Debug
./gradlew assembleStudentProductionDebug
```

#### For Staff App (Debug - Testing)

```bash
cd mobile/android

# Staff - Local Debug
./gradlew assembleStaffLocalDebug

# Staff - Staging Debug
./gradlew assembleStaffStagingDebug

# Staff - Production Debug
./gradlew assembleStaffProductionDebug
```

#### APK Output Location

After building, find your APK at:
```
mobile/android/app/build/outputs/apk/{appType}/{environment}/debug/app-{appType}-{environment}-debug.apk
```

**Examples:**
- Student Local Debug: `mobile/android/app/build/outputs/apk/student/local/debug/app-student-local-debug.apk`
- Staff Staging Debug: `mobile/android/app/build/outputs/apk/staff/staging/debug/app-staff-staging-debug.apk`

### Method 2: Using Build Scripts

```bash
cd mobile

# Build Staff app (uses production release by default)
./scripts/build-staff-android.sh
```

### Method 3: Using Android Studio

1. Open Android Studio
2. Open project: `File → Open → Select mobile/android directory`
3. Wait for Gradle sync to complete
4. Go to `Build → Select Build Variant`
5. Choose your desired variant (e.g., `studentLocalDebug`)
6. Click `Build → Make Project` or `Build → Build Bundle(s) / APK(s) → Build APK(s)`

### Method 4: Using React Native CLI

```bash
cd mobile

# Start Metro bundler (in a separate terminal)
npm start

# Run on connected device/emulator
npm run android:student  # For student app
# OR
npm run android:staff    # For staff app
```

---

## 🔍 Troubleshooting

### Issue 1: "JAVA_HOME not set" or "Java version mismatch"

**Solution:**
```bash
# Check Java version
java -version

# Set JAVA_HOME (macOS)
export JAVA_HOME=$(/usr/libexec/java_home -v 17)

# Set JAVA_HOME (Linux)
export JAVA_HOME=/usr/lib/jvm/java-17-openjdk-amd64

# Verify
echo $JAVA_HOME
```

**OR** update `android/gradle.properties` line 14 with your JDK 17 path.

### Issue 2: "SDK location not found"

**Solution:**
```bash
# Set ANDROID_HOME
export ANDROID_HOME=$HOME/Library/Android/sdk  # macOS
# OR
export ANDROID_HOME=$HOME/Android/Sdk  # Linux

# Add to gradle.properties (create if missing)
echo "sdk.dir=$ANDROID_HOME" >> android/local.properties
```

### Issue 3: "NDK version mismatch" or "NDK not found"

**Solution:**
1. Open Android Studio
2. Go to `Tools → SDK Manager → SDK Tools`
3. Check "Show Package Details"
4. Install NDK version **26.1.10909125** (exact version required)
5. Or install via command line:
   ```bash
   $ANDROID_HOME/cmdline-tools/latest/bin/sdkmanager "ndk;26.1.10909125"
   ```

### Issue 4: "Build Tools version not found"

**Solution:**
1. Install Android SDK Build-Tools 36.0.0 via Android Studio SDK Manager
2. Or via command line:
   ```bash
   $ANDROID_HOME/cmdline-tools/latest/bin/sdkmanager "build-tools;36.0.0"
   ```

### Issue 5: "Gradle daemon failed" or "Out of memory"

**Solution:**
The project already has increased memory in `gradle.properties`:
```properties
org.gradle.jvmargs=-Xmx2048m -XX:MaxMetaspaceSize=512m
```

If you still face issues, increase further:
```properties
org.gradle.jvmargs=-Xmx4096m -XX:MaxMetaspaceSize=1024m
```

### Issue 6: "google-services.json not found"

**Solution:**
The Firebase configuration files are already in the repo:
- `android/app/src/student/google-services.json` ✅
- `android/app/src/staff/google-services.json` ✅

If missing, ensure these files exist. They are required for Firebase (push notifications).

### Issue 7: "debug.keystore not found"

**Solution:**
The debug keystore should be at `android/app/debug.keystore`. If missing, generate it:

```bash
cd mobile/android/app
keytool -genkeypair -v -storetype PKCS12 -keystore debug.keystore \
  -alias androiddebugkey -keyalg RSA -keysize 2048 -validity 10000 \
  -storepass android -keypass android
```

### Issue 8: "Metro bundler connection failed"

**Solution:**
```bash
cd mobile
npm start --reset-cache
```

Then in another terminal, run the build again.

### Issue 9: "Kotlin version mismatch"

**Solution:**
The project uses Kotlin 2.1.20. Ensure Android Studio has the matching Kotlin plugin installed:
1. `Android Studio → Preferences → Plugins`
2. Search for "Kotlin"
3. Install/Update to version 2.1.20

### Issue 10: Build fails with "CMake" errors

**Solution:**
The project uses native code (CMake). Ensure:
1. CMake is installed (usually comes with Android Studio)
2. NDK 26.1.10909125 is installed
3. Clean and rebuild:
   ```bash
   cd mobile/android
   ./gradlew clean
   ./gradlew assembleStudentLocalDebug
   ```

### Issue 11: "New Architecture" build errors

**Solution:**
The project has `newArchEnabled=true` in `gradle.properties`. If you encounter issues:
1. Try disabling temporarily (not recommended for production):
   ```properties
   newArchEnabled=false
   ```
2. Or ensure all native dependencies are compatible with new architecture

### Issue 12: C++ Build Errors (NDK 25.2 Compatibility)

**Solution:**
If you encounter C++20 concept errors with NDK 25.2:
1. The project includes compatibility headers in `android/app/src/main/cpp/`
2. Run the concept patching script before building:
   ```bash
   cd mobile/android
   .\patch_prefab_concepts.ps1  # Windows
   # OR
   ./patch_prefab_concepts.sh   # macOS/Linux
   ```
3. Then rebuild:
   ```bash
   ./gradlew clean
   ./gradlew assembleStudentProductionDebug
   ```

---

## ⚠️ Important Notes

### 1. Java Version
- **CRITICAL:** Must use JDK 17. JDK 8, 11, or 21 will cause build failures.
- The project explicitly requires JDK 17 in `gradle.properties`.

### 2. Android SDK Versions
- **Compile SDK:** 36
- **Target SDK:** 36
- **Min SDK:** 26 (Android 8.0+)
- **Build Tools:** 36.0.0
- **NDK:** 26.1.10909125 (or 25.2.9519653 with compatibility patches)

### 3. Build Architecture
- Default architecture: `arm64-v8a` (64-bit ARM)
- Can be changed in `gradle.properties`:
  ```properties
  reactNativeArchitectures=arm64-v8a,armeabi-v7a,x86,x86_64
  ```

### 4. Signing
- **Debug builds:** Use `debug.keystore` (password: `android`)
- **Release builds:** Currently also use debug keystore (for testing only)
- **For production:** Generate a release keystore and configure in `build.gradle`

### 5. Firebase Configuration
- Firebase is required for push notifications
- `google-services.json` files are flavor-specific:
  - Student app uses: `android/app/src/student/google-services.json`
  - Staff app uses: `android/app/src/staff/google-services.json`
- Do not modify these files unless updating Firebase project settings

### 6. Environment Detection
- The app automatically detects environment from build flavor
- No manual environment variable configuration needed
- API endpoints are configured in `src/shared/config/app.config.ts`

### 7. React Native Version
- **React Native:** 0.82.0
- **Node.js:** >= 20
- **Hermes:** Enabled (JavaScript engine)

### 8. First Build
- First build will take significantly longer (10-15 minutes)
- Gradle will download dependencies
- Subsequent builds are faster (2-5 minutes)

### 9. Clean Build
If you encounter persistent issues:
```bash
cd mobile/android
./gradlew clean
cd ..
rm -rf node_modules
npm install
cd android
./gradlew assembleStudentLocalDebug
```

### 10. Network Requirements
- Ensure stable internet connection for first build
- Gradle will download dependencies from:
  - Google Maven Repository
  - Maven Central
  - JitPack (if used)

### 11. C++ Build Configuration
- The project uses C++17 with compatibility shims for React Native 0.82
- Compatibility headers are in `android/app/src/main/cpp/`
- See `CMakeLists.txt` for C++ build configuration

---

## 📱 Installing APK on Device

### Via ADB (Android Debug Bridge)

```bash
# Connect device via USB and enable USB debugging
adb devices  # Verify device is connected

# Install APK
adb install mobile/android/app/build/outputs/apk/student/local/debug/app-student-local-debug.apk

# Or for staff app
adb install mobile/android/app/build/outputs/apk/staff/local/debug/app-staff-local-debug.apk
```

### Via File Transfer

1. Copy APK to device (via USB, email, or cloud storage)
2. On device, enable "Install from Unknown Sources" in Settings
3. Open APK file and install

---

## 🎯 Quick Start Checklist

Use this checklist to ensure everything is set up:

- [ ] Node.js v20+ installed
- [ ] JDK 17 installed and `JAVA_HOME` set
- [ ] Android Studio installed
- [ ] Android SDK Platform 36 installed
- [ ] Android SDK Build-Tools 36.0.0 installed
- [ ] NDK 26.1.10909125 installed
- [ ] `ANDROID_HOME` environment variable set
- [ ] `gradle.properties` Java path updated (if needed)
- [ ] `npm install` completed successfully
- [ ] `google-services.json` files present
- [ ] `debug.keystore` file present
- [ ] First build completed successfully

---

## 📞 Getting Help

If you encounter issues not covered in this guide:

1. Check the error message carefully - it usually indicates what's missing
2. Verify all prerequisites are installed correctly
3. Try a clean build (see Troubleshooting section)
4. Check React Native and Android Studio documentation
5. Review the project's main README.md in the mobile directory

---

## 📝 Build Command Reference

### Common Gradle Tasks

```bash
cd mobile/android

# Clean build
./gradlew clean

# Build specific variant
./gradlew assembleStudentLocalDebug
./gradlew assembleStaffStagingDebug

# Build all variants
./gradlew assembleDebug

# List all available tasks
./gradlew tasks

# Check dependencies
./gradlew dependencies

# Build with verbose output (for debugging)
./gradlew assembleStudentLocalDebug --info
```

### React Native Commands

```bash
cd mobile

# Start Metro bundler
npm start

# Run on Android (student)
npm run android:student

# Run on Android (staff)
npm run android:staff

# Run tests
npm test
```

---

**Last Updated:** Based on codebase review  
**Project Version:** 1.0.0  
**React Native:** 0.82.0
