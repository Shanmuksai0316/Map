# iOS Review Notes

## App Overview
Two separate apps serve different roles:
- Student App: self-service for students.
- Staff App: operational tools for campus staff.

## Login
- App requires authenticated login.
- Provide reviewer credentials (see test-accounts.csv) for the relevant role.
- If OTP is required, include the OTP method and any fixed OTP for review.

## Core Flows To Test
- Student: login, dashboard, core student services.
- Staff: login, role-specific operations (e.g., laundry/requests if enabled).

## Environment
- Production endpoints are configured for production builds.

## Storefront Note
- This app’s storefront availability must not overlap with the other app per Apple Guideline 4.3(a).
