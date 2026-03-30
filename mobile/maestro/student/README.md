# Student App - Maestro E2E Tests

Comprehensive end-to-end testing suite for the MAP HMS Student Android application using [Maestro](https://maestro.mobile.dev/).

## 📋 Test Coverage

### Test Files

| Test File | Description | Coverage |
|-----------|-------------|----------|
| `00-student-complete-test.yaml` | Complete E2E test suite | All features |
| `01-student-comprehensive-test.yaml` | Comprehensive feature test | Major features |
| `02-student-login-test.yaml` | Login flow test | Authentication |
| `03-student-dashboard-test.yaml` | Dashboard test | Dashboard UI |
| `04-student-outpass-test.yaml` | Gate Pass test | Out Pass flow |
| `05-student-leave-test.yaml` | Leave management test | Leave requests |
| `06-student-features-test.yaml` | Feature flags test | Conditional features |
| `07-student-refined-test.yaml` | Refined test suite | Optimized flows |

## 🚀 Prerequisites

### 1. Install Maestro
```bash
curl -fsSL "https://get.maestro.mobile.dev" | bash
```

### 2. Android Emulator or Device
- Android emulator running (API 34 recommended)
- OR physical Android device connected via ADB

### 3. Student App Installed
```bash
# Build and install the student app
cd mapmars/mobile
npm install
npm run android:student

# OR install pre-built APK
adb install student-app-production.apk
```

### 4. Test Credentials
Update test credentials in test files:
- Phone: `9999999999` (or use actual test student phone)
- OTP: `123456` (bypass code for testing)
- Tenant Code: `PPCU` (or your test tenant)

## 🧪 Running Tests

### Quick Test (Complete Suite)
```bash
cd mapmars/mobile/maestro
maestro test student/00-student-complete-test.yaml
```

### Test Specific Feature
```bash
# Login test
maestro test student/02-student-login-test.yaml

# Dashboard test
maestro test student/03-student-dashboard-test.yaml

# Gate Pass test
maestro test student/04-student-outpass-test.yaml
```

### Run All Student Tests
```bash
cd mapmars/mobile/maestro
maestro test student/
```

### With Custom Environment Variables
```bash
maestro test student/00-student-complete-test.yaml \
  --env phone="9876543210" \
  --env otp="123456" \
  --env tenant_code="PPCU"
```

## 📁 Directory Structure

```
maestro/
├── README.md                    # This file
├── student/
│   ├── 00-student-complete-test.yaml
│   ├── 01-student-comprehensive-test.yaml
│   ├── 02-student-login-test.yaml
│   ├── 03-student-dashboard-test.yaml
│   ├── 04-student-outpass-test.yaml
│   ├── 05-student-leave-test.yaml
│   ├── 06-student-features-test.yaml
│   └── 07-student-refined-test.yaml
└── flows/
    └── student-otp-login.yaml   # Reusable login flow
```

## 🔍 API Endpoints Tested

### Authentication
- ✅ `/v1/mobile/auth/send-otp` - Send OTP
- ✅ `/v1/mobile/auth/verify-otp` - Verify OTP
- ✅ `/v1/mobile/auth/tenant-lookup` - Tenant lookup

### Student Features
- ✅ `/v1/mobile/profile` - Student profile
- ✅ `/v1/mobile/dashboard` - Dashboard data
- ✅ `/v1/mobile/gate-passes` - Gate pass requests
- ✅ `/v1/mobile/leaves` - Leave requests
- ✅ `/v1/mobile/sick-leaves` - Sick leave requests
- ✅ `/v1/mobile/guest-entries` - Guest entry requests
- ✅ `/v1/mobile/room-changes` - Room change requests
- ✅ `/v1/mobile/tickets` - Support tickets
- ✅ `/v1/mobile/notices` - Notices/announcements
- ✅ `/v1/mobile/attendance` - Attendance data
- ✅ `/v1/mobile/laundry/requests` - Laundry requests
- ✅ `/v1/mobile/sports/facilities` - Sports facilities
- ✅ `/v1/mobile/sports/bookings` - Sports bookings
- ✅ `/v1/mobile/messages` - Messages/Notice Board

## 📸 Screenshots

Tests automatically capture screenshots at key points:
- App launch
- Login flow
- Each feature screen
- Form interactions
- Error states

Screenshots are saved to `maestro/.maestro/` directory.

## 🔧 Configuration

### Environment Variables
Edit test files to modify:
- `phone`: Test student phone number
- `otp`: OTP bypass code (default: `123456`)
- `tenant_code`: Tenant code (e.g., `PPCU`)

### API Configuration
The app uses production API by default:
- Production: `https://api.mapservices.in/api/v1`
- Tenant API: `https://{tenant}.mapservices.in/api/v1`

## 🐛 Troubleshooting

### App Not Found
```bash
# Check if app is installed
adb shell pm list packages | grep mapmars

# Install app
adb install student-app-production.apk
```

### Login Fails
- Verify phone number exists in test tenant
- Check OTP bypass code is `123456`
- Ensure tenant is active

### API Errors
- Check network connectivity
- Verify API endpoints are accessible
- Check tenant code is correct
- Verify X-Tenant-Code header is sent

### Tests Timing Out
- Increase timeouts in test files: `timeout: 20000`
- Check device performance
- Ensure app is responsive
- Check API response times

## 📊 Test Results

Test results are saved with screenshots:
- Pass/Fail status
- Execution time
- Screenshots at each step
- Error messages if any

## 🤝 Writing New Tests

### Test Structure
```yaml
appId: com.mapmars.hmsstudent
name: "Test Name"
env:
  phone: "9999999999"
  otp: "123456"

---
- launchApp:
    appId: com.mapmars.hmsstudent
    clearState: true

- tapOn: "Element text"
- assertVisible: "Expected text"
- takeScreenshot: "screenshot_name"
```

### Best Practices
1. Use `extendedWaitUntil` for async operations
2. Add `optional: true` for conditional elements
3. Use `runFlow.when` for conditional test blocks
4. Take screenshots at key verification points
5. Use meaningful screenshot names

## 📞 Support

For issues:
- Test failures: Check screenshots in `.maestro/` directory
- Maestro setup: See [Maestro docs](https://maestro.mobile.dev/getting-started/installing-maestro)
- App bugs: Check API logs and network requests
- API issues: Verify backend routes and tenant configuration
