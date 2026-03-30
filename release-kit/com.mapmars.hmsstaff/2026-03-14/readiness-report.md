# Readiness Report

App ID: com.mapmars.hmsstaff
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
- P1: Reviewer test accounts missing for Staff app.
- P1: Staff app release config not present (policy URLs and reviewer contact not declared).

## Recommended Next Steps (High Priority)
1. Decide storefront split and ensure no overlap with the Student app.
2. Update App Store Connect availability for this app (Country or Region Availability -> None -> select assigned regions).
3. Provide Staff reviewer credentials and OTP instructions in App Store Connect.
4. Create a staff release config (copy student config and adjust app_id/app_name/test accounts).
5. Re-verify metadata highlights the distinct staff role.

## Fastlane Path
- iOS: deploy_staff_testflight / deploy_staff_appstore in mobile/fastlane/Fastfile.
- Android: deploy_staff_internal / deploy_staff_production.

## Direct-Console Fallback
- iOS: App Store Connect -> My Apps -> Availability -> set Country or Region Availability to "None", then select assigned storefronts.
- Android: Play Console -> Production/Internal -> create or update release, upload AAB, complete metadata.
