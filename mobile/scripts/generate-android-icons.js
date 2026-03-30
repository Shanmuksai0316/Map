/**
 * Generate Android launcher icons from SVG for both student and staff apps.
 * Uses @resvg/resvg-js to render SVG at required mipmap sizes.
 * Run from mobile/: node scripts/generate-android-icons.js
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
const SVG_PATH = path.join(SCRIPT_DIR, 'app-icon-source.svg');
const RES_ROOT = path.join(MOBILE_ROOT, 'android', 'app', 'src', 'main', 'res');

async function main() {
  let Resvg;
  try {
    Resvg = require('@resvg/resvg-js').Resvg;
  } catch (e) {
    console.error('Missing @resvg/resvg-js. Install with: npm install --save-dev @resvg/resvg-js');
    process.exit(1);
  }

  if (!fs.existsSync(SVG_PATH)) {
    console.error('Source SVG not found:', SVG_PATH);
    console.error('Copy your app icon SVG to scripts/app-icon-source.svg');
    process.exit(1);
  }

  const svg = fs.readFileSync(SVG_PATH);

  for (const { folder, size } of SIZES) {
    const resvg = new Resvg(svg, {
      fitTo: { mode: 'width', value: size },
    });
    const output = resvg.render();
    const png = output.asPng();

    const dir = path.join(RES_ROOT, folder);
    if (!fs.existsSync(dir)) {
      fs.mkdirSync(dir, { recursive: true });
    }

    const launcherPath = path.join(dir, 'ic_launcher.png');
    const roundPath = path.join(dir, 'ic_launcher_round.png');
    fs.writeFileSync(launcherPath, png);
    fs.writeFileSync(roundPath, png);
    console.log(`Generated ${size}x${size} -> ${folder}/ic_launcher.png, ic_launcher_round.png`);
  }

  console.log('Android app icons generated for student and staff apps.');
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
