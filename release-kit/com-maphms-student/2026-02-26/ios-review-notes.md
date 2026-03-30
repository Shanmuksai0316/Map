# App Review Notes - MAP Vidyarthi

## Build Summary

- Date: 2026-02-26
- App ID: com.maphms.student
- Frameworks: react-native-cli
- iOS bundle ID: com.maphms.student
- iOS version/build: 1.0 (1)

## Reviewer Access

- Contact: MAP HMS Release Team (support@maphms.com)
- Primary reviewer account (student): username `9876543210`, password `123456`
- Fallback reviewer account (student): username `9999999999`, password `123456`
- Login method: phone + OTP. For App Review, use `123456` as the OTP/password.
- Account deletion URL: https://mapservices.in/privacy-policy/

## Primary Test Flow

1. Launch MAP Vidyarthi on iPad Air 11-inch (M3).
2. Enter `9876543210` in the phone field and tap **Send OTP**.
3. Enter `123456` in **Verification Code** and tap **Verify/Login**.
4. Validate dashboard load and core navigation (Outpass, Leave, Notices, Tickets, Profile).
5. Open Profile/Settings and verify account/help links.
6. If the primary phone is unavailable, repeat with `9999999999` and OTP `123456`.

## Compliance Links

- Privacy policy: https://mapservices.in/privacy-policy/
- Support URL: https://mapservices.in/

## Fastlane lane reference

- iOS lane: `deploy_student_appstore`
