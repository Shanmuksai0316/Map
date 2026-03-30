# Push Notifications Setup

Push is implemented end-to-end (FCM on mobile, Laravel backend sending via `PushNotifier`). To enable it you need to provide credentials and optional iOS setup.

---

## What you need to provide

### 1. Firebase project (same as mobile apps)

- Use the **same Firebase project** that your Android `google-services.json` (and iOS app, if any) use.
- Ensure **Cloud Messaging** / **Firebase Cloud Messaging API** is enabled for that project.

### 2. Backend: FCM credentials (choose one)

**Option A – FCM V1 (recommended)**

- **FCM_PROJECT_ID**: Your Firebase project ID (e.g. `my-app-12345`).
- **FCM_SERVICE_ACCOUNT_JSON** or **FCM_SERVICE_ACCOUNT_PATH**:
  - In Firebase Console → Project Settings → Service accounts → “Generate new private key”.
  - Either paste the full JSON string into `FCM_SERVICE_ACCOUNT_JSON` in `.env`, or save the file and set `FCM_SERVICE_ACCOUNT_PATH` to its path (e.g. `storage/app/firebase-service-account.json`).

**Option B – Legacy server key**

- In Firebase Console → Project Settings → Cloud Messaging.
- Copy the **Server key** (or “Cloud Messaging API (Legacy)” key).
- Set `FCM_SERVER_KEY=<that key>` in `.env`. Leave `FCM_SERVICE_ACCOUNT_JSON` and `FCM_SERVICE_ACCOUNT_PATH` empty.

### 3. Backend: Enable FCM

In your API `.env`:

```env
FCM_ENABLED=true
```

Then set either Option A or Option B above.

After changing env, clear config cache on the server:

```bash
php artisan config:clear
php artisan config:cache
```

### 4. Database

Ensure the `push_device_tokens` table exists (run migrations if needed):

```bash
php artisan migrate
```

### 5. iOS (if you ship iOS)

To receive push on **real iOS devices**:

- **Firebase:** Add both iOS apps (bundle IDs `com.maphms.student` and `com.mapmars.hmsstaff`) in the same project and place each app’s `GoogleService-Info.plist` in `mobile/ios/` (see `FIREBASE_CONSOLE_SETUP.md`).
- **Xcode:** Add the **Push Notifications** capability to both app targets.
- **Apple Developer:** Create an APNs key (.p8), then in **Firebase Console** → Project Settings → Cloud Messaging → Apple app configuration, upload the key for each iOS app.

---

## Quick test (e.g. as Rector)

1. **Log in on the Staff app** as Rector (or any user). The app registers your FCM token.
2. **From the API directory**, get your `user_id`: run `php artisan push:list-devices` and note your user_id.
3. **Put the app in the background** (or lock the phone).
4. **Send a test push:** `php artisan push:send-test <user_id>` (e.g. `php artisan push:send-test 5`).
5. **On the device:** You should see the notification; **tap it** — the app opens to the **Notifications** screen.

**Send to all registered users (all roles):** To test push for everyone who has the app and a token (Rector, Warden, Guard, etc.), run:
```bash
php artisan push:send-test-all
```
Use `--dry-run` to list who would receive without sending. Optional: `--title="..."` and `--body="..."`.

If nothing appears, run `php artisan push:check-config` and ensure your token is in `push:list-devices`.

---

## Verify

- **Backend:** Run `php artisan push:check-config` (if that command exists) or send a test notification from your backend to a user who has logged in from the app (so their FCM token is in `push_device_tokens`).
- **Mobile:** Log in, then send a push from the backend (e.g. post a notice with “Push” channel, or trigger a leave/approval notification). You should receive the notification; tapping it should open the app to the Notifications screen.

---

## Optional: Deep link to a specific screen

When sending a push from the backend, you can pass a `data` payload. If you include `screen`, the app will open that screen when the user taps the notification (e.g. `data: { screen: 'Notifications' }`). Default is `Notifications` if `screen` is omitted.
