# App Store Pre-Submit Checklist (iOS)

Use this **before** submitting Student and Staff apps via Fastlane (or Transporter).

---

## 1. Install on physical iPhone and smoke-test

Connect your iPhone via USB, then from `mobile/`:

```bash
# Student app (scheme: rn082template)
npm run ios:student

# Staff app (scheme: MAPHMSStaff, config: Staff Debug)
npm run ios:staff
```

Or without npm scripts:

```bash
npx react-native run-ios --scheme rn082template --device
npx react-native run-ios --scheme MAPHMSStaff --mode "Staff Debug" --device
```

**Check on device:**

- [ ] Student app: login, core flows, no crashes
- [ ] Staff app: login (e.g. Campus Manager), dashboard, key features
- [ ] Push notifications and deep links if applicable

Use [CAMPUS_MANAGER_MOBILE_TEST_CHECKLIST.md](./CAMPUS_MANAGER_MOBILE_TEST_CHECKLIST.md) for detailed Staff/Campus Manager testing.

---

## 2. Build release IPAs (for upload)

**Option A – Shell script (both apps):**

```bash
cd mobile/ios
./build-for-app-store.sh
```

Output:

- `ios/exports/StudentApp/StudentApp.ipa`
- `ios/exports/StaffApp/StaffApp.ipa`

Upload via **Transporter** or use Fastlane (Staff only, see below).

**Option B – Fastlane (Staff app only):**

From `mobile/`:

```bash
cd fastlane
bundle exec fastlane ios build_staff_release
```

IPA: `ios/build/MAPHMSStaff.ipa`

---

## 3. Submit via Fastlane (Staff app)

**TestFlight:**

```bash
cd mobile/fastlane
bundle exec fastlane ios deploy_staff_testflight
```

**App Store (submit for review):**

```bash
bundle exec fastlane ios deploy_staff_appstore
```

Ensure env is set: `APPLE_ID`, `ITC_TEAM_ID`, `APPLE_TEAM_ID` (see `fastlane/Appfile`).

---

## 4. Student app – App Store

Fastlane currently has **Android** lanes for the Student app; for **iOS** use:

1. Build: `mobile/ios/build-for-app-store.sh` → `StudentApp.ipa`
2. Upload with **Transporter** (or add a Fastlane lane for Student iOS if needed)

---

## Quick reference

| App    | Install on device              | Release build                          | Submit                          |
|--------|--------------------------------|----------------------------------------|---------------------------------|
| Student | `npm run ios:student`          | `ios/build-for-app-store.sh`           | Transporter (StudentApp.ipa)   |
| Staff   | `npm run ios:staff`           | `build-for-app-store.sh` or Fastlane   | Fastlane TestFlight / App Store |
