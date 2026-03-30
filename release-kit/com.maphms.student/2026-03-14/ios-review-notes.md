# iOS Review Notes

## App Overview
Two separate apps serve different roles:
- Student App (Vidyarthi): self-service for students.
- Staff App (Kartha): operational tools for campus staff.

## Login
- App requires authenticated login.
- Reviewer credentials for this app are provided in test-accounts.csv.
- OTP flow: enter phone, tap Send OTP, then use fixed OTP 123456.

## Core Flows To Test
- Student: login, dashboard, core student services.

## Environment
- Production endpoints are configured for production builds.

## Storefront Note
- This app's storefront availability must not overlap with the Staff app per Apple Guideline 4.3(a).
