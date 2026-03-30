# Blocking Issues: com.maphms.student

These issues should be fixed before submission.

1. [P1] No reviewer test account path configured
   - App requires login but no provisioning command or fallback credentials were supplied.
   - Fix: Configure test_account.provision_command or fallback_accounts in release config.
2. [P1] Privacy policy URL missing
   - Reviewer-facing privacy policy URL is not configured.
   - Fix: Add public privacy policy URL in release config and store listing.
3. [P1] Release contact email missing
   - Contact email is required for reviewer clarifications.
   - Fix: Add contact.email in release config.
