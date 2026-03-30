# Simple Maestro Test Runner
# Run this script after Maestro is installed and device is connected

param(
    [switch]$Staff,
    [switch]$Student,
    [switch]$All
)

$ErrorActionPreference = "Stop"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Maestro Test Runner" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Check Maestro
Write-Host "Checking Maestro..." -ForegroundColor Yellow
try {
    $maestroVersion = maestro --version 2>&1
    if ($LASTEXITCODE -ne 0) { throw "Maestro not found" }
    Write-Host "✅ Maestro: $maestroVersion" -ForegroundColor Green
} catch {
    Write-Host "❌ Maestro not found. Please install Maestro first." -ForegroundColor Red
    Write-Host "   See SETUP_INSTRUCTIONS.md for installation steps." -ForegroundColor Yellow
    exit 1
}

# Check device
Write-Host "Checking device connection..." -ForegroundColor Yellow
$devices = adb devices
if (-not ($devices -match "device$")) {
    Write-Host "❌ No device detected. Please connect your phone and enable USB debugging." -ForegroundColor Red
    Write-Host "   See SETUP_INSTRUCTIONS.md for connection steps." -ForegroundColor Yellow
    exit 1
}
Write-Host "✅ Device connected" -ForegroundColor Green

# Check apps
Write-Host "Checking installed apps..." -ForegroundColor Yellow
$staffInstalled = adb shell pm list packages | Select-String "com.mapmars.hmsstaff"
$studentInstalled = adb shell pm list packages | Select-String "com.mapmars.hmsstudent"

if ($staffInstalled) {
    Write-Host "✅ Staff app installed" -ForegroundColor Green
} else {
    Write-Host "⚠️  Staff app not found" -ForegroundColor Yellow
}

if ($studentInstalled) {
    Write-Host "✅ Student app installed" -ForegroundColor Green
} else {
    Write-Host "⚠️  Student app not found" -ForegroundColor Yellow
}

if (-not $staffInstalled -and -not $studentInstalled) {
    Write-Host "❌ No apps installed. Please install staff and/or student apps." -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Set working directory
$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $scriptDir

# Determine what to run
$runStaff = $false
$runStudent = $false

if ($All) {
    $runStaff = $true
    $runStudent = $true
} elseif ($Staff) {
    $runStaff = $true
} elseif ($Student) {
    $runStudent = $true
} else {
    # Default: run both if available
    $runStaff = $staffInstalled
    $runStudent = $studentInstalled
}

# Run Staff Tests
if ($runStaff -and $staffInstalled) {
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "Running Staff App Tests" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""
    
    if (Test-Path "staff\run-all-roles.yaml") {
        Write-Host "Running all staff role tests..." -ForegroundColor Yellow
        maestro test staff\run-all-roles.yaml
        if ($LASTEXITCODE -ne 0) {
            Write-Host "⚠️  Some staff tests may have failed. Check output above." -ForegroundColor Yellow
        }
    } else {
        Write-Host "Running individual staff tests..." -ForegroundColor Yellow
        Get-ChildItem -Path "staff\*-test.yaml" -ErrorAction SilentlyContinue | ForEach-Object {
            Write-Host "Running: $($_.Name)" -ForegroundColor Gray
            maestro test $_.FullName
        }
    }
    Write-Host ""
}

# Run Student Tests
if ($runStudent -and $studentInstalled) {
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "Running Student App Tests" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""
    
    if (Test-Path "student\01-student-comprehensive-test.yaml") {
        Write-Host "Running comprehensive student test..." -ForegroundColor Yellow
        maestro test student\01-student-comprehensive-test.yaml
        if ($LASTEXITCODE -ne 0) {
            Write-Host "⚠️  Student test may have failed. Check output above." -ForegroundColor Yellow
        }
    } else {
        Write-Host "Running individual student tests..." -ForegroundColor Yellow
        Get-ChildItem -Path "student\*-test.yaml" -ErrorAction SilentlyContinue | ForEach-Object {
            Write-Host "Running: $($_.Name)" -ForegroundColor Gray
            maestro test $_.FullName
        }
    }
    Write-Host ""
}

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Tests Complete!" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Check test results and screenshots in the output directory." -ForegroundColor Gray

