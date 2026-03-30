# Maestro Installation and Test Runner Script for Windows
# This script installs Maestro and runs tests on staff and student apps

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Maestro Test Runner for MAP HMS Apps" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Step 1: Check ADB and device connection
Write-Host "[1/5] Checking ADB and device connection..." -ForegroundColor Yellow
$adbDevices = adb devices
if ($adbDevices -match "device$") {
    Write-Host "✅ Device detected!" -ForegroundColor Green
    $deviceId = ($adbDevices | Select-String "device$").ToString().Split("`t")[0]
    Write-Host "   Device ID: $deviceId" -ForegroundColor Gray
} else {
    Write-Host "❌ No device detected!" -ForegroundColor Red
    Write-Host ""
    Write-Host "Please ensure:" -ForegroundColor Yellow
    Write-Host "  1. USB Debugging is enabled (Settings > Developer Options > USB Debugging)" -ForegroundColor White
    Write-Host "  2. Your phone is connected via USB" -ForegroundColor White
    Write-Host "  3. You've authorized this computer (check for popup on phone)" -ForegroundColor White
    Write-Host ""
    Write-Host "Trying to restart ADB server..." -ForegroundColor Yellow
    adb kill-server
    Start-Sleep -Seconds 2
    adb start-server
    Start-Sleep -Seconds 2
    $adbDevices = adb devices
    if ($adbDevices -match "device$") {
        Write-Host "✅ Device detected after restart!" -ForegroundColor Green
    } else {
        Write-Host "❌ Still no device. Please check connection and try again." -ForegroundColor Red
        exit 1
    }
}
Write-Host ""

# Step 2: Check if Maestro is installed
Write-Host "[2/5] Checking Maestro installation..." -ForegroundColor Yellow
$maestroInstalled = $false
try {
    $maestroVersion = maestro --version 2>&1
    if ($LASTEXITCODE -eq 0) {
        Write-Host "✅ Maestro is installed: $maestroVersion" -ForegroundColor Green
        $maestroInstalled = $true
    }
} catch {
    $maestroInstalled = $false
}

# Step 3: Install Maestro if not installed
if (-not $maestroInstalled) {
    Write-Host "❌ Maestro not found. Installing..." -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Please install Maestro manually:" -ForegroundColor Yellow
    Write-Host "  1. Download from: https://maestro.mobile.dev/getting-started/installing-maestro" -ForegroundColor White
    Write-Host "  2. Or use: scoop install maestro (if you have Scoop)" -ForegroundColor White
    Write-Host "  3. Or download binary from: https://github.com/mobile-dev-inc/maestro/releases" -ForegroundColor White
    Write-Host ""
    Write-Host "For Windows, you can:" -ForegroundColor Yellow
    Write-Host "  - Download maestro-win.exe from GitHub releases" -ForegroundColor White
    Write-Host "  - Place it in a folder (e.g., C:\tools\maestro\)" -ForegroundColor White
    Write-Host "  - Add that folder to your PATH" -ForegroundColor White
    Write-Host ""
    
    $installChoice = Read-Host "Do you want to try automatic installation? (y/n)"
    if ($installChoice -eq "y" -or $installChoice -eq "Y") {
        Write-Host "Attempting to download Maestro..." -ForegroundColor Yellow
        $maestroDir = "$env:LOCALAPPDATA\maestro"
        New-Item -ItemType Directory -Path $maestroDir -Force | Out-Null
        
        try {
            $ProgressPreference = 'SilentlyContinue'
            $url = "https://github.com/mobile-dev-inc/maestro/releases/latest/download/maestro-win.exe"
            $output = "$maestroDir\maestro.exe"
            Invoke-WebRequest -Uri $url -OutFile $output -ErrorAction Stop
            Write-Host "✅ Maestro downloaded successfully!" -ForegroundColor Green
            $env:Path += ";$maestroDir"
            [Environment]::SetEnvironmentVariable("Path", $env:Path, [EnvironmentVariableTarget]::User)
            Write-Host "✅ Maestro installed to: $maestroDir" -ForegroundColor Green
            Write-Host "   Please restart your terminal or run: `$env:Path += `";$maestroDir`"" -ForegroundColor Yellow
            $maestroInstalled = $true
        } catch {
            Write-Host "❌ Failed to download Maestro automatically." -ForegroundColor Red
            Write-Host "   Error: $_" -ForegroundColor Red
            Write-Host "   Please install manually and run this script again." -ForegroundColor Yellow
            exit 1
        }
    } else {
        Write-Host "Please install Maestro and run this script again." -ForegroundColor Yellow
        exit 1
    }
}
Write-Host ""

# Step 4: Check if apps are installed
Write-Host "[3/5] Checking if apps are installed..." -ForegroundColor Yellow
$staffAppId = "com.mapmars.hmsstaff"
$studentAppId = "com.mapmars.hmsstudent"

$staffInstalled = adb shell pm list packages | Select-String $staffAppId
$studentInstalled = adb shell pm list packages | Select-String $studentAppId

if ($staffInstalled) {
    Write-Host "✅ Staff app is installed" -ForegroundColor Green
} else {
    Write-Host "❌ Staff app not found. Please install it first." -ForegroundColor Red
    Write-Host "   Run: adb install <path-to-staff-app.apk>" -ForegroundColor Yellow
}

if ($studentInstalled) {
    Write-Host "✅ Student app is installed" -ForegroundColor Green
} else {
    Write-Host "❌ Student app not found. Please install it first." -ForegroundColor Red
    Write-Host "   Run: adb install <path-to-student-app.apk>" -ForegroundColor Yellow
}

if (-not $staffInstalled -and -not $studentInstalled) {
    Write-Host ""
    Write-Host "Please install at least one app before running tests." -ForegroundColor Red
    exit 1
}
Write-Host ""

# Step 5: Run tests
Write-Host "[4/5] Preparing to run tests..." -ForegroundColor Yellow
$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $scriptDir

Write-Host ""
Write-Host "[5/5] Running tests..." -ForegroundColor Yellow
Write-Host ""

# Run staff app tests
if ($staffInstalled) {
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "Running Staff App Tests" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""
    
    # Run all staff role tests
    if (Test-Path "staff\run-all-roles.yaml") {
        Write-Host "Running all staff role tests..." -ForegroundColor Yellow
        maestro test staff\run-all-roles.yaml
    } else {
        Write-Host "Running individual staff tests..." -ForegroundColor Yellow
        Get-ChildItem -Path "staff\*-test.yaml" | ForEach-Object {
            Write-Host "Running: $($_.Name)" -ForegroundColor Gray
            maestro test $_.FullName
        }
    }
    Write-Host ""
}

# Run student app tests
if ($studentInstalled) {
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "Running Student App Tests" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""
    
    # Run comprehensive student test
    if (Test-Path "student\01-student-comprehensive-test.yaml") {
        Write-Host "Running comprehensive student test..." -ForegroundColor Yellow
        maestro test student\01-student-comprehensive-test.yaml
    } else {
        Write-Host "Running individual student tests..." -ForegroundColor Yellow
        Get-ChildItem -Path "student\*-test.yaml" | ForEach-Object {
            Write-Host "Running: $($_.Name)" -ForegroundColor Gray
            maestro test $_.FullName
        }
    }
    Write-Host ""
}

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Tests Complete!" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

