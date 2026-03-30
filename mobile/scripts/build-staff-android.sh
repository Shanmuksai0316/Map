#!/bin/bash

# Staff App Android Build Script
# This script builds the staff variant of the MAP HMS app for Android

set -e

echo "🚀 Building MAP HMS Staff App for Android..."
echo "============================================="

# Navigate to mobile directory
cd "$(dirname "$0")/.."

# Install dependencies if needed
if [ ! -d "node_modules" ]; then
    echo "📦 Installing dependencies..."
    npm install
fi

# Clean previous builds
echo "🧹 Cleaning previous builds..."
cd android
./gradlew clean

# Build release APK for staff variant
echo "🏗️ Building Staff Release APK..."
./gradlew assembleStaffRelease

# Build release AAB for Play Store
echo "📦 Building Staff Release Bundle (AAB)..."
./gradlew bundleStaffRelease

echo ""
echo "✅ Build complete!"
echo ""
echo "APK location: android/app/build/outputs/apk/staff/release/app-staff-release.apk"
echo "AAB location: android/app/build/outputs/bundle/staffRelease/app-staff-release.aab"
echo ""
echo "To install APK on connected device:"
echo "  adb install android/app/build/outputs/apk/staff/release/app-staff-release.apk"

