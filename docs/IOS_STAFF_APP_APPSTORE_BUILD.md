# Staff App (Kartha) – App Store Build in Xcode

Steps to build the **Staff app** (Kartha, bundle ID `com.mapmars.hmsstaff`) for App Store submission using Xcode.

---

## Prerequisites

- **Mac** with **Xcode** (latest stable).
- **Apple Developer** account and **Team ID** (e.g. `2ZDX6T9JMV`) set in the project.
- **Signing:** Automatic signing with your team, or valid Distribution certificate + App Store provisioning profile for `com.mapmars.hmsstaff`.
- **CocoaPods:** From `mobile/`: run `cd ios && pod install` if you haven’t recently.

---

## 1. Open the project in Xcode

```bash
cd mobile/ios
open rn082template.xcworkspace
```

Use the **`.xcworkspace`** file (not the `.xcodeproj`), so CocoaPods are included.

---

## 2. Select the Staff scheme

1. In the Xcode toolbar, click the **scheme** (next to the Run/Stop buttons).
2. Choose **MAPHMSStaff** (Staff / Kartha app).
3. Confirm the run destination is **“Any iOS Device (arm64)”** (or a connected device).  
   Do **not** use a simulator for archiving.

---

## 3. Create an archive

1. Menu: **Product → Archive**.
2. Wait for the build to finish. If it succeeds, the **Organizer** window opens with the new archive.
3. The archive will show as **Kartha** (product name for the staff app).

---

## 4. Distribute to App Store Connect

1. In Organizer, select the archive you just created.
2. Click **Distribute App**.
3. Choose **App Store Connect** → **Next**.
4. Choose **Upload** → **Next**.
5. Leave options as default (e.g. upload symbols, manage version/build) → **Next**.
6. Select your **distribution certificate** and **provisioning profile** (or use automatic) → **Next**.
7. Click **Upload** and wait for the upload to finish.

---

## 5. After upload

1. Go to [App Store Connect](https://appstoreconnect.apple.com).
2. Open your **Staff (Kartha)** app.
3. In the **TestFlight** tab, wait for the build to finish processing.
4. When ready, add the build to a version in the **App Store** tab and submit for review.

---

## Quick reference

| Item        | Value                    |
|------------|---------------------------|
| **Scheme** | MAPHMSStaff              |
| **App name** | Kartha                 |
| **Bundle ID** | com.mapmars.hmsstaff   |
| **Build config for archive** | Staff Release |

---

## Alternative: command-line build (IPA only)

From `mobile/ios`:

```bash
./build-for-app-store.sh
```

This builds **both** Student and Staff. Staff IPA:

- `mobile/ios/exports/StaffApp/StaffApp.ipa`

Upload that IPA with the **Transporter** app, or use Fastlane (e.g. `fastlane ios deploy_staff_testflight` or `deploy_staff_appstore`).

---

## Troubleshooting

- **“No signing certificate”:** Xcode → **Signing & Capabilities** for the target → set **Team** and enable **Automatically manage signing** (or choose a valid Distribution profile for App Store).
- **“Provisioning profile doesn’t include …”:** In [Developer Portal](https://developer.apple.com/account), ensure the App ID `com.mapmars.hmsstaff` exists and an **App Store** provisioning profile includes it and your certificate.
- **Pod / build errors:** Run `cd mobile/ios && pod install` and clean in Xcode (**Product → Clean Build Folder**), then archive again.
- **Wrong app (e.g. Student) after archive:** Ensure the selected scheme is **MAPHMSStaff** and the archive was created with that scheme (Staff Release).
