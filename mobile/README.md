# 📱 MAP HMS Mobile Apps

React Native mobile applications for MAP HMS - Hostel Management System.

---

## 📋 Overview

| App | Package ID | Description |
|-----|------------|-------------|
| **Student App** | `com.maphms.student` | Self-service for students |
| **Staff App** | `com.mapmars.hmsstaff` | Operations for staff |

---

## 🏗️ Build Variants

The app uses **two flavor dimensions**:

### App Type (`app`)
- `student` - Student-facing features
- `staff` - Staff/Admin features

### Environment (`environment`)
- `local` - Development (localhost:8000)
- `staging` - Testing (staging.mapservices.in)
- `production` - Live (mapservices.in)

### Combined Variants

| Variant | API Endpoint | Use Case |
|---------|--------------|----------|
| `studentLocalDebug` | localhost:8000 | Dev testing |
| `studentStagingRelease` | staging.mapservices.in | QA testing |
| `studentProductionRelease` | mapservices.in | App Store |
| `staffLocalDebug` | localhost:8000 | Dev testing |
| `staffStagingRelease` | staging.mapservices.in | QA testing |
| `staffProductionRelease` | mapservices.in | Play Store |

---

## 🚀 Quick Start

### Prerequisites

```bash
# Node.js 18+
node --version

# React Native CLI
npm install -g react-native-cli

# Android Studio (for Android builds)
# Xcode (for iOS builds, Mac only)
```

### Install Dependencies

```bash
cd mobile
npm install

# iOS only
cd ios && pod install && cd ..
```

### Run Local Development

```bash
# Start Metro bundler
npm start

# Android (Student App - Local)
npm run android:student:local

# Android (Staff App - Local)  
npm run android:staff:local

# iOS (requires Mac)
npm run ios:student
npm run ios:staff
```

---

## 📦 Building for Release

### Android APK/AAB

```bash
# Student App - Production
./build-student-android-production.sh

# Staff App - Production
./build-staff-android-production.sh

# Or use Gradle directly:
cd android

# APK for testing
./gradlew assembleStudentProductionRelease
./gradlew assembleStaffProductionRelease

# AAB for Play Store
./gradlew bundleStudentProductionRelease
./gradlew bundleStaffProductionRelease
```

**Output locations:**
- APK: `android/app/build/outputs/apk/{flavor}/release/`
- AAB: `android/app/build/outputs/bundle/{flavor}Release/`

### iOS (TestFlight)

```bash
# Build for TestFlight
./build-ios-testflight.sh

# Or use Fastlane
cd mobile
fastlane ios deploy_staff_testflight
```

---

## 🔧 Environment Configuration

The app detects environment from build config:

```typescript
// src/shared/config/app.config.ts
const ENV_CONFIG = {
  local: {
    API_BASE_DOMAIN: 'api.localhost',
    API_PROTOCOL: 'http',
  },
  staging: {
    API_BASE_DOMAIN: 'api.staging.mapservices.in',
    API_PROTOCOL: 'https',
  },
  production: {
    API_BASE_DOMAIN: 'api.mapservices.in',
    API_PROTOCOL: 'https',
  },
};
```

---

## 🔐 Signing

### Android

**Debug:** Uses `android/app/debug.keystore` (default)

**Release:** Create a release keystore:
```bash
keytool -genkeypair -v -storetype PKCS12 -keystore release.keystore \
  -alias maphms -keyalg RSA -keysize 2048 -validity 10000
```

Add to `android/gradle.properties`:
```properties
MAPHMS_RELEASE_STORE_FILE=release.keystore
MAPHMS_RELEASE_KEY_ALIAS=maphms
MAPHMS_RELEASE_STORE_PASSWORD=your_password
MAPHMS_RELEASE_KEY_PASSWORD=your_password
```

### iOS

Configure in Xcode:
1. Open `ios/rn082template.xcworkspace`
2. Select target → Signing & Capabilities
3. Choose your Apple Developer Team
4. Enable "Automatically manage signing"

---

## 🧪 Testing

### Unit Tests
```bash
npm test
```

### E2E Tests (Maestro)
```bash
# Run all tests
./run-maestro-all.sh

# Run specific test
maestro test maestro/student/test-student-prod-login.yaml
```

---

## 📁 Project Structure

```
mobile/
├── android/           # Android native code
├── ios/              # iOS native code
├── src/
│   ├── app/          # App entry points
│   ├── features/     # Feature modules
│   ├── components/   # Shared components
│   ├── services/     # API services
│   ├── stores/       # Zustand state stores
│   └── shared/       # Shared utilities
├── maestro/          # E2E test flows
├── fastlane/         # Fastlane config
└── scripts/          # Build scripts
```

---

## 📱 App Store Submission

### Google Play Store

1. Build AAB: `./gradlew bundleStudentProductionRelease`
2. Go to [Play Console](https://play.google.com/console)
3. Create app → Upload AAB
4. Fill store listing, content rating, pricing
5. Submit for review

### Apple App Store

1. Build with Fastlane: `fastlane ios deploy_staff_testflight`
2. Or use Xcode: Archive → Upload to App Store
3. Go to [App Store Connect](https://appstoreconnect.apple.com)
4. Submit for review

---

## 🔗 Deep Links

| Link | Description |
|------|-------------|
| `maphms://login` | Open login screen |
| `maphms://outpass/{id}` | Open outpass details |
| `maphms://ticket/{id}` | Open ticket details |

---

## 📞 Troubleshooting

### Metro bundler issues
```bash
npm start --reset-cache
```

### Android build issues
```bash
cd android && ./gradlew clean && cd ..
```

### iOS build issues
```bash
cd ios && pod deintegrate && pod install && cd ..
```

---

**Version:** 1.0.0  
**React Native:** 0.72.10
