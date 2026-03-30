#!/bin/bash

# Staff App iOS Build Script
# This script builds the staff variant of the MAP HMS app for iOS

set -e

echo "🚀 Building MAP HMS Staff App for iOS..."
echo "========================================"

# Navigate to mobile directory
cd "$(dirname "$0")/.."

# Install dependencies if needed
if [ ! -d "node_modules" ]; then
    echo "📦 Installing dependencies..."
    npm install
fi

# Install CocoaPods dependencies
echo "📦 Installing CocoaPods dependencies..."
cd ios
pod install --repo-update

# Clean previous builds
echo "🧹 Cleaning previous builds..."
xcodebuild clean -workspace rn082template.xcworkspace -scheme "staff" -configuration Release

# Archive the app
echo "🏗️ Creating iOS Archive..."
xcodebuild archive \
    -workspace rn082template.xcworkspace \
    -scheme "staff" \
    -configuration Release \
    -archivePath build/staff.xcarchive \
    -allowProvisioningUpdates

echo ""
echo "✅ Archive complete!"
echo ""
echo "Archive location: ios/build/staff.xcarchive"
echo ""
echo "To export IPA for App Store:"
echo "  xcodebuild -exportArchive -archivePath ios/build/staff.xcarchive -exportOptionsPlist ExportOptions.plist -exportPath ios/build"
echo ""
echo "Or use Xcode to distribute the archive:"
echo "  1. Open Xcode"
echo "  2. Window > Organizer"
echo "  3. Select the archive and click 'Distribute App'"

