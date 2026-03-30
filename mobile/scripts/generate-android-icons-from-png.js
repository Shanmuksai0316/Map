/**
 * Generate Android launcher icons from a PNG source for both student and staff apps.
 * Run from mobile/: node scripts/generate-android-icons-from-png.js [source.png]
 * Default source: scripts/app-icon-source.png
 */

const fs = require('fs');
const path = require('path');

const SIZES = [
  { folder: 'mipmap-mdpi', size: 48 },
  { folder: 'mipmap-hdpi', size: 72 },
  { folder: 'mipmap-xhdpi', size: 96 },
  { folder: 'mipmap-xxhdpi', size: 144 },
  { folder: 'mipmap-xxxhdpi', size: 192 },
];

const SCRIPT_DIR = __dirname;
const MOBILE_ROOT = path.resolve(SCRIPT_DIR, '..');
const DEFAULT_SOURCE = path.join(SCRIPT_DIR, 'app-icon-source.png');
const RES_ROOT = path.join(MOBILE_ROOT, 'android', 'app', 'src', 'main', 'res');

async function main() {
  let sharp;
  try {
    sharp = require('sharp');
  } catch (e) {
    console.error('Missing sharp. Install with: npm install --save-dev sharp');
    process.exit(1);
  }

  const sourcePath = process.argv[2] ? path.resolve(process.cwd(), process.argv[2]) : DEFAULT_SOURCE;
  if (!fs.existsSync(sourcePath)) {
    console.error('Source image not found:', sourcePath);
    console.error('Usage: node scripts/generate-android-icons-from-png.js [path/to/icon.png]');
    process.exit(1);
  }

  const buffer = fs.readFileSync(sourcePath);

  for (const { folder, size } of SIZES) {
    const dir = path.join(RES_ROOT, folder);
    if (!fs.existsSync(dir)) {
      fs.mkdirSync(dir, { recursive: true });
    }

    const resized = await sharp(buffer)
      .resize(size, size)
      .png()
      .toBuffer();

    const launcherPath = path.join(dir, 'ic_launcher.png');
    const roundPath = path.join(dir, 'ic_launcher_round.png');
    fs.writeFileSync(launcherPath, resized);
    fs.writeFileSync(roundPath, resized);
    console.log(`Generated ${size}x${size} -> ${folder}/ic_launcher.png, ic_launcher_round.png`);
  }

  console.log('Android app icons generated for both student and staff apps.');
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
