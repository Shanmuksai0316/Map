# Run Maestro Tests for Student App
# This script runs the Maestro E2E tests

param(
    [string]$TestFile = "student/00-student-complete-test.yaml",
    [string]$Phone = "9999999999",
    [string]$Otp = "123456",
    [string]$TenantCode = "PPCU"
)

Write-Host "🧪 Running Maestro Tests for Student App" -ForegroundColor Cyan
Write-Host "Test File: $TestFile" -ForegroundColor Yellow
Write-Host "Phone: $Phone" -ForegroundColor Yellow
Write-Host "OTP: $Otp" -ForegroundColor Yellow
Write-Host "Tenant: $TenantCode" -ForegroundColor Yellow
Write-Host ""

# Check if device is connected
$devices = adb devices
if ($devices -notmatch "device$") {
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

Write-Host "`n🚀 Running tests..." -ForegroundColor Cyan
Write-Host ""

# Run Maestro test
maestro test $TestFile `
    --env phone="$Phone" `
    --env otp="$Otp" `
    --env tenant_code="$TenantCode"

if ($LASTEXITCODE -eq 0) {
    Write-Host "`n✅ Tests completed successfully!" -ForegroundColor Green
    Write-Host "Check screenshots in maestro/.maestro/ directory" -ForegroundColor Cyan
} else {
    Write-Host "`n❌ Tests failed!" -ForegroundColor Red
    Write-Host "Check the output above for details" -ForegroundColor Yellow
    exit 1
}
