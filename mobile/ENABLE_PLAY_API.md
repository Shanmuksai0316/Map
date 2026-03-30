# How to Enable Google Play Developer API

## 📍 Step-by-Step Guide

### Step 1: Go to Google Cloud Console
1. Open your browser and go to: **https://console.cloud.google.com/**
2. Sign in with the same Google account you use for Play Console

### Step 2: Select or Create a Project
1. **If you already have a project:**
   - Click the project dropdown at the top (next to "Google Cloud")
   - Select your existing project

2. **If you need to create a new project:**
   - Click the project dropdown → **"New Project"**
   - Project name: `map-hms-play-store` (or any name you prefer)
   - Click **"Create"**
   - Wait a few seconds for the project to be created
   - Select the new project from the dropdown

### Step 3: Navigate to APIs & Services
1. In the left sidebar, click **"APIs & Services"**
2. Click **"Library"** (or go directly to: https://console.cloud.google.com/apis/library)

### Step 4: Search for Google Play Developer API
1. In the search bar at the top, type: **"Google Play Developer API"**
2. Click on **"Google Play Developer API"** from the search results

### Step 5: Enable the API
1. You'll see the API details page
2. Click the big blue **"ENABLE"** button at the top
3. Wait a few seconds for it to enable (you'll see a success message)

### Step 6: Verify It's Enabled
1. Go back to **APIs & Services** → **Library**
2. Click **"Enabled APIs"** tab (or search for "Google Play Developer API" again)
3. You should see **"Google Play Developer API"** listed with a green checkmark ✅

---

## 🔗 Direct Links

**Quick access URLs:**

- **APIs & Services Library:** https://console.cloud.google.com/apis/library
- **Google Play Developer API (direct):** https://console.cloud.google.com/apis/library/androidpublisher.googleapis.com
- **Enabled APIs list:** https://console.cloud.google.com/apis/dashboard

---

## ✅ What You Should See

After enabling, you should see:
- ✅ "API enabled" success message
- ✅ The API listed in "Enabled APIs"
- ✅ Status: "Enabled" with a green checkmark

---

## 🐛 Troubleshooting

### "API not found" or search returns nothing
- Make sure you're signed in with the correct Google account
- Try refreshing the page
- Check that you have the correct project selected

### "Permission denied" error
- Ensure you're using an account with Owner or Editor permissions
- If using a work account, contact your Google Workspace admin

### API shows as "Enabled" but still not working
- Wait 5-10 minutes for changes to propagate
- Try disabling and re-enabling the API
- Check that billing is enabled (required for some APIs, though Play Developer API is free)

---

## 📝 Next Steps After Enabling

Once the API is enabled, continue with:
1. **Create Service Account** (Step 4 in GOOGLE_PLAY_SETUP.md)
2. **Create JSON Key** (Step 5 in GOOGLE_PLAY_SETUP.md)
3. **Link to Play Console** (Step 2 in GOOGLE_PLAY_SETUP.md)

---

## 💡 Pro Tip

You can also enable it via command line if you have `gcloud` CLI installed:
```bash
gcloud services enable androidpublisher.googleapis.com
```

But the web interface is easier for first-time setup!

