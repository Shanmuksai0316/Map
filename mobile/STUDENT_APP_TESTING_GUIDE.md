# Student App Testing Guide

## Overview
This guide covers testing the Student Mobile App with all API endpoints and functionalities using Maestro E2E testing framework.

## ✅ Configuration Fixed

### API Endpoints
All endpoints have been updated to match backend routes:
- Base URL format: `https://{tenant}.mapservices.in/api/v1` or `https://api.mapservices.in/api/v1`
- Mobile endpoints use `/mobile/` prefix (e.g., `/mobile/gate-passes`)
- All endpoints correctly configured in `src/shared/config/app.config.ts`

### Authentication Flow
- OTP Send: `/v1/mobile/auth/send-otp`
- OTP Verify: `/v1/mobile/auth/verify-otp`
- Tenant Lookup: `/v1/mobile/auth/tenant-lookup`
- Bypass OTP: `123456` (for testing)

### Tenant Context
- X-Tenant-Code header automatically added to all requests
- Tenant service correctly normalizes API URLs
- Base URL updates dynamically when tenant changes

## 📱 Student App Features Tested

### Core Features
1. **Authentication**
   - Tenant selection
   - OTP login
   - Session management

2. **Dashboard**
   - Profile display
   - Quick actions
   - Notifications count
   - Tenant logo

3. **Gate Pass / Out Pass**
   - View gate passes
   - Create new gate pass
   - View history
   - Status tracking

4. **Leave Management**
   - Regular leaves
   - Sick leaves
   - Leave history
   - Status tracking

5. **Attendance**
   - View attendance
   - Attendance statistics
   - Attendance history

6. **Tickets / Complaints**
   - Create tickets (Housekeeping/Repair)
   - View ticket history
   - Upload photos
   - Status tracking

7. **Notices**
   - View notices
   - Notice details
   - Filter by category

8. **Guest Entry**
   - Request guest entry
   - View guest history
   - Status tracking

9. **Room Change**
   - Request room change
   - View room change history
   - Status tracking

10. **Sports** (if enabled)
    - View facilities
    - Book facilities
    - View bookings
    - Cancel bookings

11. **Laundry** (if enabled)
    - Create laundry request
    - View request history
    - Track status

12. **Messages / Notice Board**
    - View messages
    - Send messages
    - Message history

## 🧪 Maestro Test Suite

### Test Files
- `00-student-complete-test.yaml` - Complete E2E test suite (RECOMMENDED)
- `01-student-comprehensive-test.yaml` - Comprehensive feature test
- `02-student-login-test.yaml` - Login flow only
- `03-student-dashboard-test.yaml` - Dashboard test
- `04-student-outpass-test.yaml` - Gate Pass test
- `05-student-leave-test.yaml` - Leave test
- `06-student-features-test.yaml` - Feature flags test
- `07-student-refined-test.yaml` - Refined test suite

### Running Tests

#### Quick Start
```bash
cd mapmars/mobile/maestro
maestro test student/00-student-complete-test.yaml
```

#### With Custom Credentials
```bash
maestro test student/00-student-complete-test.yaml \
  --env phone="9876543210" \
  --env otp="123456" \
  --env tenant_code="PPCU"
```

#### Run All Student Tests
```bash
maestro test student/
```

## 🔍 API Endpoints Verified

### Authentication
- ✅ `POST /v1/mobile/auth/send-otp`
- ✅ `POST /v1/mobile/auth/verify-otp`
- ✅ `POST /v1/mobile/auth/tenant-lookup`

### Student Features
- ✅ `GET /v1/mobile/profile`
- ✅ `GET /v1/mobile/dashboard`
- ✅ `GET /v1/mobile/gate-passes`
- ✅ `POST /v1/mobile/gate-passes`
- ✅ `GET /v1/mobile/leaves`
- ✅ `POST /v1/mobile/leaves`
- ✅ `GET /v1/mobile/sick-leaves`
- ✅ `POST /v1/mobile/sick-leaves`
- ✅ `GET /v1/mobile/guest-entries`
- ✅ `POST /v1/mobile/guest-entries`
- ✅ `GET /v1/mobile/room-changes`
- ✅ `POST /v1/mobile/room-changes`
- ✅ `GET /v1/mobile/tickets`
- ✅ `POST /v1/mobile/tickets`
- ✅ `GET /v1/mobile/notices`
- ✅ `GET /v1/mobile/attendance`
- ✅ `GET /v1/mobile/attendance/stats`
- ✅ `GET /v1/mobile/laundry/requests`
- ✅ `GET /v1/mobile/sports/facilities`
- ✅ `GET /v1/mobile/sports/bookings`
- ✅ `POST /v1/mobile/sports/bookings`
- ✅ `GET /v1/mobile/messages`

## 🚀 Building the App

### Android
```bash
cd mapmars/mobile
npm install
npm run android:student
```

### Production Build
```bash
cd mapmars/mobile/android
./gradlew assembleStudentRelease
```

## 📊 Testing Checklist

### Pre-Testing
- [ ] App builds successfully
- [ ] Test student account exists in tenant
- [ ] OTP bypass code works (123456)
- [ ] Tenant is active
- [ ] API endpoints are accessible

### During Testing
- [ ] Login flow works
- [ ] Dashboard loads correctly
- [ ] All features accessible
- [ ] API calls succeed
- [ ] Error handling works
- [ ] Screenshots captured

### Post-Testing
- [ ] Review test results
- [ ] Check screenshots
- [ ] Verify API logs
- [ ] Document any issues

## 🐛 Troubleshooting

### Login Issues
1. Verify phone number exists in tenant
2. Check OTP bypass code: `123456`
3. Ensure tenant is active
4. Check API connectivity

### API Errors
1. Verify base URL: `https://{tenant}.mapservices.in/api/v1`
2. Check X-Tenant-Code header is sent
3. Verify authentication token
4. Check backend logs

### Test Failures
1. Check screenshots in `.maestro/` directory
2. Verify element selectors
3. Increase timeouts if needed
4. Check device performance

## 📝 Notes

- All API endpoints use `/v1/mobile/` prefix
- Base URL includes `/api/v1` (e.g., `https://ppcu.mapservices.in/api/v1`)
- X-Tenant-Code header is automatically added
- OTP bypass code: `123456` (for testing only)
- Production API: `https://api.mapservices.in/api/v1`
- Tenant API: `https://{tenant}.mapservices.in/api/v1`

## 🔗 Related Files

- API Config: `src/shared/config/app.config.ts`
- API Service: `src/shared/services/api.service.ts`
- Tenant Service: `src/shared/services/tenant.service.ts`
- Maestro Tests: `maestro/student/`
- Test README: `maestro/student/README.md`
