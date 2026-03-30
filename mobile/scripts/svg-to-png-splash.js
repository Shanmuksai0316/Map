/**
 * Convert student-splash.svg to PNG for React Native Image
 * Run from mobile/: node scripts/svg-to-png-splash.js
 */
const path = require('path');
const fs = require('fs');

const SCRIPT_DIR = __dirname;
const MOBILE_ROOT = path.resolve(SCRIPT_DIR, '..');
const SVG_PATH = path.join(MOBILE_ROOT, 'src', 'shared', 'assets', 'student-splash.svg');
const PNG_PATH = path.join(MOBILE_ROOT, 'src', 'shared', 'assets', 'student-splash.png');

// Output at 3x logical size for sharp display on high-DPI devices (e.g. 390*3 ≈ 1170 width)
const OUTPUT_WIDTH = 1170;

async function main() {
  const sharp = require('sharp');
  const svgBuffer = fs.readFileSync(SVG_PATH);
  await sharp(svgBuffer)
    .resize(OUTPUT_WIDTH)
    .png()
    .toFile(PNG_PATH);
  console.log('Created:', PNG_PATH, `(${OUTPUT_WIDTH}px wide)`);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
