# Rejection Triage Report

## Source Excerpt

```text
Hello,

Thank you for your resubmission. Upon further review, we identified additional issues that need your attention. See below for more information.

If you have any questions, we are here to help. Reply to this message in App Store Connect and let us know.

Review Environment
Submission ID: 274831ce-c4aa-4586-92b0-9591d4da9fa6
Review date: February 26, 2026
Review Device: iPad Air 11-inch (M3)
Version reviewed: 1.0


Guideline 2.1 - Information Needed

We are unable to successfully access all or part of the app. In order to continue the review, we need to have a way to verify all app features and functionality for all account types. Typically this is done by providing a demo account that has access to all features and functionality in the app.

Next Steps

To resolve this issue, provide a user name and password in the App Review Information section of App Store Connect. It is also acceptable to include a demonstration mode that exhibits the app’s full features and functionality. Note that providing a demo video showing the app in use is not sufficient to continue the review.

Resources

To learn more about providing information in App Store Connect, see App Store Connect Help.

Support
- Reply to this message in your preferred language if you need assistance. If you need additional support, use the Contact Us module.
- Consult with fellow developers and Apple engineers on the Apple Developer Forums.
- Request an App Review Appointment at Meet with Apple to discuss your app's review. Appointments subject to availability during your local business hours on Tuesdays and Thursdays.
- Provide feedback on this message and your review experience by completing a short survey.
```

## Matched Patterns

| Rule | Platform | Severity | Confidence |
|---|---|---|---:|
| IOS-GUIDELINE-001 | ios | P1 | 0.4 |
| REV-ACCESS-001 | both | P1 | 0.17 |

## Recommended Remediation

1. [P1] Apple guideline functional issue
   - Reproduce reviewer scenario and patch stability/flow issues.
   - Add deterministic repro and test steps to review notes.
   - Confirm fixed behavior on release candidate build.
   - Evidence to include:
     - Crash-free smoke test output.
     - Release notes listing corrected reviewer path.
2. [P1] Reviewer access/test account issue
   - Provide stable reviewer credentials with non-expiring password.
   - Document exact login steps and environment in review notes.
   - Add fallback account if provisioning automation fails.
   - Evidence to include:
     - Updated review notes with login walkthrough.
     - Credential CSV with role and notes.
     - Smoke test run proving successful login.
