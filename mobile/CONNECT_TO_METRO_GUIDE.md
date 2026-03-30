# Connecting App to Metro Bundler

## Issue: App Not Loading from Localhost

If your app is not connecting to Metro bundler, follow these steps:

## Step 1: Verify Metro is Running

Check if Metro is running on port 8081:
```powershell
netstat -ano | findstr ":8081"
```

You should see:
```
TCP    0.0.0.0:8081           0.0.0.0:0              LISTENING
```

## Step 2: Set Up Port Forwarding (USB Connection)

If your device is connected via USB:
```powershell
adb reverse tcp:8081 tcp:8081
```

Verify it worked:
```powershell
adb reverse --list
```

You should see:
```
UsbFfs tcp:8081 tcp:8081
```

## Step 3: Reload the App

### Method 1: Shake Device Menu
1. **Shake your device** (or press volume buttons)
2. Tap **"Reload"** from the menu
3. The app should reload and connect to Metro

### Method 2: ADB Command
```powershell
# Reload the app
adb shell input text "RR"
```

Or:
```powershell
# Send reload command
adb shell am broadcast -a com.maphms.student.RELOAD
```

### Method 3: Force Stop and Restart
```powershell
adb shell am force-stop com.maphms.student
adb shell am start -n com.maphms.student/.MainActivity
```

## Step 4: Check if App is Debug Build

**Important**: Only **debug builds** connect to Metro bundler. Production builds use bundled JavaScript.

### Check Build Type
If you built the APK yourself, check the build command:
- ✅ **Debug build**: `assembleStudentDebug` or `assembleStudentProductionDebug`
- ❌ **Release build**: `assembleStudentRelease` or `assembleStudentProductionRelease`

### If You Have a Release Build
You need to rebuild as debug:
```powershell
cd "C:\Users\Nagraj Y R\OneDrive\Desktop\mapmars8\mapmars\mobile\android"
.\gradlew.bat assembleStudentProductionDebug
```

Then install:
```powershell
adb install -r android\app\build\outputs\apk\studentProduction\debug\app-student-production-debug.apk
```

## Step 5: Verify Connection

### Check Metro Bundler Logs
In the Metro bundler terminal, you should see:
```
 BUNDLE  ./index.js
```

When the app loads, you'll see bundle requests.

### Check Device Logs
```powershell
adb logcat | findstr "ReactNative\|Metro\|8081"
```

You should see connection attempts.

## Step 6: Manual Connection (If Needed)

If automatic connection doesn't work:

1. **Shake device** → Select **"Settings"**
2. Enter your computer's IP address:
   - Find your computer's IP: `ipconfig` (look for IPv4 Address)
   - Example: `192.168.1.100:8081`
3. Tap **"Reload"**

## Troubleshooting

### App Still Not Connecting

1. **Check Network**: Device and computer must be on same Wi-Fi (for Wi-Fi debugging)
2. **Check Firewall**: Windows Firewall might be blocking port 8081
3. **Check Metro**: Ensure Metro bundler is actually running
4. **Rebuild App**: The APK might be a release build

### For USB Connection
- Port forwarding is already set up (`adb reverse tcp:8081 tcp:8081`)
- Try reloading the app (shake device → Reload)

### For Wi-Fi Connection
- Ensure device and computer are on same network
- Find computer IP: `ipconfig`
- In app settings, enter: `YOUR_IP:8081`

## Quick Test

1. **Shake device** → Select **"Reload"**
2. Watch Metro bundler terminal - you should see bundle requests
3. If you see bundle requests, connection is working!

## Next Steps

Once connected:
1. Shake device → Select **"Debug"**
2. Chrome DevTools opens
3. Go to **Network** tab
4. Test login and see requests
