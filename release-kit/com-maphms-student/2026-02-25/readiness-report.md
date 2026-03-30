# Mobile Release Readiness Report: com.maphms.student

- Generated: 2026-02-25T10:55:19.135493+00:00
- App root: `/Users/nagrajyr/Downloads/mapmars/mobile`
- Frameworks: react-native-cli
- Gate: **BLOCKED**
- Readiness score: **38 / 100**

## Severity Summary

| P0 | P1 | P2 | P3 |
|---:|---:|---:|---:|
| 0 | 3 | 3 | 1 |

## Findings

1. [P1] No reviewer test account path configured
   - App requires login but no provisioning command or fallback credentials were supplied.
   - Fix: Configure test_account.provision_command or fallback_accounts in release config.
2. [P1] Privacy policy URL missing
   - Reviewer-facing privacy policy URL is not configured.
   - Fix: Add public privacy policy URL in release config and store listing.
3. [P1] Release contact email missing
   - Contact email is required for reviewer clarifications.
   - Fix: Add contact.email in release config.
4. [P2] Release config not found
   - No .release/release.config.json or release.config.json file found.
   - Fix: Create release config from assets/templates/release-config.example.json.
5. [P2] Support URL missing
   - Support URL is absent; this often delays metadata approval.
   - Fix: Provide support URL in release config and store metadata.
6. [P2] Screen-size/device matrix coverage is thin
   - Configured device matrix has fewer than four target profiles.
   - Fix: Test on small, medium, and large phones plus one tablet profile for each platform.
7. [P3] Encryption usage flag not declared (/Users/nagrajyr/Downloads/mapmars/mobile/ios/rn082template/Info.plist)
   - ITSAppUsesNonExemptEncryption key is not explicitly declared.
   - Fix: Set the key explicitly to avoid review clarification delays.

## Recommended Next Actions

- Resolve P1 issues before uploading binaries to avoid predictable review loops.
- Guarantee reviewer login access via provisioning command and fallback credentials.
