#!/bin/bash

# Generate iOS app icons from source image
# Usage: ./scripts/generate-ios-icons.sh [source-image.png]

SOURCE_IMAGE="${1:-src/assets/map-logo.png}"
OUTPUT_DIR="ios/rn082template/Images.xcassets/AppIcon.appiconset"

if [ ! -f "$SOURCE_IMAGE" ]; then
  echo "Error: Source image not found: $SOURCE_IMAGE"
  exit 1
fi

# Check if ImageMagick or sips is available
if command -v sips &> /dev/null; then
  CONVERT_CMD="sips"
elif command -v convert &> /dev/null; then
  CONVERT_CMD="convert"
else
  echo "Error: Neither 'sips' (macOS) nor 'convert' (ImageMagick) found"
  echo "Please install ImageMagick: brew install imagemagick"
  exit 1
fi

echo "Generating iOS app icons from $SOURCE_IMAGE..."

# Create output directory if it doesn't exist
mkdir -p "$OUTPUT_DIR"

# iOS App Icon sizes (in points, need @2x and @3x)
# 20pt: 40x40, 60x60
# 29pt: 58x58, 87x87
# 40pt: 80x80, 120x120
# 60pt: 120x120, 180x180
# 1024pt: 1024x1024 (App Store)

if [ "$CONVERT_CMD" = "sips" ]; then
  # Using macOS sips
  sips -z 40 40 "$SOURCE_IMAGE" --out "$OUTPUT_DIR/icon-20@2x.png" 2>/dev/null
  sips -z 60 60 "$SOURCE_IMAGE" --out "$OUTPUT_DIR/icon-20@3x.png" 2>/dev/null
  sips -z 58 58 "$SOURCE_IMAGE" --out "$OUTPUT_DIR/icon-29@2x.png" 2>/dev/null
  sips -z 87 87 "$SOURCE_IMAGE" --out "$OUTPUT_DIR/icon-29@3x.png" 2>/dev/null
  sips -z 80 80 "$SOURCE_IMAGE" --out "$OUTPUT_DIR/icon-40@2x.png" 2>/dev/null
  sips -z 120 120 "$SOURCE_IMAGE" --out "$OUTPUT_DIR/icon-40@3x.png" 2>/dev/null
  sips -z 120 120 "$SOURCE_IMAGE" --out "$OUTPUT_DIR/icon-60@2x.png" 2>/dev/null
  sips -z 180 180 "$SOURCE_IMAGE" --out "$OUTPUT_DIR/icon-60@3x.png" 2>/dev/null
  sips -z 1024 1024 "$SOURCE_IMAGE" --out "$OUTPUT_DIR/icon-1024@1x.png" 2>/dev/null
else
  # Using ImageMagick convert
  convert "$SOURCE_IMAGE" -resize 40x40 "$OUTPUT_DIR/icon-20@2x.png"
  convert "$SOURCE_IMAGE" -resize 60x60 "$OUTPUT_DIR/icon-20@3x.png"
  convert "$SOURCE_IMAGE" -resize 58x58 "$OUTPUT_DIR/icon-29@2x.png"
  convert "$SOURCE_IMAGE" -resize 87x87 "$OUTPUT_DIR/icon-29@3x.png"
  convert "$SOURCE_IMAGE" -resize 80x80 "$OUTPUT_DIR/icon-40@2x.png"
  convert "$SOURCE_IMAGE" -resize 120x120 "$OUTPUT_DIR/icon-40@3x.png"
  convert "$SOURCE_IMAGE" -resize 120x120 "$OUTPUT_DIR/icon-60@2x.png"
  convert "$SOURCE_IMAGE" -resize 180x180 "$OUTPUT_DIR/icon-60@3x.png"
  convert "$SOURCE_IMAGE" -resize 1024x1024 "$OUTPUT_DIR/icon-1024@1x.png"
fi

# Update Contents.json
cat > "$OUTPUT_DIR/Contents.json" << 'EOF'
{
  "images" : [
    {
      "filename" : "icon-20@2x.png",
      "idiom" : "iphone",
      "scale" : "2x",
      "size" : "20x20"
    },
    {
      "filename" : "icon-20@3x.png",
      "idiom" : "iphone",
      "scale" : "3x",
      "size" : "20x20"
    },
    {
      "filename" : "icon-29@2x.png",
      "idiom" : "iphone",
      "scale" : "2x",
      "size" : "29x29"
    },
    {
      "filename" : "icon-29@3x.png",
      "idiom" : "iphone",
      "scale" : "3x",
      "size" : "29x29"
    },
    {
      "filename" : "icon-40@2x.png",
      "idiom" : "iphone",
      "scale" : "2x",
      "size" : "40x40"
    },
    {
      "filename" : "icon-40@3x.png",
      "idiom" : "iphone",
      "scale" : "3x",
      "size" : "40x40"
    },
    {
      "filename" : "icon-60@2x.png",
      "idiom" : "iphone",
      "scale" : "2x",
      "size" : "60x60"
    },
    {
      "filename" : "icon-60@3x.png",
      "idiom" : "iphone",
      "scale" : "3x",
      "size" : "60x60"
    },
    {
      "filename" : "icon-1024@1x.png",
      "idiom" : "ios-marketing",
      "scale" : "1x",
      "size" : "1024x1024"
    }
  ],
  "info" : {
    "author" : "xcode",
    "version" : 1
  }
}
EOF

echo "✅ iOS app icons generated successfully!"
echo "Icons saved to: $OUTPUT_DIR"

