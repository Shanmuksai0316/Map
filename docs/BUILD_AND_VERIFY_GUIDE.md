# MAP HMS - Build and UI/UX Verification Guide

This guide provides step-by-step instructions for fetching latest code, building debug/production APKs, iOS debug apps, and verifying UI/UX consistency across platforms.

## Prerequisites

### Required Setup (One-Time)

#### 1. Fix Xcode Command Line Tools
```bash
# Set Xcode developer directory (requires admin password)
sudo xcode-select -s /Applications/Xcode.app/Contents/Developer

# Verify
xcodebuild -version
```

#### 2. Install Maestro (for UI Testing)
```bash
# Install Maestro
curl -Ls "https://get.maestro.mobile.dev" | bash

# Add to PATH (add to ~/.zshrc for persistence)
export PATH="$PATH":"$HOME/.maestro/bin"

# Verify
maestro --version
```

#### 3. Create Android Emulator
1. Open Android Studio
2. Go to **Tools → Device Manager**
3. Click **Create Device**
4. Select: **Pixel 6** or similar
5. Select system image: **API 34** (Android 14)
6. Complete setup

#### 4. Create iOS Simulator (if not available)
1. Open Xcode
2. Go to **Xcode → Settings → Platforms**
3. Install iOS 17+ simulator runtime
4. Simulators will auto-appear

---

## Quick Start (After Prerequisites)

### Option 1: Automated Script
```bash
cd /Users/paragmasteh/Downloads/MAP
./scripts/build-and-verify.sh
```

### Option 2: Step-by-Step Manual Process

---

## Step 1: Fetch Latest Code from GitHub

```bash
cd /Users/paragmasteh/Downloads/MAP

# Stash local changes if any
git stash

# Fetch and pull latest from main (production) branch
git fetch origin
git pull origin main

# Verify current commit
git log --oneline -3
```

---

## Step 2: Install Dependencies

```bash
cd mobile

# Install npm packages
npm install

# Install iOS CocoaPods
cd ios && pod install && cd ..
```

---

## Step 3: Build Android APKs

### Using Gradle (Command Line)

```bash
cd mobile/android

# Clean previous builds
./gradlew clean

# Build Student Debug APK
./gradlew assembleStudentProductionDebug

# Build Student Release APK (production)
./gradlew assembleStudentProductionRelease

# Build Staff Debug APK
./gradlew assembleStaffProductionDebug

# Build Staff Release APK (production)
./gradlew assembleStaffProductionRelease
```

### APK Locations
| Variant | Path |
|---------|------|
| Student Debug | `app/build/outputs/apk/studentProduction/debug/` |
| Student Release | `app/build/outputs/apk/studentProduction/release/` |
| Staff Debug | `app/build/outputs/apk/staffProduction/debug/` |
| Staff Release | `app/build/outputs/apk/staffProduction/release/` |

### Copy to Central Location
```bash
mkdir -p ../apk-builds
cp app/build/outputs/apk/studentProduction/debug/*.apk ../apk-builds/app-student-debug.apk
cp app/build/outputs/apk/studentProduction/release/*.apk ../apk-builds/app-student-release.apk
cp app/build/outputs/apk/staffProduction/debug/*.apk ../apk-builds/app-staff-debug.apk
cp app/build/outputs/apk/staffProduction/release/*.apk ../apk-builds/app-staff-release.apk
```

### Using Android Studio (Alternative)
1. Open Android Studio
2. File → Open → select `mobile/android`
3. Wait for Gradle sync
4. Build → Select Build Variant → choose variant
5. Build → Build Bundle(s) / APK(s) → Build APK(s)

---

## Step 4: Build iOS Debug Apps

### Build Student App
```bash
cd mobile/ios

xcodebuild \
  -workspace rn082template.xcworkspace \
  -scheme rn082template \
  -configuration Debug \
  -sdk iphonesimulator \
  -derivedDataPath build/DerivedData \
  build
```

### Build Staff App
```bash
xcodebuild \
  -workspace rn082template.xcworkspace \
  -scheme MAPHMSStaff \
  -configuration Debug \
  -sdk iphonesimulator \
  -derivedDataPath build/DerivedData \
  build
```

### Using Xcode (Alternative)
1. Open `mobile/ios/rn082template.xcworkspace` in Xcode
2. Select scheme: **rn082template** (Student) or **MAPHMSStaff** (Staff)
3. Select destination: Any iPhone/iPad Simulator
4. Press **Cmd+B** to build

---

## Step 5: Start Emulators/Simulators

### Android Emulator
```bash
# List available emulators
$ANDROID_HOME/emulator/emulator -list-avds

# Start emulator (replace with your AVD name)
$ANDROID_HOME/emulator/emulator -avd Pixel_6_API_34 &

# Wait for boot
adb wait-for-device
```

### iOS Simulator
```bash
# List simulators
xcrun simctl list devices available | grep iPhone

# Boot simulator (use ID from above)
xcrun simctl boot "iPhone 15 Pro"

# Open Simulator app
open -a Simulator
```

---

## Step 6: Install Apps

### Android
```bash
cd mobile

# Install Student app
adb install -r apk-builds/app-student-debug.apk

# Install Staff app
adb install -r apk-builds/app-staff-debug.apk
```

### iOS
```bash
# Get booted simulator ID
SIMULATOR_ID=$(xcrun simctl list devices booted | grep -oE "[A-F0-9-]{36}" | head -1)

# Install Student app
xcrun simctl install "$SIMULATOR_ID" ios/build/DerivedData/Build/Products/Debug-iphonesimulator/rn082template.app

# Install Staff app
xcrun simctl install "$SIMULATOR_ID" ios/build/DerivedData/Build/Products/Debug-iphonesimulator/MAPHMSStaff.app
```

---

## Step 7: UI/UX Verification

### Option A: Maestro Automated Tests (Recommended)

```bash
cd mobile/maestro

# Run Student app tests
maestro test student/00-student-complete-test.yaml

# Run Staff app tests (all roles)
maestro test staff/01-guard-smoke-test.yaml
maestro test staff/02-warden-smoke-test.yaml
maestro test staff/03-campus-manager-smoke-test.yaml
maestro test staff/04-rector-smoke-test.yaml

# Run all staff role tests
maestro test staff/run-all-role-tests.yaml
```

### Option B: Side-by-Side Manual Comparison

1. **Open both emulator and simulator** side by side
2. **Launch the same app** on both platforms
3. **Navigate through screens** and compare:

#### Screens to Check
| Screen | Check Points |
|--------|--------------|
| Login | Phone input, OTP layout, branding logo |
| Dashboard | Tile layout, colors, spacing, icons |
| Forms | Input fields, date pickers, validation |
| Lists | Card design, pull-to-refresh, loading |
| Modals | Confirmation dialogs, action sheets |
| Navigation | Tab bar, back button, headers |

#### Design System Compliance
- Primary Color: `#1E56D9`
- Border Radius: `rounded-xl` (16px)
- Shadows: Consistent elevation
- Touch Targets: Min 44px
- Typography: Consistent sizes/weights
- Spacing: 4px grid system

### Option C: Screenshot Comparison

```bash
# Android screenshot
adb exec-out screencap -p > android-screen.png

# iOS screenshot
xcrun simctl io booted screenshot ios-screen.png

# Compare using any image diff tool or manually
```

---

## Build Variants Reference

### Android Variants
| App | Environment | Build Type | Gradle Task |
|-----|-------------|------------|-------------|
| Student | Production | Debug | `assembleStudentProductionDebug` |
| Student | Production | Release | `assembleStudentProductionRelease` |
| Staff | Production | Debug | `assembleStaffProductionDebug` |
| Staff | Production | Release | `assembleStaffProductionRelease` |
| Student | Local | Debug | `assembleStudentLocalDebug` |
| Staff | Local | Debug | `assembleStaffLocalDebug` |

### iOS Schemes
| App | Scheme Name |
|-----|-------------|
| Student | `rn082template` |
| Staff | `MAPHMSStaff` |

---

## Troubleshooting

### Android Build Fails with CMake/NDK Error
```bash
# Ensure NDK is installed
ls $ANDROID_HOME/ndk

# If missing, install via Android Studio:
# Tools → SDK Manager → SDK Tools → NDK (Side by side)
```

### iOS Build Fails with Pod Issues
```bash
cd mobile/ios
rm -rf Pods Podfile.lock
pod install --repo-update
```

### Maestro Not Finding App
```bash
# Check app is installed
adb shell pm list packages | grep maphms

# For iOS
xcrun simctl listapps booted | grep MAP
```

### Xcode Not Found
```bash
sudo xcode-select -s /Applications/Xcode.app/Contents/Developer
```

---

## Expected Output

After successful completion, you should have:

```
mobile/
├── apk-builds/
│   ├── app-student-debug.apk
│   ├── app-student-release.apk
│   ├── app-staff-debug.apk
│   └── app-staff-release.apk
├── ios/build/
│   └── DerivedData/Build/Products/Debug-iphonesimulator/
│       ├── rn082template.app  (Student)
│       └── MAPHMSStaff.app    (Staff)
```

---

## Quick Commands Reference

```bash
# Full workflow
cd /Users/paragmasteh/Downloads/MAP
git pull origin main
cd mobile && npm install
cd ios && pod install && cd ..
cd android && ./gradlew assembleStudentProductionDebug assembleStaffProductionDebug && cd ..

# Quick test on Android
adb install -r apk-builds/app-student-debug.apk
cd maestro && maestro test student/00-student-complete-test.yaml
```
