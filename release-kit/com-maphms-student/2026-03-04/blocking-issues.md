# Blocking Issues: com.maphms.student

Manual rejection triage override (from App Review on February 28, 2026).

## P1 blockers (must fix before resubmission)

1. **Guideline 4.3(a) Spam - storefront overlap with similar app**
   - Impact: high rejection risk until storefronts are segmented between similar binaries.
   - Required action (App Store Connect):
     - For each overlapping app, open **Pricing and Availability**.
     - Set **Country or Region Availability = None** then explicitly select non-overlapping regions.
     - Keep a single app per storefront cluster.
   - Evidence to attach in App Review response:
     - Region matrix showing non-overlap by bundle ID.

2. **Guideline 2.1 App Completeness - 'Post Notice' unresponsive on iPad**
   - Likely root cause: navigation route missing in shared stack for flows that open `CommBox` outside role-specific nested stacks.
   - Code fix applied:
     - Added `PostNotice` route to shared staff navigator.
     - File: `mobile/src/shared/navigation/staff-navigator.tsx`
   - Required validation before resubmission:
     - iPad Air 11-inch (M3) + latest iPadOS simulator/device.
     - Login as Campus Manager/Warden/Rector.
     - Comm Box -> **Post Notice** opens compose screen and can submit.

3. **Guideline 1.5 Safety - support URL reported non-functional**
   - Risk: metadata rejection until a stable support destination is provided.
   - Required action (App Store Connect):
     - Replace Support URL with a dedicated support page that is always reachable and not geo/JS dependent.
     - Recommended temporary URL: `https://mapservices.in/privacy-policy/` (returns HTTP 200 as of March 4, 2026).
     - Preferred final URL: `https://mapservices.in/support` after publishing a permanent support page.

## Exit criteria for resubmission

- [ ] Storefront overlap removed and documented.
- [ ] iPad repro path for Post Notice passes on release build.
- [ ] Support URL updated in App Store Connect and verified from non-authenticated browser.
- [ ] App Review notes updated with exact retest steps and credentials.
