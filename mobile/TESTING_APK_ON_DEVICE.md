# Testing APK on Device - Step by Step Guide

## Step 1: Start Metro Bundler (Required for Debugging)

**Open a terminal/PowerShell and run:**
```powershell
cd "C:\Users\Nagraj Y R\OneDrive\Desktop\mapmars8\mapmars\mobile"
npm start
```

**Keep this terminal open** - Metro bundler must be running for debugging to work.

## Step 2: Connect Device to Same Network

- **Physical Device**: Ensure your phone and computer are on the same Wi-Fi network
- **Emulator**: Already connected automatically

## Step 3: Open the App on Your Device

1. Find the app icon on your device
2. Tap to open it
3. You should see the login screen

## Step 4: Enable Debugging

### On Physical Device:
1. **Shake your device** (or press and hold volume buttons)
2. A menu will appear
3. Tap **"Debug"** or **"Open Debugger"**

### On Emulator:
- Press `Ctrl + M` (Windows) or `Cmd + M` (Mac)
- Select **"Debug"**

## Step 5: Chrome DevTools Will Open

1. Chrome browser should open automatically
2. If not, manually go to: `http://localhost:8081/debugger-ui/`
3. You'll see Chrome DevTools interface

## Step 6: Test Login and Debug 400 Error

### A. Open Network Tab
1. In Chrome DevTools, click **"Network"** tab
2. Make sure it's recording (red circle should be active)

### B. Try to Login
1. Go back to your device
2. Enter phone number (e.g., `9663275871`)
3. Tap "Send OTP" or enter OTP `123456`
4. Try to login

### C. Check Network Requests
1. Go back to Chrome DevTools
2. In Network tab, you'll see requests appear
3. Look for:
   - `/mobile/auth/send-otp` (when sending OTP)
   - `/mobile/auth/verify-otp` (when verifying OTP)

### D. Inspect Failed Request
1. Click on the failed request (it will be red if it failed)
2. Check these sections:

   **Headers Tab:**
   - `Content-Type`: Should be `application/json`
   - `X-Tenant-Code`: Should be present (e.g., tenant code)
   - `Authorization`: Should be present for authenticated requests

   **Payload Tab (or Request):**
   - See what data was sent
   - Check `phone` format (should be string, no spaces)
   - Check `otp` format (should be string, exactly 6 digits)

   **Response Tab:**
   - See the error message
   - Check error code and details

### E. Check Console Tab
1. Click **"Console"** tab in Chrome DevTools
2. Look for:
   - Error messages
   - `console.log()` output from the app
   - Network errors

## Step 7: What to Look For

### If You See 400 Error:

**Check Request Payload:**
```json
{
  "phone": "9663275871",  // ✅ Should be string, no spaces
  "otp": "123456"         // ✅ Should be string, exactly 6 digits
}
```

**Common Issues:**
- ❌ `"phone": 9663275871` (number instead of string)
- ❌ `"phone": "9663 2758 71"` (spaces in phone)
- ❌ `"otp": 123456` (number instead of string)
- ❌ `"otp": "12345"` (less than 6 digits)
- ❌ Missing `Content-Type: application/json` header

### If You See 401 Error:
- Token issue - check Authorization header
- Token might be expired or invalid

### If You See 404 Error:
- API endpoint not found
- Check if URL is correct

## Step 8: Take Screenshots/Notes

**Document what you see:**
1. Screenshot of Network tab showing the failed request
2. Screenshot of Request Payload
3. Screenshot of Response error
4. Note the exact error message

## Step 9: Check Server Logs (If Possible)

If you have SSH access:
```bash
ssh -i "C:\Users\Nagraj Y R\Downloads\hostinger.pem" root@72.62.79.173
docker exec map-hms-app tail -100 /var/www/html/storage/logs/laravel.log | grep "🔍 MobileAuthController"
```

## Quick Checklist

- [ ] Metro bundler is running (`npm start`)
- [ ] Device and computer on same network
- [ ] App opened on device
- [ ] Debug menu opened (shake device or Ctrl+M)
- [ ] Chrome DevTools opened
- [ ] Network tab is open and recording
- [ ] Attempted login
- [ ] Checked failed request in Network tab
- [ ] Reviewed Request Payload
- [ ] Reviewed Response error
- [ ] Checked Console for errors

## Next Steps Based on What You Find

### If Phone/OTP Format is Wrong:
- The fixes I made should handle this, but if not, we'll need to adjust the formatting code

### If Tenant Code is Missing:
- Check if `X-Tenant-Code` header is being sent
- Verify tenant code is correct

### If Content-Type is Wrong:
- Check `api.service.ts` - it should set this automatically

### If Request Body is Malformed:
- Check if JSON is properly formatted
- Check if special characters are escaped

## Need Help?

Share with me:
1. Screenshot of Network tab showing the failed request
2. The Request Payload (what was sent)
3. The Response error message
4. Any Console errors

This will help me identify exactly what's causing the 400 error!
