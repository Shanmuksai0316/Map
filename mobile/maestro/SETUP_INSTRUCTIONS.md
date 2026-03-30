# Maestro Test Setup Instructions

## Current Status
- ❌ Maestro CLI not installed
- ❌ Device not detected by ADB
- ⏳ Apps installation status unknown

## Step 1: Install Maestro

### Option A: Download Binary (Recommended)
1. Visit: https://github.com/mobile-dev-inc/maestro/releases/latest
2. Download `maestro-win.exe`
3. Create folder: `C:\tools\maestro\` (or any location you prefer)
4. Move `maestro-win.exe` to that folder and rename it to `maestro.exe`
5. Add the folder to your PATH:
   - Open System Properties > Environment Variables
   - Edit "Path" under User variables
   - Add: `C:\tools\maestro` (or your chosen path)
   - Click OK and restart your terminal

### Option B: Using Scoop (if installed)
```powershell
scoop bucket add extras
scoop install maestro
```

### Option C: Manual Installation Script
Run the PowerShell script in this directory:
```powershell
.\install-and-run-tests.ps1
```

## Step 2: Connect Your Phone

### Enable USB Debugging
1. On your Android phone, go to **Settings**
2. Navigate to **About Phone**
3. Tap **Build Number** 7 times to enable Developer Options
4. Go back to **Settings** > **Developer Options**
5. Enable **USB Debugging**
6. Enable **Install via USB** (if available)

### Connect and Authorize
1. Connect your phone to your computer via USB
2. On your phone, you should see a popup: **"Allow USB debugging?"**
3. Check **"Always allow from this computer"** and tap **OK**
4. Verify connection:
   ```powershell
   adb devices
   ```
   You should see your device listed (e.g., `ABC123XYZ    device`)

### Troubleshooting Device Connection
If device still not detected:
1. Try a different USB cable
2. Try a different USB port
3. Install/update USB drivers for your phone
4. On phone: Settings > Developer Options > Revoke USB debugging authorizations (then reconnect)
5. Restart ADB:
   ```powershell
   adb kill-server
   adb start-server
   adb devices
   ```

## Step 3: Install Apps

### Install Staff App
```powershell
adb install <path-to-staff-app.apk>
# Example: adb install "C:\path\to\staff-app.apk"
```

### Install Student App
```powershell
adb install <path-to-student-app.apk>
# Example: adb install "C:\path\to\student-app.apk"
```

### Verify Apps Installed
```powershell
adb shell pm list packages | Select-String "mapmars"
```
Should show:
- `com.mapmars.hmsstaff`
- `com.mapmars.hmsstudent`

## Step 4: Run Tests

### Verify Maestro Installation
```powershell
maestro --version
```

### Run All Staff Tests
```powershell
cd mapmars\mobile\maestro
maestro test staff\run-all-roles.yaml
```

### Run Individual Staff Role Test
```powershell
maestro test staff\01-guard-smoke-test.yaml
maestro test staff\02-warden-smoke-test.yaml
# etc.
```

### Run Student App Tests
```powershell
maestro test student\01-student-comprehensive-test.yaml
```

### Run All Tests (Staff + Student)
Use the helper script:
```powershell
.\install-and-run-tests.ps1
```

## Test Credentials

### Staff App Test Credentials
| Role | Phone Number | OTP |
|------|--------------|-----|
| Guard | 8888888881 | 123456 |
| Warden | 8888888882 | 123456 |
| Campus Manager | 8888888883 | 123456 |
| Rector | 8888888884 | 123456 |
| HK Supervisor | 8888888885 | 123456 |
| RM Supervisor | 8888888886 | 123456 |
| Laundry Manager | 8888888887 | 123456 |
| Sports Manager | 8888888888 | 123456 |

### Student App Test Credentials
- Phone: 9999999999
- OTP: 123456

## Troubleshooting

### Maestro Command Not Found
- Ensure Maestro is in your PATH
- Restart your terminal after adding to PATH
- Try using full path: `C:\tools\maestro\maestro.exe --version`

### Device Not Detected
- Check USB debugging is enabled
- Authorize computer on phone
- Try different USB cable/port
- Restart ADB server

### Tests Failing
- Check screenshots in `maestro/output/` directory
- Verify apps are installed correctly
- Check test credentials are correct
- Ensure device is unlocked during tests

## Quick Start (Once Everything is Set Up)

```powershell
# Navigate to maestro directory
cd mapmars\mobile\maestro

# Run all staff tests
maestro test staff\run-all-roles.yaml

# Run student comprehensive test
maestro test student\01-student-comprehensive-test.yaml
```

## Need Help?

- Maestro Docs: https://maestro.mobile.dev
- Maestro GitHub: https://github.com/mobile-dev-inc/maestro
- Check test output and screenshots in `maestro/output/` directory

