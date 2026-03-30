# Firebase Console Setup Guide (Push Notifications)

This guide walks you through setting up Firebase for the MAP HMS mobile apps (Student + Staff, **Android and iOS**) and getting credentials for your Laravel backend so push notifications work.

---

## Your app identifiers

| Platform | Student              | Staff                 |
|----------|----------------------|------------------------|
| **Android package** | `com.maphms.student`   | `com.mapmars.hmsstaff` |
| **iOS bundle ID**   | `com.maphms.student`   | `com.mapmars.hmsstaff` |

Register **all four** (2 Android + 2 iOS) in the **same** Firebase project so one FCM configuration can send push to every app.

---

## Step 1: Open Firebase Console

1. Go to **[https://console.firebase.google.com](https://console.firebase.google.com)**.
2. Sign in with your Google account.

---

## Step 2: Create a project (or use an existing one)

**If you don’t have a project yet:**

1. Click **“Create a project”** (or “Add project”).
2. **Project name:** e.g. `MAP-HMS` or `map-hms`.
3. Click **Continue**.
4. **Google Analytics:** You can enable or disable it (optional for push). Click **Continue** and then **Create project**.
5. When it’s ready, click **Continue** to open the project.

**If you already have a project** (e.g. you already use `google-services.json`):

- Select that project from the list. Use **one** project for both Student and Staff apps.

---

## Step 3: Add your Android apps to the project

You need to register **two** Android apps (Student and Staff) in this project.

### Add the Student app

1. On the project overview page, click the **Android** icon (or “Add app” → Android).
2. **Android package name:** enter exactly  
   `com.maphms.student`
3. **App nickname (optional):** e.g. `MAP HMS Student`.
4. **Debug signing certificate SHA-1 (optional for now):** you can skip; add later if you use Google Sign-In or similar.
5. Click **Register app**.
6. **Download `google-services.json`:**
   - Click **Download google-services.json**.
   - Save it and then place it in your repo at:  
     `mobile/android/app/src/student/google-services.json`  
     (overwrite the existing file if there is one).
7. Click **Next** → **Next** → **Continue to console**.

### Add the Staff app

1. In the same Firebase project, go to **Project settings** (gear icon next to “Project overview”).
2. Scroll to **“Your apps”**.
3. Click **“Add app”** and choose **Android**.
4. **Android package name:** enter exactly  
   `com.mapmars.hmsstaff`
5. **App nickname (optional):** e.g. `MAP HMS Staff`.
6. Click **Register app**.
7. **Download `google-services.json`** for the Staff app.
8. Place it at:  
   `mobile/android/app/src/staff/google-services.json`
9. Click **Next** → **Next** → **Continue to console**.

You should now see **two** Android apps under “Your apps” in Project settings.

---

## Step 3b: Add your iOS apps to the project

Register **two** iOS apps (Student and Staff) in the same Firebase project.

### Add the Student iOS app

1. Go to **Project overview** → click the **iOS** icon (or “Add app” → iOS).
2. **iOS bundle ID:** enter exactly  
   `com.maphms.student`
3. **App nickname (optional):** e.g. `MAP HMS Student (iOS)`.
4. **App Store ID (optional):** leave blank for now.
5. Click **Register app**.
6. **Download `GoogleService-Info.plist`**:
   - Click **Download GoogleService-Info.plist**.
   - Place it in your repo at:  
     `mobile/ios/GoogleService-Info.plist`  
     (overwrite the existing file if there is one).
7. Click **Next** → **Next** → **Continue to console**.

### Add the Staff iOS app

1. Go to **Project settings** (gear) → scroll to **“Your apps”**.
2. Click **“Add app”** → **iOS**.
3. **iOS bundle ID:** enter exactly  
   `com.mapmars.hmsstaff`
4. **App nickname (optional):** e.g. `MAP HMS Staff (iOS)`.
5. Click **Register app**.
6. **Download `GoogleService-Info.plist`** for the Staff app.
7. Place it at:  
   `mobile/ios/GoogleService-Info-staff.plist`
8. Click **Next** → **Next** → **Continue to console**.

You should now see **four** apps under “Your apps” (2 Android + 2 iOS).

---

## Step 4: Enable Cloud Messaging (FCM)

1. In the left sidebar, open **“Build”** → **“Cloud Messaging”** (or **Engage** → **Messaging** in newer UI).
2. If you see a prompt to enable the **Firebase Cloud Messaging API**, enable it.
3. For **Firebase Cloud Messaging API (Legacy)** (if still shown):  
   - In Google Cloud Console, the “Firebase Cloud Messaging” or “Cloud Messaging API” may need to be enabled for the project.  
   - You can do that from: [Google Cloud Console](https://console.cloud.google.com) → select the same project → **APIs & Services** → **Enabled APIs** → enable **Firebase Cloud Messaging API** if needed.

No extra configuration is required in the Messaging UI for basic push; the backend will send messages using the credentials from the next steps.

---

## Step 5: Get credentials for your Laravel backend

Your API needs **one** of the following to send push. Prefer **Option A** (V1) if possible.

### Option A: FCM V1 API (recommended) – Service account

1. In Firebase, go to **Project settings** (gear icon) → **Service accounts** tab.
2. Click **“Generate new private key”** → **Generate key**.  
   A JSON file will download (e.g. `map-hms-xxxxx-firebase-adminsdk-xxxxx.json`).
3. **Keep this file secret.** Do not commit it to git.
4. **Option 5a – Use as env variable (good for servers):**
   - Open the JSON file in a text editor.
   - Copy the **entire** JSON (one line or pretty-printed).
   - In your Laravel `.env` set:
     ```env
     FCM_ENABLED=true
     FCM_PROJECT_ID=your-project-id
     FCM_SERVICE_ACCOUNT_JSON={"type":"service_account",...}
     ```
   - `FCM_PROJECT_ID` is in the JSON as `"project_id"` or in Firebase **Project settings** → **General** → “Project ID”.
5. **Option 5b – Use as file (e.g. on server):**
   - Upload the JSON file to the server in a safe place (e.g. `storage/app/firebase-service-account.json`).
   - In `.env`:
     ```env
     FCM_ENABLED=true
     FCM_PROJECT_ID=your-project-id
     FCM_SERVICE_ACCOUNT_PATH=/full/path/to/firebase-service-account.json
     ```

### Option B: Legacy API – Server key

1. In Firebase, go to **Project settings** (gear) → **Cloud Messaging** tab.
2. Under **“Cloud Messaging API (Legacy)”**, find **Server key**.
3. If you don’t see it, you may need to enable the legacy API or use a different project that still has it. New projects sometimes only show the V1 API; in that case use **Option A**.
4. Copy the **Server key**.
5. In your Laravel `.env`:
   ```env
   FCM_ENABLED=true
   FCM_SERVER_KEY=your-server-key-here
   ```
   Leave `FCM_PROJECT_ID` and `FCM_SERVICE_ACCOUNT_*` empty if you use only the server key.

---

## Step 6: Backend checklist

1. **`.env`** (on the machine where the API runs):
   - `FCM_ENABLED=true`
   - Either V1 (project ID + service account JSON/path) or Legacy (server key) as above.
2. **Config cache** (after changing `.env`):
   ```bash
   php artisan config:clear
   php artisan config:cache
   ```
3. **Database:** Ensure the `push_device_tokens` table exists:
   ```bash
   php artisan migrate
   ```

---

## Step 7: Rebuild the apps (if you replaced config files)

**Android** (if you replaced `google-services.json`):

1. Place **Student** at: `mobile/android/app/src/student/google-services.json`.
2. Place **Staff** at: `mobile/android/app/src/staff/google-services.json`.
3. Rebuild:
   - Student: `npx react-native run-android --variant=studentProductionDebug` (or your usual variant).
   - Staff: your usual staff build command.

**iOS** (if you replaced `GoogleService-Info.plist` files):

1. Place **Student** at: `mobile/ios/GoogleService-Info.plist`.
2. Place **Staff** at: `mobile/ios/GoogleService-Info-staff.plist`.
3. Rebuild in Xcode (or `npx react-native run-ios --scheme ...`) for both Student and Staff schemes.

---

## Step 8: iOS push notifications (APNs)

For push to work on **real iOS devices**, Apple requires an APNs key or certificate. Do this after the iOS apps are added in Firebase.

### 8a. Enable Push in Xcode

1. Open `mobile/ios/rn082template.xcworkspace` in Xcode.
2. For **each** app target (Student and Staff):
   - Select the target → **Signing & Capabilities**.
   - Click **+ Capability** → add **Push Notifications**.
3. Save and build.

### 8b. Create an APNs key in Apple Developer

1. Go to [Apple Developer](https://developer.apple.com/account) → **Certificates, Identifiers & Profiles** → **Keys**.
2. Click **+** to create a new key.
3. Name it (e.g. `MAP-HMS APNs`), enable **Apple Push Notifications service (APNs)**, then **Continue** → **Register**.
4. **Download the `.p8` file** (you can only download it once). Note the **Key ID**.
5. In **Identifiers**, open your App ID (e.g. `com.maphms.student`) and ensure **Push Notifications** is enabled. Repeat for `com.mapmars.hmsstaff` if you have a separate App ID.

### 8c. Upload the APNs key to Firebase

1. In Firebase, go to **Project settings** (gear) → **Cloud Messaging** tab.
2. Scroll to **Apple app configuration**.
3. For **each** iOS app (Student and Staff) listed:
   - Click **Upload** under “APNs Authentication Key”.
   - Upload your `.p8` file.
   - Enter your **Key ID** and **Team ID** (from Apple Developer → Membership).
   - Save.

After this, FCM can deliver push to your iOS devices. Simulators cannot receive push; use a real device to test.

---

## Quick reference

| Item              | Where to find it |
|-------------------|------------------|
| Project ID        | Project settings → General → Project ID |
| Service account   | Project settings → Service accounts → Generate new private key |
| Server key       | Project settings → Cloud Messaging → Server key (Legacy) |
| Android Student   | Package `com.maphms.student` → `google-services.json` in `android/app/src/student/` |
| Android Staff     | Package `com.mapmars.hmsstaff` → `google-services.json` in `android/app/src/staff/` |
| iOS Student      | Bundle `com.maphms.student` → `GoogleService-Info.plist` in `ios/` |
| iOS Staff        | Bundle `com.mapmars.hmsstaff` → `GoogleService-Info-staff.plist` in `ios/` |
| APNs key (iOS)   | Project settings → Cloud Messaging → Apple app configuration → Upload .p8 |

---

## Troubleshooting

- **“No FCM token” / token not registered**  
  Ensure the user has logged in at least once so the app can call `POST /devices/register`. Check that `push_device_tokens` has a row for that user.

- **Push not received on device**  
  Confirm `FCM_ENABLED=true` and the correct credentials in `.env`. Check Laravel logs for “FCM no-op”, “FCM V1 send failed”, or “FCM Legacy API send failed”.

- **Wrong app or build**  
  Each build variant (student vs staff) must use its own config file from the same Firebase project; double-check the paths in Step 3 (Android) and Step 3b (iOS).

- **iOS: push not received on device**  
  Ensure you added the **Push Notifications** capability in Xcode for both targets, and uploaded the **APNs key** (.p8) in Firebase → Project settings → Cloud Messaging → Apple app configuration. Push does not work on the iOS Simulator.

If you want, the next step can be adding a small “Send test push” action (e.g. in Filament or an Artisan command) so you can verify delivery from the backend.
