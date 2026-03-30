# iOS Review Notes

## App Overview
Two separate apps serve different roles:
- Student App (Vidyarthi): self-service for students.
- Staff App (Kartha): operational tools for campus staff.

## Login
- App requires authenticated login.
- Reviewer credentials for this app are required in test-accounts.csv.
- If OTP is required, include the OTP method and any fixed OTP for review.

## Core Flows To Test
- Staff: login, role-specific operations (e.g., guard, laundry, warden, or other staff tools enabled for the account).

## Environment
- Production endpoints are configured for production builds.

## Storefront Note
- This app's storefront availability must not overlap with the Student app per Apple Guideline 4.3(a).
