# MAP HMS Mobile APK Builds

**Build Date:** February 7, 2026  
**React Native Version:** 0.82.0  
**Build Variant:** Production flavor with latest code from origin/main

## APK Files

### ✅ For Production Use (Currently Installed on Phone)
- **Staff App:** `app-staff-production-release.apk` (64 MB)
  - Package: `com.mapmars.hmsstaff`
  - Optimized, production-ready
  - JavaScript bundled inside
  
- **Student App:** `app-student-production-release.apk` (64 MB)
  - Package: `com.maphms.student`
  - Optimized, production-ready
  - JavaScript bundled inside

### 🔧 For Developer Testing (Debuggable with JS bundled)
- **Staff App:** `app-staff-debuggable.apk` (107 MB)
  - Package: `com.mapmars.hmsstaff`
  - Debuggable build with JavaScript bundled inside
  - Can be debugged with Chrome DevTools
  - Works without Metro bundler
  
- **Student App:** `app-student-debuggable.apk` (107 MB)
  - Package: `com.maphms.student`
  - Debuggable build with JavaScript bundled inside
  - Can be debugged with Chrome DevTools
  - Works without Metro bundler

## Installation Commands

### Install Release APKs (Production)
```bash
adb install -r app-staff-production-release.apk
adb install -r app-student-production-release.apk
```

### Install Debuggable APKs (For Developers)
```bash
adb install -r app-staff-debuggable.apk
adb install -r app-student-debuggable.apk
```

## Status
✅ **Both apps verified working on Android device**
- Staff app launches and runs successfully
- Student app launches and runs successfully
- No Metro bundler required (JavaScript is packaged inside)

## Issues Resolved
❌ **Previous Issue:** Debug APKs showed blank screens
✅ **Root Cause:** Debug APKs require Metro bundler connection (JavaScript loaded over network)
✅ **Solution:** Built release APKs with JavaScript bundled inside

## Technical Notes
- Release APKs (64MB) are smaller due to optimization and minification
- Debuggable APKs (107MB) include debug symbols and source maps
- All APKs use production API endpoints (https://api.mapservices.in)
- Dependencies updated: `react-native-worklets` and `react-native-reanimated` fixed

## For Developers
The debuggable APKs (`app-*-debuggable.apk`) are perfect for:
- Testing on physical devices
- QA/UAT testing
- Debugging with Chrome DevTools
- Sharing with testers who don't have development environment

They work standalone without requiring Metro bundler to be running.
