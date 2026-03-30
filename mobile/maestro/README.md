# MAP HMS Staff App - Maestro E2E Tests

Comprehensive end-to-end testing suite for the MAP HMS Staff Android application using [Maestro](https://maestro.mobile.dev/).

## 📋 Test Coverage

### Roles Tested
| Role | Test File | User Stories |
|------|-----------|--------------|
| Warden | `staff/warden-full-test.yaml` | US-WAR-01, US-WAR-02 |
| Guard | `staff/guard-full-test.yaml` | US-GRD-01, US-GRD-02, US-GRD-03 |
| HK Supervisor | `staff/hk-supervisor-full-test.yaml` | US-SUP-01, US-SUP-02 |
| RM Supervisor | `staff/rm-supervisor-full-test.yaml` | US-SUP-01, US-SUP-02 |
| Laundry Manager | `staff/laundry-manager-full-test.yaml` | US-LAU-01 |
| Sports Manager | `staff/sports-manager-full-test.yaml` | US-SPM-01, US-SPM-02 |
| Rector | `staff/rector-full-test.yaml` | US-REC-01, US-REC-02, US-RECT-001-004 |
| Campus Manager | `staff/campus-manager-full-test.yaml` | US-CM-* |

### Core Flows Tested
- App launch and initialization
- Demo token deep link authentication
- Tab navigation for each role
- Dashboard verification
- Feature-specific screens
- Screenshot capture at key points

## 🚀 Prerequisites

### 1. Install Maestro
```bash
curl -fsSL "https://get.maestro.mobile.dev" | bash
```

### 2. Android Emulator or Device
- Android emulator running (API 34 recommended)
- OR physical Android device connected via ADB

### 3. Staff App Installed
```bash
# Install the staff app APK
adb install mobile/staff-app-production.apk
```

### 4. (Optional) Generate Demo Tokens
For full authentication testing:
```bash
cd scripts
node generate-demo-tokens.js \
  --tenant STXAV \
  --hmac-secret YOUR_SECRET \
  --api https://stxaviers.mapservices.in
```

## 🧪 Running Tests

### Quick Smoke Test
```bash
cd mobile/maestro
./run-all-staff-tests.sh --quick
```

### Test Specific Role
```bash
# Without authentication (tests UI navigation)
./run-all-staff-tests.sh --role warden

# With deep link authentication
./run-all-staff-tests.sh --role warden --deep-link "maphms://demo?token=xxx&tenant=STXAV&role=warden"
```

### Run All Tests
```bash
./run-all-staff-tests.sh
```

### Run Individual Test File
```bash
# Test app launch
maestro test flows/app-launch.yaml

# Test warden role
maestro test staff/warden-full-test.yaml

# Test with deep link
maestro test staff/guard-full-test.yaml --env deepLink="maphms://demo?token=xxx"
```

## 📁 Directory Structure

```
maestro/
├── README.md                    # This file
├── run-all-staff-tests.sh       # Test runner script
├── config/
│   └── test-config.yaml         # Shared configuration
├── flows/
│   ├── app-launch.yaml          # App launch test
│   └── demo-token-login.yaml    # Deep link auth flow
├── staff/
│   ├── warden-full-test.yaml
│   ├── guard-full-test.yaml
│   ├── hk-supervisor-full-test.yaml
│   ├── laundry-manager-full-test.yaml
│   ├── rector-full-test.yaml
│   ├── sports-manager-full-test.yaml
│   └── campus-manager-full-test.yaml
└── output/                      # Test outputs (git-ignored)
    └── YYYYMMDD_HHMMSS/         # Timestamped results
        ├── screenshots/
        └── reports/
```

## 📸 Screenshots

Tests automatically capture screenshots at key points:
- App launch
- Each tab/screen navigation
- Form interactions
- Error states

Screenshots are saved to `output/<timestamp>/<role>/` directory.

## 🔧 Configuration

Edit `config/test-config.yaml` to modify:
- API base URL
- Tenant code
- Timeouts
- Device settings

## 🤝 Writing New Tests

### Test Structure
```yaml
appId: com.mapmars.hmsstaff
name: "Test Name"
tags:
  - tag1
  - tag2

---

# Test steps here
- launchApp:
    appId: com.mapmars.hmsstaff
    clearState: true

- tapOn: "Element text"
- assertVisible: "Expected text"
- takeScreenshot: "screenshot_name"
```

### Best Practices
1. Use `anyOf` for flexible element matching
2. Add `optional: true` for elements that may not always be visible
3. Use `runFlow.when` for conditional test blocks
4. Take screenshots at key verification points
5. Use meaningful screenshot names following `role/sequence_description` pattern

## 🐛 Troubleshooting

### App Not Found
```bash
# Check if app is installed
adb shell pm list packages | grep mapmars

# Install app
adb install mobile/staff-app-production.apk
```

### Emulator Not Detected
```bash
# List connected devices
adb devices

# Ensure emulator is running
emulator -list-avds
emulator -avd YourAVDName
```

### Tests Timing Out
- Increase timeouts in test files: `timeout: 20000`
- Check device performance
- Ensure app is responsive

### Screenshots Not Saving
- Check write permissions in output directory
- Ensure sufficient disk space

## 📊 CI/CD Integration

For automated testing in CI:
```bash
# Install Maestro in CI
curl -fsSL "https://get.maestro.mobile.dev" | bash

# Run tests with JUnit output
maestro test staff/warden-full-test.yaml --format junit --output results.xml
```

## 📞 Support

For issues with:
- Test failures: Check screenshots in output directory
- Maestro setup: See [Maestro docs](https://maestro.mobile.dev/getting-started/installing-maestro)
- App bugs: Create issue in main repository

