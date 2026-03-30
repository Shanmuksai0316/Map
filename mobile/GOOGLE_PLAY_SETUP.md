# Google Play Store Automation Setup Guide

This guide will help you set up automated uploads to Google Play Store for both **MAP HMS Student** and **MAP HMS Staff** apps.

---

## 📋 Prerequisites

- Google Play Developer Account (one-time $25 fee)
- Access to Google Cloud Console
- Fastlane installed (`bundle install` in `mobile/` directory)

---

## 🔑 Step 1: Create Service Account in Google Cloud (5-10 minutes)

1. **Go to [Google Cloud Console](https://console.cloud.google.com/)**
2. **Create or select a project:**
   - Click the project dropdown at the top
   - Click "New Project" or select existing
   - Name it: `map-hms-play-store` (or any name you prefer)

3. **Enable Google Play Developer API:**
   - Go to **APIs & Services** → **Library**
   - Search for "Google Play Developer API"
   - Click **Enable**

4. **Create Service Account:**
   - Go to **APIs & Services** → **Credentials**
   - Click **"Create Credentials"** → **"Service Account"**
   - Name: `play-store-upload`
   - Click **"Create and Continue"**
   - Skip role assignment (click **Continue**)
   - Click **Done**

5. **Create JSON Key:**
   - Click on the service account you just created (`play-store-upload@...`)
   - Go to **Keys** tab
   - Click **"Add Key"** → **"Create new key"**
   - Select **JSON**
   - Click **Create**
   - **Save the downloaded JSON file** (e.g., `play-store-key.json`)
   - ⚠️ **Keep this file secure!** It provides full access to your Play Console.

---

## 🔗 Step 2: Link Service Account to Play Console (5 minutes)

1. **Go to [Google Play Console](https://play.google.com/console)**
2. **Navigate to Settings:**
   - Click the **gear icon** (⚙️) in the top right
   - Click **"API access"** in the left sidebar

3. **Link Google Cloud Project:**
   - Click **"Link"** next to your Google Cloud project
   - If you don't see your project, click **"Create new project"** and follow the prompts
   - Wait for linking to complete (may take a minute)

4. **Grant Access to Service Account:**
   - Find your service account: `play-store-upload@[project-id].iam.gserviceaccount.com`
   - Click **"Grant access"**
   - Set permissions: **Admin** (or at least **Release manager**)
   - Click **"Invite user"** → **"Send invite"**
   - ✅ Service account should now show as "Active"

---

## 📱 Step 3: Create Apps in Play Console (One-time, ~10 minutes per app)

Before automated uploads work, the apps must exist in Play Console:

### Create Student App:
1. In Play Console, click **"Create app"**
2. Fill in:
   - **App name:** `MAP HMS Student`
   - **Default language:** English (United States)
   - **App or game:** App
   - **Free or paid:** Free
3. Click **"Create"**
4. Complete **Store listing** (minimum required):
   - App name: `MAP HMS Student`
   - Short description: `Hostel Management System for Students`
   - Full description: `MAP HMS Student app for managing hostel activities, room bookings, and more.`
   - App icon: Upload a 512x512 icon
5. **Package name must match:** `com.maphms.student` ✅

### Create Staff App:
1. Click **"Create app"** again
2. Fill in:
   - **App name:** `MAP HMS Staff`
   - **Default language:** English (United States)
   - **App or game:** App
   - **Free or paid:** Free
3. Click **"Create"**
4. Complete **Store listing** (minimum required):
   - App name: `MAP HMS Staff`
   - Short description: `Hostel Management System for Staff`
   - Full description: `MAP HMS Staff app for managing hostel operations, student check-ins, and administrative tasks.`
   - App icon: Upload a 512x512 icon
5. **Package name must match:** `com.mapmars.hmsstaff` ✅

---

## 🚀 Step 4: Configure Local Environment

1. **Place JSON key file:**
   ```bash
   # Copy your downloaded JSON key to a secure location
   # Example: ~/play-store-key.json or mobile/fastlane/play-store-key.json
   cp ~/Downloads/play-store-key.json ~/play-store-key.json
   ```

2. **Set environment variable:**
   ```bash
   # Add to your ~/.bashrc or ~/.zshrc
   export GOOGLE_PLAY_JSON_KEY_PATH="$HOME/play-store-key.json"
   
   # Or set it per session:
   export GOOGLE_PLAY_JSON_KEY_PATH="/path/to/play-store-key.json"
   ```

3. **Verify Fastlane is installed:**
   ```bash
   cd mobile
   bundle install
   ```

---

## ✅ Step 5: Test the Setup

### Test Staff App Upload:
```bash
cd mobile
export GOOGLE_PLAY_JSON_KEY_PATH="$HOME/play-store-key.json"
bundle exec fastlane android deploy_staff_internal
```

### Test Student App Upload:
```bash
cd mobile
export GOOGLE_PLAY_JSON_KEY_PATH="$HOME/play-store-key.json"
bundle exec fastlane android deploy_student_internal
```

### Deploy Both Apps:
```bash
cd mobile
export GOOGLE_PLAY_JSON_KEY_PATH="$HOME/play-store-key.json"
bundle exec fastlane android deploy_both_internal
```

---

## 📝 Available Fastlane Commands

### Staff App:
- `bundle exec fastlane android build_staff_debug` - Build debug APK
- `bundle exec fastlane android build_staff_release` - Build release APK
- `bundle exec fastlane android build_staff_bundle` - Build release AAB
- `bundle exec fastlane android deploy_staff_internal` - Deploy to Internal Testing
- `bundle exec fastlane android deploy_staff_production` - Deploy to Production

### Student App:
- `bundle exec fastlane android build_student_debug` - Build debug APK
- `bundle exec fastlane android build_student_release` - Build release APK
- `bundle exec fastlane android build_student_bundle` - Build release AAB
- `bundle exec fastlane android deploy_student_internal` - Deploy to Internal Testing
- `bundle exec fastlane android deploy_student_production` - Deploy to Production

### Combined:
- `bundle exec fastlane android deploy_both_internal` - Deploy both to Internal Testing

---

## 🔒 Security Best Practices

1. **Never commit the JSON key to Git:**
   - Add to `.gitignore`: `play-store-key.json`
   - Add to `.gitignore`: `*.json` (if in fastlane directory)

2. **Use environment variables:**
   - Store JSON key path in environment variable
   - Use CI/CD secrets for automated deployments

3. **Limit service account permissions:**
   - Use **Release manager** instead of **Admin** if possible
   - Only grant access to specific apps if needed

4. **Rotate keys periodically:**
   - Create new keys every 6-12 months
   - Revoke old keys after rotation

---

## 🐛 Troubleshooting

### Error: "Service account not found"
- Ensure service account is linked in Play Console → Settings → API access
- Check that permissions are granted (Admin or Release manager)

### Error: "App not found"
- Verify apps exist in Play Console
- Check package names match exactly:
  - Student: `com.maphms.student`
  - Staff: `com.mapmars.hmsstaff`

### Error: "Invalid JSON key"
- Verify JSON file path is correct
- Check file permissions (should be readable)
- Ensure JSON file is valid (not corrupted)

### Error: "AAB not found"
- Run build command first: `bundle exec fastlane android build_staff_bundle`
- Check AAB path in Fastfile matches your build output

---

## 📚 Additional Resources

- [Google Play Developer API Documentation](https://developers.google.com/android-publisher)
- [Fastlane Android Documentation](https://docs.fastlane.tools/getting-started/android/setup/)
- [Service Account Best Practices](https://cloud.google.com/iam/docs/best-practices-service-accounts)

---

## ✅ Checklist

- [ ] Google Cloud project created
- [ ] Google Play Developer API enabled
- [ ] Service account created
- [ ] JSON key downloaded and saved securely
- [ ] Service account linked in Play Console
- [ ] Service account granted Admin/Release manager access
- [ ] Student app created in Play Console (`com.maphms.student`)
- [ ] Staff app created in Play Console (`com.mapmars.hmsstaff`)
- [ ] Environment variable `GOOGLE_PLAY_JSON_KEY_PATH` set
- [ ] Fastlane installed (`bundle install`)
- [ ] Test upload successful

---

**Once all steps are complete, you can automate all Play Store uploads!** 🎉

