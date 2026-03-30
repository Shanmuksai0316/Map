# Resubmission Response Draft (Apple)

Dear App Review Team,

Thank you for the detailed review.

We addressed all items from the review dated **February 28, 2026** (Submission ID: **cb6d675b-c9d6-4424-834e-2597d9fc1689**):

- The binary is configured as **iPhone-only** (`TARGETED_DEVICE_FAMILY = 1`). We still validated the reported path in iPad compatibility mode because that was the original review environment.

1. **Guideline 4.3(a) - Design/Spam**
   - We updated country/region availability so similar apps no longer overlap in storefront availability.

2. **Guideline 2.1 - App Completeness**
   - We fixed the Comm Box -> Post Notice navigation flow that could appear unresponsive on iPad.
   - We retested this flow on tablet form factor (including iPad Air 11-inch profile) using a clean install.

3. **Guideline 1.5 - Safety**
   - We updated the Support URL in App Store Connect to a functional support destination.

### Retest path

- Login with the reviewer account provided in App Review Notes.
- Go to Comm Box.
- Tap **Post Notice**.
- Compose and submit a notice.

If you need additional evidence (screen recording, account-specific steps, or region matrix), we can provide it immediately.

Best regards,
MAP HMS Release Team
