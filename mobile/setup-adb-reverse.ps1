# Setup ADB Reverse Port Forwarding for React Native Metro Bundler
# This allows Android device/emulator to connect to Metro on localhost:8081

Write-Host "Setting up ADB reverse port forwarding..." -ForegroundColor Cyan

# Check if device is connected
Write-Host "`nChecking for connected devices..." -ForegroundColor Yellow
$devices = adb devices
Write-Host $devices

if ($devices -match "device$") {
    Write-Host "`n✅ Device detected! Setting up port forwarding..." -ForegroundColor Green
    
    # Reverse port forwarding for Metro bundler
    adb reverse tcp:8081 tcp:8081
    
    # Also forward port 8097 for React DevTools (optional)
    adb reverse tcp:8097 tcp:8097
    
    Write-Host "`n✅ Port forwarding configured!" -ForegroundColor Green
    Write-Host "`nActive reverse connections:" -ForegroundColor Cyan
    adb reverse --list
    
    Write-Host "`n✅ You can now reload the app on your device!" -ForegroundColor Green
    Write-Host "   Shake device and select 'Reload' or press 'R' in Metro bundler" -ForegroundColor Gray
} else {
    Write-Host "`n❌ No Android device/emulator detected!" -ForegroundColor Red
    Write-Host "`nPlease:" -ForegroundColor Yellow
    Write-Host "1. Connect your Android device via USB" -ForegroundColor White
    Write-Host "2. Enable USB Debugging:" -ForegroundColor White
    Write-Host "   - Go to Settings > About Phone" -ForegroundColor Gray
    Write-Host "   - Tap 'Build Number' 7 times to enable Developer Options" -ForegroundColor Gray
    Write-Host "   - Go to Settings > Developer Options" -ForegroundColor Gray
    Write-Host "   - Enable 'USB Debugging'" -ForegroundColor Gray
    Write-Host "3. Accept the USB debugging prompt on your device" -ForegroundColor White
    Write-Host "4. Run this script again" -ForegroundColor White
    Write-Host "`nOr start an Android emulator and run this script again." -ForegroundColor Yellow
}

Write-Host "`nPress any key to exit..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
