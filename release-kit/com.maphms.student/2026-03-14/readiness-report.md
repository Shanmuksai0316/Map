# Readiness Report

App ID: com.maphms.student
Date: 2026-03-14
Project type: React Native CLI (ios/ + android/)
Detected lanes: fastlane present (see mobile/fastlane/Fastfile)

## Latest Rejection
- Guideline 4.3(a) duplication/overlapping storefronts
- Submission ID: cb6d675b-c9d6-4424-834e-2597d9fc1689
- Review date: March 11, 2026
- Review device: iPad Air 11-inch (M3)
- Version reviewed: 1.0

## Summary
- Release artifacts generated with manual fallback. The automated audit script (scripts/mobile_release_expert.py) is missing in this repo.
- App Store rejection must be resolved before resubmission.

## Findings
- P0: App Store Guideline 4.3(a) duplication/overlapping storefronts rejection.
- P1: Storefront split between Student and Staff apps not confirmed in App Store Connect.
- P2: Confirm reviewer credentials are entered in App Store Connect for this app.

## Recommended Next Steps (High Priority)
1. Decide storefront split and ensure no overlap with the Staff app.
2. Update App Store Connect availability for this app (Country or Region Availability -> None -> select assigned regions).
3. Add/update reviewer credentials and OTP instructions in App Store Connect.
4. Re-verify metadata highlights the distinct student role.

## Fastlane Path
- iOS: deploy_student_testflight / deploy_student_appstore in mobile/fastlane/Fastfile.
- Android: deploy_student_internal / deploy_student_production.

## Direct-Console Fallback
- iOS: App Store Connect -> My Apps -> Availability -> set Country or Region Availability to "None", then select assigned storefronts.
- Android: Play Console -> Production/Internal -> create or update release, upload AAB, complete metadata.
