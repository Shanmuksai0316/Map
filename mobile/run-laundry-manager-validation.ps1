# Run Laundry Manager UI Validation Tests
# Validates all UI changes per requirements

param(
    [string]$Phone = "9538678739",
    [string]$Otp = "123456"
)

Write-Host "🧪 Running Laundry Manager UI Validation Tests" -ForegroundColor Cyan
Write-Host "Phone: $Phone" -ForegroundColor Yellow
Write-Host "OTP: $Otp" -ForegroundColor Yellow
Write-Host ""

# Check if device is connected
$devicesOutput = adb devices 2>&1 | Out-String
$deviceLines = $devicesOutput -split "`n" | Where-Object { $_ -match "device$" }
if ($deviceLines.Count -eq 0) {
    Write-Host "❌ No Android device connected!" -ForegroundColor Red
    Write-Host "Please connect your device via USB and enable USB debugging" -ForegroundColor Yellow
    exit 1
}

Write-Host "✅ Device found!" -ForegroundColor Green

# Check if Maestro is installed
$maestroInstalled = Get-Command maestro -ErrorAction SilentlyContinue
if (-not $maestroInstalled) {
    Write-Host "❌ Maestro is not installed!" -ForegroundColor Red
    Write-Host "Installing Maestro..." -ForegroundColor Yellow
    curl -fsSL "https://get.maestro.mobile.dev" | bash
    if ($LASTEXITCODE -ne 0) {
        Write-Host "❌ Failed to install Maestro!" -ForegroundColor Red
        Write-Host "Please install manually: https://maestro.mobile.dev/getting-started/installing-maestro" -ForegroundColor Yellow
        exit 1
    }
}

# Navigate to maestro directory
if (Test-Path "maestro") {
    cd maestro
} else {
    Write-Host "❌ Maestro directory not found!" -ForegroundColor Red
    exit 1
}

Write-Host "`n🚀 Running UI validation tests..." -ForegroundColor Cyan
Write-Host ""

# Run Maestro test
maestro test staff/laundry-manager-ui-validation-test.yaml `
    --env phone="$Phone" `
    --env otp="$Otp"

$testResult = $LASTEXITCODE

Write-Host ""

if ($testResult -eq 0) {
    Write-Host "✅ All UI validation tests PASSED!" -ForegroundColor Green
    Write-Host ""
    Write-Host "Validated:" -ForegroundColor Cyan
    Write-Host "  ✓ Header with 'Laundry Manager' and bell icon" -ForegroundColor Green
    Write-Host "  ✓ Greeting card with user name and logo" -ForegroundColor Green
    Write-Host "  ✓ NO stats matrix (removed)" -ForegroundColor Green
    Write-Host "  ✓ 2x2 Action tiles (Raise Request, Active Requests, Profile, Comm Box)" -ForegroundColor Green
    Write-Host "  ✓ NO bottom navigation bar (removed)" -ForegroundColor Green
    Write-Host "  ✓ Raise Request form with all fields" -ForegroundColor Green
    Write-Host "  ✓ Active Requests with search and filters" -ForegroundColor Green
    Write-Host "  ✓ Profile screen with name, phone, logout" -ForegroundColor Green
    Write-Host "  ✓ Comm Box screen with notifications" -ForegroundColor Green
    Write-Host "  ✓ Navigation works correctly" -ForegroundColor Green
    Write-Host ""
    Write-Host "Check screenshots in maestro/.maestro/ directory" -ForegroundColor Cyan
} else {
    Write-Host "❌ Some tests FAILED!" -ForegroundColor Red
    Write-Host "Check the output above for details" -ForegroundColor Yellow
    Write-Host "Screenshots available in maestro/.maestro/ directory" -ForegroundColor Cyan
    exit 1
}
