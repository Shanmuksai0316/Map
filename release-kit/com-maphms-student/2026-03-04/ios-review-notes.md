# App Review Notes - Resubmission

## Build summary

- Date: 2026-03-04
- Submission reference: `cb6d675b-c9d6-4424-834e-2597d9fc1689` (previous rejection)
- App ID in config: `com.maphms.student`
- Note: If this rejection is for staff binary, verify bundle ID and submission target before upload.
- Device family configuration: iPhone only (`TARGETED_DEVICE_FAMILY = 1` in Xcode project).

## Fixed issues for this resubmission

1. Guideline 4.3(a): storefront overlap removed (non-overlapping country availability configured across similar apps).
2. Guideline 2.1: Post Notice navigation path fixed for shared staff navigation flows.
3. Guideline 1.5: Support URL updated to a stable support destination.
4. **Guideline 5.1.1(v) – Account deletion:** In-app account deletion initiation added.

## Account deletion path (5.1.1(v))

1. Open the app and log in with reviewer credentials.
2. Go to the **Profile** tab (bottom navigation).
3. Tap **Request Account Deletion**.
4. Tap **Continue** in the confirmation dialog.
5. Enter the 6-digit OTP sent to your registered phone number.
6. Tap **Submit**. You will be logged out immediately upon success.

**Note:** OTP is sent via SMS. For test accounts, use OTP `123456` when bypass is enabled in development, or ensure the test phone can receive SMS.

## Reviewer test credentials

- See `test-accounts.csv`.
- Primary contact: MAP HMS Release Team (`support@maphms.com`).
- For account deletion testing: use a test account phone that can receive OTP, or OTP `123456` in dev/test environments where applicable.

## Reviewer retest path (iPad)

1. Install fresh build.
2. Login with reviewer credentials.
3. Open Comm Box.
4. Tap **Post Notice**.
5. Confirm compose screen opens.
6. Enter title/body and submit.
7. Confirm success toast and new notice appears in list.
8. Note: app is designed for iPhone; iPad retest is for compatibility mode behavior only.

## Compliance links

- Privacy policy: https://mapservices.in/privacy-policy/
- Support URL (set in App Store Connect): https://mapservices.in/privacy-policy/
- Account deletion: Initiated in-app via Profile → Request Account Deletion (no external URL required).

## Fastlane reference

- iOS lane: `deploy_student_appstore`
- Fallback: use `direct-console-fallback.md`
