# Readiness Report

App ID: com.maphms.student
Date: 2026-03-10
Project type: React Native CLI (ios/ + android/)
Detected lanes: fastlane present (see mobile/fastlane/Fastfile)

## Latest Rejection
- Guideline 4.3(a) duplication/overlapping storefronts
- Submission ID: cb6d675b-c9d6-4424-834e-2597d9fc1689
- Review date: March 09, 2026

## Summary
- Release artifacts generated with manual fallback. The automated audit script (scripts/mobile_release_expert.py) is missing in this repo.
- App Store rejection must be resolved before resubmission.

## Findings
- P0: App Store Guideline 4.3(a) duplication/overlapping storefronts rejection.
- P1: Reviewer test accounts not provided.
- P1: Policy URLs not confirmed in repo.

## Recommended Next Steps (High Priority)
1. Decide storefront split for the two apps and ensure no overlap. Update App Store Connect availability accordingly.
2. Provide reviewer credentials for each app (Student, Staff). Confirm login flow requires OTP or other steps.
3. Confirm Privacy Policy, Terms, and Support URLs in App Store Connect and Play Console.

## Fastlane Path
- iOS: fastlane lanes in mobile/fastlane/Fastfile (e.g., deploy_staff_testflight / deploy_student_testflight).
- Android: deploy_staff_internal / deploy_student_internal / deploy_*_production.

## Direct-Console Fallback
- iOS: App Store Connect -> My Apps -> Availability -> set Country or Region Availability to “None”, then select non-overlapping storefronts.
- Android: Play Console -> Production/Internal -> create or update release, upload AAB, complete metadata.
