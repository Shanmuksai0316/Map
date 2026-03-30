# Release Checklist - Resubmission Focus

## Current gate

- Automated audit: GO (0 blockers)
- Manual rejection triage gate: **HOLD** until P1 items below are closed

## P1 closure checklist

- [ ] 4.3(a): Storefront overlap removed and cross-app region matrix documented.
- [ ] 2.1: `PostNotice` route fix merged and release build smoke-tested on iPad.
- [ ] 1.5: Support URL updated in App Store Connect and verified externally.

## iOS submission prep

- [ ] Bundle ID and scheme match target app under review.
- [ ] Version/build incremented.
- [ ] App Review notes updated from `ios-review-notes.md`.
- [ ] Reviewer credentials in `test-accounts.csv` confirmed valid.

## Fastlane

- [x] iOS lane present: `deploy_student_appstore`
- [x] Android lane present: `deploy_student_production`
- [x] Fallback instructions present: `direct-console-fallback.md`

## Evidence packet

- [ ] iPad recording: Comm Box -> Post Notice -> Submit success.
- [ ] Screenshot: updated Support URL page renders without error.
- [ ] Screenshot/export: non-overlapping storefront setup.
