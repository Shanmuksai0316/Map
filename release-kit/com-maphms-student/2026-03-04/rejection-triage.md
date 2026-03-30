# Rejection Triage Report (Manual Override)

## Review metadata

- Submission ID: `cb6d675b-c9d6-4424-834e-2597d9fc1689`
- Review date: February 28, 2026
- Review device: iPad Air 11-inch (M3)
- Version reviewed: 1.0

## Classified issues

1. **Guideline 4.3(a) - Design/Spam**
   - Category: Store metadata + distribution strategy
   - Confidence: High
   - Root cause: Two or more similar apps were available in overlapping storefronts.

2. **Guideline 2.1 - Performance/App Completeness**
   - Category: Functional bug
   - Confidence: High
   - Root cause hypothesis: Missing `PostNotice` route in shared staff navigator causes navigation no-op on certain navigation trees.
   - Fix status: **Patched locally** in `mobile/src/shared/navigation/staff-navigator.tsx` by registering `PostNotice` screen.

3. **Guideline 1.5 - Safety**
   - Category: Store metadata URL integrity
   - Confidence: Medium-High
   - Root cause: Support URL pointed to a page Apple treated as unavailable/invalid for support context.

## Remediation checklist

### Code
- [x] Add `PostNotice` route to shared navigator.
- [ ] Build release IPA and run iPad regression for Comm Box -> Post Notice.
- [ ] Attach screen recording/screenshot proof of working tap flow.

### App Store Connect metadata
- [ ] Segment storefronts so there is no overlap with sibling app(s).
- [ ] Update Support URL to stable support destination.
- [ ] Update review notes with issue-by-issue fix mapping.

### Reviewer communication
- [ ] Mention exact roles/accounts used to verify Post Notice.
- [ ] Mention storefront segmentation completed.
- [ ] Mention support URL was corrected and verified.
